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

// Load API helper for trading mode support
require_once __DIR__ . '/api_helper.php';

// Get BingX API credentials
$apiKey = getenv('BINGX_API_KEY') ?: '';
$apiSecret = getenv('BINGX_SECRET_KEY') ?: '';

function getBingXPrice($symbol, $apiKey = '', $apiSecret = '') {
    try {
        // Use trading mode aware API URL
        $baseUrl = getBingXApiUrl();
        $publicUrl = $baseUrl . "/openApi/swap/v2/quote/price";
        $params = ['symbol' => $symbol];
        
        $queryString = http_build_query($params);
        $url = $publicUrl . '?' . $queryString;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpCode == 200) {
            $data = json_decode($response, true);
            
            if ($data && $data['code'] == 0 && isset($data['data']['price'])) {
                return [
                    'success' => true,
                    'price' => $data['data']['price'],
                    'symbol' => $symbol
                ];
            }
        }
        
        // If public API fails, try authenticated API if credentials are available
        if (!empty($apiKey) && !empty($apiSecret)) {
            return getBingXPriceAuthenticated($symbol, $apiKey, $apiSecret);
        }
        
        // Try alternative public endpoints
        return tryAlternativeEndpoints($symbol);
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'API request failed: ' . $e->getMessage()
        ];
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
        
        $baseUrl = getBingXApiUrl();
        $url = $baseUrl . "/openApi/swap/v2/quote/price?" . $queryString . "&signature=" . $signature;
        
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
            
            if ($data && $data['code'] == 0 && isset($data['data']['price'])) {
                return [
                    'success' => true,
                    'price' => $data['data']['price'],
                    'symbol' => $symbol
                ];
            }
        }
        
        return [
            'success' => false,
            'error' => 'Authenticated API request failed'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Authenticated API request failed: ' . $e->getMessage()
        ];
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
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
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
                            return [
                                'success' => true,
                                'price' => $price,
                                'symbol' => $symbol,
                                'format_used' => $format,
                                'endpoint_used' => $endpoint
                            ];
                        }
                    }
                }
                
            } catch (Exception $e) {
                continue;
            }
        }
    }
    
    return [
        'success' => false,
        'error' => 'No working endpoint found for symbol: ' . $symbol
    ];
}

// Main execution
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Only GET method allowed');
    }
    
    $symbol = $_GET['symbol'] ?? '';
    if (empty($symbol)) {
        throw new Exception('Symbol parameter is required');
    }
    
    // Clean and format symbol
    $cleanSymbol = strtoupper(trim($symbol));
    
    // Convert clean symbol to BingX format (BTC -> BTC-USDT)
    if (strpos($cleanSymbol, 'USDT') === false) {
        $cleanSymbol = $cleanSymbol . '-USDT';
    }
    
    $result = getBingXPrice($cleanSymbol, $apiKey, $apiSecret);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>