<?php
require_once 'auth/config.php';
requireAuth();

// Test data that would be sent from the trade form
$testData = [
    'symbol' => 'BTCUSDT',
    'direction' => 'long',
    'leverage' => 5,
    'entry_market' => true,
    'entry_margin_market' => 100,
    'entry_2' => false,
    'entry_3' => false
];

echo "<h2>Testing Trade Form API</h2>";
echo "<h3>Test Data:</h3>";
echo "<pre>" . json_encode($testData, JSON_PRETTY_PRINT) . "</pre>";

// Test debug endpoint first
echo "<h3>Testing Debug Endpoint:</h3>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://brainity.com.au/ta/api/debug_order_data.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());

$debugResponse = curl_exec($ch);
$debugHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p>HTTP Code: $debugHttpCode</p>";
echo "<p>Response:</p>";
echo "<pre>" . htmlspecialchars($debugResponse) . "</pre>";

// Test place order endpoint
echo "<h3>Testing Place Order Endpoint:</h3>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://brainity.com.au/ta/api/place_order.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());

$orderResponse = curl_exec($ch);
$orderHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p>HTTP Code: $orderHttpCode</p>";
echo "<p>Response:</p>";
echo "<pre>" . htmlspecialchars($orderResponse) . "</pre>";

// Check session info
echo "<h3>Session Info:</h3>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session Name: " . session_name() . "</p>";
echo "<p>User: " . (getCurrentUser()['email'] ?? 'Not logged in') . "</p>";
?>