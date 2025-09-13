<?php
require_once 'auth/config.php';
requireAuth();

echo "<h2>Testing API JSON Responses</h2>";

// Test data
$testData = [
    'symbol' => 'BTCUSDT',
    'direction' => 'long',
    'leverage' => 5,
    'entry_market' => '50000',
    'entry_market_margin' => 100,
    'entry_2' => false,
    'entry_3' => false
];

// Test debug endpoint
echo "<h3>1. Testing Debug Endpoint</h3>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://brainity.com.au/ta/api/debug_order_data.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Cookie: ' . $_SERVER['HTTP_COOKIE']
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
echo "<p><strong>Raw Response:</strong></p>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Test if it's valid JSON
$decoded = json_decode($response, true);
if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    echo "<p><strong style='color: red;'>❌ JSON ERROR: " . json_last_error_msg() . "</strong></p>";
} else {
    echo "<p><strong style='color: green;'>✅ Valid JSON</strong></p>";
}

echo "<hr>";

// Test place_order endpoint
echo "<h3>2. Testing Place Order Endpoint</h3>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://brainity.com.au/ta/api/place_order.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Cookie: ' . $_SERVER['HTTP_COOKIE']
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response2 = curl_exec($ch);
$httpCode2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p><strong>HTTP Code:</strong> $httpCode2</p>";
echo "<p><strong>Raw Response:</strong></p>";
echo "<pre>" . htmlspecialchars($response2) . "</pre>";

// Test if it's valid JSON
$decoded2 = json_decode($response2, true);
if ($decoded2 === null && json_last_error() !== JSON_ERROR_NONE) {
    echo "<p><strong style='color: red;'>❌ JSON ERROR: " . json_last_error_msg() . "</strong></p>";
} else {
    echo "<p><strong style='color: green;'>✅ Valid JSON</strong></p>";
}

echo "<hr>";
echo "<h3>Session Info</h3>";
echo "<p>User: " . (getCurrentUser()['email'] ?? 'Not logged in') . "</p>";
echo "<p>Session ID: " . session_id() . "</p>";
?>