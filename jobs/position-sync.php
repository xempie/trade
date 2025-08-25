<?php
/**
 * Position Sync Cron Job
 * Runs every 5 minutes to sync positions with BingX and update P&L
 * Sends notifications for profit/loss milestones
 */

// Prevent direct browser access
if (php_sapi_name() !== 'cli' && !defined('CRON_RUNNING')) {
    die('This script can only be run from command line');
}

define('CRON_RUNNING', true);

// Change to project directory
$projectDir = dirname(__DIR__);
chdir($projectDir);

// Load environment variables
function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        if (strpos($line, '=') === false) {
            continue;
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

loadEnv($projectDir . '/.env');

// Database connection
function getDbConnection() {
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: '';
    $password = getenv('DB_PASSWORD') ?: '';
    $database = getenv('DB_NAME') ?: 'crypto_trading';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Position Sync - Database connection failed: " . $e->getMessage());
        return null;
    }
}

// Get positions from BingX
function getBingXPositions($apiKey, $apiSecret) {
    try {
        $timestamp = round(microtime(true) * 1000);
        $queryString = "timestamp={$timestamp}";
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        
        $url = "https://open-api.bingx.com/openApi/swap/v2/user/positions?" . $queryString . "&signature=" . $signature;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-BX-APIKEY: ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP error: {$httpCode}");
        }
        
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['code']) || $data['code'] !== 0) {
            throw new Exception('Invalid API response');
        }
        
        return $data['data'] ?? [];
        
    } catch (Exception $e) {
        error_log("Position Sync - Failed to get positions: " . $e->getMessage());
        return [];
    }
}

// Send Telegram notification
function sendTelegramNotification($message, $priority = 'MEDIUM') {
    $botToken = getenv('TELEGRAM_BOT_TOKEN');
    $chatId = getenv('TELEGRAM_CHAT_ID');
    
    if (empty($botToken) || empty($chatId)) {
        return false;
    }
    
    $priorityEmojis = [
        'HIGH' => '🚨',
        'MEDIUM' => '💰',
        'LOW' => 'ℹ️'
    ];
    
    $emoji = $priorityEmojis[$priority] ?? '💰';
    $finalMessage = $emoji . ' ' . $message;
    
    try {
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $postData = [
            'chat_id' => $chatId,
            'text' => $finalMessage,
            'parse_mode' => 'HTML'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            return $result['ok'] ?? false;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Position Sync - Telegram notification failed: " . $e->getMessage());
        return false;
    }
}

// Update position P&L in database
function updatePositionPnL($pdo, $positionId, $unrealizedPnL) {
    try {
        $sql = "UPDATE positions SET unrealized_pnl = :pnl WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':pnl' => $unrealizedPnL,
            ':id' => $positionId
        ]);
    } catch (Exception $e) {
        error_log("Position Sync - Failed to update P&L for position {$positionId}: " . $e->getMessage());
        return false;
    }
}

// Check if significant P&L milestone reached
function checkPnLMilestone($oldPnL, $newPnL, $marginUsed) {
    if ($marginUsed == 0) return false;
    
    $oldPercent = ($oldPnL / $marginUsed) * 100;
    $newPercent = ($newPnL / $marginUsed) * 100;
    
    // Check for 10%, 25%, 50% profit milestones
    $profitMilestones = [10, 25, 50];
    foreach ($profitMilestones as $milestone) {
        if ($oldPercent < $milestone && $newPercent >= $milestone) {
            return ['type' => 'profit', 'milestone' => $milestone, 'percent' => $newPercent];
        }
    }
    
    // Check for -10%, -25%, -50% loss milestones
    $lossMilestones = [-10, -25, -50];
    foreach ($lossMilestones as $milestone) {
        if ($oldPercent > $milestone && $newPercent <= $milestone) {
            return ['type' => 'loss', 'milestone' => abs($milestone), 'percent' => $newPercent];
        }
    }
    
    return false;
}

// Main execution
echo "Starting position sync at " . date('Y-m-d H:i:s') . "\n";

try {
    $pdo = getDbConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    $apiKey = getenv('BINGX_API_KEY') ?: '';
    $apiSecret = getenv('BINGX_SECRET_KEY') ?: '';
    
    if (empty($apiKey) || empty($apiSecret)) {
        throw new Exception('BingX API credentials not configured');
    }
    
    // Get current positions from database
    $sql = "SELECT * FROM positions WHERE status = 'OPEN'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $dbPositions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($dbPositions)) {
        echo "No open positions to sync\n";
        return;
    }
    
    // Get positions from BingX
    $bingxPositions = getBingXPositions($apiKey, $apiSecret);
    
    // Create mapping of BingX positions
    $bingxMap = [];
    foreach ($bingxPositions as $pos) {
        if (isset($pos['symbol']) && isset($pos['positionSide']) && floatval($pos['positionAmt']) != 0) {
            $key = $pos['symbol'] . '_' . $pos['positionSide'];
            $bingxMap[$key] = $pos;
        }
    }
    
    $updatedCount = 0;
    $notificationsSent = 0;
    
    foreach ($dbPositions as $dbPos) {
        // Convert symbol to BingX format
        $symbol = $dbPos['symbol'];
        if (!strpos($symbol, 'USDT')) {
            $symbol = $symbol . '-USDT';
        }
        
        $side = strtoupper($dbPos['side']);
        $key = $symbol . '_' . $side;
        
        if (isset($bingxMap[$key])) {
            $bingxPos = $bingxMap[$key];
            $newPnL = floatval($bingxPos['unrealizedProfit']);
            $oldPnL = floatval($dbPos['unrealized_pnl']);
            $marginUsed = floatval($dbPos['margin_used']);
            
            // Update P&L in database
            if (updatePositionPnL($pdo, $dbPos['id'], $newPnL)) {
                $updatedCount++;
                
                // Check for P&L milestones
                $milestone = checkPnLMilestone($oldPnL, $newPnL, $marginUsed);
                if ($milestone) {
                    $type = $milestone['type'];
                    $milestonePercent = $milestone['milestone'];
                    $currentPercent = round($milestone['percent'], 2);
                    
                    $emoji = $type === 'profit' ? '💰' : '📉';
                    $direction = $type === 'profit' ? 'PROFIT' : 'LOSS';
                    
                    $message = "<b>{$direction} Milestone Reached!</b>\n\n" .
                              "{$emoji} <b>{$dbPos['symbol']}</b> (" . strtoupper($dbPos['side']) . ")\n" .
                              "🎯 Milestone: {$milestonePercent}%\n" .
                              "📊 Current P&L: {$currentPercent}%\n" .
                              "💵 P&L Amount: \${$newPnL}";
                    
                    if (sendTelegramNotification($message, 'MEDIUM')) {
                        $notificationsSent++;
                    }
                    
                    echo "Milestone: {$dbPos['symbol']} {$direction} {$milestonePercent}% (current: {$currentPercent}%)\n";
                }
            }
        } else {
            echo "Position not found on exchange: {$dbPos['symbol']} {$dbPos['side']}\n";
        }
    }
    
    echo "Position sync completed. Updated: {$updatedCount} positions, Sent: {$notificationsSent} notifications\n";
    
} catch (Exception $e) {
    error_log("Position Sync - Error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Position sync finished at " . date('Y-m-d H:i:s') . "\n";
?>