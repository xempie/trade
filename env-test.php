<?php
chdir(__DIR__ . '/ta');

// Load environment manually
$lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    if (strpos($line, '=') !== false) {
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, '"\'');
        putenv("{$key}={$value}");
        echo "Set: $key = " . substr($value, 0, 10) . "...\n";
    }
}

echo "\nTesting telegram variables:\n";
echo "TELEGRAM_BOT_TOKEN_NOTIF: " . (getenv('TELEGRAM_BOT_TOKEN_NOTIF') ? 'SET' : 'NOT SET') . "\n";
echo "TELEGRAM_CHAT_ID_NOTIF: " . (getenv('TELEGRAM_CHAT_ID_NOTIF') ? 'SET' : 'NOT SET') . "\n";

// Test sending a simple message
$botToken = getenv('TELEGRAM_BOT_TOKEN_NOTIF');
$chatId = getenv('TELEGRAM_CHAT_ID_NOTIF');

if ($botToken && $chatId) {
    $message = "🧪 Direct API test - " . date('Y-m-d H:i:s');
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

    $params = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML'
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
    $result = @file_get_contents($url, false, $context);

    if ($result) {
        $response = json_decode($result, true);
        echo "\nDirect API result: " . ($response['ok'] ? 'SUCCESS' : 'FAILED') . "\n";
        if (!$response['ok']) {
            echo "Error: " . $response['description'] . "\n";
        }
    } else {
        echo "\nDirect API result: NETWORK ERROR\n";
    }
} else {
    echo "\nMissing telegram credentials\n";
}
?>