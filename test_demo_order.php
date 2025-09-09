<?php
// Simple HTTP test of place_order.php API for demo trading
header('Content-Type: text/plain');

echo "=== Demo Order Placement HTTP Test ===\n\n";

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
    'notes' => 'Demo HTTP test order'
];

echo "Order Data:\n" . json_encode($orderData, JSON_PRETTY_PRINT) . "\n\n";

// Make HTTP request to the API
$url = 'http://localhost/trade/api/place_order.php';
$jsonData = json_encode($orderData);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($jsonData)
]);

echo "Making HTTP POST request to: $url\n\n";

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
}

// Also check debug log
$debugLog = __DIR__ . '/debug.log';
if (file_exists($debugLog)) {
    echo "\n=== Recent Debug Log Entries ===\n";
    $lines = file($debugLog);
    $recentLines = array_slice($lines, -10); // Last 10 lines
    echo implode('', $recentLines);
}
?>