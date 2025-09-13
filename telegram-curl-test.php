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
    }
}

$botToken = getenv('TELEGRAM_BOT_TOKEN_NOTIF');
$chatId = getenv('TELEGRAM_CHAT_ID_NOTIF');

if (!$botToken || !$chatId) {
    echo "Missing telegram credentials\n";
    exit(1);
}

echo "Testing with cURL...\n";
echo "Bot Token: " . substr($botToken, 0, 10) . "...\n";
echo "Chat ID: $chatId\n\n";

$message = "🧪 cURL test - " . date('Y-m-d H:i:s');
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
curl_setopt($ch, CURLOPT_VERBOSE, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

// Capture verbose output
$verboseHandle = fopen('php://temp', 'rw+');
curl_setopt($ch, CURLOPT_STDERR, $verboseHandle);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

// Get verbose output
rewind($verboseHandle);
$verboseOutput = stream_get_contents($verboseHandle);
fclose($verboseHandle);

curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "cURL Error: " . ($error ?: 'None') . "\n";
echo "Response: $result\n\n";

if ($result) {
    $response = json_decode($result, true);
    if ($response && $response['ok']) {
        echo "✅ SUCCESS! Message sent to Telegram\n";
        echo "Message ID: " . $response['result']['message_id'] . "\n";
    } else {
        echo "❌ TELEGRAM ERROR: " . ($response['description'] ?? 'Unknown') . "\n";
    }
} else {
    echo "❌ NETWORK ERROR\n";
    echo "Verbose output:\n$verboseOutput\n";
}
?>