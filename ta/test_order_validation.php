<?php
// Test order placement validation without authentication
header('Content-Type: text/plain');

echo "=== Order Placement Validation Test ===\n\n";

// Set up environment
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'brainity.com.au';

// Test data matching what the form sends
$orderData = [
    'symbol' => 'DOLO',
    'direction' => 'long',
    'leverage' => 2,
    'enabled_entries' => [
        [
            'type' => 'market',
            'price' => 0.17586,
            'margin' => 4
        ]
    ],
    'notes' => 'Validation test order'
];

echo "Test Order Data:\n" . json_encode($orderData, JSON_PRETTY_PRINT) . "\n\n";

try {
    // Load environment
    $envPath = '.env';
    if (file_exists($envPath)) {
        $envContent = file_get_contents($envPath);
        foreach (explode("\n", $envContent) as $line) {
            $line = trim($line);
            if ($line && strpos($line, '#') !== 0 && strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                putenv(trim($key) . '=' . trim($value));
                $_ENV[trim($key)] = trim($value);
            }
        }
    }
    
    // Test basic validation logic from place_order.php
    $required = ['symbol', 'direction', 'leverage', 'enabled_entries'];
    echo "Testing required fields validation:\n";
    foreach ($required as $field) {
        $exists = isset($orderData[$field]);
        $value = $orderData[$field] ?? null;
        echo "  - {$field}: " . ($exists ? "✅ EXISTS" : "❌ MISSING") . " (value: " . json_encode($value) . ")\n";
    }
    
    // Test API credentials
    echo "\nTesting API credentials:\n";
    $apiKey = getenv('BINGX_API_KEY') ?: '';
    $apiSecret = getenv('BINGX_SECRET_KEY') ?: '';
    echo "  - API Key: " . ($apiKey ? "✅ CONFIGURED" : "❌ MISSING") . " (length: " . strlen($apiKey) . ")\n";
    echo "  - API Secret: " . ($apiSecret ? "✅ CONFIGURED" : "❌ MISSING") . " (length: " . strlen($apiSecret) . ")\n";
    
    // Test database connection
    echo "\nTesting database connection:\n";
    try {
        $host = getenv('DB_HOST') ?: 'localhost';
        $user = getenv('DB_USER') ?: '';
        $password = getenv('DB_PASSWORD') ?: '';
        $database = getenv('DB_NAME') ?: 'crypto_trading';
        
        $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "  - Database: ✅ CONNECTED\n";
        
        // Test if required tables exist
        $tables = ['signals', 'orders', 'positions'];
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            $exists = $stmt->rowCount() > 0;
            echo "  - Table '{$table}': " . ($exists ? "✅ EXISTS" : "❌ MISSING") . "\n";
        }
        
    } catch (PDOException $e) {
        echo "  - Database: ❌ FAILED - " . $e->getMessage() . "\n";
    }
    
    // Test trading mode
    echo "\nTesting trading mode:\n";
    require_once './api/api_helper.php';
    $tradingMode = strtolower(getenv('TRADING_MODE') ?: 'live');
    $isDemoMode = isDemoMode();
    $apiUrl = getBingXApiUrl();
    echo "  - Trading Mode: {$tradingMode}\n";
    echo "  - Is Demo Mode: " . ($isDemoMode ? 'YES' : 'NO') . "\n";
    echo "  - API URL: {$apiUrl}\n";
    
    echo "\n=== Validation Summary ===\n";
    echo "✅ All basic validation checks completed\n";
    echo "✅ This data should pass initial API validation\n";
    echo "\nIf the form still fails with 400 error, the issue is likely:\n";
    echo "- During order processing (after validation)\n";
    echo "- In the BingX API call itself\n";
    echo "- In database operations\n";
    
} catch (Exception $e) {
    echo "❌ Validation test failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
?>