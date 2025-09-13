<?php
/**
 * Direct Test for Telegram Notifications
 * Tests telegram functionality directly without going through web server
 */

// Change to bot directory
chdir(__DIR__);

// Include required files
require_once 'env_loader.php';
require_once 'telegram_sender.php';

echo "=== DIRECT TELEGRAM NOTIFICATION TEST ===\n\n";

echo "1. Testing Environment Loading...\n";
if (!file_exists('.env')) {
    echo "❌ .env file not found!\n";
    exit(1);
}

// Check environment variables
$enableTelegram = EnvLoader::getBool('ENABLE_TELEGRAM');
$botToken = EnvLoader::get('TELEGRAM_BOT_TOKEN');
$chatId = EnvLoader::get('TELEGRAM_CHAT_ID');
$adminBotToken = EnvLoader::get('TELEGRAM_BOT_TOKEN_ADMIN');
$adminChatId = EnvLoader::get('TELEGRAM_CHAT_ID_ADMIN');

echo "ENABLE_TELEGRAM: " . ($enableTelegram ? 'TRUE' : 'FALSE') . "\n";
echo "TELEGRAM_BOT_TOKEN: " . ($botToken ? 'SET (' . substr($botToken, 0, 10) . '...)' : 'NOT SET') . "\n";
echo "TELEGRAM_CHAT_ID: " . ($chatId ? 'SET (' . $chatId . ')' : 'NOT SET') . "\n";
echo "TELEGRAM_BOT_TOKEN_ADMIN: " . ($adminBotToken ? 'SET (' . substr($adminBotToken, 0, 10) . '...)' : 'NOT SET') . "\n";
echo "TELEGRAM_CHAT_ID_ADMIN: " . ($adminChatId ? 'SET (' . $adminChatId . ')' : 'NOT SET') . "\n\n";

echo "2. Testing TelegramSender Initialization...\n";
$telegram = new TelegramSender();
echo "✅ TelegramSender object created\n\n";

echo "3. Testing Simple Message...\n";
$testMessage = "🧪 Direct test from bot - " . date('Y-m-d H:i:s');
$result = $telegram->sendMessage($testMessage);
echo "Simple message result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

echo "4. Testing Admin Message (IN_TREND)...\n";
$adminTestMessage = "🧪 Admin test (IN_TREND) - " . date('Y-m-d H:i:s');
$adminResult = $telegram->sendAdminMessage('IN_TREND', $adminTestMessage);
echo "Admin message result: " . json_encode($adminResult, JSON_PRETTY_PRINT) . "\n\n";

echo "5. Testing FVG Alert...\n";
$fvgResult = $telegram->sendFVGAlert('BTCUSDT', 'LONG', 2.5);
echo "FVG alert result: " . json_encode($fvgResult, JSON_PRETTY_PRINT) . "\n\n";

echo "6. Testing Trading Signal Alert...\n";
$entries = ['entry_market' => 45000, 'entry_2' => 44500, 'entry_3' => null];
$targets = ['take_profit_1' => 46800, 'take_profit_2' => 48600, 'take_profit_3' => null, 'take_profit_4' => null, 'take_profit_5' => null];
$stopLoss = 43650;

if (method_exists($telegram, 'sendTradingSignalAlert')) {
    $tradingResult = $telegram->sendTradingSignalAlert('BTCUSDT', 'LONG', $entries, $targets, $stopLoss, 5);
    echo "Trading signal alert result: " . json_encode($tradingResult, JSON_PRETTY_PRINT) . "\n\n";
} else {
    echo "❌ sendTradingSignalAlert method does not exist\n\n";
}

echo "7. Testing Error Notification...\n";
$errorResult = $telegram->sendErrorNotification('Test error message', 123);
echo "Error notification result: " . json_encode($errorResult, JSON_PRETTY_PRINT) . "\n\n";

echo "8. Checking Log Files...\n";
$logFiles = ['telegram_debug.log', 'debug_log.txt', 'errors.log'];

foreach ($logFiles as $logFile) {
    if (file_exists($logFile)) {
        echo "=== $logFile (last 10 lines) ===\n";
        $lines = file($logFile);
        $recentLines = array_slice($lines, -10);
        echo implode('', $recentLines);
        echo "\n";
    } else {
        echo "$logFile: NOT FOUND\n";
    }
    echo "\n";
}

echo "=== TEST COMPLETED ===\n";
echo "Check the results above to see if notifications are working.\n";
echo "If all tests show success: true, then notifications are working!\n";

?>