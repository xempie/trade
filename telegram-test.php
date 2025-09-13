<?php
// Simple telegram test
chdir(__DIR__ . '/ta');
require_once 'api/telegram.php';

echo "Testing telegram configuration...\n";
$message = '🧪 Direct test from server - ' . date('Y-m-d H:i:s');
$result = sendTelegramMessage($message);
echo 'Result: ' . json_encode($result, JSON_PRETTY_PRINT) . "\n";

// Also test environment variables
echo "\nEnvironment variables:\n";
echo "TELEGRAM_BOT_TOKEN_NOTIF: " . (getenv('TELEGRAM_BOT_TOKEN_NOTIF') ? 'SET' : 'NOT SET') . "\n";
echo "TELEGRAM_CHAT_ID_NOTIF: " . (getenv('TELEGRAM_CHAT_ID_NOTIF') ? 'SET' : 'NOT SET') . "\n";
echo "TELEGRAM_BOT_TOKEN: " . (getenv('TELEGRAM_BOT_TOKEN') ? 'SET' : 'NOT SET') . "\n";
echo "TELEGRAM_CHAT_ID: " . (getenv('TELEGRAM_CHAT_ID') ? 'SET' : 'NOT SET') . "\n";
?>