<?php
/**
 * Quick test for stop loss calculation fix
 */

// Change to bot directory
chdir(__DIR__);

// Include required files
require_once 'env_loader.php';
require_once 'telegram_sender.php';

echo "=== STOP LOSS CALCULATION FIX TEST ===\n\n";

// Test signal with percentage stop loss
$testSignal = [
    'symbol' => 'BTCUSDT',
    'side' => 'LONG',
    'leverage' => 5,
    'entries' => [45000, 44500],
    'targets' => ['2%', '4%'],
    'stop_loss' => ['3%'],  // This should calculate to ~43,650
    'external_signal_id' => 'stopLoss-test-001'
];

echo "Test signal data:\n";
echo json_encode($testSignal, JSON_PRETTY_PRINT) . "\n\n";

// Include bot.php and test the processing
require_once 'bot.php';

// Create handler and use reflection to test private methods
$handler = new SignalWebhookHandler();
$reflectionClass = new ReflectionClass($handler);

// Test processStopLoss method directly
$processStopLossMethod = $reflectionClass->getMethod('processStopLoss');
$processStopLossMethod->setAccessible(true);

$entryPrice = 45000;
$isLong = true;
$stopLossArray = ['3%'];

echo "Testing processStopLoss method:\n";
echo "Entry Price: $entryPrice\n";
echo "Is Long: " . ($isLong ? 'true' : 'false') . "\n";
echo "Stop Loss Input: " . json_encode($stopLossArray) . "\n";

try {
    $calculatedStopLoss = $processStopLossMethod->invoke($handler, $stopLossArray, $entryPrice, $isLong);
    echo "Calculated Stop Loss: $calculatedStopLoss\n";
    echo "Expected (~43,650): " . ($entryPrice * 0.97) . "\n";

    if (abs($calculatedStopLoss - ($entryPrice * 0.97)) < 1) {
        echo "✅ Stop Loss calculation is CORRECT!\n";
    } else {
        echo "❌ Stop Loss calculation is incorrect\n";
    }
} catch (Exception $e) {
    echo "❌ Error testing stop loss: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("-", 50) . "\n";

// Test with a SHORT position
echo "\nTesting SHORT position:\n";
$entryPriceShort = 45000;
$isLongShort = false;
$stopLossArrayShort = ['3%'];

echo "Entry Price: $entryPriceShort\n";
echo "Is Long: " . ($isLongShort ? 'true' : 'false') . "\n";
echo "Stop Loss Input: " . json_encode($stopLossArrayShort) . "\n";

try {
    $calculatedStopLossShort = $processStopLossMethod->invoke($handler, $stopLossArrayShort, $entryPriceShort, $isLongShort);
    echo "Calculated Stop Loss: $calculatedStopLossShort\n";
    echo "Expected (~46,350): " . ($entryPriceShort * 1.03) . "\n";

    if (abs($calculatedStopLossShort - ($entryPriceShort * 1.03)) < 1) {
        echo "✅ SHORT Stop Loss calculation is CORRECT!\n";
    } else {
        echo "❌ SHORT Stop Loss calculation is incorrect\n";
    }
} catch (Exception $e) {
    echo "❌ Error testing SHORT stop loss: " . $e->getMessage() . "\n";
}

echo "\n=== TEST COMPLETED ===\n";

?>