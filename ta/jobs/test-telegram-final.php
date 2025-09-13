<?php
/**
 * Final Working Telegram Test Cron Job
 * Uses cURL directly since file_get_contents doesn't work on this server
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

/**
 * Send message to Telegram using cURL
 */
function sendTelegramMessage($message) {
    $botToken = getenv('TELEGRAM_BOT_TOKEN_NOTIF');
    $chatId = getenv('TELEGRAM_CHAT_ID_NOTIF');

    if (!$botToken || !$chatId) {
        return ['success' => false, 'error' => 'Missing bot token or chat ID'];
    }

    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => "cURL error: $error"];
    }

    if ($httpCode !== 200) {
        return ['success' => false, 'error' => "HTTP error: $httpCode"];
    }

    if (!$result) {
        return ['success' => false, 'error' => 'Empty response'];
    }

    $response = json_decode($result, true);

    if (!$response || !$response['ok']) {
        $errorMsg = $response['description'] ?? 'Unknown API error';
        return ['success' => false, 'error' => $errorMsg];
    }

    return [
        'success' => true,
        'message_id' => $response['result']['message_id'],
        'chat_id' => $response['result']['chat']['id']
    ];
}

// Load environment
if (!loadEnvironment()) {
    echo "[ERROR] Failed to load environment\n";
    exit(1);
}

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
$logFile = __DIR__ . '/test-telegram-final.log';
$logEntry = "[{$timestamp}] Sending test message to Telegram\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

try {
    // Send via direct cURL
    $result = sendTelegramMessage($message);

    if ($result['success']) {
        $logEntry = "[{$timestamp}] ✅ Message sent successfully - ID: {$result['message_id']}\n";
        echo $logEntry;
    } else {
        $logEntry = "[{$timestamp}] ❌ Failed to send message: {$result['error']}\n";
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