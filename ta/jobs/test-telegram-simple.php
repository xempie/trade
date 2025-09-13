<?php
/**
 * Simple Telegram Test Cron Job
 * Uses existing telegram.php infrastructure
 */

// Change to the ta directory to access includes properly
chdir(dirname(__DIR__));

// Include the telegram API helper
require_once 'api/telegram.php';

$timestamp = date('Y-m-d H:i:s');
$hostname = gethostname();
$phpVersion = PHP_VERSION;

// Create test message
$message = "🧪 <b>Cron Test Message</b>\n\n";
$message .= "📅 <b>Time:</b> {$timestamp}\n";
$message .= "🖥️ <b>Server:</b> {$hostname}\n";
$message .= "🐘 <b>PHP:</b> {$phpVersion}\n\n";
$message .= "✅ Cron jobs are working!\n";
$message .= "🔄 Sent every minute for testing.";

// Log to file
$logFile = __DIR__ . '/test-telegram-simple.log';
$logEntry = "[{$timestamp}] Sending test message to Telegram\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

try {
    // Send via existing telegram API function
    $result = sendTelegramMessage($message);

    if ($result && $result['success']) {
        $logEntry = "[{$timestamp}] ✅ Message sent successfully\n";
        echo $logEntry;
    } else {
        $errorMsg = $result['error'] ?? 'Unknown error';
        $logEntry = "[{$timestamp}] ❌ Failed to send message: {$errorMsg}\n";
        echo $logEntry;
    }

    file_put_contents($logFile, $logEntry, FILE_APPEND);

} catch (Exception $e) {
    $errorMsg = "[{$timestamp}] Error: " . $e->getMessage() . "\n";
    echo $errorMsg;
    file_put_contents($logFile, $errorMsg, FILE_APPEND);
}

echo "Test telegram cron job completed\n";
?>