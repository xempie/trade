<?php
/**
 * Test balance API directly (bypassing protectAPI to see what happens)
 */

header('Content-Type: application/json');

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

// Get BingX API credentials
$apiKey = getenv('BINGX_API_KEY') ?: '';
$apiSecret = getenv('BINGX_SECRET_KEY') ?: '';

function getBingXBalance($apiKey, $apiSecret) {
    try {
        if (empty($apiKey) || empty($apiSecret)) {
            throw new Exception('BingX API credentials not configured. Please check your .env file.');
        }
        
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
        $curlError = curl_error($ch);
        curl_close($ch);
        
        $debug = [
            'url' => $url,
            'http_code' => $httpCode,
            'curl_error' => $curlError,
            'response_length' => strlen($response),
            'response_preview' => substr($response, 0, 200)
        ];
        
        if ($curlError) {
            throw new Exception('cURL Error: ' . $curlError);
        }
        
        if (!$response) {
            throw new Exception('No response from BingX API');
        }
        
        if ($httpCode !== 200) {
            throw new Exception("BingX API HTTP error: {$httpCode}. Response: " . substr($response, 0, 200));
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response: ' . json_last_error_msg());
        }
        
        return [
            'success' => true,
            'data' => $data,
            'debug' => $debug
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'debug' => $debug ?? []
        ];
    }
}

$result = getBingXBalance($apiKey, $apiSecret);
echo json_encode($result, JSON_PRETTY_PRINT);
?>