<?php
// Direct test of place_order.php API for demo trading
header('Content-Type: text/plain');

echo "=== Demo Order Placement Direct Test ===\n\n";

// First, set the trading mode to demo temporarily
$envPath = '.env';
$envContent = file_get_contents($envPath);
$lines = explode("\n", $envContent);
$updatedLines = [];

foreach ($lines as $line) {
    if (strpos($line, 'TRADING_MODE=') === 0) {
        $updatedLines[] = 'TRADING_MODE=demo';
        echo "Setting TRADING_MODE to demo...\n";
    } else {
        $updatedLines[] = $line;
    }
}

// Add TRADING_MODE if it doesn't exist
if (!preg_grep('/^TRADING_MODE=/', $lines)) {
    $updatedLines[] = 'TRADING_MODE=demo';
    echo "Adding TRADING_MODE=demo...\n";
}

file_put_contents($envPath, implode("\n", $updatedLines));

// Simulate POST request data
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'localhost';

// Test data in correct format
$orderData = [
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
    'notes' => 'Demo direct test order'
];

echo "Order Data:\n" . json_encode($orderData, JSON_PRETTY_PRINT) . "\n\n";

// Simulate the POST body
file_put_contents('php://input', json_encode($orderData));

// Capture output from API
ob_start();

try {
    // Include the API file directly
    include './api/place_order.php';
    $output = ob_get_contents();
} catch (Exception $e) {
    $output = "Error: " . $e->getMessage();
}

ob_end_clean();

echo "API Response:\n";
echo $output . "\n\n";

// Try to decode response
if ($output) {
    $data = json_decode($output, true);
    if ($data) {
        echo "Parsed JSON Response:\n";
        print_r($data);
    } else {
        echo "Failed to parse JSON response\n";
        echo "JSON Error: " . json_last_error_msg() . "\n";
    }
}

// Reset trading mode back to live
$updatedLines = [];
foreach (explode("\n", file_get_contents($envPath)) as $line) {
    if (strpos($line, 'TRADING_MODE=') === 0) {
        $updatedLines[] = 'TRADING_MODE=live';
    } else {
        $updatedLines[] = $line;
    }
}
file_put_contents($envPath, implode("\n", $updatedLines));
echo "\nReset TRADING_MODE back to live.\n";

// Also check debug log
$debugLog = __DIR__ . '/debug.log';
if (file_exists($debugLog)) {
    echo "\n=== Recent Debug Log Entries ===\n";
    $lines = file($debugLog);
    $recentLines = array_slice($lines, -10); // Last 10 lines
    echo implode('', $recentLines);
}
?>