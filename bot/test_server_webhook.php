<?php
// Test the actual webhook on the server

// Test signal data (simulate what TradingView sends)
$testSignal = [
    'symbol' => 'BTCUSDT',
    'side' => 'LONG',
    'leverage' => 6,
    'entries' => [65000, 64000],
    'targets' => ['%2', '%4'],
    'stop_loss' => '%5',
    'type' => 'TRADING_SIGNAL',
    'external_signal_id' => 'test-' . time()
];

$jsonData = json_encode($testSignal);

echo "=== TESTING SERVER WEBHOOK ===\n\n";
echo "Sending to: https://brainity.com.au/bot/bot.php\n";
echo "JSON data:\n";
echo $jsonData . "\n\n";

// Use cURL to test the actual server webhook
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => 'https://brainity.com.au/bot/bot.php',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $jsonData,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: TradingView-Webhook/1.0'
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_VERBOSE => true
]);

// Enable verbose output to a temp file
$verboseHandle = fopen('php://temp', 'rw+');
curl_setopt($ch, CURLOPT_STDERR, $verboseHandle);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

// Get verbose output
rewind($verboseHandle);
$verboseOutput = stream_get_contents($verboseHandle);
fclose($verboseHandle);

curl_close($ch);

echo "=== RESPONSE DETAILS ===\n";
echo "HTTP Code: $httpCode\n";
echo "Content Type: $contentType\n";
echo "Effective URL: $effectiveUrl\n";
echo "cURL Error: " . ($error ?: 'None') . "\n";
echo "Response Length: " . strlen($response) . " bytes\n\n";

if ($response) {
    echo "=== RAW RESPONSE ===\n";
    echo $response . "\n\n";

    // Try to parse as JSON
    $decoded = json_decode($response, true);
    if ($decoded) {
        echo "=== PARSED JSON RESPONSE ===\n";
        echo json_encode($decoded, JSON_PRETTY_PRINT) . "\n\n";
    } else {
        echo "Response is not valid JSON\n\n";
    }
} else {
    echo "No response received\n\n";
}

echo "=== VERBOSE cURL OUTPUT ===\n";
echo $verboseOutput . "\n\n";

// Test a simple GET request to make sure the endpoint exists
echo "=== TESTING ENDPOINT ACCESSIBILITY ===\n";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://brainity.com.au/bot/',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_NOBODY => true, // HEAD request
    CURLOPT_FOLLOWLOCATION => true
]);

$headResponse = curl_exec($ch);
$headHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Bot directory HTTP Code: $headHttpCode\n";

// Test bot.php directly
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://brainity.com.au/bot/bot.php',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_NOBODY => true, // HEAD request
    CURLOPT_FOLLOWLOCATION => true
]);

$botResponse = curl_exec($ch);
$botHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "bot.php HTTP Code: $botHttpCode\n";

?>