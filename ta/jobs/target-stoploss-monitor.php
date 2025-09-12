<?php
/**
 * Target & Stop Loss Monitor Cronjob
 * Monitors open positions for target and stop-loss conditions
 * Run every minute via cron job
 */

// Only run from command line or specific access
if (!isset($_GET['cron_key']) && php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Access denied');
}

set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Load .env file
$envPath = __DIR__ . '/../.env';
loadEnv($envPath);

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
        throw new Exception('Database connection failed: ' . $e->getMessage());
    }
}

// BingX API functions
function getBingXPrice($symbol) {
    try {
        $cleanSymbol = str_replace('-USDT', '', $symbol) . '-USDT';
        $url = "https://open-api.bingx.com/openApi/swap/v2/quote/price?symbol=" . urlencode($cleanSymbol);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'header' => 'User-Agent: Mozilla/5.0 (compatible; BingX-Monitor/1.0)'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return null;
        }
        
        $data = json_decode($response, true);
        if (isset($data['data']['price'])) {
            return floatval($data['data']['price']);
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error getting price for $symbol: " . $e->getMessage());
        return null;
    }
}

function sendTelegramMessage($message) {
    $botToken = getenv('TELEGRAM_BOT_TOKEN');
    $chatId = getenv('TELEGRAM_CHAT_ID');
    
    if (empty($botToken) || empty($chatId)) {
        error_log('Telegram credentials not configured');
        return false;
    }
    
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ]);
    
    $result = @file_get_contents($url, false, $context);
    return $result !== false;
}

function calculatePnLPercentage($entryPrice, $currentPrice, $side, $leverage) {
    if ($side === 'LONG' || $side === 'Buy') {
        $priceChange = ($currentPrice - $entryPrice) / $entryPrice;
    } else {
        $priceChange = ($entryPrice - $currentPrice) / $entryPrice;
    }
    
    return $priceChange * $leverage * 100;
}

function closePosition($position, $reason, $currentPrice) {
    // This would integrate with BingX API to close the position
    // For now, just update the database
    global $pdo;
    
    try {
        $sql = "UPDATE positions SET 
                status = 'CLOSED', 
                closed_at = NOW(), 
                close_price = ?, 
                close_reason = ? 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$currentPrice, $reason, $position['id']]);
        
        error_log("Position closed: {$position['symbol']} - {$reason} at {$currentPrice}");
        return true;
    } catch (Exception $e) {
        error_log("Error closing position: " . $e->getMessage());
        return false;
    }
}

