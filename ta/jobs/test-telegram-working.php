<?php
/**
 * Working Telegram Test Cron Job
 * Loads environment properly for cron context
 */

// Set working directory
chdir(__DIR__ . '/..');

// Load environment variables from .env file
function loadEnvironment($envFile = '.env') {
    if (!file_exists($envFile)) {
        error_log("Environment file not found: $envFile");
        return false;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse key=value pairs
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '"\'');

            // Set environment variable
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    return true;
}

// Load environment
if (!loadEnvironment()) {
    echo "[ERROR] Failed to load environment\n";
    exit(1);
}

// Now require telegram API
require_once 'api/telegram.php';

$timestamp = date('Y-m-d H:i:s');
$hostname = gethostname();

// Create test message
$message = "🤖 <b>Cron Test Message</b>\n\n";
$message .= "📅 <b>Time:</b> {$timestamp}\n";
$message .= "🖥️ <b>Server:</b> {$hostname}\n";
$message .= "🐘 <b>PHP:</b> " . PHP_VERSION . "\n\n";
$message .= "✅ Cron jobs are working correctly!\n";
$message .= "🔄 This message is sent every minute for testing.";

// Log to file
$logFile = __DIR__ . '/test-telegram-working.log';
$logEntry = "[{$timestamp}] Sending test message to Telegram\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

try {
    // Send via telegram API
    $result = sendTelegramMessage($message);

    if ($result && isset($result['success']) && $result['success']) {
        $logEntry = "[{$timestamp}] ✅ Message sent successfully\n";
        echo $logEntry;
    } else {
        $errorMsg = isset($result['error']) ? $result['error'] : 'Unknown error';
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