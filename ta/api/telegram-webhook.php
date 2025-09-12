<?php
/**
 * Telegram Webhook Handler
 * Handles callback queries from inline buttons
 * Set this as your bot's webhook URL: https://yourdomain.com/trade/api/telegram-webhook.php
 */

header('Content-Type: application/json');

// Include the Telegram messaging class
require_once 'telegram.php';

// Get the webhook data
$input = file_get_contents('php://input');
$update = json_decode($input, true);

// Log the webhook for debugging
error_log("Telegram Webhook: " . $input);

if (!$update) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

try {
    $telegram = new TelegramMessenger();
    
    // Handle callback queries (inline button clicks)
    if (isset($update['callback_query'])) {
        $callbackQuery = $update['callback_query'];
        $callbackData = $callbackQuery['data'] ?? '';
        $callbackQueryId = $callbackQuery['id'] ?? '';
        $chatId = $callbackQuery['message']['chat']['id'] ?? '';
        $messageId = $callbackQuery['message']['message_id'] ?? '';
        
        if ($callbackData && $chatId && $messageId) {
            $result = handleCallbackQuery($callbackData, $callbackQueryId, $chatId, $messageId, $telegram);
            echo json_encode(['ok' => true, 'result' => $result]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Missing callback data']);
        }
        
    // Handle regular messages (optional - for future commands)
    } elseif (isset($update['message'])) {
        $message = $update['message'];
        $chatId = $message['chat']['id'] ?? '';
        $text = $message['text'] ?? '';
        
        // You can add command handling here if needed
        // For now, just acknowledge
        echo json_encode(['ok' => true, 'message' => 'Message received']);
        
    } else {
        echo json_encode(['ok' => true, 'message' => 'Update type not handled']);
    }
    
} catch (Exception $e) {
    error_log("Telegram Webhook Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

/**
 * Handle callback query from inline buttons
 */
function handleCallbackQuery($callbackData, $callbackQueryId, $chatId, $messageId, $telegram) {
    try {
        $data = json_decode($callbackData, true);
        
        if (!$data || !isset($data['action'])) {
            return answerCallback($callbackQueryId, "Invalid callback data");
        }
        
        switch ($data['action']) {
            case 'open_position':
                return handleOpenPosition($data, $callbackQueryId, $chatId, $messageId, $telegram);
                
            case 'remove_watchlist':
                return handleRemoveWatchlist($data, $callbackQueryId, $chatId, $messageId, $telegram);
                
            default:
                return answerCallback($callbackQueryId, "Unknown action: " . $data['action']);
        }
        
    } catch (Exception $e) {
        error_log("Callback handling error: " . $e->getMessage());
        return answerCallback($callbackQueryId, "Error processing request");
    }
}

/**
 * Handle open position callback
 */
function handleOpenPosition($data, $callbackQueryId, $chatId, $messageId, $telegram) {
    $symbol = $data['symbol'] ?? '';
    $direction = $data['direction'] ?? '';
    $price = $data['price'] ?? '';
    $entryType = $data['entry_type'] ?? '';
    
    if (empty($symbol) || empty($direction)) {
        return answerCallback($callbackQueryId, "Missing symbol or direction");
    }
    
    // Place market order
    $orderResult = placeMarketOrder($symbol, $direction, $price, $entryType);
    
    if ($orderResult['success']) {
        // Success - edit the original message
        $newMessage = "✅ <b>Order Placed Successfully!</b>\n\n" .
                     "📈 <b>{$symbol}</b> {$direction}\n" .
                     "💰 Market Order Executed\n" .
                     "💵 Price: \${$price}\n" .
                     "🎯 Entry: " . strtoupper(str_replace('_', ' ', $entryType)) . "\n" .
                     "⏰ Time: " . date('Y-m-d H:i:s');
        
        editMessage($chatId, $messageId, $newMessage);
        return answerCallback($callbackQueryId, "✅ Order placed successfully!");
        
    } else {
        $errorMsg = $orderResult['error'] ?? 'Unknown error';
        return answerCallback($callbackQueryId, "❌ Order failed: " . $errorMsg);
    }
}

/**
 * Handle remove watchlist callback
 */
function handleRemoveWatchlist($data, $callbackQueryId, $chatId, $messageId, $telegram) {
    $watchlistId = $data['watchlist_id'] ?? '';
    $symbol = $data['symbol'] ?? '';
    
    if (empty($watchlistId)) {
        return answerCallback($callbackQueryId, "Missing watchlist ID");
    }
    
    // Remove from database
    $removeResult = removeWatchlistItem($watchlistId);
    
    if ($removeResult) {
        // Success - edit the original message
        $newMessage = "🗑️ <b>Alert Removed</b>\n\n" .
                     "❌ <b>{$symbol}</b> watchlist alert removed\n" .
                     "⏰ Time: " . date('Y-m-d H:i:s') . "\n\n" .
                     "The price alert has been successfully removed from your watchlist.";
        
        editMessage($chatId, $messageId, $newMessage);
        return answerCallback($callbackQueryId, "🗑️ Alert removed successfully!");
        
    } else {
        return answerCallback($callbackQueryId, "❌ Failed to remove alert");
    }
}

/**
 * Place market order via API
 */
function placeMarketOrder($symbol, $direction, $price, $entryType) {
    try {
        // Determine order side
        $side = $direction === 'LONG' ? 'BUY' : 'SELL';
        
        // Create order data
        $orderData = [
            'symbol' => $symbol,
            'side' => $side,
            'type' => 'MARKET',
            'direction' => $direction,
            'entry_level' => $entryType === 'entry_2' ? 'ENTRY_2' : ($entryType === 'entry_3' ? 'ENTRY_3' : 'MARKET'),
            'telegram_trigger' => true // Flag to indicate this came from Telegram
        ];
        
        // Get the current domain/host for API call
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = dirname($_SERVER['REQUEST_URI'] ?? '/');
        $apiUrl = "{$protocol}://{$host}{$basePath}/place_order.php";
        
        // Make API call
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode($orderData),
                'timeout' => 30
            ]
        ]);
        
        $response = file_get_contents($apiUrl, false, $context);
        
        if ($response === false) {
            throw new Exception('API call failed');
        }
        
        $result = json_decode($response, true);
        
        if (!$result) {
            throw new Exception('Invalid API response');
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Market order placement failed: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Remove watchlist item from database
 */
function removeWatchlistItem($watchlistId) {
    try {
        // Load environment variables
        loadEnv();
        
        $host = getenv('DB_HOST') ?: 'localhost';
        $user = getenv('DB_USER') ?: '';
        $password = getenv('DB_PASSWORD') ?: '';
        $database = getenv('DB_NAME') ?: 'crypto_trading';
        
        $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql = "DELETE FROM watchlist WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([':id' => $watchlistId]);
        
    } catch (Exception $e) {
        error_log("Failed to remove watchlist item {$watchlistId}: " . $e->getMessage());
        return false;
    }
}

/**
 * Answer callback query
 */
function answerCallback($callbackQueryId, $text) {
    $botToken = getenv('TELEGRAM_BOT_TOKEN');
    
    if (empty($botToken)) {
        error_log("Missing Telegram bot token for callback answer");
        return false;
    }
    
    $url = "https://api.telegram.org/bot{$botToken}/answerCallbackQuery";
    $postData = [
        'callback_query_id' => $callbackQueryId,
        'text' => $text,
        'show_alert' => false
    ];
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($postData),
            'timeout' => 10
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    return $response !== false;
}

/**
 * Edit an existing message
 */
function editMessage($chatId, $messageId, $newText) {
    $botToken = getenv('TELEGRAM_BOT_TOKEN');
    
    if (empty($botToken)) {
        error_log("Missing Telegram bot token for message edit");
        return false;
    }
    
    $url = "https://api.telegram.org/bot{$botToken}/editMessageText";
    $postData = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $newText,
        'parse_mode' => 'HTML'
    ];
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($postData),
            'timeout' => 10
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    return $response !== false;
}

/**
 * Load environment variables
 */
function loadEnv() {
    $envPath = dirname(__DIR__) . '/.env';
    
    if (!file_exists($envPath)) {
        return;
    }
    
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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

// Load environment at startup
loadEnv();
?>