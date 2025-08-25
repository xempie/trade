<?php
/**
 * Detailed debug for balance API issue
 */

// Start session
session_start();

header('Content-Type: application/json');

// Include authentication config
require_once dirname(__DIR__) . '/auth/config.php';

$debug = [
    'step1_session_status' => [
        'session_id' => session_id(),
        'session_data' => $_SESSION ?? [],
        'cookies' => $_COOKIE ?? []
    ],
    'step2_auth_functions' => [
        'isAuthenticated_result' => isAuthenticated(),
        'getCurrentUser_result' => getCurrentUser(),
        'isLocalhost_result' => isLocalhost()
    ],
    'step3_server_info' => [
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'NOT SET',
        'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? 'NOT SET',
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'NOT SET',
        'HTTPS' => $_SERVER['HTTPS'] ?? 'NOT SET'
    ]
];

// If authenticated, try to get balance
if (isAuthenticated()) {
    $debug['step4_balance_attempt'] = 'AUTHENTICATED - ATTEMPTING BALANCE';
    
    // Load .env file
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
    
    $debug['step5_env_status'] = [
        'env_file_exists' => file_exists($envPath),
        'api_key_present' => !empty($apiKey),
        'api_secret_present' => !empty($apiSecret)
    ];
} else {
    $debug['step4_balance_attempt'] = 'NOT AUTHENTICATED - SKIPPING BALANCE';
}

echo json_encode([
    'authenticated' => isAuthenticated(),
    'debug' => $debug,
    'timestamp' => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT);
?>