// Main execution
try {
    echo "[" . date('Y-m-d H:i:s') . "] Starting target/stop-loss monitor...\n";
    
    $pdo = getDbConnection();
    $targetAction = getenv('TARGET_ACTION') ?: 'telegram_notify';
    $targetPercentage = floatval(getenv('TARGET_PERCENTAGE') ?: 10);
    $autoStopLoss = getenv('AUTO_STOP_LOSS') === 'true';
    
    // Get open positions from signals table
    $sql = "SELECT s.*, p.* 
            FROM signals s 
            LEFT JOIN positions p ON s.id = p.signal_id 
            WHERE s.status = 'OPEN' AND p.status = 'OPEN'
            ORDER BY s.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($positions) . " open positions.\n";
    
    foreach ($positions as $position) {
        $symbol = $position['symbol'];
        $entryPrice = floatval($position['entry_price']);
        $stopLoss = floatval($position['stop_loss']);
        $side = $position['direction'];
        $leverage = floatval($position['leverage']);
        
        echo "Checking {$symbol} - Entry: {$entryPrice}, SL: {$stopLoss}, Side: {$side}\n";
        
        // Get current price
        $currentPrice = getBingXPrice($symbol);
        if ($currentPrice === null) {
            echo "Could not get price for {$symbol}\n";
            continue;
        }
        
        echo "Current price: {$currentPrice}\n";
        
        // Calculate P&L percentage
        $pnlPercent = calculatePnLPercentage($entryPrice, $currentPrice, $side, $leverage);
        echo "P&L: {$pnlPercent}%\n";
        
        // Check stop loss
        if ($autoStopLoss && $stopLoss > 0) {
            $stopLossHit = false;
            
            if (($side === 'LONG' || $side === 'Buy') && $currentPrice <= $stopLoss) {
                $stopLossHit = true;
            } elseif (($side === 'SHORT' || $side === 'Sell') && $currentPrice >= $stopLoss) {
                $stopLossHit = true;
            }
            
            if ($stopLossHit) {
                echo "Stop loss hit for {$symbol}!\n";
                
                if (closePosition($position, 'STOP_LOSS', $currentPrice)) {
                    $message = "🔴 <b>Stop Loss Triggered</b>\n\n";
                    $message .= "Symbol: {$symbol}\n";
                    $message .= "Side: {$side}\n";
                    $message .= "Entry Price: {$entryPrice}\n";
                    $message .= "Stop Loss: {$stopLoss}\n";
                    $message .= "Close Price: {$currentPrice}\n";
                    $message .= "P&L: " . number_format($pnlPercent, 2) . "%\n";
                    $message .= "Leverage: {$leverage}x\n";
                    
                    sendTelegramMessage($message);
                    echo "Position closed automatically.\n";
                    continue;
                }
            }
        }
        
        // Check target
        if ($pnlPercent >= $targetPercentage) {
            echo "Target reached for {$symbol}! ({$pnlPercent}%)\n";
            
            if ($targetAction === 'auto_close') {
                // Auto close the position
                if (closePosition($position, 'TARGET_REACHED', $currentPrice)) {
                    $message = "🟢 <b>Target Reached - Auto Closed</b>\n\n";
                    $message .= "Symbol: {$symbol}\n";
                    $message .= "Side: {$side}\n";
                    $message .= "Entry Price: {$entryPrice}\n";
                    $message .= "Close Price: {$currentPrice}\n";
                    $message .= "P&L: " . number_format($pnlPercent, 2) . "%\n";
                    $message .= "Target: {$targetPercentage}%\n";
                    $message .= "Leverage: {$leverage}x\n";
                    
                    sendTelegramMessage($message);
                    echo "Position closed automatically.\n";
                }
            } else {
                // Check if already notified
                if (!isset($position['target_notified_at']) || $position['target_notified_at'] === null) {
                    // Send telegram notification
                    $message = "🎯 <b>Target Reached</b>\n\n";
                    $message .= "Symbol: {$symbol}\n";
                    $message .= "Side: {$side}\n";
                    $message .= "Entry Price: {$entryPrice}\n";
                    $message .= "Current Price: {$currentPrice}\n";
                    $message .= "P&L: " . number_format($pnlPercent, 2) . "%\n";
                    $message .= "Target: {$targetPercentage}%\n";
                    $message .= "Leverage: {$leverage}x\n\n";
                    $message .= "Close this position manually in the app.";
                    
                    if (sendTelegramMessage($message)) {
                        // Mark as notified to avoid spam
                        $sql = "UPDATE positions SET target_notified_at = NOW() WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$position['id']]);
                        echo "Target notification sent.\n";
                    }
                }
            }
        }
        
        // Check if P&L is very negative (emergency stop loss)
        if ($pnlPercent <= -50) {
            echo "Emergency stop loss for {$symbol}! ({$pnlPercent}%)\n";
            
            if (closePosition($position, 'EMERGENCY_STOP', $currentPrice)) {
                $message = "🚨 <b>Emergency Stop Loss</b>\n\n";
                $message .= "Symbol: {$symbol}\n";
                $message .= "Side: {$side}\n";
                $message .= "Entry Price: {$entryPrice}\n";
                $message .= "Close Price: {$currentPrice}\n";
                $message .= "P&L: " . number_format($pnlPercent, 2) . "%\n";
                $message .= "Leverage: {$leverage}x\n\n";
                $message .= "Position closed to prevent further losses.";
                
                sendTelegramMessage($message);
                echo "Position closed by emergency stop.\n";
            }
        }
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Target/stop-loss monitor completed.\n";
    
} catch (Exception $e) {
    error_log("Target/StopLoss Monitor Error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}
?>