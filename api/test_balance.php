<?php
// Simple test to debug BingX API
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Load .env
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

loadEnv(__DIR__ . '/../.env');

$apiKey = getenv('BINGX_API_KEY');
$apiSecret = getenv('BINGX_SECRET_KEY');

echo json_encode([
    'env_loaded' => true,
    'api_key_set' => !empty($apiKey),
    'api_secret_set' => !empty($apiSecret),
    'api_key_length' => strlen($apiKey),
    'api_secret_length' => strlen($apiSecret),
    'timestamp' => time(),
    'php_version' => PHP_VERSION,
    'curl_available' => function_exists('curl_init')
]);
?>