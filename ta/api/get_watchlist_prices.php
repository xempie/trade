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
    // Try different symbol formats
    $symbolFormats = [
        $symbol,                    // BTC-USDT
        str_replace('-', '', $symbol), // BTCUSDT
        $symbol . '.P',            // BTC-USDT.P
        str_replace('-', '', $symbol) . '.P' // BTCUSDT.P
    ];
    
    $endpoints = [
        'https://open-api.bingx.com/openApi/swap/v2/quote/ticker',
        'https://open-api.bingx.com/openApi/spot/v1/ticker/24hr'
    ];
    
    foreach ($endpoints as $endpoint) {
        foreach ($symbolFormats as $format) {
            try {
                $url = $endpoint . '?symbol=' . urlencode($format);
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($response && $httpCode == 200) {
                    $data = json_decode($response, true);
                    
                    if ($data && $data['code'] == 0) {
                        $price = null;
                        
                        // Try different price fields
                        if (isset($data['data']['price'])) {
                            $price = $data['data']['price'];
                        } elseif (isset($data['data']['lastPrice'])) {
                            $price = $data['data']['lastPrice'];
                        } elseif (isset($data['data']['close'])) {
                            $price = $data['data']['close'];
                        }
                        
                        if ($price) {
                            return floatval($price);
                        }
                    }
                }
                
            } catch (Exception $e) {
                continue;
            }
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
    
    if ($direction === 'long') {
        // Long position: target is below current, waiting for price to drop
        if ($currentPrice <= $targetPrice) {
            // Price has reached or passed the target (good for long entry)
            return 'reached';
        } elseif ($distance >= -0.1 && $distance <= 0) {
            // Price is within 0.1% above target (close but not reached)
            return 'close';
        }
    } else { // short
        // Short position: target is above current, waiting for price to rise
        if ($currentPrice >= $targetPrice) {
            // Price has reached or passed the target (good for short entry)
            return 'reached';
        } elseif ($distance <= 0.1 && $distance >= 0) {
            // Price is within 0.1% below target (close but not reached)
            return 'close';
        }
    }
    
    return 'normal';
}

// Main execution
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Only GET method allowed');
    }
    
    $pdo = getDbConnection();
    
    // Get all active watchlist items
    $sql = "SELECT * FROM watchlist WHERE status = 'active' ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $watchlistItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results = [];
    $uniqueSymbols = [];
    
    // Collect unique symbols to minimize API calls
    foreach ($watchlistItems as $item) {
        $symbol = $item['symbol'];
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
    
    // Process each watchlist item with current prices
    foreach ($watchlistItems as $item) {
        $symbol = $item['symbol'];
        $targetPrice = floatval($item['entry_price']);
        $currentPrice = $currentPrices[$symbol];
        
        $distance = 0;
        $alertStatus = 'normal';
        $priceStatus = 'unknown';
        
        if ($currentPrice !== null) {
            $distance = calculateDistance($currentPrice, $targetPrice);
            $alertStatus = getPriceStatus($currentPrice, $targetPrice, $item['direction']);
            $priceStatus = 'available';
        } else {
            $priceStatus = 'unavailable';
        }
        
        $results[] = [
            'id' => $item['id'],
            'symbol' => $symbol,
            'entry_type' => $item['entry_type'],
            'entry_price' => $targetPrice,
            'current_price' => $currentPrice,
            'distance_percent' => round($distance, 2),
            'alert_status' => $alertStatus, // 'normal', 'close', 'reached'
            'price_status' => $priceStatus,
            'direction' => $item['direction'],
            'margin_amount' => $item['margin_amount'],
            'percentage' => $item['percentage'],
            'created_at' => $item['created_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $results,
        'fetched_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Watchlist prices API Error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>