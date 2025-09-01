<?php
/**
 * Telegram Messaging System
 * Handles sending messages with inline buttons to different channels
 */

class TelegramMessenger {
    
    private $defaultBotToken;
    private $defaultChatId;
    
    public function __construct($botToken = null, $chatId = null) {
        // Load from environment if not provided
        $this->defaultBotToken = $botToken ?: (getenv('TELEGRAM_BOT_TOKEN_NOTIF') ?: '');
        $this->defaultChatId = $chatId ?: (getenv('TELEGRAM_CHAT_ID_NOTIF') ?: '');
    }
    
    /**
     * Send a simple text message
     */
    public function sendMessage($message, $chatId = null, $botToken = null, $priority = 'MEDIUM') {
        $botToken = $botToken ?: $this->defaultBotToken;
        $chatId = $chatId ?: $this->defaultChatId;
        
        if (empty($botToken) || empty($chatId)) {
            error_log("Telegram - Missing bot token or chat ID");
            return false;
        }
        
        $priorityEmojis = [
            'HIGH' => '🚨',
            'MEDIUM' => '💰',
            'LOW' => 'ℹ️'
        ];
        
        $emoji = $priorityEmojis[$priority] ?? '💰';
        $finalMessage = $emoji . ' ' . $message;
        
        return $this->sendToTelegram($botToken, $chatId, $finalMessage);
    }
    
    /**
     * Send message with inline buttons
     */
    public function sendMessageWithButtons($message, $buttons, $chatId = null, $botToken = null, $priority = 'MEDIUM') {
        $botToken = $botToken ?: $this->defaultBotToken;
        $chatId = $chatId ?: $this->defaultChatId;
        
        if (empty($botToken) || empty($chatId)) {
            error_log("Telegram - Missing bot token or chat ID");
            return false;
        }
        
        $priorityEmojis = [
            'HIGH' => '🚨',
            'MEDIUM' => '🎯',
            'LOW' => 'ℹ️'
        ];
        
        $emoji = $priorityEmojis[$priority] ?? '🎯';
        $finalMessage = $emoji . ' ' . $message;
        
        return $this->sendToTelegram($botToken, $chatId, $finalMessage, $buttons);
    }
    
    /**
     * Send price alert with trading buttons
     */
    public function sendPriceAlert($symbol, $entryType, $targetPrice, $currentPrice, $direction, $marginAmount, $watchlistId, $orderId = null, $chatId = null, $botToken = null) {
        $directionEmoji = $direction === 'long' ? '📈' : '📉';
        $entryTypeFormatted = str_replace('_', ' ', strtoupper($entryType));
        
        $message = "<b>Price Alert Triggered!</b>\n\n" .
                  "{$directionEmoji} <b>{$symbol}</b> ({$entryTypeFormatted})\n" .
                  "🎯 Target: \${$targetPrice}\n" .
                  "💰 Current: \${$currentPrice}\n" .
                  "📊 Direction: " . strtoupper($direction) . "\n" .
                  "💵 Margin: \${$marginAmount}";
        
        // Generate secure tokens for API access
        require_once __DIR__ . '/auth_token.php';
        $baseUrl = $this->getBaseUrl();
        
        $openToken = TokenAuth::generateToken($orderId, 'open_position', 3600); // 1 hour expiry
        $cancelToken = TokenAuth::generateToken($orderId, 'cancel_order', 3600); // 1 hour expiry
        
        $openUrl = $baseUrl . "/api/open_limit_position.php?token=" . urlencode($openToken);
        $cancelUrl = $baseUrl . "/api/cancel_limit_order.php?token=" . urlencode($cancelToken);
        
        // Create inline buttons
        $buttons = [
            [
                [
                    'text' => ($direction === 'long' ? '📈 Open LONG' : '📉 Open SHORT'),
                    'url' => $openUrl
                ],
                [
                    'text' => '🗑️ Ignore/Cancel',
                    'url' => $cancelUrl
                ]
            ]
        ];
        
        return $this->sendMessageWithButtons($message, $buttons, $chatId, $botToken, 'HIGH');
    }
    
    /**
     * Get base URL for API endpoints
     */
    private function getBaseUrl() {
        // You can configure this in environment or use auto-detection
        $baseUrl = getenv('APP_BASE_URL');
        if (!$baseUrl) {
            // Auto-detect based on server configuration
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $protocol . $host . '/trade';
        }
        return rtrim($baseUrl, '/');
    }
    
