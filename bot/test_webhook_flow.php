<?php
/**
 * Test bot.php webhook data processing flow
 * Simulates actual POST data being sent to bot.php and verifies the complete flow
 */

// Change to bot directory
chdir(__DIR__);

echo "=== BOT.PHP WEBHOOK DATA FLOW TEST ===\n\n";

// Test data for different signal types (simulating actual webhook payloads)
$testSignals = [
    'trading_signal_basic' => [
        'symbol' => 'BTCUSDT',
        'side' => 'LONG',
        'leverage' => 5,
        'entries' => [45000, 44500, 44000],
        'targets' => ['2%', '4%', '6%'],
        'stop_loss' => ['3%'],
        'external_signal_id' => 'webhook-test-001',
        'notes' => 'Test webhook trading signal'
    ],

    'trading_signal_without_entries' => [
        'symbol' => 'ETHUSDT',
        'side' => 'SHORT',
        'leverage' => 3,
        'entries' => null,  // Should auto-generate from market price
        'targets' => ['1.5%', '3%'],
        'stop_loss' => ['2%']
    ],

    'fvg_signal' => [
        'type' => 'FVG',
        'symbol' => 'ADAUSDT',
        'side' => 'LONG',
        'metadata' => [
            'fvg_size_pct' => 3.2,
            'candle_body_pct' => 1.8,
            'upper_shadow_pct' => 0.5,
            'dist_to_fvg_pct' => 1.2
        ]
    ],

    'trigger_cross_signal' => [
        'type' => 'TRIGGER_CROSS',
        'symbol' => 'SOLUSDT',
        'side' => 'SHORT',
        'levels' => 'HML',
        'prices' => '95.50 | 96.20 | 97.00'
    ],

    'ichimoku_signal' => [
        'type' => 'ICHIMOKU_AFTER_CROSS',
        'symbol' => 'DOGEUSDT',
        'side' => 'LONG',
        'entry' => 0.085
    ],

    'trend_signal' => [
        'type' => 'IN_TREND',
        'symbol' => 'MATICUSDT',
        'side' => 'SHORT',
        'entry' => 1.25,
        'candle_size' => 0.15,
        'distance_to_t3' => 0.8,
        'candle_position' => 'above_t3',
        'distance_to_trend_start' => 15,
        't3_status' => 'converging',
        't3_distance' => 0.3,
        't3_strength' => 0.04,
        't3_squeeze' => 'false',
        'conv_bars' => 8,
        'div_bars' => 0
    ]
];

/**
 * Function to simulate webhook POST request to bot.php
 */
function testWebhookSignal($signalData, $signalName) {
    echo "=== Testing $signalName ===\n";
    echo "Input data: " . json_encode($signalData, JSON_PRETTY_PRINT) . "\n";

    // Simulate POST input by setting up the environment
    $jsonData = json_encode($signalData);

    // Capture the output by including bot.php in a controlled way
    ob_start();

    // Simulate POST data
    $tempFile = tempnam(sys_get_temp_dir(), 'webhook_test');
    file_put_contents($tempFile, $jsonData);

    // Override php://input for testing
    $originalData = $jsonData;

    try {
        // We'll use a different approach - create a test version of bot.php
        $result = simulateBotWebhook($signalData);
        echo "Processing result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";

    } catch (Exception $e) {
        echo "❌ Error during processing: " . $e->getMessage() . "\n";
        echo "Stack trace: " . $e->getTraceAsString() . "\n";
    }

    ob_end_clean();

    echo "\n" . str_repeat("-", 60) . "\n\n";
}

/**
 * Simulate the bot webhook processing
 */
function simulateBotWebhook($signalData) {
    // Include required files
    require_once 'env_loader.php';
    require_once 'telegram_sender.php';

    // Create handler instance
    require_once 'bot.php';

    // Use reflection to access private methods for testing
    $handler = new SignalWebhookHandler();

    echo "✅ SignalWebhookHandler created\n";

    // Since we can't easily mock php://input, we'll test the processing methods directly
    // by using reflection to access private methods

    $reflectionClass = new ReflectionClass($handler);

    // Test data validation
    $validateMethod = $reflectionClass->getMethod('validateRequiredFields');
    $validateMethod->setAccessible(true);

    try {
        $validateMethod->invoke($handler, $signalData);
        echo "✅ Data validation passed\n";
    } catch (Exception $e) {
        echo "❌ Data validation failed: " . $e->getMessage() . "\n";
        return ['success' => false, 'error' => 'Validation failed: ' . $e->getMessage()];
    }

    // Test signal processing
    $processMethod = $reflectionClass->getMethod('processSignalByType');
    $processMethod->setAccessible(true);

    try {
        $result = $processMethod->invoke($handler, $signalData);
        echo "✅ Signal processing completed\n";
        return $result;
    } catch (Exception $e) {
        echo "❌ Signal processing failed: " . $e->getMessage() . "\n";
        return ['success' => false, 'error' => 'Processing failed: ' . $e->getMessage()];
    }
}

// Clear any existing log files to start fresh
$logFiles = ['debug_log.txt', 'telegram_debug.log', 'signals_log.json'];
foreach ($logFiles as $logFile) {
    if (file_exists($logFile)) {
        file_put_contents($logFile, "=== WEBHOOK FLOW TEST STARTED " . date('Y-m-d H:i:s') . " ===\n");
    }
}

// Run tests for each signal type
foreach ($testSignals as $signalName => $signalData) {
    testWebhookSignal($signalData, $signalName);

    // Small delay between tests
    sleep(1);
}

echo "=== CHECKING LOG FILES ===\n\n";

// Check log files for detailed information
foreach ($logFiles as $logFile) {
    if (file_exists($logFile)) {
        echo "=== $logFile ===\n";
        echo file_get_contents($logFile);
        echo "\n" . str_repeat("=", 50) . "\n\n";
    } else {
        echo "$logFile: NOT FOUND\n\n";
    }
}

echo "=== WEBHOOK FLOW TEST COMPLETED ===\n";
echo "Check the results above to verify:\n";
echo "1. Data validation is working\n";
echo "2. Signal processing is working\n";
echo "3. Telegram notifications are being sent\n";
echo "4. All log files show proper data flow\n";

?>