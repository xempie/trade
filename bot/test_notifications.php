<?php
/**
 * Test Script for Bot.php Telegram Notifications
 * Tests various signal types to see if notifications are sent properly
 */

// Change to bot directory
chdir(__DIR__);

// Set content type for JSON response
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== BOT.PHP NOTIFICATION TEST ===\n\n";

// Test data for different signal types
$testSignals = [
    'trading_signal' => [
        'symbol' => 'BTCUSDT',
        'side' => 'LONG',
        'leverage' => 5,
        'entries' => [45000, 44500],
        'targets' => ['2%', '4%'],
        'stop_loss' => ['3%'],
        'external_signal_id' => 'test-001',
        'notes' => 'Test trading signal notification'
    ],

    'fvg_signal' => [
        'type' => 'FVG',
        'symbol' => 'ETHUSDT',
        'side' => 'SHORT',
        'metadata' => [
            'fvg_size_pct' => 2.5
        ]
    ],

    'trigger_cross' => [
        'type' => 'TRIGGER_CROSS',
        'symbol' => 'ADAUSDT',
        'side' => 'LONG',
        'levels' => 'HML',
        'prices' => '0.45 | 0.46 | 0.47'
    ],

    'ichimoku_signal' => [
        'type' => 'ICHIMOKU_AFTER_CROSS',
        'symbol' => 'SOLUSDT',
        'side' => 'SHORT',
        'entry' => 95.50
    ]
];

// Function to make POST request to bot.php
function testSignal($signalData, $signalName) {
    echo "Testing $signalName...\n";

    // Convert to JSON
    $jsonData = json_encode($signalData);
    echo "Sending data: " . $jsonData . "\n";

    // Initialize cURL
    $ch = curl_init();

    // Set cURL options
    curl_setopt_array($ch, [
        CURLOPT_URL => 'http://localhost/trade/bot/bot.php',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData)
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true
    ]);

    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    echo "HTTP Code: $httpCode\n";
    if ($error) {
        echo "cURL Error: $error\n";
    }

    echo "Response: $response\n";

    // Parse response if it's JSON
    $decodedResponse = json_decode($response, true);
    if ($decodedResponse) {
        echo "Success: " . ($decodedResponse['success'] ? 'YES' : 'NO') . "\n";
        if (isset($decodedResponse['error'])) {
            echo "Error: " . $decodedResponse['error'] . "\n";
        }
    }

    echo "\n" . str_repeat("-", 50) . "\n\n";

    return $decodedResponse;
}

// Test each signal type
foreach ($testSignals as $signalName => $signalData) {
    testSignal($signalData, $signalName);

    // Wait a bit between tests
    sleep(1);
}

echo "=== CHECKING LOG FILES ===\n\n";

// Check for log files
$logFiles = [
    'debug_log.txt',
    'telegram_debug.log',
    'errors.log'
];

foreach ($logFiles as $logFile) {
    if (file_exists($logFile)) {
        echo "=== $logFile (last 20 lines) ===\n";
        $lines = file($logFile);
        $recentLines = array_slice($lines, -20);
        echo implode('', $recentLines);
        echo "\n" . str_repeat("=", 50) . "\n\n";
    } else {
        echo "$logFile: NOT FOUND\n\n";
    }
}

echo "=== TEST COMPLETED ===\n";
echo "Check the log files above for detailed information about what happened.\n";
echo "If notifications are not working, check:\n";
echo "1. Environment variables in .env file\n";
echo "2. ENABLE_TELEGRAM setting\n";
echo "3. Bot tokens and chat IDs\n";
echo "4. Network connectivity\n";

?>