    /**
     * Send order filled notification
     */
    public function sendOrderFilled($symbol, $side, $quantity, $fillPrice, $leverage, $entryLevel, $chatId = null, $botToken = null) {
        $sideEmoji = ($side === 'BUY') ? '📈' : '📉';
        $entryLevelFormatted = str_replace('_', ' ', strtoupper($entryLevel));
        
        $message = "<b>Order Filled!</b>\n\n" .
                  "{$sideEmoji} <b>{$symbol}</b> ({$entryLevelFormatted})\n" .
                  "💰 Size: \${$quantity}\n" .
                  "💵 Fill Price: \${$fillPrice}\n" .
                  "⚡ Leverage: {$leverage}x\n" .
                  "🎯 Side: " . strtoupper($side);
        
        return $this->sendMessage($message, $chatId, $botToken, 'HIGH');
    }
    
    /**
     * Send P&L milestone notification
     */
    public function sendPnLMilestone($symbol, $side, $type, $milestonePercent, $currentPercent, $pnlAmount, $chatId = null, $botToken = null) {
        $emoji = $type === 'profit' ? '💰' : '📉';
        $direction = $type === 'profit' ? 'PROFIT' : 'LOSS';
        
        $message = "<b>{$direction} Milestone Reached!</b>\n\n" .
                  "{$emoji} <b>{$symbol}</b> (" . strtoupper($side) . ")\n" .
                  "🎯 Milestone: {$milestonePercent}%\n" .
                  "📊 Current P&L: {$currentPercent}%\n" .
                  "💵 P&L Amount: \${$pnlAmount}";
        
        return $this->sendMessage($message, $chatId, $botToken, 'MEDIUM');
    }
    
    /**
     * Send balance change notification
     */
    public function sendBalanceChange($type, $changePercent, $oldTotal, $newTotal, $changeAmount, $chatId = null, $botToken = null) {
        $emoji = $type === 'increase' ? '📈' : '📉';
        $direction = $type === 'increase' ? 'increased' : 'decreased';
        
        $message = "<b>Balance Change Alert</b>\n\n" .
                  "{$emoji} Account balance has {$direction}\n" .
                  "📊 Change: {$changePercent}%\n" .
                  "💰 From: \${$oldTotal}\n" .
                  "💰 To: \${$newTotal}\n" .
                  "📊 Difference: \${$changeAmount}";
        
        return $this->sendMessage($message, $chatId, $botToken, 'MEDIUM');
    }
    
    /**
     * Send order cancelled notification
     */
    public function sendOrderCancelled($symbol, $quantity, $price, $side, $chatId = null, $botToken = null) {
        $message = "<b>Order Cancelled</b>\n\n" .
                  "❌ <b>{$symbol}</b>\n" .
                  "💰 Size: \${$quantity}\n" .
                  "💵 Price: \${$price}\n" .
                  "🎯 Side: " . strtoupper($side);
        
        return $this->sendMessage($message, $chatId, $botToken, 'MEDIUM');
    }
    
