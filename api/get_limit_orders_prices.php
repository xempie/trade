<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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
loadEnv(__DIR__ . '/../.env');

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

// Get BingX API credentials
$apiKey = getenv('BINGX_API_KEY') ?: '';
$apiSecret = getenv('BINGX_SECRET_KEY') ?: '';

function getBingXPrice($symbol, $apiKey = '', $apiSecret = '') {
    try {
        // First try public API without authentication
        $publicUrl = "https://open-api.bingx.com/openApi/swap/v2/quote/price";
        $params = ['symbol' => $symbol];
        
        $queryString = http_build_query($params);
        $url = $publicUrl . '?' . $queryString;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            error_log("cURL error for $symbol: $curlError");
            return null;
        }
        
        if ($response && $httpCode == 200) {
            $data = json_decode($response, true);
            
            if ($data && $data['code'] == 0 && isset($data['data']['price'])) {
                return floatval($data['data']['price']);
            } else {
                error_log("BingX API error for $symbol: " . ($data['msg'] ?? 'Unknown error') . " (Code: " . ($data['code'] ?? 'N/A') . ")");
            }
        } else {
            error_log("HTTP error for $symbol: HTTP $httpCode, Response: " . substr($response, 0, 200));
        }
        
        // If public API fails, try authenticated API if credentials are available
        if (!empty($apiKey) && !empty($apiSecret)) {
            return getBingXPriceAuthenticated($symbol, $apiKey, $apiSecret);
        }
        
        // Try alternative public endpoints
        return tryAlternativeEndpoints($symbol);
        
    } catch (Exception $e) {
        return null;
    }
}

function getBingXPriceAuthenticated($symbol, $apiKey, $apiSecret) {
    try {
        $timestamp = round(microtime(true) * 1000);
        $params = [
            'symbol' => $symbol,
            'timestamp' => $timestamp
        ];
        
        $queryString = http_build_query($params);
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
        
        if ($response && $httpCode == 200) {
            $data = json_decode($response, true);
            
            if ($data && $data['code'] == 0 && isset($data['data']['price'])) {
                return floatval($data['data']['price']);
            }
        }
        
        return null;
        
    } catch (Exception $e) {
        return null;
    }
}

function tryAlternativeEndpoints($symbol) {
    // Try 24hr ticker endpoint
    $alternatives = [
        "https://open-api.bingx.com/openApi/swap/v2/quote/ticker?symbol=" . urlencode($symbol),
        "https://open-api.bingx.com/openApi/swap/v1/ticker/24hr?symbol=" . urlencode($symbol)
    ];
    
    foreach ($alternatives as $url) {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($response && $httpCode == 200) {
                $data = json_decode($response, true);
                
                // Different endpoints may have different response structures
                if ($data && isset($data['data'])) {
                    if (is_array($data['data']) && isset($data['data']['lastPrice'])) {
                        return floatval($data['data']['lastPrice']);
                    } elseif (isset($data['data']['price'])) {
                        return floatval($data['data']['price']);
                    } elseif (is_array($data['data']) && count($data['data']) > 0) {
                        $firstItem = $data['data'][0];
                        if (isset($firstItem['lastPrice'])) {
                            return floatval($firstItem['lastPrice']);
                        } elseif (isset($firstItem['price'])) {
                            return floatval($firstItem['price']);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            continue;
        }
    }
    
    return null;
}

function calculateDistance($currentPrice, $targetPrice) {
    if ($currentPrice == 0) return 0;
    // Calculate percentage from current price perspective
    // Shows how much price needs to change to reach target
    return (($targetPrice - $currentPrice) / $currentPrice) * 100;
}

function getPriceStatus($currentPrice, $targetPrice, $direction) {
    if ($targetPrice == 0) return 'normal';
    
    $distance = calculateDistance($currentPrice, $targetPrice);
    
    // For limit orders, we're waiting for price to reach the target
    // Unlike watchlist which waits for entry opportunities
    if (abs($distance) <= 0.1) {
        // Price is within 0.1% of target - very close
        return 'close';
    } elseif (
        ($direction === 'long' && $currentPrice <= $targetPrice) || 
        ($direction === 'short' && $currentPrice >= $targetPrice)
    ) {
        // Price has reached the target for limit order execution
        return 'reached';
    }
    
    return 'normal';
}

// Main execution
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Only GET method allowed');
    }
    
    $pdo = getDbConnection();
    
    // Get all limit orders with NEW or PENDING status
    $sql = "SELECT id, symbol, side, type, entry_level, quantity, price, leverage, status, created_at, signal_id
            FROM orders 
            WHERE type = 'LIMIT' AND status IN ('NEW', 'PENDING')
            ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $limitOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results = [];
    $uniqueSymbols = [];
    
    // Collect unique symbols to minimize API calls
    foreach ($limitOrders as $order) {
        $symbol = $order['symbol'];
        if (!in_array($symbol, $uniqueSymbols)) {
            $uniqueSymbols[] = $symbol;
        }
    }
    
    // Fetch current prices for all unique symbols
    $currentPrices = [];
    foreach ($uniqueSymbols as $symbol) {
        // Convert clean symbol to BingX format (BTC -> BTC-USDT)
        $bingxSymbol = $symbol;
        if (strpos($bingxSymbol, 'USDT') === false) {
            $bingxSymbol = $bingxSymbol . '-USDT';
        }
        
        $price = getBingXPrice($bingxSymbol, $apiKey, $apiSecret);
        $currentPrices[$symbol] = $price;
        
        // Debug logging
        if ($price === null) {
            error_log("Failed to get price for symbol: $symbol (BingX format: $bingxSymbol)");
        } else {
            error_log("Successfully got price for $symbol: $price");
        }
    }
    
    // Process each limit order with current prices
    foreach ($limitOrders as $order) {
        $symbol = $order['symbol'];
        $targetPrice = floatval($order['price']);
        $currentPrice = $currentPrices[$symbol];
        
        // Determine direction based on side (BUY = long, SELL = short)
        $direction = ($order['side'] === 'BUY') ? 'long' : 'short';
        
        $distance = 0;
        $alertStatus = 'normal';
        $priceStatus = 'unknown';
        
        if ($currentPrice !== null) {
            $distance = calculateDistance($currentPrice, $targetPrice);
            $alertStatus = getPriceStatus($currentPrice, $targetPrice, $direction);
            $priceStatus = 'success';
        } else {
            $priceStatus = 'unavailable';
        }
        
        $results[] = [
            'id' => $order['id'],
            'symbol' => $symbol,
            'entry_type' => strtolower($order['entry_level']), // Convert ENTRY_2 to entry_2
            'entry_price' => $targetPrice,
            'current_price' => $currentPrice,
            'distance_percent' => round($distance, 2),
            'alert_status' => $alertStatus, // 'normal', 'close', 'reached'
            'price_status' => $priceStatus, // 'success', 'unavailable'
            'direction' => $direction,
            'margin_amount' => $order['quantity'], // Using quantity as margin amount equivalent
            'order_type' => $order['type'],
            'order_status' => strtolower($order['status']),
            'leverage' => $order['leverage'],
            'signal_id' => $order['signal_id'],
            'created_at' => $order['created_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $results,
        'fetched_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Limit Orders prices API Error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>