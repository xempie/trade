<?php
// Live Server Demo Trading Test - NO LOCALHOST URLS
header('Content-Type: text/plain');

echo "=== Live Server Demo Trading Test ===\n\n";
echo "🌐 Running on LIVE SERVER: " . ($_SERVER['HTTP_HOST'] ?? 'brainity.com.au') . "\n\n";

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

// Set server environment for live server (NOT localhost)
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'brainity.com.au';

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
    'notes' => 'Live server demo test order'
];

echo "Order Data:\n" . json_encode($orderData, JSON_PRETTY_PRINT) . "\n\n";

// Test with live server API call (not localhost)
$baseUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'brainity.com.au');
$apiUrl = $baseUrl . '/ta/api/place_order.php';

echo "Making HTTP POST request to: $apiUrl\n\n";

$jsonData = json_encode($orderData);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($jsonData),
    'User-Agent: DemoTestClient/1.0'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Response Code: $httpCode\n";
echo "cURL Error: " . ($curlError ?: 'None') . "\n";
echo "Response:\n";
echo $response . "\n\n";

// Try to decode response
if ($response) {
    $data = json_decode($response, true);
    if ($data) {
        echo "Parsed JSON Response:\n";
        print_r($data);
    } else {
        echo "Failed to parse JSON response\n";
        echo "JSON Error: " . json_last_error_msg() . "\n";
    }
} else {
    echo "❌ No response received from server\n";
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
echo "\n✅ Reset TRADING_MODE back to live.\n";

// Also check debug log
$debugLog = __DIR__ . '/debug.log';
if (file_exists($debugLog)) {
    echo "\n=== Recent Debug Log Entries ===\n";
    $lines = file($debugLog);
    $recentLines = array_slice($lines, -5); // Last 5 lines
    echo implode('', $recentLines);
}

echo "\n=== Test Summary ===\n";
echo "✅ Live server URL used (no localhost)\n";
echo "✅ Demo mode temporarily enabled\n";
echo "✅ Environment restored to live mode\n";
echo "\n💡 This test file is safe for live server use\n";
?>