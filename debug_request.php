<?php
// Debug the actual request being sent to place_order.php
header('Content-Type: text/plain');

echo "=== Request Debug Tool ===\n\n";

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');

// Mock the exact request structure from frontend
$orderData = [
    'symbol' => 'BTC-USDT',
    'direction' => 'long', 
    'leverage' => 5,
    'enabled_entries' => [
        [
            'type' => 'market',
            'price' => 0,
            'margin' => 50
        ]
    ]
];

echo "Simulating request with data:\n";
echo json_encode($orderData, JSON_PRETTY_PRINT) . "\n\n";

// Load environment to check trading mode
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

loadDebugEnv(__DIR__ . '/.env');

echo "Trading Mode: " . (getenv('TRADING_MODE') ?: 'live') . "\n";
echo "API Key Present: " . (!empty(getenv('BINGX_API_KEY')) ? 'Yes' : 'No') . "\n";
echo "Secret Key Present: " . (!empty(getenv('BINGX_SECRET_KEY')) ? 'Yes' : 'No') . "\n\n";

// Test the API validation
echo "=== Testing API Validation ===\n";

$required = ['symbol', 'direction', 'leverage', 'enabled_entries'];
foreach ($required as $field) {
    $present = isset($orderData[$field]);
    echo "Field '$field': " . ($present ? 'PRESENT' : 'MISSING') . "\n";
    if ($present && $field === 'enabled_entries') {
        echo "  enabled_entries count: " . count($orderData[$field]) . "\n";
        if (count($orderData[$field]) > 0) {
            echo "  First entry: " . json_encode($orderData[$field][0]) . "\n";
        }
    }
}

echo "\n=== Testing Database Connection ===\n";
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: '';
$password = getenv('DB_PASSWORD') ?: '';
$database = getenv('DB_NAME') ?: 'crypto_trading';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connection: SUCCESS\n";
} catch (PDOException $e) {
    echo "❌ Database connection: FAILED - " . $e->getMessage() . "\n";
}

echo "\nCheck the actual server response at: https://brainity.com.au/ta/api/place_order.php\n";
echo "Test payload can be sent via curl or browser dev tools\n";
?>