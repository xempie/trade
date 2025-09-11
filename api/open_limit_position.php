<?php
/**
 * API to open position from limit order with token authentication
 * Used by Telegram bot links to execute limit orders when price is reached
 */

require_once 'auth_token.php';
require_once 'api_helper.php';
// Database config loaded via loadEnv function below

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

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

// Helper function to count current open trades
function countOpenTrades($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM positions WHERE status = 'OPEN'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return intval($result['count']);
    } catch (Exception $e) {
        error_log("Error counting open trades: " . $e->getMessage());
        return 0;
    }
}

// Helper function to get MAX_OPEN_TRADES setting
function getMaxOpenTrades() {
    $maxTrades = getenv('MAX_OPEN_TRADES');
    return $maxTrades ? intval($maxTrades) : 999; // Default to 999 if not set (no limit)
}

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

// Get account balance for position sizing
function getAccountBalance($apiKey, $apiSecret) {
    try {
        $timestamp = round(microtime(true) * 1000);
        $queryString = "timestamp={$timestamp}";
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        
        $tradingMode = strtolower(getenv('TRADING_MODE') ?: 'live');
        $baseUrl = ($tradingMode === 'demo') ? 
            (getenv('BINGX_DEMO_URL') ?: getenv('BINGX_LIVE_URL') ?: 'https://open-api.bingx.com') : 
            (getenv('BINGX_LIVE_URL') ?: 'https://open-api.bingx.com');
        $url = $baseUrl . "/openApi/swap/v2/user/balance?" . $queryString . "&signature=" . $signature;
        
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

// Set leverage on BingX
function setBingXLeverage($apiKey, $apiSecret, $symbol, $leverage, $side = 'BOTH') {
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
        $tradingMode = strtolower(getenv('TRADING_MODE') ?: 'live');
        $baseUrl = ($tradingMode === 'demo') ? 
            (getenv('BINGX_DEMO_URL') ?: getenv('BINGX_LIVE_URL') ?: 'https://open-api.bingx.com') : 
            (getenv('BINGX_LIVE_URL') ?: 'https://open-api.bingx.com');
        curl_setopt($ch, CURLOPT_URL, $baseUrl . "/openApi/swap/v2/trade/leverage");
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
        error_log("Leverage setting error: " . $e->getMessage());
        return false;
    }
}

// Place market order on BingX
function placeBingXMarketOrder($apiKey, $apiSecret, $orderData) {
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
        $tradingMode = strtolower(getenv('TRADING_MODE') ?: 'live');
        $baseUrl = ($tradingMode === 'demo') ? 
            (getenv('BINGX_DEMO_URL') ?: getenv('BINGX_LIVE_URL') ?: 'https://open-api.bingx.com') : 
            (getenv('BINGX_LIVE_URL') ?: 'https://open-api.bingx.com');
        curl_setopt($ch, CURLOPT_URL, $baseUrl . "/openApi/swap/v2/trade/order");
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
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception('cURL Error: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new Exception("BingX API HTTP error: {$httpCode}. Response: " . $response);
        }
        
        $data = json_decode($response, true);
        
        if (!$data) {
            throw new Exception('Invalid JSON response from BingX: ' . json_last_error_msg());
        }
        
        if (!isset($data['code']) || $data['code'] !== 0) {
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

// Save position to database
function savePositionToDb($pdo, $positionData) {
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
            ':notes' => $positionData['notes'] ?? 'Opened from limit trigger'
        ]);
    } catch (Exception $e) {
        error_log("Database error saving position: " . $e->getMessage());
        return false;
    }
}

