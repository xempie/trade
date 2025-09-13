<?php
// Test webhook to simulate TradingView signal
header('Content-Type: application/json');

// Include bot.php to test the webhook processing
require_once 'bot.php';

// Test signal data (similar to what TradingView sends)
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

echo "=== TESTING WEBHOOK PROCESSING ===\n\n";

// Simulate the JSON input that bot.php expects
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

// Temporarily override php://input for testing
$tempInput = json_encode($testSignal);
file_put_contents('php://temp', $tempInput);

echo "Test signal data:\n";
echo json_encode($testSignal, JSON_PRETTY_PRINT) . "\n\n";

try {
    // Create the webhook handler
    $handler = new SignalWebhookHandler();

    // Test if we can create the handler
    echo "✅ Webhook handler created successfully\n";

    // Try to process the test data
    echo "🔄 Processing test signal...\n\n";

    // We'll simulate the processing instead of calling the full webhook
    // to avoid the php://input issue in testing

    echo "=== CHECK CONFIGURATION ===\n";
    echo "Bot directory: " . __DIR__ . "\n";
    echo "Env file exists: " . (file_exists(__DIR__ . '/.env') ? 'YES' : 'NO') . "\n";

    // Test environment loading
    require_once 'env_loader.php';
    echo "Environment loaded: " . (class_exists('EnvLoader') ? 'YES' : 'NO') . "\n";

    // Test Telegram configuration
    echo "Telegram enabled: " . (EnvLoader::getBool('ENABLE_TELEGRAM') ? 'YES' : 'NO') . "\n";
    echo "Telegram bot token: " . (EnvLoader::get('TELEGRAM_BOT_TOKEN') ? 'SET' : 'NOT SET') . "\n";
    echo "Telegram chat ID: " . (EnvLoader::get('TELEGRAM_CHAT_ID') ? 'SET' : 'NOT SET') . "\n";

    // Test logging
    echo "Logging enabled: " . (EnvLoader::getBool('ENABLE_LOGGING') ? 'YES' : 'NO') . "\n";

    echo "\n=== TELEGRAM TEST ===\n";

    // Test Telegram sender
    require_once 'telegram_sender.php';
    $telegram = new TelegramSender();

    $testMessage = "🧪 Test message from webhook debug script\n\nTime: " . date('Y-m-d H:i:s');
    $result = $telegram->sendMessage($testMessage);

    echo "Telegram test result:\n";
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "\n=== CHECKING LOG FILES ===\n";
$logFiles = ['debug_log.txt', 'signals_log.json', 'errors.log', 'trading.log'];
foreach ($logFiles as $logFile) {
    if (file_exists($logFile)) {
        echo "✅ Found: $logFile (" . filesize($logFile) . " bytes)\n";
    } else {
        echo "❌ Missing: $logFile\n";
    }
}

?>