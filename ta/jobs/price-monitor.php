<?php
/**
 * Price Monitor Cron Job
 * Runs every minute to check watchlist items against current prices
 * Sends Telegram notifications when price targets are reached
 */

// Prevent direct browser access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

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
        error_log("Price Monitor - Database connection failed: " . $e->getMessage());
        return null;
    }
}

// Get current price from BingX
function getCurrentPrice($symbol, $apiKey, $apiSecret) {
    try {
        // Convert symbol to BingX format
        $bingxSymbol = $symbol;
        if (!strpos($bingxSymbol, 'USDT')) {
            $bingxSymbol = $symbol . '-USDT';
        }
        
        $timestamp = round(microtime(true) * 1000);
        $queryString = "symbol={$bingxSymbol}&timestamp={$timestamp}";
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        
        $url = "https://open-api.bingx.com/openApi/swap/v2/quote/price?" . $queryString . "&signature=" . $signature;
        
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
        
        return floatval($data['data']['price']);
        
    } catch (Exception $e) {
        error_log("Price Monitor - Failed to get price for {$symbol}: " . $e->getMessage());
        return null;
    }
}

// Include Telegram messaging class
require_once $projectDir . '/api/telegram.php';

// Get auto trading settings
function getAutoTradingSettings() {
    $settings = [
        'auto_trading_enabled' => false,
        'limit_order_action' => 'telegram_approval'
    ];
    
    $envPath = dirname(__DIR__) . '/.env';
    if (file_exists($envPath)) {
        $envContent = file_get_contents($envPath);
        $lines = explode("\n", $envContent);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line && strpos($line, '#') !== 0 && strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                if ($key === 'AUTO_TRADING_ENABLED') {
                    $settings['auto_trading_enabled'] = $value === 'true';
                } elseif ($key === 'LIMIT_ORDER_ACTION') {
                    $settings['limit_order_action'] = $value;
                }
            }
        }
    }
    
    return $settings;
}

// Auto execute limit order
function autoExecuteLimitOrder($pdo, $orderId, $apiKey, $apiSecret) {
    try {
        // Import the functions needed for auto execution
        require_once dirname(__DIR__) . '/api/api_helper.php';
        
        // Get the order details
        $sql = "SELECT o.*, s.signal_type, s.leverage as signal_leverage 
                FROM orders o 
                LEFT JOIN signals s ON o.signal_id = s.id 
                WHERE o.id = :order_id AND o.status IN ('NEW', 'PENDING')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            error_log("Price Monitor - Auto Trade: Order {$orderId} not found or already processed");
            return false;
        }
        
        // Check if position already exists for this signal
        $sql = "SELECT COUNT(*) FROM positions WHERE signal_id = :signal_id AND status = 'OPEN'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':signal_id' => $order['signal_id']]);
        $existingPositions = $stmt->fetchColumn();
        
        if ($existingPositions > 0) {
            // Update order status to prevent multiple openings
            $sql = "UPDATE orders SET status = 'FILLED', fill_time = NOW() WHERE id = :order_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':order_id' => $orderId]);
            
            error_log("Price Monitor - Auto Trade: Position already exists for signal {$order['signal_id']}");
            return false;
        }
        
        // Get account balance
        $availableBalance = getAccountBalanceForAutoTrade($apiKey, $apiSecret);
        if ($availableBalance <= 0) {
            error_log("Price Monitor - Auto Trade: Unable to get account balance or insufficient funds");
            return false;
        }
        
        // Calculate position details
        $quantity = floatval($order['quantity']);
        $leverage = intval($order['leverage']) ?: intval($order['signal_leverage']) ?: 1;
        $symbol = $order['symbol'];
        $side = $order['side'];
        $direction = strtolower($order['signal_type']);
        
        // Set leverage first
        $positionSide = ($direction === 'long') ? 'LONG' : 'SHORT';
        $leverageSet = setBingXLeverageForAutoTrade($apiKey, $apiSecret, $symbol, $leverage, $positionSide);
        if ($leverageSet) {
            usleep(500000); // 0.5 second delay
        }
        
        // Place market order
        $orderData = [
            'symbol' => $symbol,
            'side' => $side,
            'quantity' => $quantity,
            'direction' => $direction
        ];
        
        $orderResult = placeBingXMarketOrderForAutoTrade($apiKey, $apiSecret, $orderData);
        
        if (!$orderResult['success']) {
            error_log("Price Monitor - Auto Trade: Failed to place market order for order {$orderId}: " . $orderResult['error']);
            return false;
        }
        
        // Calculate margin used
        $marginUsed = ($quantity * (floatval($order['price']) ?: 1)) / $leverage;
        
        // Save position to database
        $positionSaved = savePositionToDbForAutoTrade($pdo, [
            'symbol' => str_replace('-USDT', '', $symbol),
            'side' => strtoupper($direction),
            'size' => $quantity,
            'entry_price' => floatval($order['price']),
            'leverage' => $leverage,
            'margin_used' => $marginUsed,
            'signal_id' => $order['signal_id']
        ]);
        
        if (!$positionSaved) {
            error_log("Price Monitor - Auto Trade: Failed to save position to database for order {$orderId}");
            return false;
        }
        
        // Update order status
        $sql = "UPDATE orders SET 
                status = 'FILLED', 
                bingx_order_id = :bingx_order_id, 
                fill_time = NOW() 
                WHERE id = :order_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':bingx_order_id' => $orderResult['order_id'],
            ':order_id' => $orderId
        ]);
        
        error_log("Price Monitor - Auto Trade: Successfully executed order {$orderId} - BingX Order ID: {$orderResult['order_id']}");
        return true;
        
    } catch (Exception $e) {
        error_log("Price Monitor - Auto Trade Error for order {$orderId}: " . $e->getMessage());
        return false;
    }
}

