<?php
/**
 * Balance Sync Cron Job
 * Runs every 15 minutes to sync account balance from BingX
 * Updates available funds for position sizing calculations
 */

// Prevent direct browser access
if (php_sapi_name() !== 'cli' ) {
    //die('This script can only be run from command line');
}

//define('CRON_RUNNING', true);

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
        error_log("Balance Sync - Database connection failed: " . $e->getMessage());
        return null;
    }
}

// Get account balance from BingX
function getAccountBalance($apiKey, $apiSecret) {
    try {
        $timestamp = round(microtime(true) * 1000);
        $queryString = "timestamp={$timestamp}";
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        
        $url = "https://open-api.bingx.com/openApi/swap/v2/user/balance?" . $queryString . "&signature=" . $signature;
        
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
        
        // Find USDT balance
        foreach ($data['data'] as $balance) {
            if (isset($balance['asset']) && $balance['asset'] === 'USDT') {
                return [
                    'total_balance' => floatval($balance['balance'] ?? 0),
                    'available_balance' => floatval($balance['availableMargin'] ?? $balance['available'] ?? 0),
                    'margin_used' => floatval($balance['usedMargin'] ?? 0),
                    'unrealized_pnl' => floatval($balance['unrealizedProfit'] ?? 0)
                ];
            }
        }
        
        throw new Exception('USDT balance not found');
        
    } catch (Exception $e) {
        error_log("Balance Sync - Failed to get balance: " . $e->getMessage());
        return null;
    }
}

// Update or insert balance record
function updateAccountBalance($pdo, $balanceData) {
    try {
        // Check if there's an existing record
        $checkSql = "SELECT id FROM account_balance ORDER BY updated_at DESC LIMIT 1";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute();
        $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingRecord) {
            // Update existing record
            $sql = "UPDATE account_balance SET 
                    total_balance = :total_balance,
                    available_balance = :available_balance,
                    margin_used = :margin_used,
                    unrealized_pnl = :unrealized_pnl,
                    updated_at = NOW()
                    WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                ':total_balance' => $balanceData['total_balance'],
                ':available_balance' => $balanceData['available_balance'],
                ':margin_used' => $balanceData['margin_used'],
                ':unrealized_pnl' => $balanceData['unrealized_pnl'],
                ':id' => $existingRecord['id']
            ]);
        } else {
            // Insert new record
            $sql = "INSERT INTO account_balance 
                    (total_balance, available_balance, margin_used, unrealized_pnl) 
                    VALUES (:total_balance, :available_balance, :margin_used, :unrealized_pnl)";
            
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                ':total_balance' => $balanceData['total_balance'],
                ':available_balance' => $balanceData['available_balance'],
                ':margin_used' => $balanceData['margin_used'],
                ':unrealized_pnl' => $balanceData['unrealized_pnl']
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Balance Sync - Failed to update balance: " . $e->getMessage());
        return false;
    }
}

// Include Telegram messaging class
require_once $projectDir . '/api/telegram.php';

// Check for significant balance changes
function checkBalanceChanges($oldBalance, $newBalance) {
    if (!$oldBalance) return false;
    
    $oldTotal = floatval($oldBalance['total_balance']);
    $newTotal = floatval($newBalance['total_balance']);
    
    if ($oldTotal == 0) return false;
    
    $changePercent = (($newTotal - $oldTotal) / $oldTotal) * 100;
    
    // Notify for changes > 5%
    if (abs($changePercent) >= 5) {
        return [
            'type' => $changePercent > 0 ? 'increase' : 'decrease',
            'percent' => round(abs($changePercent), 2),
            'old_total' => $oldTotal,
            'new_total' => $newTotal,
            'change_amount' => round($newTotal - $oldTotal, 2)
        ];
    }
    
    return false;
}

// Main execution
echo "Starting balance sync at " . date('Y-m-d H:i:s') . "\n";

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
    
    // Get previous balance for comparison
    $prevSql = "SELECT * FROM account_balance ORDER BY updated_at DESC LIMIT 1";
    $prevStmt = $pdo->prepare($prevSql);
    $prevStmt->execute();
    $previousBalance = $prevStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get current balance from BingX
    $currentBalance = getAccountBalance($apiKey, $apiSecret);
    
    if (!$currentBalance) {
        throw new Exception('Failed to fetch account balance from BingX');
    }
    
    // Update database
    if (updateAccountBalance($pdo, $currentBalance)) {
        echo "Balance updated successfully\n";
        echo "Total: ${$currentBalance['total_balance']}\n";
        echo "Available: ${$currentBalance['available_balance']}\n";
        echo "Margin Used: ${$currentBalance['margin_used']}\n";
        echo "Unrealized P&L: ${$currentBalance['unrealized_pnl']}\n";
        
        // Check for significant changes
        if ($previousBalance) {
            $change = checkBalanceChanges($previousBalance, $currentBalance);
            
            if ($change) {
                $telegram = new TelegramMessenger();
                $telegram->sendBalanceChange(
                    $change['type'],
                    $change['percent'],
                    $change['old_total'],
                    $change['new_total'],
                    $change['change_amount']
                );
                
                echo "Balance change notification sent: {$change['type']} {$change['percent']}%\n";
            }
        }
        
    } else {
        throw new Exception('Failed to update balance in database');
    }
    
    echo "Balance sync completed successfully\n";
    
} catch (Exception $e) {
    error_log("Balance Sync - Error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Balance sync finished at " . date('Y-m-d H:i:s') . "\n";
?>