// Main execution
try {
    // Authenticate request
    $payload = authenticateApiRequest();
    
    if (!isset($payload['order_id']) || !isset($payload['action'])) {
        throw new Exception('Invalid token payload');
    }
    
    if ($payload['action'] !== 'open_position') {
        throw new Exception('Invalid action for this endpoint');
    }
    
    $orderId = intval($payload['order_id']);
    
    // Get BingX API credentials
    $apiKey = getenv('BINGX_API_KEY') ?: '';
    $apiSecret = getenv('BINGX_SECRET_KEY') ?: '';
    
    if (empty($apiKey) || empty($apiSecret)) {
        throw new Exception('BingX API credentials not configured');
    }
    
    $pdo = getDbConnection();
    
    // Get the order details
    $sql = "SELECT o.*, s.signal_type, s.leverage as signal_leverage 
            FROM orders o 
            LEFT JOIN signals s ON o.signal_id = s.id 
            WHERE o.id = :order_id AND o.status IN ('NEW', 'PENDING')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':order_id' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order not found or already processed');
    }
    
    // Check if position already exists for this order
    $sql = "SELECT COUNT(*) FROM positions WHERE signal_id = :signal_id AND status = 'OPEN'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':signal_id' => $order['signal_id']]);
    $existingPositions = $stmt->fetchColumn();
    
    if ($existingPositions > 0) {
        // Update order status to prevent multiple openings
        $sql = "UPDATE orders SET status = 'FILLED', fill_time = NOW() WHERE id = :order_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Position already exists for this signal',
            'already_opened' => true
        ]);
        exit;
    }
    
    // Check if maximum number of open trades has been reached
    $currentOpenTrades = countOpenTrades($pdo);
    $maxOpenTrades = getMaxOpenTrades();
    
    if ($currentOpenTrades >= $maxOpenTrades) {
        error_log("Trade limit reached in limit order execution: {$currentOpenTrades}/{$maxOpenTrades} open trades");
        throw new Exception("Maximum number of open trades reached ({$currentOpenTrades}/{$maxOpenTrades}). Please close some positions before opening new ones.");
    }
    
    error_log("Trade limit check passed in limit order: {$currentOpenTrades}/{$maxOpenTrades} open trades");
    
    // Get current market price for size calculation
    $availableBalance = getAccountBalance($apiKey, $apiSecret);
    if ($availableBalance <= 0) {
        throw new Exception('Unable to get account balance or insufficient funds');
    }
    
    // Calculate position size from original order
    $quantity = floatval($order['quantity']);
    $leverage = intval($order['leverage']) ?: intval($order['signal_leverage']) ?: 1;
    $symbol = $order['symbol'];
    $side = $order['side'];
    $direction = strtolower($order['signal_type']);
    
    // Set leverage first
    $positionSide = ($direction === 'long') ? 'LONG' : 'SHORT';
    $leverageSet = setBingXLeverage($apiKey, $apiSecret, $symbol, $leverage, $positionSide);
    if (!$leverageSet) {
        error_log("Warning: Failed to set leverage for {$symbol} to {$leverage}x");
    } else {
        usleep(500000); // 0.5 second delay
    }
    
    // Place market order
    $orderData = [
        'symbol' => $symbol,
        'side' => $side,
        'quantity' => $quantity,
        'direction' => $direction
    ];
    
    $orderResult = placeBingXMarketOrder($apiKey, $apiSecret, $orderData);
    
    if (!$orderResult['success']) {
        throw new Exception('Failed to place market order: ' . $orderResult['error']);
    }
    
    // Calculate margin used (approximation based on current market)
    $marginUsed = ($quantity * (floatval($order['price']) ?: 1)) / $leverage;
    
    // Save position to database
    $positionSaved = savePositionToDb($pdo, [
        'symbol' => str_replace('-USDT', '', $symbol),
        'side' => strtoupper($direction),
        'size' => $quantity,
        'entry_price' => floatval($order['price']),
        'leverage' => $leverage,
        'margin_used' => $marginUsed,
        'signal_id' => $order['signal_id']
    ]);
    
    if (!$positionSaved) {
        throw new Exception('Failed to save position to database');
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
    
    echo json_encode([
        'success' => true,
        'message' => 'Position opened successfully',
        'order_id' => $orderId,
        'bingx_order_id' => $orderResult['order_id'],
        'position_size' => $quantity,
        'leverage' => $leverage,
        'symbol' => $symbol,
        'side' => $direction
    ]);
    
} catch (Exception $e) {
    error_log("Open Limit Position API Error: " . $e->getMessage());
    
    http_response_code($e->getCode() ?: 400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>