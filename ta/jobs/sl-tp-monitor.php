<?php
/**
 * Stop Loss and Take Profit Order Monitor
 * 
 * This script runs every 5 minutes to ensure all open positions have both:
 * - Stop Loss (SL) order set on BingX
 * - At least 1 Take Profit (TP) order set on BingX
 * 
 * If missing, it creates the missing orders using prices from the signals table
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/sl-tp-monitor.log');

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

// Get BingX API credentials and configuration
function getBingXConfig() {
    $config = [
        'api_key' => getenv('BINGX_API_KEY') ?: '',
        'api_secret' => getenv('BINGX_SECRET_KEY') ?: '',
        'trading_mode' => getenv('TRADING_MODE') ?: 'demo',
    ];
    
    // Determine API URL based on trading mode
    if ($config['trading_mode'] === 'live') {
        $config['base_url'] = getenv('BINGX_LIVE_URL') ?: 'https://open-api.bingx.com';
        $config['is_demo'] = false;
    } else {
        $config['base_url'] = getenv('BINGX_DEMO_URL') ?: 'https://open-api-vst.bingx.com';
        $config['is_demo'] = true;
    }
    
    return $config;
}

// Logging function
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    echo $logMessage;
    file_put_contents(__DIR__ . '/sl-tp-monitor.log', $logMessage, FILE_APPEND | LOCK_EX);
}

// Get all open positions from database
function getOpenPositions($pdo) {
    try {
        $sql = "SELECT p.*, s.stop_loss, s.take_profit_1, s.take_profit_2, s.take_profit_3 
                FROM positions p 
                LEFT JOIN signals s ON p.signal_id = s.id 
                WHERE p.status = 'OPEN'";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        logMessage("Error getting open positions: " . $e->getMessage());
        return [];
    }
}

// Get existing orders for a position from BingX
function getExistingOrders($config, $symbol) {
    try {
        $timestamp = round(microtime(true) * 1000);
        $params = [
            'symbol' => $symbol,
            'timestamp' => $timestamp
        ];
        
        $queryString = http_build_query($params);
        $signature = hash_hmac('sha256', $queryString, $config['api_secret']);
        
        $url = $config['base_url'] . "/openApi/swap/v2/trade/openOrders?" . $queryString . "&signature=" . $signature;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-BX-APIKEY: ' . $config['api_key']
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpCode == 200) {
            $data = json_decode($response, true);
            if ($data && $data['code'] == 0) {
                return $data['data'] ?? [];
            }
        }
        
        return [];
    } catch (Exception $e) {
        logMessage("Error getting existing orders for {$symbol}: " . $e->getMessage());
        return [];
    }
}

// Check if position has stop loss and take profit orders
function analyzeExistingOrders($orders, $position) {
    $hasStopLoss = false;
    $hasTakeProfit = false;
    $orderTypes = [];
    
    foreach ($orders as $order) {
        $orderTypes[] = $order['type'] ?? 'UNKNOWN';
        
        if (in_array($order['type'] ?? '', ['STOP_MARKET', 'STOP'])) {
            $hasStopLoss = true;
        }
        
        if (in_array($order['type'] ?? '', ['TAKE_PROFIT_MARKET', 'TAKE_PROFIT'])) {
            $hasTakeProfit = true;
        }
    }
    
    logMessage("Position {$position['symbol']} {$position['side']} analysis: SL={$hasStopLoss}, TP={$hasTakeProfit}, Order types: " . implode(', ', $orderTypes));
    
    return [
        'has_stop_loss' => $hasStopLoss,
        'has_take_profit' => $hasTakeProfit,
        'order_count' => count($orders)
    ];
}

// Create stop loss order on BingX
function createStopLossOrder($config, $symbol, $side, $quantity, $stopPrice, $leverage, $positionSide) {
    try {
        $timestamp = round(microtime(true) * 1000);
        
        // Determine the opposite side for closing
        $orderSide = $side === 'LONG' ? 'SELL' : 'BUY';
        
        $params = [
            'symbol' => $symbol,
            'side' => $orderSide,
            'type' => 'STOP_MARKET',
            'quantity' => $quantity,
            'stopPrice' => $stopPrice,
            'positionSide' => $positionSide,
            'timeInForce' => 'GTC',
            'timestamp' => $timestamp
        ];
        
        $queryString = http_build_query($params);
        $signature = hash_hmac('sha256', $queryString, $config['api_secret']);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $config['base_url'] . "/openApi/swap/v2/trade/order");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $queryString . "&signature=" . $signature);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'X-BX-APIKEY: ' . $config['api_key']
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpCode == 200) {
            $data = json_decode($response, true);
            if ($data && $data['code'] == 0) {
                $orderId = $data['data']['orderId'] ?? $data['data']['order']['orderId'] ?? '';
                return [
                    'success' => true,
                    'order_id' => $orderId,
                    'message' => 'Stop loss order created successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $data['msg'] ?? 'Unknown API error',
                    'code' => $data['code'] ?? 0
                ];
            }
        }
        
        return ['success' => false, 'error' => 'HTTP request failed'];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Create take profit order on BingX
function createTakeProfitOrder($config, $symbol, $side, $quantity, $stopPrice, $leverage, $positionSide) {
    try {
        $timestamp = round(microtime(true) * 1000);
        
        // Determine the opposite side for closing
        $orderSide = $side === 'LONG' ? 'SELL' : 'BUY';
        
        $params = [
            'symbol' => $symbol,
            'side' => $orderSide,
            'type' => 'TAKE_PROFIT_MARKET',
            'quantity' => $quantity,
            'stopPrice' => $stopPrice,
            'positionSide' => $positionSide,
            'timeInForce' => 'GTC',
            'timestamp' => $timestamp
        ];
        
        $queryString = http_build_query($params);
        $signature = hash_hmac('sha256', $queryString, $config['api_secret']);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $config['base_url'] . "/openApi/swap/v2/trade/order");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $queryString . "&signature=" . $signature);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'X-BX-APIKEY: ' . $config['api_key']
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpCode == 200) {
            $data = json_decode($response, true);
            if ($data && $data['code'] == 0) {
                $orderId = $data['data']['orderId'] ?? $data['data']['order']['orderId'] ?? '';
                return [
                    'success' => true,
                    'order_id' => $orderId,
                    'message' => 'Take profit order created successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $data['msg'] ?? 'Unknown API error',
                    'code' => $data['code'] ?? 0
                ];
            }
        }
        
        return ['success' => false, 'error' => 'HTTP request failed'];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Calculate default SL/TP prices if not set in signals table
function calculateDefaultPrices($entryPrice, $side) {
    $stopLossPercent = 0.02; // 2% stop loss
    $takeProfitPercent = 0.05; // 5% take profit
    
    if (strtoupper($side) === 'LONG') {
        $stopLoss = $entryPrice * (1 - $stopLossPercent);
        $takeProfit = $entryPrice * (1 + $takeProfitPercent);
    } else { // SHORT
        $stopLoss = $entryPrice * (1 + $stopLossPercent);
        $takeProfit = $entryPrice * (1 - $takeProfitPercent);
    }
    
    return [
        'stop_loss' => round($stopLoss, 8),
        'take_profit' => round($takeProfit, 8)
    ];
}

// Main execution
try {
    logMessage("=== SL/TP Monitor Starting ===");
    
    $config = getBingXConfig();
    
    if (empty($config['api_key']) || empty($config['api_secret'])) {
        throw new Exception('BingX API credentials not configured');
    }
    
    logMessage("Trading mode: " . $config['trading_mode'] . " (" . ($config['is_demo'] ? 'DEMO' : 'LIVE') . ")");
    
    $pdo = getDbConnection();
    $positions = getOpenPositions($pdo);
    
    logMessage("Found " . count($positions) . " open positions to check");
    
    $processed = 0;
    $created_sl = 0;
    $created_tp = 0;
    $errors = 0;
    
    foreach ($positions as $position) {
        $symbol = $position['symbol'];
        $side = $position['side'];
        $size = $position['size'];
        $entryPrice = $position['entry_price'];
        $leverage = $position['leverage'];
        
        // Convert symbol to BingX format if needed
        if (!strpos($symbol, 'USDT')) {
            $symbol = $symbol . '-USDT';
        }
        
        logMessage("Checking position: {$symbol} {$side} Size: {$size}");
        
        // Get existing orders for this position
        $existingOrders = getExistingOrders($config, $symbol);
        $analysis = analyzeExistingOrders($existingOrders, $position);
        
        // Get SL/TP prices from signals table or calculate defaults
        $stopLossPrice = null;
        $takeProfitPrice = null;
        
        if (!empty($position['stop_loss'])) {
            $stopLossPrice = floatval($position['stop_loss']);
        }
        
        if (!empty($position['take_profit_1'])) {
            $takeProfitPrice = floatval($position['take_profit_1']);
        }
        
        // If no prices from signals table, calculate defaults
        if (!$stopLossPrice || !$takeProfitPrice) {
            $defaults = calculateDefaultPrices($entryPrice, $side);
            if (!$stopLossPrice) $stopLossPrice = $defaults['stop_loss'];
            if (!$takeProfitPrice) $takeProfitPrice = $defaults['take_profit'];
            logMessage("Using calculated prices - SL: {$stopLossPrice}, TP: {$takeProfitPrice}");
        } else {
            logMessage("Using signal table prices - SL: {$stopLossPrice}, TP: {$takeProfitPrice}");
        }
        
        $positionSide = strtoupper($side); // LONG or SHORT for hedge mode
        
        // Create stop loss if missing
        if (!$analysis['has_stop_loss'] && $stopLossPrice) {
            logMessage("Creating missing stop loss order for {$symbol} at {$stopLossPrice}");
            $slResult = createStopLossOrder($config, $symbol, $side, $size, $stopLossPrice, $leverage, $positionSide);
            
            if ($slResult['success']) {
                $created_sl++;
                logMessage("✅ Created SL order: " . $slResult['order_id']);
            } else {
                $errors++;
                logMessage("❌ Failed to create SL order: " . $slResult['error']);
            }
            
            // Small delay between requests
            sleep(1);
        }
        
        // Create take profit if missing
        if (!$analysis['has_take_profit'] && $takeProfitPrice) {
            logMessage("Creating missing take profit order for {$symbol} at {$takeProfitPrice}");
            $tpResult = createTakeProfitOrder($config, $symbol, $side, $size, $takeProfitPrice, $leverage, $positionSide);
            
            if ($tpResult['success']) {
                $created_tp++;
                logMessage("✅ Created TP order: " . $tpResult['order_id']);
            } else {
                $errors++;
                logMessage("❌ Failed to create TP order: " . $tpResult['error']);
            }
            
            // Small delay between requests
            sleep(1);
        }
        
        if ($analysis['has_stop_loss'] && $analysis['has_take_profit']) {
            logMessage("✅ {$symbol} already has both SL and TP orders");
        }
        
        $processed++;
        
        // Prevent overwhelming the API
        if ($processed % 5 == 0) {
            sleep(2);
        }
    }
    
    logMessage("=== SL/TP Monitor Complete ===");
    logMessage("Processed: {$processed} positions");
    logMessage("Created SL orders: {$created_sl}");
    logMessage("Created TP orders: {$created_tp}");
    logMessage("Errors: {$errors}");
    
} catch (Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    exit(1);
}
?>