<?php
/**
 * Order Status Cron Job
 * Runs every 2 minutes to check pending order status
 * Sends notifications when orders are filled or cancelled
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
        error_log("Order Status - Database connection failed: " . $e->getMessage());
        return null;
    }
}

// Get order status from BingX
function getOrderStatus($orderId, $symbol, $apiKey, $apiSecret) {
    try {
        // Convert symbol to BingX format
        $bingxSymbol = $symbol;
        if (!strpos($bingxSymbol, 'USDT')) {
            $bingxSymbol = $symbol . '-USDT';
        }
        
        $timestamp = round(microtime(true) * 1000);
        $queryString = "orderId={$orderId}&symbol={$bingxSymbol}&timestamp={$timestamp}";
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        
        $url = "https://open-api.bingx.com/openApi/swap/v2/trade/order?" . $queryString . "&signature=" . $signature;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
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
        
        return $data['data'];
        
    } catch (Exception $e) {
        error_log("Order Status - Failed to get order status for {$orderId}: " . $e->getMessage());
        return null;
    }
}

// Update order status in database
function updateOrderStatus($pdo, $orderId, $status, $fillPrice = null, $fillTime = null) {
    try {
        $sql = "UPDATE orders SET status = :status";
        $params = [':status' => $status, ':id' => $orderId];
        
        if ($fillPrice !== null) {
            $sql .= ", fill_price = :fill_price";
            $params[':fill_price'] = $fillPrice;
        }
        
        if ($fillTime !== null) {
            $sql .= ", fill_time = :fill_time";
            $params[':fill_time'] = $fillTime;
        }
        
        $sql .= " WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
        
    } catch (Exception $e) {
        error_log("Order Status - Failed to update order {$orderId}: " . $e->getMessage());
        return false;
    }
}

// Create position record for filled market orders
function createPositionRecord($pdo, $order, $fillPrice) {
    try {
        // Check if position already exists
        $checkSql = "SELECT id FROM positions WHERE signal_id = :signal_id AND status = 'OPEN'";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':signal_id' => $order['signal_id']]);
        
        if ($checkStmt->fetch()) {
            return true; // Position already exists
        }
        
        // Determine position side
        $positionSide = ($order['side'] === 'BUY') ? 'LONG' : 'SHORT';
        
        // Calculate margin used
        $marginUsed = floatval($order['quantity']) / floatval($order['leverage']);
        
        $sql = "INSERT INTO positions 
                (symbol, side, size, entry_price, leverage, margin_used, signal_id) 
                VALUES (:symbol, :side, :size, :entry_price, :leverage, :margin_used, :signal_id)";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':symbol' => $order['symbol'],
            ':side' => $positionSide,
            ':size' => $order['quantity'],
            ':entry_price' => $fillPrice,
            ':leverage' => $order['leverage'],
            ':margin_used' => $marginUsed,
            ':signal_id' => $order['signal_id']
        ]);
        
    } catch (Exception $e) {
        error_log("Order Status - Failed to create position for order {$order['id']}: " . $e->getMessage());
        return false;
    }
}

// Send Telegram notification
function sendTelegramNotification($message, $priority = 'HIGH') {
    $botToken = getenv('TELEGRAM_BOT_TOKEN');
    $chatId = getenv('TELEGRAM_CHAT_ID');
    
    if (empty($botToken) || empty($chatId)) {
        return false;
    }
    
    $priorityEmojis = [
        'HIGH' => '✅',
        'MEDIUM' => '🎯',
        'LOW' => 'ℹ️'
    ];
    
    $emoji = $priorityEmojis[$priority] ?? '✅';
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
        error_log("Order Status - Telegram notification failed: " . $e->getMessage());
        return false;
    }
}

// Main execution
echo "Starting order status check at " . date('Y-m-d H:i:s') . "\n";

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
    
    // Get pending orders from database
    $sql = "SELECT * FROM orders WHERE status IN ('NEW', 'PENDING') AND bingx_order_id IS NOT NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $pendingOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($pendingOrders)) {
        echo "No pending orders to check\n";
        return;
    }
    
    echo "Found " . count($pendingOrders) . " pending orders\n";
    
    $updatedCount = 0;
    $filledCount = 0;
    $cancelledCount = 0;
    
    foreach ($pendingOrders as $order) {
        $bingxOrderId = $order['bingx_order_id'];
        $symbol = $order['symbol'];
        
        // Get order status from BingX
        $orderStatus = getOrderStatus($bingxOrderId, $symbol, $apiKey, $apiSecret);
        
        if (!$orderStatus) {
            continue;
        }
        
        $status = $orderStatus['status'] ?? '';
        $fillPrice = isset($orderStatus['avgPrice']) ? floatval($orderStatus['avgPrice']) : null;
        $fillTime = null;
        
        // Convert BingX status to our status
        $newStatus = null;
        if ($status === 'FILLED') {
            $newStatus = 'FILLED';
            $fillTime = date('Y-m-d H:i:s');
            $filledCount++;
            
            // Create position record for market orders
            if ($order['type'] === 'MARKET' && $fillPrice) {
                createPositionRecord($pdo, $order, $fillPrice);
            }
            
            // Send filled notification
            $sideEmoji = ($order['side'] === 'BUY') ? '📈' : '📉';
            $entryLevel = str_replace('_', ' ', strtoupper($order['entry_level']));
            
            $message = "<b>Order Filled!</b>\n\n" .
                      "{$sideEmoji} <b>{$order['symbol']}</b> ({$entryLevel})\n" .
                      "💰 Size: \${$order['quantity']}\n" .
                      "💵 Fill Price: \${$fillPrice}\n" .
                      "⚡ Leverage: {$order['leverage']}x\n" .
                      "🎯 Side: " . strtoupper($order['side']);
            
            sendTelegramNotification($message, 'HIGH');
            
        } elseif ($status === 'CANCELED' || $status === 'CANCELLED') {
            $newStatus = 'CANCELLED';
            $cancelledCount++;
            
            // Send cancelled notification
            $message = "<b>Order Cancelled</b>\n\n" .
                      "❌ <b>{$order['symbol']}</b>\n" .
                      "💰 Size: \${$order['quantity']}\n" .
                      "💵 Price: \${$order['price']}\n" .
                      "🎯 Side: " . strtoupper($order['side']);
            
            sendTelegramNotification($message, 'MEDIUM');
        }
        
        if ($newStatus && $newStatus !== $order['status']) {
            if (updateOrderStatus($pdo, $order['id'], $newStatus, $fillPrice, $fillTime)) {
                $updatedCount++;
                echo "Updated: {$order['symbol']} order {$bingxOrderId} -> {$newStatus}\n";
            }
        }
    }
    
    echo "Order status check completed. Updated: {$updatedCount}, Filled: {$filledCount}, Cancelled: {$cancelledCount}\n";
    
} catch (Exception $e) {
    error_log("Order Status - Error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Order status check finished at " . date('Y-m-d H:i:s') . "\n";
?>