<?php
/**
 * Telegram Notification Test Script
 * Tests all types of Telegram notifications to verify setup
 */

// Load environment variables
function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        if (strpos($line, '=') === false) {
            continue;
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

loadEnv('.env');

// Load Telegram messaging class
require_once 'api/telegram.php';

echo "=== Telegram Notification Test ===\n\n";

// Get Telegram configuration
$botToken = getenv('TELEGRAM_BOT_TOKEN_NOTIF') ?: '';
$chatId = getenv('TELEGRAM_CHAT_ID_NOTIF') ?: '';
$notificationsEnabled = strtolower(getenv('TELEGRAM_NOTIFICATIONS_ENABLED') ?: 'false') === 'true';

echo "📋 Configuration Check:\n";
echo "  - Bot Token: " . (empty($botToken) ? "❌ Missing" : "✅ Present (" . substr($botToken, 0, 10) . "...)") . "\n";
echo "  - Chat ID: " . (empty($chatId) ? "❌ Missing" : "✅ Present ({$chatId})") . "\n";
echo "  - Notifications Enabled: " . ($notificationsEnabled ? "✅ Yes" : "⚠️ No") . "\n\n";

if (empty($botToken) || empty($chatId)) {
    echo "❌ ERROR: Missing Telegram configuration!\n";
    echo "Please set TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID in .env file\n\n";
    exit(1);
}

// Initialize Telegram messenger
$telegram = new TelegramMessenger($botToken, $chatId);

echo "🚀 Running Telegram Tests...\n\n";

// Test 1: Simple message
echo "Test 1: Simple notification message\n";
$result1 = $telegram->sendMessage("🧪 Test: Simple notification from crypto trading app", $chatId, $botToken, 'LOW');
echo "Result: " . ($result1 ? "✅ SUCCESS" : "❌ FAILED") . "\n\n";

// Test 2: Price alert (most important for watchlist)
echo "Test 2: Price alert notification\n";
$result2 = $telegram->sendPriceAlert(
    'BTC', 
    'entry_2', 
    45000.50, 
    44950.25, 
    'long', 
    100.00, 
    123
);
echo "Result: " . ($result2 ? "✅ SUCCESS" : "❌ FAILED") . "\n\n";

// Test 3: Order filled notification
echo "Test 3: Order filled notification\n";
$result3 = $telegram->sendOrderFilled(
    'ETH',
    'BUY',
    150.00,
    2850.75,
    5,
    'entry_2'
);
echo "Result: " . ($result3 ? "✅ SUCCESS" : "❌ FAILED") . "\n\n";

// Test 4: P&L milestone notification
echo "Test 4: P&L milestone notification\n";
$result4 = $telegram->sendPnLMilestone(
    'BTC',
    'LONG',
    'profit',
    25,
    27.5,
    275.00
);
echo "Result: " . ($result4 ? "✅ SUCCESS" : "❌ FAILED") . "\n\n";

// Test 5: Balance change notification
echo "Test 5: Balance change notification\n";
$result5 = $telegram->sendBalanceChange(
    'increase',
    8.5,
    1000.00,
    1085.00,
    85.00
);
echo "Result: " . ($result5 ? "✅ SUCCESS" : "❌ FAILED") . "\n\n";

// Test 6: Order cancelled notification
echo "Test 6: Order cancelled notification\n";
$result6 = $telegram->sendOrderCancelled(
    'SOL',
    75.00,
    125.50,
    'BUY'
);
echo "Result: " . ($result6 ? "✅ SUCCESS" : "❌ FAILED") . "\n\n";

// Summary
$totalTests = 6;
$passedTests = array_sum([$result1, $result2, $result3, $result4, $result5, $result6]);

echo "=== Test Summary ===\n";
echo "Total Tests: {$totalTests}\n";
echo "Passed: {$passedTests}\n";
echo "Failed: " . ($totalTests - $passedTests) . "\n\n";

if ($passedTests === $totalTests) {
    echo "🎉 ALL TESTS PASSED!\n";
    echo "✅ Telegram notifications are working correctly\n";
    echo "✅ Your watchlist price alerts will be sent properly\n\n";
} else {
    echo "⚠️ SOME TESTS FAILED\n";
    echo "❌ Check your Telegram bot configuration\n";
    echo "❌ Verify bot token and chat ID are correct\n\n";
    
    // Additional debugging info
    echo "🔧 Troubleshooting Steps:\n";
    echo "1. Verify bot token is correct and active\n";
    echo "2. Make sure the bot is added to the chat/channel\n";
    echo "3. Check if chat ID format is correct (@channel or numeric ID)\n";
    echo "4. Try sending a message to the bot first\n";
    echo "5. Ensure TELEGRAM_NOTIFICATIONS_ENABLED=true\n\n";
}

echo "📱 Expected Behavior:\n";
echo "- You should receive 6 different notification messages\n";
echo "- Messages should have different emojis and formats\n";
echo "- Price alert should have interactive buttons\n";
echo "- All messages should appear in your configured chat/channel\n\n";

echo "🔗 Useful Links:\n";
echo "- Bot Management: https://t.me/BotFather\n";
echo "- Get Chat ID: Send message to bot, then visit:\n";
echo "  https://api.telegram.org/bot{$botToken}/getUpdates\n\n";

?>