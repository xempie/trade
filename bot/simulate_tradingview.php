<?php
// Simulate TradingView webhook call to bot.php

// Test signal data (simulate what TradingView sends)
$testSignal = [
    'symbol' => 'BTCUSDT',
    'side' => 'LONG',
    'leverage' => 6,
    'entries' => [65000, 64000],
    'targets' => ['%2', '%4'],
    'stop_loss' => '%5',
    'type' => 'TRADING_SIGNAL',
    'external_signal_id' => 'tradingview-' . time()
];

$jsonData = json_encode($testSignal);

echo "=== SIMULATING TRADINGVIEW WEBHOOK ===\n\n";
echo "Sending JSON data:\n";
echo $jsonData . "\n\n";

// Use cURL to simulate the webhook call
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => 'http://localhost/trade/bot/bot.php',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $jsonData,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json'
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "=== WEBHOOK RESPONSE ===\n";
echo "HTTP Code: $httpCode\n";
echo "cURL Error: " . ($error ?: 'None') . "\n";
echo "Response:\n";
echo $response . "\n\n";

// Check if response is JSON
if ($response) {
    $decoded = json_decode($response, true);
    if ($decoded) {
        echo "=== PARSED RESPONSE ===\n";
        echo json_encode($decoded, JSON_PRETTY_PRINT) . "\n\n";
    }
}

// Check log files after the call
echo "=== LOG FILES AFTER WEBHOOK ===\n";
$logFiles = ['debug_log.txt', 'signals_log.json', 'errors.log', 'trading.log'];
foreach ($logFiles as $logFile) {
    if (file_exists($logFile)) {
        echo "✅ $logFile (" . filesize($logFile) . " bytes)\n";
        // Show last few lines of the log
        $content = file_get_contents($logFile);
        $lines = explode("\n", $content);
        $lastLines = array_slice($lines, -5);
        echo "   Last entries:\n";
        foreach ($lastLines as $line) {
            if (trim($line)) {
                echo "   > " . trim($line) . "\n";
            }
        }
        echo "\n";
    } else {
        echo "❌ Missing: $logFile\n";
    }
}

?>