// Helper functions for auto trading (simplified versions)
function getAccountBalanceForAutoTrade($apiKey, $apiSecret) {
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
        
        if ($response && $httpCode == 200) {
            $data = json_decode($response, true);
            if ($data && $data['code'] == 0 && isset($data['data'])) {
                foreach ($data['data'] as $balance) {
                    if (isset($balance['asset']) && $balance['asset'] === 'USDT') {
                        return floatval($balance['availableMargin'] ?? $balance['available'] ?? 0);
                    }
                }
            }
        }
        return 0;
    } catch (Exception $e) {
        return 0;
    }
}

function setBingXLeverageForAutoTrade($apiKey, $apiSecret, $symbol, $leverage, $side = 'BOTH') {
    try {
        $timestamp = round(microtime(true) * 1000);
        
        $params = [
            'symbol' => $symbol,
            'side' => $side,
            'leverage' => $leverage,
            'timestamp' => $timestamp
        ];
        
        $queryString = http_build_query($params);
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://open-api.bingx.com/openApi/swap/v2/trade/leverage");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $queryString . "&signature=" . $signature);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'X-BX-APIKEY: ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpCode == 200) {
            $data = json_decode($response, true);
            return $data && $data['code'] == 0;
        }
        return false;
        
    } catch (Exception $e) {
        return false;
    }
}

function placeBingXMarketOrderForAutoTrade($apiKey, $apiSecret, $orderData) {
    try {
        $timestamp = round(microtime(true) * 1000);
        
        $positionSide = 'BOTH'; // Default for one-way mode
        if (isset($orderData['direction'])) {
            $positionSide = strtoupper($orderData['direction']); 
        }
        
        $params = [
            'symbol' => $orderData['symbol'],
            'side' => $orderData['side'],
            'type' => 'MARKET',
            'quantity' => $orderData['quantity'],
            'positionSide' => $positionSide,
            'timestamp' => $timestamp
        ];
        
        $queryString = http_build_query($params);
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://open-api.bingx.com/openApi/swap/v2/trade/order");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $queryString . "&signature=" . $signature);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'X-BX-APIKEY: ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("BingX API HTTP error: {$httpCode}. Response: " . $response);
        }
        
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['code']) || $data['code'] !== 0) {
            $errorMsg = isset($data['msg']) ? $data['msg'] : 'Unknown API error';
            throw new Exception('BingX Order Error: ' . $errorMsg);
        }
        
        return [
            'success' => true,
            'order_id' => $data['data']['orderId'] ?? null,
            'client_order_id' => $data['data']['clientOrderId'] ?? null,
            'data' => $data['data']
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function savePositionToDbForAutoTrade($pdo, $positionData) {
    try {
        $sql = "INSERT INTO positions (
            symbol, side, size, entry_price, leverage, margin_used,
            signal_id, status, opened_at, notes
        ) VALUES (
            :symbol, :side, :size, :entry_price, :leverage, :margin_used,
            :signal_id, :status, NOW(), :notes
        )";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':symbol' => $positionData['symbol'],
            ':side' => $positionData['side'],
            ':size' => $positionData['size'],
            ':entry_price' => $positionData['entry_price'],
            ':leverage' => $positionData['leverage'],
            ':margin_used' => $positionData['margin_used'],
            ':signal_id' => $positionData['signal_id'],
            ':status' => 'OPEN',
            ':notes' => $positionData['notes'] ?? 'Auto-opened from limit trigger'
        ]);
    } catch (Exception $e) {
        return false;
    }
}