    /**
     * Core function to send message to Telegram
     */
    private function sendToTelegram($botToken, $chatId, $message, $buttons = null) {
        try {
            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            $postData = [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML'
            ];
            
            // Add inline keyboard if buttons provided
            if ($buttons) {
                $postData['reply_markup'] = json_encode([
                    'inline_keyboard' => $buttons
                ]);
            }
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $result = json_decode($response, true);
                return $result['ok'] ?? false;
            }
            
            error_log("Telegram API HTTP error: {$httpCode}. Response: " . $response);
            return false;
            
        } catch (Exception $e) {
            error_log("Telegram notification failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Handle callback queries from inline buttons
     */
    public function handleCallback($callbackData, $chatId, $messageId, $botToken = null) {
        $botToken = $botToken ?: $this->defaultBotToken;
        
        try {
            $data = json_decode($callbackData, true);
            
            if (!$data || !isset($data['action'])) {
                return $this->answerCallback($botToken, $callbackData, "Invalid callback data");
            }
            
            switch ($data['action']) {
                case 'open_position':
                    return $this->handleOpenPosition($data, $chatId, $messageId, $botToken);
                    
                case 'remove_watchlist':
                    return $this->handleRemoveWatchlist($data, $chatId, $messageId, $botToken);
                    
                default:
                    return $this->answerCallback($botToken, $callbackData, "Unknown action");
            }
            
        } catch (Exception $e) {
            error_log("Telegram callback handling failed: " . $e->getMessage());
            return $this->answerCallback($botToken, $callbackData, "Error processing request");
        }
    }
    
    /**
     * Handle open position callback
     */
    private function handleOpenPosition($data, $chatId, $messageId, $botToken) {
        // This would integrate with your order placement API
        $symbol = $data['symbol'] ?? '';
        $direction = $data['direction'] ?? '';
        $price = $data['price'] ?? '';
        
        // Call your order placement API
        $orderResult = $this->placeMarketOrder($symbol, $direction, $price);
        
        if ($orderResult['success']) {
            $response = "✅ Market order placed for {$direction} {$symbol} at \${$price}";
            
            // Edit the original message to show order was placed
            $this->editMessage($botToken, $chatId, $messageId, 
                "🎯 <b>Price Alert - ORDER PLACED</b>\n\n" .
                "✅ {$direction} {$symbol} market order placed at \${$price}"
            );
        } else {
            $response = "❌ Failed to place order: " . ($orderResult['error'] ?? 'Unknown error');
        }
        
        return $this->answerCallback($botToken, $data, $response);
    }
    
    /**
     * Handle remove watchlist callback
     */
    private function handleRemoveWatchlist($data, $chatId, $messageId, $botToken) {
        $watchlistId = $data['watchlist_id'] ?? '';
        $symbol = $data['symbol'] ?? '';
        
        // Remove from database
        $removeResult = $this->removeWatchlistItem($watchlistId);
        
        if ($removeResult) {
            $response = "🗑️ Alert removed for {$symbol}";
            
            // Edit the original message
            $this->editMessage($botToken, $chatId, $messageId,
                "🗑️ <b>Price Alert - REMOVED</b>\n\n" .
                "Alert for {$symbol} has been removed from watchlist."
            );
        } else {
            $response = "❌ Failed to remove alert";
        }
        
        return $this->answerCallback($botToken, $data, $response);
    }
    
    /**
     * Answer callback query
     */
    private function answerCallback($botToken, $callbackQueryId, $text) {
        $url = "https://api.telegram.org/bot{$botToken}/answerCallbackQuery";
        $postData = [
            'callback_query_id' => $callbackQueryId,
            'text' => $text
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return $response;
    }
    
    /**
     * Edit an existing message
     */
    private function editMessage($botToken, $chatId, $messageId, $newText) {
        $url = "https://api.telegram.org/bot{$botToken}/editMessageText";
        $postData = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $newText,
            'parse_mode' => 'HTML'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return $response;
    }
    
    /**
     * Place market order (integrate with your existing API)
     */
    private function placeMarketOrder($symbol, $direction, $price) {
        // This should call your existing order placement API
        try {
            // Determine order side
            $side = $direction === 'LONG' ? 'BUY' : 'SELL';
            
            // Create order data (you'll need to adjust this to match your API)
            $orderData = [
                'symbol' => $symbol,
                'side' => $side,
                'type' => 'MARKET',
                'direction' => $direction,
                'entry_level' => 'MARKET'
            ];
            
            // Make API call to your place_order.php endpoint
            $response = file_get_contents('http://localhost/trade/api/place_order.php', false, stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => json_encode($orderData)
                ]
            ]));
            
            $result = json_decode($response, true);
            return $result ?: ['success' => false, 'error' => 'Invalid API response'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Remove watchlist item from database
     */
    private function removeWatchlistItem($watchlistId) {
        try {
            // Load environment variables
            $this->loadEnv();
            
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
     * Load environment variables
     */
    private function loadEnv() {
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
}

/**
 * Quick helper functions for backward compatibility
 */
function sendTelegramMessage($message, $chatId = null, $botToken = null, $priority = 'MEDIUM') {
    $telegram = new TelegramMessenger($botToken, $chatId);
    return $telegram->sendMessage($message, $chatId, $botToken, $priority);
}

function sendTelegramPriceAlert($symbol, $entryType, $targetPrice, $currentPrice, $direction, $marginAmount, $watchlistId, $orderId = null, $chatId = null, $botToken = null) {
    $telegram = new TelegramMessenger($botToken, $chatId);
    return $telegram->sendPriceAlert($symbol, $entryType, $targetPrice, $currentPrice, $direction, $marginAmount, $watchlistId, $orderId, $chatId, $botToken);
}
?>