<?php
// Direct test of place_order.php API for demo trading
header('Content-Type: application/json');

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');

// Load environment
function testLoadEnv($path) {
    if (!file_exists($path)) return false;
    
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

testLoadEnv(__DIR__ . '/.env');

// Mock stream wrapper for php://input
class MockPHPInput {
    public static $data = '';
    private $position = 0;
    
    public function stream_open($path, $mode, $options, &$opened_path) {
        return true;
    }
    
    public function stream_read($count) {
        $ret = substr(self::$data, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }
    
    public function stream_eof() {
        return $this->position >= strlen(self::$data);
    }
    
    public function stream_stat() {
        return array();
    }
}

echo "Testing Demo Order Placement...\n\n";

// Test data in the format expected by place_order.php (JSON structure)
$testOrderData = [
    'symbol' => 'BTC-USDT',
    'direction' => 'long',
    'leverage' => 5,
    'enabled_entries' => [
        [
            'type' => 'market',
            'price' => 0, // Market price
            'margin' => 50
        ]
    ],
    'notes' => 'Demo test order'
];

echo "Trading Mode: " . (getenv('TRADING_MODE') ?: 'live') . "\n";
echo "Order Data:\n" . json_encode($testOrderData, JSON_PRETTY_PRINT) . "\n\n";

// Simulate the correct JSON POST request
$jsonData = json_encode($testOrderData);
$_SERVER['REQUEST_METHOD'] = 'POST';

// Mock the JSON input that place_order.php expects
$tempFile = tmpfile();
fwrite($tempFile, $jsonData);
rewind($tempFile);

echo "=== Calling place_order.php ===\n";

// Capture output
ob_start();

try {
    // Mock php://input for the API
    stream_wrapper_unregister("php");
    stream_wrapper_register("php", "MockPHPInput");
    MockPHPInput::$data = $jsonData;
    
    include 'api/place_order.php';
    $output = ob_get_contents();
} catch (Exception $e) {
    $output = "Exception: " . $e->getMessage();
} catch (Error $e) {
    $output = "Error: " . $e->getMessage();
}

ob_end_clean();

// Restore original stream wrapper
stream_wrapper_restore("php");

echo "Response:\n";
echo $output;

// Also check debug log
$debugLog = __DIR__ . '/debug.log';
if (file_exists($debugLog)) {
    echo "\n\n=== Recent Debug Log Entries ===\n";
    $lines = file($debugLog);
    $recentLines = array_slice($lines, -20); // Last 20 lines
    echo implode('', $recentLines);
}
?>