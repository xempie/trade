<?php
/**
 * Telegram Webhook Setup Script
 * Configures the Telegram bot webhook for handling button callbacks
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

echo "Telegram Webhook Setup\n";
echo "======================\n\n";

// Check if running from command line
if (php_sapi_name() !== 'cli') {
    echo "ERROR: This script must be run from command line\n";
    echo "Usage: php setup-telegram-webhook.php [set|delete|info] [webhook_url]\n";
    exit(1);
}

$command = $argv[1] ?? 'info';
$webhookUrl = $argv[2] ?? '';

// Load environment
loadEnv(__DIR__ . '/.env');

$botToken = getenv('TELEGRAM_BOT_TOKEN');

if (empty($botToken)) {
    echo "ERROR: TELEGRAM_BOT_TOKEN not found in .env file\n";
    exit(1);
}

switch ($command) {
    case 'set':
        if (empty($webhookUrl)) {
            echo "ERROR: Webhook URL is required for 'set' command\n";
            echo "Usage: php setup-telegram-webhook.php set https://yourdomain.com/trade/api/telegram-webhook.php\n";
            exit(1);
        }
        setWebhook($botToken, $webhookUrl);
        break;
        
    case 'delete':
        deleteWebhook($botToken);
        break;
        
    case 'info':
    default:
        getWebhookInfo($botToken);
        break;
}

function setWebhook($botToken, $webhookUrl) {
    echo "Setting webhook URL: {$webhookUrl}\n";
    
    $url = "https://api.telegram.org/bot{$botToken}/setWebhook";
    $postData = [
        'url' => $webhookUrl,
        'allowed_updates' => json_encode(['message', 'callback_query'])
    ];
    
    $response = makeRequest($url, $postData);
    
    if ($response && isset($response['ok']) && $response['ok']) {
        echo "✅ Webhook set successfully!\n";
        echo "Description: " . ($response['description'] ?? 'N/A') . "\n";
    } else {
        echo "❌ Failed to set webhook\n";
        echo "Error: " . ($response['description'] ?? 'Unknown error') . "\n";
    }
}

function deleteWebhook($botToken) {
    echo "Deleting webhook...\n";
    
    $url = "https://api.telegram.org/bot{$botToken}/deleteWebhook";
    
    $response = makeRequest($url, []);
    
    if ($response && isset($response['ok']) && $response['ok']) {
        echo "✅ Webhook deleted successfully!\n";
    } else {
        echo "❌ Failed to delete webhook\n";
        echo "Error: " . ($response['description'] ?? 'Unknown error') . "\n";
    }
}

function getWebhookInfo($botToken) {
    echo "Getting webhook information...\n\n";
    
    $url = "https://api.telegram.org/bot{$botToken}/getWebhookInfo";
    
    $response = makeRequest($url);
    
    if ($response && isset($response['ok']) && $response['ok']) {
        $info = $response['result'];
        
        echo "Webhook Information:\n";
        echo "===================\n";
        echo "URL: " . ($info['url'] ?: 'Not set') . "\n";
        echo "Has Custom Certificate: " . ($info['has_custom_certificate'] ? 'Yes' : 'No') . "\n";
        echo "Pending Updates: " . ($info['pending_update_count'] ?? 0) . "\n";
        echo "Max Connections: " . ($info['max_connections'] ?? 40) . "\n";
        echo "Allowed Updates: " . json_encode($info['allowed_updates'] ?? []) . "\n";
        
        if (!empty($info['last_error_date'])) {
            echo "Last Error Date: " . date('Y-m-d H:i:s', $info['last_error_date']) . "\n";
            echo "Last Error Message: " . ($info['last_error_message'] ?? 'N/A') . "\n";
        }
        
        if (empty($info['url'])) {
            echo "\n⚠️  No webhook is currently set.\n";
            echo "\nTo set a webhook, run:\n";
            echo "php setup-telegram-webhook.php set https://yourdomain.com/trade/api/telegram-webhook.php\n";
        } else {
            echo "\n✅ Webhook is configured and active.\n";
        }
        
    } else {
        echo "❌ Failed to get webhook info\n";
        echo "Error: " . ($response['description'] ?? 'Unknown error') . "\n";
    }
    
    // Also test the bot
    testBot($botToken);
}

function testBot($botToken) {
    echo "\nTesting bot...\n";
    echo "==============\n";
    
    $url = "https://api.telegram.org/bot{$botToken}/getMe";
    $response = makeRequest($url);
    
    if ($response && isset($response['ok']) && $response['ok']) {
        $bot = $response['result'];
        echo "✅ Bot is active and responding\n";
        echo "Bot Name: " . $bot['first_name'] . "\n";
        echo "Bot Username: @" . $bot['username'] . "\n";
        echo "Bot ID: " . $bot['id'] . "\n";
        echo "Can Join Groups: " . ($bot['can_join_groups'] ? 'Yes' : 'No') . "\n";
        echo "Can Read All Messages: " . ($bot['can_read_all_group_messages'] ? 'Yes' : 'No') . "\n";
        echo "Supports Inline: " . ($bot['supports_inline_queries'] ? 'Yes' : 'No') . "\n";
    } else {
        echo "❌ Bot test failed\n";
        echo "Error: " . ($response['description'] ?? 'Unknown error') . "\n";
        echo "Please check your TELEGRAM_BOT_TOKEN in .env file\n";
    }
}

function makeRequest($url, $postData = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo "HTTP Error: {$httpCode}\n";
        return null;
    }
    
    return json_decode($response, true);
}

echo "\nSetup Instructions:\n";
echo "==================\n";
echo "1. Make sure your webhook URL is publicly accessible\n";
echo "2. Use HTTPS (required by Telegram)\n";
echo "3. Test the webhook endpoint manually first\n";
echo "4. Set the webhook using: php setup-telegram-webhook.php set [URL]\n";
echo "5. Test by triggering a price alert with buttons\n\n";

echo "Example webhook URLs:\n";
echo "- https://yourdomain.com/trade/api/telegram-webhook.php\n";
echo "- https://yourserver.com/path/to/trade/api/telegram-webhook.php\n\n";
?>