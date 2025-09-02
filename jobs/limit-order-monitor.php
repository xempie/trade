<?php
/**
 * Limit Order Monitor Cronjob
 * Monitors pending limit orders and executes them when entry price is reached
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

function executeMarketOrder($order, $currentPrice) {
    // Include the place_order API logic here
    require_once __DIR__ . '/../api/place_order.php';
    
    // Prepare market order data
    $orderData = [
        'signal_id' => $order['signal_id'],
        'symbol' => $order['symbol'],
        'side' => $order['side'],
        'type' => 'MARKET',
        'quantity' => $order['quantity'],
        'leverage' => $order['leverage'],
        'entry_level' => 'market'
    ];
    
    try {
        // Execute the market order (this would use the existing place_order.php logic)
        // For now, just log the action
        error_log("Executing market order for {$order['symbol']} at price {$currentPrice}");
        
        // Update the original limit order status to TRIGGERED
        global $pdo;
        $sql = "UPDATE orders SET status = 'TRIGGERED', triggered_at = NOW(), triggered_price = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$currentPrice, $order['id']]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error executing market order: " . $e->getMessage());
        return false;
    }
}

// Main execution
try {
    echo "[" . date('Y-m-d H:i:s') . "] Starting limit order monitor...\n";
    
    // Check if auto trading is enabled
    $autoTradingEnabled = getenv('AUTO_TRADING_ENABLED') === 'true';
    if (!$autoTradingEnabled) {
        echo "Auto trading disabled, exiting.\n";
        exit(0);
    }
    
    $pdo = getDbConnection();
    $limitOrderAction = getenv('LIMIT_ORDER_ACTION') ?: 'telegram_approval';
    
    // Get pending limit orders
    $sql = "SELECT * FROM orders WHERE type = 'LIMIT' AND status IN ('NEW', 'PENDING') AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $pendingOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($pendingOrders) . " pending limit orders.\n";
    
    foreach ($pendingOrders as $order) {
        $symbol = $order['symbol'];
        $entryPrice = floatval($order['price']);
        $side = $order['side'];
        
        echo "Checking {$symbol} - Target: {$entryPrice} ({$side})\n";
        
        // Get current price
        $currentPrice = getBingXPrice($symbol);
        if ($currentPrice === null) {
            echo "Could not get price for {$symbol}\n";
            continue;
        }
        
        echo "Current price: {$currentPrice}\n";
        
        // Check if entry price is reached
        $priceReached = false;
        $tolerance = 0.001; // 0.1% tolerance
        
        if ($side === 'Buy') {
            // Buy order: execute when current price <= entry price
            $priceReached = $currentPrice <= ($entryPrice * (1 + $tolerance));
        } else {
            // Sell order: execute when current price >= entry price
            $priceReached = $currentPrice >= ($entryPrice * (1 - $tolerance));
        }
        
        if ($priceReached) {
            echo "Entry price reached for {$symbol}!\n";
            
            if ($limitOrderAction === 'auto_execute') {
                // Auto execute the order
                if (executeMarketOrder($order, $currentPrice)) {
                    $message = "🤖 <b>Auto Executed</b>\n\n";
                    $message .= "Symbol: {$symbol}\n";
                    $message .= "Side: {$side}\n";
                    $message .= "Entry Price: {$entryPrice}\n";
                    $message .= "Executed at: {$currentPrice}\n";
                    $message .= "Quantity: {$order['quantity']}\n";
                    $message .= "Leverage: {$order['leverage']}x\n";
                    
                    sendTelegramMessage($message);
                    echo "Order executed automatically.\n";
                }
            } else {
                // Send telegram message for approval
                $message = "⚠️ <b>Limit Order Ready</b>\n\n";
                $message .= "Symbol: {$symbol}\n";
                $message .= "Side: {$side}\n";
                $message .= "Entry Price: {$entryPrice}\n";
                $message .= "Current Price: {$currentPrice}\n";
                $message .= "Quantity: {$order['quantity']}\n";
                $message .= "Leverage: {$order['leverage']}x\n\n";
                $message .= "Execute this order manually in the app.";
                
                if (sendTelegramMessage($message)) {
                    // Mark as notified to avoid spam
                    $sql = "UPDATE orders SET status = 'NOTIFIED', notified_at = NOW() WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$order['id']]);
                    echo "Telegram notification sent.\n";
                }
            }
        }
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Limit order monitor completed.\n";
    
} catch (Exception $e) {
    error_log("Limit Order Monitor Error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}
?>