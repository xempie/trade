<?php
/**
 * Test Cron Job for Telegram Notifications
 * Sends a test message every minute to verify cron jobs are working
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Change to script directory
chdir(__DIR__);

// Load environment variables
require_once '../config/database.php';

// Simple environment loader for cron jobs
function loadEnvFromFile($file = '../.env') {
    if (!file_exists($file)) {
        throw new Exception("Environment file not found: $file");
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

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
}

// Load environment
try {
    loadEnvFromFile();
    echo "[" . date('Y-m-d H:i:s') . "] Environment loaded successfully\n";
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error loading environment: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Send test message to Telegram
 */
function sendTestTelegramMessage() {
    // Get telegram configuration
    $botToken = getenv('TELEGRAM_BOT_TOKEN_NOTIF') ?: getenv('TELEGRAM_BOT_TOKEN');
    $chatId = getenv('TELEGRAM_CHAT_ID_NOTIF') ?: getenv('TELEGRAM_CHAT_ID');

    if (empty($botToken) || empty($chatId)) {
        echo "[" . date('Y-m-d H:i:s') . "] Error: Telegram credentials not found\n";
        echo "Bot Token: " . ($botToken ? 'SET' : 'NOT SET') . "\n";
        echo "Chat ID: " . ($chatId ? 'SET' : 'NOT SET') . "\n";
        return false;
    }

    // Create test message
    $hostname = gethostname();
    $timestamp = date('Y-m-d H:i:s');
    $uptime = shell_exec('uptime') ?: 'Unknown';
    $phpVersion = PHP_VERSION;

    $message = "🤖 <b>Cron Job Test Message</b>\n\n";
    $message .= "📅 <b>Time:</b> {$timestamp}\n";
    $message .= "🖥️ <b>Server:</b> {$hostname}\n";
    $message .= "🐘 <b>PHP:</b> {$phpVersion}\n";
    $message .= "⏱️ <b>Uptime:</b> " . trim($uptime) . "\n\n";
    $message .= "✅ Cron jobs are working correctly!\n";
    $message .= "🔄 This message is sent every minute for testing.";

    // Send via Telegram API
    $telegramUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";

    $params = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];

    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($params),
            'timeout' => 10
        ],
    ];

    $context = stream_context_create($options);
    $result = @file_get_contents($telegramUrl, false, $context);

    if ($result === false) {
        echo "[" . date('Y-m-d H:i:s') . "] Failed to send telegram message\n";
        return false;
    }

    $response = json_decode($result, true);

    if ($response && $response['ok']) {
        echo "[" . date('Y-m-d H:i:s') . "] ✅ Test message sent successfully to {$chatId}\n";
        echo "Message ID: " . $response['result']['message_id'] . "\n";
        return true;
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] ❌ Failed to send message: " . ($response['description'] ?? 'Unknown error') . "\n";
        return false;
    }
}

/**
 * Log system information
 */
function logSystemInfo() {
    $logFile = __DIR__ . '/test-telegram-cron.log';

    $info = [
        'timestamp' => date('Y-m-d H:i:s'),
        'hostname' => gethostname(),
        'php_version' => PHP_VERSION,
        'memory_usage' => memory_get_usage(true),
        'memory_peak' => memory_get_peak_usage(true),
        'load_average' => sys_getloadavg(),
        'disk_free' => disk_free_space('/'),
        'working_directory' => getcwd()
    ];

    $logEntry = date('Y-m-d H:i:s') . " - System Info: " . json_encode($info) . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

    echo "[" . date('Y-m-d H:i:s') . "] System info logged\n";
}

// Main execution
echo "=== TELEGRAM CRON TEST STARTED ===\n";
echo "[" . date('Y-m-d H:i:s') . "] Starting telegram cron test\n";

try {
    // Log system information
    logSystemInfo();

    // Send test message
    $success = sendTestTelegramMessage();

    if ($success) {
        echo "[" . date('Y-m-d H:i:s') . "] Test completed successfully\n";
        exit(0);
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] Test failed\n";
        exit(1);
    }

} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
}

echo "=== TELEGRAM CRON TEST FINISHED ===\n";
?>