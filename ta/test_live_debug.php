<?php
// Test the live debug endpoint
header('Content-Type: text/plain');

echo "=== Testing Live Debug Endpoint ===\n\n";

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

$url = 'https://brainity.com.au/ta/api/test_place_order_debug.php';
$jsonData = json_encode($orderData);

echo "Testing debug endpoint: $url\n";
echo "Payload: " . json_encode($orderData, JSON_PRETTY_PRINT) . "\n\n";

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

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Response Code: $httpCode\n";
echo "cURL Error: " . ($curlError ?: 'None') . "\n\n";
echo "Response:\n";
echo $response . "\n\n";

// Now test the actual place_order.php
echo "=== Testing Actual place_order.php ===\n\n";

$actualUrl = 'https://brainity.com.au/ta/api/place_order.php';
echo "Testing actual endpoint: $actualUrl\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $actualUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($jsonData)
]);

$response2 = curl_exec($ch);
$httpCode2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError2 = curl_error($ch);
curl_close($ch);

echo "HTTP Response Code: $httpCode2\n";
echo "cURL Error: " . ($curlError2 ?: 'None') . "\n\n";
echo "Response:\n";
echo $response2 . "\n";
?>