<?php
/**
 * Test leverage setting on BingX without placing an order
 */

require_once '../auth/api_protection.php';
protectAPI();

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

$envPath = __DIR__ . '/../.env';
loadEnv($envPath);

$apiKey = getenv('BINGX_API_KEY') ?: '';
$apiSecret = getenv('BINGX_SECRET_KEY') ?: '';

function testSetLeverage($apiKey, $apiSecret, $symbol, $leverage, $side = 'BUY') {
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
        
        return [
            'success' => $httpCode == 200,
            'http_code' => $httpCode,
            'response' => $response,
            'parsed' => json_decode($response, true)
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Test setting leverage to 7x for a common symbol
$symbol = $_GET['symbol'] ?? 'BTC-USDT';
$leverage = $_GET['leverage'] ?? 7;
$side = $_GET['side'] ?? 'LONG';

echo json_encode([
    'test_params' => [
        'symbol' => $symbol,
        'leverage' => $leverage,
        'side' => $side
    ],
    'result' => testSetLeverage($apiKey, $apiSecret, $symbol, $leverage, $side)
], JSON_PRETTY_PRINT);
?>