// Mark watchlist item as triggered
function markTriggered($pdo, $watchlistId) {
    try {
        $sql = "UPDATE watchlist SET status = 'triggered', triggered_at = NOW() WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([':id' => $watchlistId]);
    } catch (Exception $e) {
        error_log("Price Monitor - Failed to mark watchlist item {$watchlistId} as triggered: " . $e->getMessage());
        return false;
    }
}

// Main execution
echo "Starting price monitor at " . date('Y-m-d H:i:s') . "\n";

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
    
    // Get auto trading settings
    $autoTradingSettings = getAutoTradingSettings();
    echo "Auto trading enabled: " . ($autoTradingSettings['auto_trading_enabled'] ? 'YES' : 'NO') . "\n";
    echo "Limit order action: " . $autoTradingSettings['limit_order_action'] . "\n";
    
    // Get active watchlist items
    $sql = "SELECT * FROM watchlist WHERE status = 'active' ORDER BY created_at ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $watchlistItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($watchlistItems) . " active watchlist items\n";
    
    $checkedSymbols = [];
    $triggeredCount = 0;
    $autoExecutedCount = 0;
    
    foreach ($watchlistItems as $item) {
        $symbol = $item['symbol'];
        $entryPrice = floatval($item['entry_price']);
        $entryType = $item['entry_type'];
        $direction = $item['direction'];
        $marginAmount = floatval($item['margin_amount']);
        
        // Get current price (cache per symbol to avoid multiple API calls)
        if (!isset($checkedSymbols[$symbol])) {
            $currentPrice = getCurrentPrice($symbol, $apiKey, $apiSecret);
            if ($currentPrice === null) {
                continue;
            }
            $checkedSymbols[$symbol] = $currentPrice;
        } else {
            $currentPrice = $checkedSymbols[$symbol];
        }
        
        // Check if price target is reached
        $triggered = false;
        
        if ($direction === 'long') {
            // For long positions, trigger when price goes down to entry level
            $triggered = $currentPrice <= $entryPrice;
        } else {
            // For short positions, trigger when price goes up to entry level
            $triggered = $currentPrice >= $entryPrice;
        }
        
        if ($triggered) {
            // Mark as triggered
            if (markTriggered($pdo, $item['id'])) {
                $triggeredCount++;
                
                // Find the corresponding limit order for this watchlist item
                $orderId = null;
                $sql = "SELECT id FROM orders 
                        WHERE symbol = :symbol 
                        AND price = :price 
                        AND entry_level = :entry_level 
                        AND status IN ('NEW', 'PENDING') 
                        ORDER BY created_at DESC 
                        LIMIT 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':symbol' => $symbol . '-USDT',
                    ':price' => $entryPrice,
                    ':entry_level' => strtoupper($entryType)
                ]);
                $orderResult = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($orderResult) {
                    $orderId = $orderResult['id'];
                }
                
                echo "Triggered: {$symbol} {$direction} at {$currentPrice} (target: {$entryPrice}) - Order ID: {$orderId}\n";
                
                // Check if auto trading is enabled and should auto execute
                if ($autoTradingSettings['auto_trading_enabled'] && 
                    $autoTradingSettings['limit_order_action'] === 'auto_execute' && 
                    $orderId) {
                    
                    echo "Auto executing order {$orderId}...\n";
                    $autoExecuted = autoExecuteLimitOrder($pdo, $orderId, $apiKey, $apiSecret);
                    
                    if ($autoExecuted) {
                        $autoExecutedCount++;
                        echo "Successfully auto-executed order {$orderId}\n";
                        
                        // Send success notification
                        $telegram = new TelegramMessenger();
                        $telegram->sendAutoTradeNotification(
                            $symbol,
                            $entryType, 
                            $entryPrice,
                            $currentPrice,
                            $direction,
                            $marginAmount,
                            'executed'
                        );
                    } else {
                        echo "Failed to auto-execute order {$orderId}\n";
                        
                        // Send failure notification and fall back to manual approval
                        $telegram = new TelegramMessenger();
                        $telegram->sendPriceAlert(
                            $symbol,
                            $entryType, 
                            $entryPrice,
                            $currentPrice,
                            $direction,
                            $marginAmount,
                            $item['id'],
                            $orderId
                        );
                    }
                } else {
                    // Send normal notification with interactive buttons for manual approval
                    $telegram = new TelegramMessenger();
                    $telegram->sendPriceAlert(
                        $symbol,
                        $entryType, 
                        $entryPrice,
                        $currentPrice,
                        $direction,
                        $marginAmount,
                        $item['id'],
                        $orderId  // Pass order ID for token generation
                    );
                }
            }
        }
    }
    
    echo "Price monitoring completed. Triggered: {$triggeredCount} alerts, Auto-executed: {$autoExecutedCount} orders\n";
    
} catch (Exception $e) {
    error_log("Price Monitor - Error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Price monitor finished at " . date('Y-m-d H:i:s') . "\n";
?>