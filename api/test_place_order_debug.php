<?php
// Debug endpoint for place_order.php on live server - NO AUTH PROTECTION FOR DEBUGGING
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Load environment for debugging
function loadDebugEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
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

loadDebugEnv(__DIR__ . '/../.env');

try {
    // Log the incoming request
    $rawInput = file_get_contents('php://input');
    $method = $_SERVER['REQUEST_METHOD'];
    $headers = getallheaders();
    
    $debug = [
        'timestamp' => date('Y-m-d H:i:s'),
        'method' => $method,
        'raw_input' => $rawInput,
        'raw_input_length' => strlen($rawInput),
        'headers' => $headers,
        'trading_mode' => getenv('TRADING_MODE') ?: 'live',
        'api_key_present' => !empty(getenv('BINGX_API_KEY')),
        'secret_key_present' => !empty(getenv('BINGX_SECRET_KEY'))
    ];
    
    if ($method === 'POST') {
        $input = json_decode($rawInput, true);
        $debug['parsed_json'] = $input;
        $debug['json_error'] = json_last_error_msg();
        
        if ($input) {
            // Validate required fields
            $required = ['symbol', 'direction', 'leverage', 'enabled_entries'];
            $debug['validation'] = [];
            
            foreach ($required as $field) {
                $debug['validation'][$field] = isset($input[$field]) ? 'present' : 'missing';
            }
            
            if (isset($input['enabled_entries'])) {
                $debug['enabled_entries_count'] = count($input['enabled_entries']);
                $debug['enabled_entries_data'] = $input['enabled_entries'];
            }
        }
    }
    
    // Test database connection
    try {
        $host = getenv('DB_HOST') ?: 'localhost';
        $user = getenv('DB_USER') ?: '';
        $password = getenv('DB_PASSWORD') ?: '';
        $database = getenv('DB_NAME') ?: 'crypto_trading';
        
        $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $debug['database'] = 'connected';
    } catch (PDOException $e) {
        $debug['database'] = 'failed: ' . $e->getMessage();
    }
    
    echo json_encode([
        'success' => true,
        'debug' => $debug
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
        ]
    ], JSON_PRETTY_PRINT);
}
?>