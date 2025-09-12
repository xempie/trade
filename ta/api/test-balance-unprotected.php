<?php
/**
 * Test balance API without authentication protection
 */

header('Content-Type: application/json');

// Include authentication config but don't protect
require_once dirname(__DIR__) . '/auth/config.php';

// Check auth status
$authStatus = [
    'is_authenticated' => isAuthenticated(),
    'user' => getCurrentUser(),
    'session_data' => $_SESSION ?? []
];

// If not authenticated, return auth status instead of blocking
if (!isAuthenticated()) {
    echo json_encode([
        'success' => false,
        'error' => 'Not authenticated',
        'debug' => $authStatus
    ]);
    exit;
}

// If authenticated, try to get balance
try {
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

    echo json_encode([
        'success' => true,
        'message' => 'Authentication working!',
        'user' => getCurrentUser(),
        'api_keys_present' => [
            'api_key' => !empty($apiKey),
            'api_secret' => !empty($apiSecret)
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'auth_status' => $authStatus
    ]);
}
?>