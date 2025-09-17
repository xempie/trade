<?php

require_once 'env_loader.php';

class TelegramSender {
    private $botToken;
    private $chatId;
    private $enabled;
    private $adminBotToken;
    private $adminChatId;
    private $fvgBotToken;
    private $fvgChatId;
    private $blueBotToken;
    private $blueChatId;
    
    public function __construct() {
        $this->enabled = EnvLoader::getBool('ENABLE_TELEGRAM');
        $this->botToken = EnvLoader::get('TELEGRAM_BOT_TOKEN');
        $this->chatId = EnvLoader::get('TELEGRAM_CHAT_ID');
        $this->adminBotToken = EnvLoader::get('TELEGRAM_BOT_TOKEN_ADMIN');
        $this->adminChatId = EnvLoader::get('TELEGRAM_CHAT_ID_ADMIN');
        $this->fvgBotToken = EnvLoader::get('TELEGRAM_BOT_TOKEN_FVG');
        $this->fvgChatId = EnvLoader::get('TELEGRAM_CHAT_ID_FVG');
        $this->blueBotToken = EnvLoader::get('TELEGRAM_BOT_TOKEN_BLUE');
        $this->blueChatId = EnvLoader::get('TELEGRAM_CHAT_ID_BLUE');

        // Log initialization status
        $this->logDebug("TelegramSender initialized:");
        $this->logDebug("- ENABLE_TELEGRAM: " . ($this->enabled ? 'true' : 'false'));
        $this->logDebug("- Bot Token: " . ($this->botToken ? 'SET' : 'NOT SET'));
        $this->logDebug("- Chat ID: " . ($this->chatId ? 'SET' : 'NOT SET'));
        $this->logDebug("- Admin Bot Token: " . ($this->adminBotToken ? 'SET' : 'NOT SET'));
        $this->logDebug("- Admin Chat ID: " . ($this->adminChatId ? 'SET' : 'NOT SET'));
    }
    
    public function sendMessage($message) {
        $this->logDebug("sendMessage called with message length: " . strlen($message));

        if (!$this->enabled) {
            $this->logDebug("Telegram is DISABLED");
            return ['success' => false, 'message' => 'Telegram disabled'];
        }

        if (empty($this->botToken) || empty($this->chatId)) {
            $this->logDebug("Missing credentials - Bot Token: " . ($this->botToken ? 'SET' : 'NOT SET') . ", Chat ID: " . ($this->chatId ? 'SET' : 'NOT SET'));
            return ['success' => false, 'message' => 'Telegram credentials missing'];
        }

        $this->logDebug("Calling sendTelegramMessage with bot token and chat ID");
        $result = $this->sendTelegramMessage($this->botToken, $this->chatId, $message);
        $this->logDebug("sendMessage result: " . json_encode($result));
        return $result;
    }
    
    public function sendAdminMessage($type, $message, $inlineKeyboard = null) {
        $this->logDebug("sendAdminMessage called with type: $type, message length: " . strlen($message));

        if (!$this->enabled) {
            $this->logDebug("Telegram is DISABLED for admin message");
            return ['success' => false, 'message' => 'Telegram disabled'];
        }

        if ($type=='IN_TREND') {
            $this->logDebug("Sending IN_TREND message to admin channel");
            $result = $this->sendTelegramMessage($this->adminBotToken, $this->adminChatId, $message, $inlineKeyboard);
            $this->logDebug("IN_TREND result: " . json_encode($result));
            return $result;
        } elseif ($type=='ICHIMOKU_BEFORE_CROSS' || $type=='ICHIMOKU_AFTER_CROSS') {
            $this->logDebug("Sending ICHIMOKU message to admin channel");
            $result = $this->sendTelegramMessage($this->adminBotToken, $this->adminChatId, $message, $inlineKeyboard);
            $this->logDebug("ICHIMOKU result: " . json_encode($result));
            return $result;
        } elseif ($type=='UP_TREND') {
            $this->logDebug("Sending UP_TREND message to blue channel");
            $result = $this->sendTelegramMessage($this->blueBotToken, $this->blueChatId, $message, $inlineKeyboard);
            $this->logDebug("UP_TREND result: " . json_encode($result));
            return $result;
        } elseif ($type=='FVG') {
            $this->logDebug("Sending FVG message to FVG channel");
            $result = $this->sendTelegramMessage($this->fvgBotToken, $this->fvgChatId, $message, $inlineKeyboard);
            $this->logDebug("FVG result: " . json_encode($result));
            return $result;
        } else {
            $this->logDebug("Unknown admin message type: $type");
            return ['success' => false, 'message' => "Unknown message type: $type"];
        }
    }
    
    private function sendTelegramMessage($botToken, $chatId, $message, $inlineKeyboard = null) {
        $telegramUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $this->logDebug("Sending to Telegram URL: $telegramUrl");

        $params = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];

        if ($inlineKeyboard !== null) {
            $params['reply_markup'] = json_encode(['inline_keyboard' => $inlineKeyboard]);
        }

        $this->logDebug("Telegram params: " . json_encode($params));

        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($params),
                'timeout' => 10
            ],
        ];

        $context = stream_context_create($options);
        $this->logDebug("Making file_get_contents request to Telegram API...");

        // Use error_get_last() to capture more details about the failure
        $result = @file_get_contents($telegramUrl, false, $context);

        if ($result === false) {
            $error = error_get_last();
            $errorMsg = 'Failed to send telegram message';
            if ($error) {
                $errorMsg .= ': ' . $error['message'];
            }
            $this->logDebug("file_get_contents FAILED: $errorMsg");

            // Try with cURL as fallback
            $this->logDebug("Attempting fallback with cURL...");
            return $this->sendTelegramMessageCurl($botToken, $chatId, $message, $inlineKeyboard);
        }

        $this->logDebug("Raw Telegram response: " . $result);

        $response = json_decode($result, true);
        $finalResult = [
            'success' => $response['ok'] ?? false,
            'message' => $response['description'] ?? 'Unknown response',
            'raw_response' => $response
        ];

        $this->logDebug("Final result: " . json_encode($finalResult));
        return $finalResult;
    }

    private function sendTelegramMessageCurl($botToken, $chatId, $message, $inlineKeyboard = null) {
        $telegramUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";

        $params = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];

        if ($inlineKeyboard !== null) {
            $params['reply_markup'] = json_encode(['inline_keyboard' => $inlineKeyboard]);
        }

        $this->logDebug("Using cURL fallback for Telegram API call");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $telegramUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $this->logDebug("cURL HTTP Code: $httpCode");
        $this->logDebug("cURL Error: " . ($error ?: 'None'));
        $this->logDebug("cURL Response: $result");

        if ($error) {
            return ['success' => false, 'message' => "cURL error: $error"];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'message' => "HTTP error: $httpCode"];
        }

        if (!$result) {
            return ['success' => false, 'message' => 'Empty response from Telegram'];
        }

        $response = json_decode($result, true);

        if (!$response || !$response['ok']) {
            $errorMsg = $response['description'] ?? 'Unknown API error';
            return ['success' => false, 'message' => $errorMsg];
        }

        return [
            'success' => true,
            'message_id' => $response['result']['message_id'],
            'chat_id' => $response['result']['chat']['id']
        ];
    }
    
    public function createMetadataDetails($metadata, $side) {
        if (empty($metadata)) return '';
        
        // Extract and format values first
        $fvgSize = isset($metadata['fvg_size_pct']) ? floatval($metadata['fvg_size_pct']) : 0;
        
        // Handle shadow based on side
        $shadow = 0;
        if ($side === 'SHORT' && isset($metadata['lower_shadow_pct'])) {
            $shadow = floatval($metadata['lower_shadow_pct']);
        } elseif ($side === 'LONG' && isset($metadata['upper_shadow_pct'])) {
            $shadow = floatval($metadata['upper_shadow_pct']);
        }
        
        $distToFvg = isset($metadata['dist_to_fvg_pct']) ? floatval($metadata['dist_to_fvg_pct']) : 0;
        $prevShadow = isset($metadata['prev_shadow_pct']) ? floatval($metadata['prev_shadow_pct']) : 0;
        $liquidity = isset($metadata['ob_size_percent']) ? floatval($metadata['ob_size_percent']) : 0;
        $distToPrev = isset($metadata['dist_to_prev']) ? floatval($metadata['dist_to_prev']) : 0;
        $bodySize = isset($metadata['candle_body_pct']) ? floatval($metadata['candle_body_pct']) : 0;
        
        // Format for display
        $fvgSizeDisplay = $fvgSize > 0 ? $fvgSize . "%" : 'N/A';
        $shadowDisplay = $shadow > 0 ? $shadow . "%" : 'N/A';
        $distToFvgDisplay = $distToFvg > 0 ? $distToFvg . "%" : 'N/A';
        $prevShadowDisplay = $prevShadow > 0 ? $prevShadow . "%" : 'N/A';
        $liquidityDisplay = $liquidity > 0 ? $liquidity . "%" : 'N/A';
        $distToPrevDisplay = $distToPrev > 0 ? $distToPrev : 'N/A';
        $bodySizeDisplay = $bodySize != 0 ? $bodySize . "%" : 'N/A';
        
        $pinbar_pcnt = 0;
        if  ($bodySize > 0) {
            $pinbar_pcnt = $shadow / $bodySize;
        }
        
        // Icon logic
        $side_icon = $side == "LONG" ? "🟩" : "🟥";
        $side_rev_icon = $side == "SHORT" ? "🟩" : "🟥";
        
        $fvgSize_icon = $fvgSize > 2 ? $side_icon : "⬜";
        $bodySize_icon = "⬜";
        $shadow_icon = ($shadow < 1) ? $side_icon : $side_rev_icon;
        $distToFvg_icon = ($distToFvg >= 1 && $shadow > 1) ? $side_rev_icon : "⬜";
        $isPinBar_icon = (intval($pinbar_pcnt) >= 3) ? $side_rev_icon : 
                 ((intval($pinbar_pcnt) < 0.5) ? $side_icon : "⬜");
                 
        $prevShadow_icon = ($prevShadow > $fvgSize) ? $side_rev_icon : "⬜";
        $liquidity_icon = ($liquidity < $fvgSize) ? $side_icon : $side_rev_icon;
        $distToPrev_icon = ($distToPrev < 30) ? $side_icon : $side_rev_icon;
        
        // Build simple format
        $metadataDetails = "\n\n<b>📰 Metadata:</b>\n";
        $metadataDetails .= "====================\n";
        $metadataDetails .= $fvgSize_icon." <b>FVG Size:</b> " . $fvgSizeDisplay  . "\n";
        $metadataDetails .= $bodySize_icon ." <b>Candle Body:</b> " . $bodySizeDisplay . "\n";
        $metadataDetails .= $shadow_icon. " <b>Candle Shadow:</b> " . $shadowDisplay . "\n";
        $metadataDetails .= $isPinBar_icon. " <b>Pinbar Ratio:</b> " . number_format($pinbar_pcnt, 1) . "\n";
        $metadataDetails .= $distToFvg_icon. " <b>Dist to FVG:</b> " . $distToFvgDisplay . "\n";
        $metadataDetails .= $prevShadow_icon ." <b>Prev Shadow:</b> " . $prevShadowDisplay  . "\n";
        $metadataDetails .= $liquidity_icon ." <b>Liquidity:</b> " . $liquidityDisplay . "\n";
        $metadataDetails .= $distToPrev_icon." <b>Dist to Prev:</b> " . $distToPrevDisplay;
        
        return $metadataDetails;
    }
    
    public function createOrderDetails($entry, $stoploss, $target, $quantity, $leverage) {
        // Extract and format values first
        $entryFormatted = "$" . $entry;
        $stoplossFormatted = "$" . $stoploss;
        $targetFormatted = "$" . $target;
        $quantityFormatted = $quantity;
        $leverageFormatted = $leverage . "x";
        
        // Build simple format
        $orderDetails = "\n<b>📊 Order Details:</b>\n";
        $orderDetails .= "====================\n";
        $orderDetails .= "<b>Entry Trigger:</b> " . $entryFormatted . "\n";
        $orderDetails .= "<b>Stop Loss:</b> " . $stoplossFormatted . "\n";
        $orderDetails .= "<b>Take Profit:</b> " . $targetFormatted . "\n";
        $orderDetails .= "<b>Leverage:</b> " . $leverageFormatted;
        
        return $orderDetails;
    }
    
    public function sendErrorNotification($errorMessage, $errorLine) {
        try {
            $message = "🚨 <b>Bot Error Alert</b>\n\n";
            $message .= "📍 <b>Error:</b> " . htmlspecialchars($errorMessage) . "\n";
            $message .= "📄 <b>Line:</b> " . $errorLine . "\n";
            $message .= "⏰ <b>Time:</b> " . date('Y-m-d H:i:s') . "\n";
            
            return $this->sendAdminMessage('IN_TREND',$message);
        } catch (Exception $e) {
            // Log to file if Telegram fails
            error_log("Failed to send Telegram error notification: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function createInlineKeyboard($symbol, $side, $dbId, $entry) {
        // Clean symbol for URL (remove -USDT if present)
        $cleanSymbol = str_replace("-USDT", "", $symbol);
        
        // URL encode parameters to handle special characters
        $encodedSymbol = urlencode($cleanSymbol);
        $encodedSide = urlencode($side);
        $encodedPrice = urlencode($entry);
        
        // Create the inline keyboard with 3 buttons in 2 rows as URL links
        $keyboard = [
            [
                [
                    'text' => '📈 Trigger Order',
                    'url' => "https://your-trading-platform.com/trigger?symbol={$encodedSymbol}&side={$encodedSide}&price={$encodedPrice}"
                ],
                [
                    'text' => '🔄 Reverse Market',
                    'url' => "https://new.kripton.app/helpers/tradeform?symbol={$encodedSymbol}&side={$encodedSide}&price={$encodedPrice}"
                ]
            ],
            [
                [
                    'text' => '👁️ Add Watch',
                    'url' => "https://academit.com.au/bot/watchprice.php?symbol={$encodedSymbol}&side={$encodedSide}&price={$encodedPrice}"
                ]
            ]
        ];
        
        return $keyboard;
    }

    
    public function sendOrderPlacedMessage($orderData) {
        $mode = $orderData['mode'] ?? 'UNKNOWN';
        $symbol = $orderData['symbol'] ?? 'N/A';
        $side = $orderData['side'] ?? 'N/A';
        $setup = $orderData['setup'] ?? 'Unknown';
        $timeframe = $orderData['timeframe'] ?? 'N/A';
        $orderId = $orderData['order_id'] ?? 'N/A';
        $dbId = $orderData['db_id'] ?? 'N/A';
        $entry = $orderData['entry'] ?? 0;
        $stoploss = $orderData['stoploss'] ?? 0;
        $target = $orderData['target'] ?? 0;
        $quantity = $orderData['quantity'] ?? 0;
        $leverage = $orderData['leverage'] ?? 0;
        $marginUsed = $orderData['margin_used'] ?? 0;
        $positionValue = $orderData['position_value'] ?? 0;
        $metadata = $orderData['metadata'] ?? [];
        
        // Build order details
        $orderDetails = $this->createOrderDetails($entry, $stoploss, $target, $quantity, $leverage);
        
        // Build metadata details
        $metadataDetails = $this->createMetadataDetails($metadata, $side);
        
        $message = "<b>💡 Initial Raw Signal [{$dbId}] </b>\n\n" .
          "<b>Symbol:</b> " . str_replace("-USDT", "", $symbol) . "\n" .
          "<b>Side:</b> " . $side . " " . ($side == "LONG" ? "🟩" : "🟥") . "\n" .
          "<b>Setup:</b> " . $setup . "\n" .
          "<b>Timeframe:</b> " . $timeframe . " min\n" .
          $orderDetails .
          $metadataDetails;
        
        // Send to regular channel
        //$regularResult = $this->sendMessage($message);
        
        // Send to admin channel with buttons
        $inlineKeyboard = $this->createInlineKeyboard($symbol, $side, $dbId, $entry);
        //$adminResult = $this->sendAdminMessage($message, $inlineKeyboard);
        
        return [
            //'regular' => $regularResult//,
            //'admin' => $adminResult
        ];
    }
    
    private function fvgProgressBar(string $levels): string {
        $levels = strtoupper($levels);
    
        // Bars
        $bar1 = "🟧🟧⬜⬜⬜⬜⬜⬜"; // only "H"
        $bar02 = "⬜⬜🟨🟨⬜⬜⬜⬜"; // "M" 
        $bar2 = "🟧🟧🟨🟨⬜⬜⬜⬜"; // "HM" 
        $bar03 = "️⬜⬜⬜⬜🟩🟩⬜⬜"; // "L"
        $bar3 = "🟧🟧🟨🟨🟩🟩⬜⬜"; // HML, ML, L (as specified)
        $bar4 = "🟧🟧🟨🟨🟩🟩🟦🟪"; // > 3 characters
    
        if (strlen($levels) > 3) {
            return $bar4;
        }
        if ($levels === 'H') {
            return $bar1;
        }
        if ($levels === 'M') {
            return $bar02;
        }
        if ($levels== 'HM') {
            return $bar2;
        }
        if ($levels === 'L') {
            return $bar03;
        }
        if (in_array($levels, ['HML', 'ML'], true)) {
            return $bar3;
        }
    
        // Fallback (optional): infer by unique chars
        $uniq = count(array_unique(str_split(preg_replace('/[^HML]/', '', $levels))));
        return $uniq >= 3 ? $bar3 : ($uniq === 2 ? $bar2 : $bar1);
    }


    public function sendHitCrossAlert($symbol, $side, $levels, $prices) {

        $bars = $this->fvgProgressBar($levels);
        $formatted_prices = str_replace(' | ', "\n", $prices);
        $level_depth = str_replace(['H', 'M', 'L'], ['25%, ', '50%, ', '75%, '], $levels);
        $level_depth = rtrim($level_depth, ', ');
        $emoji = ($side === 'LONG') ? '🟩' : '🟥';

        $message = "<b>💥 FVG Price Hit Alert</b>\n\n" .
                  "<b>Symbol:</b> " . $symbol . "\n" .
                  "<b>Side:</b> " . $side . ' ' . $emoji . "\n\n" .
                  "<b>FVG Hit Depth:</b> " . $level_depth . "\n\n" .
                  "<b>Triggers:</b> " . $formatted_prices . "\n\n" .
                  "<i>Consider 5-minute timeframe</i>". "\n\n" .
                  $bars. "\n";
        
        return $this->sendMessage($message);
    }
    
    public function sendBaselineAlert($symbol, $side, $price) {

        $formatted_prices = str_replace(' | ', "\n", $price);

        $emoji = ($side === 'LONG') ? '🟩' : '🟥';

        $message = "<b>⚔️️🤞 Cross Pattern Hit Alert</b>\n\n" .
                  "<b>Symbol:</b> " . $symbol . "\n" .
                  "<b>Side:</b> " . $side . ' ' . $emoji . "\n\n" .
                  "<b>Entry:</b> " . $formatted_prices . "\n\n" ;
        return $this->sendMessage($message);
    }
    
    public function sendIchiAlert($symbol, $side, $price, $type) {

        $formatted_prices = str_replace(' | ', "\n", $price);

        $emoji = ($side === 'LONG') ? '🟩' : '🟥';
        $exp = ($type === 'ICHIMOKU_AFTER_CROSS') ? 'Cross Passed' : 'Cross Ahead';

        $message = "<b>🔀 ICHI Cross Alert</b>\n\n" .
                  "<b>Symbol:</b> " . $symbol . "\n" .
                  "<b>Side:</b> " . $side . ' ' . $emoji . "\n\n" .
                  "<b>Type:</b> " . $exp . "\n\n" .
                  "<b>Entry:</b> " . $formatted_prices . "\n\n" ;
        return $this->sendAdminMessage($type, $message);
    }
    
    public function sendFVGAlert($symbol, $side, $type, $metadata) {

        $emoji = ($side === 'LONG') ? '🟩' : '🟥';
        
        if ($type=="FVGTOUCH") {
            $message = "<b>♒ FVG Box Touched</b>\n\n";
        } else {
            $message = "<b>🔀 LNL Cross Signal</b>\n\n";
        }                
                  $message .= "====================" . "\n" .
                  "<b>Symbol:</b> " . $symbol . "\n" .
                  "<b>Side:</b> " . $side . ' ' . $emoji . "\n\n" .
                  "<b>Entry:</b> $" . $metadata['entry'] . "\n" ;
                  "<b>Cross Since:</b> " . $metadata['cross_bars_ago'] . " Bars ago\n" ;
                  "<b>T3 Distance:</b> " . $metadata['t3_distance'] . "%\n" ;
                  "<b>T3 Lines Status:</b> " . $metadata['t3_lines'] . "\n" ;
        return $this->sendAdminMessage('FVG', $message);
    }
    
    public function sendAdaptiveAlert($type, $symbol, $side, $entry, $candle_size, $distance_to_t3, $candle_position, $distance_to_trend_start, $t3_status, $t3_distance, $t3_strength, $t3_squeeze, $conv_bars, $div_bars) {
       
       $side_icon = $side=="LONG"?'🟩' : '🟥';
       $symbol = str_replace("-USDT","",$symbol);
       $trend_start = intval($distance_to_trend_start)==0?true:false;
       $trend_icon =$trend_start?"💠":"";
       $message = "🚨 SSL/RSI ADAPTIVE ALERT\n\n";
       $message .= "Symbol: <b>$symbol</b>\n";
       $message .= "Side: $side ".$side_icon."\n";
       $message .= "Entry: $entry\n\n";
       $message .= "📊 Candle Data:\n";
       $message .="====================\n";
       $message .= "Size: $candle_size\n";
       $message .= "Distance to T3: $distance_to_t3\n";
       $message .= "Position: $candle_position\n";
       $message .= "Trend Start: $distance_to_trend_start" . " candles ago " . $trend_icon . "\n\n";
       /*
       $message .= "🔄 T3 Convergence:\n";
       $message .="====================\n";
       $message .= "Status: $t3_status\n";
       $message .= "Distance: $t3_distance\n";
       $message .= "Strength: $t3_strength\n";
       $message .= "Squeeze: $t3_squeeze\n";
       $message .= "Conv Bars: $conv_bars\n";
       $message .= "Div Bars: $div_bars\n\n";*/
       
       //$message .=$this->analyzeSignalSituation($t3_status, $t3_squeeze, $conv_bars, $div_bars, $candle_position, $distance_to_t3, $t3_strength, $side, $distance_to_trend_start);
       if (true) {
            return $this->sendAdminMessage($type,$message);
       } else {
           
       }
    }
    
    public function analyzeSignalSituation($t3_status, $t3_squeeze, $conv_bars, $div_bars, $candle_position, $distance_to_t3, $t3_strength, $side, $distance_to_trend_start) {
   
       $analysis = "";
       
       // بررسی فاصله تا شروع ترند
       $trend_distance = (int)str_replace(['_candles', 'candles', '_bars', 'bars', 's'], '', $distance_to_trend_start);
       if ($trend_distance > 30) {
           $analysis .= "⚠️ هشدار: $trend_distance کندل از شروع ترند گذشته - ترند ممکن است ضعیف شده یا در حال برگشت باشد!\n";
           $analysis .= "🔄 احتمال تغییر جهت ترند وجود دارد - احتیاط کنید\n\n";
       } elseif ($trend_distance > 20) {
           $analysis .= "📊 $trend_distance کندل از شروع ترند گذشته - ترند در مراحل میانی\n\n";
       } elseif ($trend_distance > 10) {
           $analysis .= "✅ $trend_distance کندل از شروع ترند گذشته - ترند هنوز جوان است\n\n";
       } else {
           $analysis .= "🆕 $trend_distance کندل از شروع ترند گذشته - ترند تازه و قوی\n\n";
       }
       
       // بررسی وضعیت فشردگی T3
       if ($t3_squeeze == "true") {
           $analysis .= "⚠️ خطوط T3 در حالت فشردگی قرار دارند - احتمال شکست قوی وجود دارد!\n\n";
       }
       
       // بررسی وضعیت همگرایی/واگرایی
       if ($t3_status == "converging") {
           if ($conv_bars >= 10) {
               $analysis .= "🔥 خطوط T3 بیش از $conv_bars کندل در حال نزدیک شدن هستند - انرژی زیادی ذخیره شده!\n";
           } elseif ($conv_bars >= 5) {
               $analysis .= "📈 خطوط T3 از $conv_bars کندل پیش شروع به نزدیک شدن کرده‌اند - آماده شکست\n";
           } else {
               $analysis .= "🔄 خطوط T3 تازه شروع به نزدیک شدن کرده‌اند\n";
           }
       } elseif ($t3_status == "diverging") {
           if ($div_bars >= 3) {
               $analysis .= "🚀 خطوط T3 از $div_bars کندل پیش در حال دور شدن هستند - ترند قوی در جریان!\n";
           } else {
               $analysis .= "📊 خطوط T3 تازه شروع به دور شدن کرده‌اند - ترند تازه شکل گرفته\n";
           }
       } else {
           $analysis .= "➡️ خطوط T3 موازی هستند - بازار در حالت تعادل\n";
       }
       
       // بررسی قدرت همگرایی/واگرایی
       $strength_value = (float)str_replace('%', '', $t3_strength);
       if ($strength_value > 0.05) {
           $analysis .= "⚡ قدرت حرکت خطوط بالاست ($t3_strength) - حرکت سریع در جریان\n";
       } elseif ($strength_value > 0.02) {
           $analysis .= "📈 قدرت حرکت متوسط ($t3_strength) - حرکت معمولی\n";
       } else {
           $analysis .= "🐌 قدرت حرکت کم ($t3_strength) - حرکت آهسته\n";
       }
       
       // بررسی موقعیت کندل
       if ($candle_position == "crossing_t3") {
           $analysis .= "🎯 کندل در حال عبور از خط T3 است - نقطه مهم!\n";
       } elseif ($candle_position == "above_t3") {
           $analysis .= "📈 کندل بالای خط T3 قرار دارد - موقعیت صعودی\n";
       } else {
           $analysis .= "📉 کندل زیر خط T3 قرار دارد - موقعیت نزولی\n";
       }
       
       // بررسی فاصله تا T3
       $distance_value = (float)str_replace('%', '', $distance_to_t3);
       if (abs($distance_value) < 0.5) {
           $analysis .= "🎯 قیمت خیلی نزدیک خط T3 است ($distance_to_t3) - حساسیت بالا\n";
       } elseif (abs($distance_value) > 2) {
           $analysis .= "📏 قیمت دور از خط T3 است ($distance_to_t3) - حرکت قوی\n";
       }
       
       // تحلیل کلی بر اساس سمت سیگنال
       $analysis .= "\n💡 تحلیل کلی:\n";
       if ($side == "LONG") {
           if ($trend_distance > 30) {
               $analysis .= "⚠️ سیگنال خرید اما ترند قدیمی است - ریسک بالا!";
           } elseif ($t3_squeeze == "true" && $t3_status == "converging") {
               $analysis .= "🚀 سیگنال خرید در شرایط فشردگی - احتمال حرکت صعودی قوی!";
           } elseif ($t3_status == "diverging" && $div_bars >= 2) {
               $analysis .= "📈 سیگنال خرید در ادامه ترند صعودی - ورود مناسب";
           } else {
               $analysis .= "🔵 سیگنال خرید - مراقب تایید باشید";
           }
       } elseif ($side == "SHORT") {
           if ($trend_distance > 30) {
               $analysis .= "⚠️ سیگنال فروش اما ترند قدیمی است - ریسک بالا!";
           } elseif ($t3_squeeze == "true" && $t3_status == "converging") {
               $analysis .= "📉 سیگنال فروش در شرایط فشردگی - احتمال حرکت نزولی قوی!";
           } elseif ($t3_status == "diverging" && $div_bars >= 2) {
               $analysis .= "🔻 سیگنال فروش در ادامه ترند نزولی - ورود مناسب";
           } else {
               $analysis .= "🔴 سیگنال فروش - مراقب تایید باشید";
           }
       }
       
       return $analysis;
    }

    /**
     * Send trading signal alert to Telegram
     */
    public function sendTradingSignalAlert($symbol, $side, $entries, $targets, $stopLoss, $leverage) {
        $emoji = ($side === "LONG") ? "🟩" : "🟥";
        $cleanSymbol = str_replace("-USDT", "", $symbol);

        $message = "<b>🚨 TRADING SIGNAL ALERT</b>\n\n";
        $message .= "<b>Symbol:</b> {$cleanSymbol}\n";
        $message .= "<b>Side:</b> {$side} {$emoji}\n";
        $message .= "<b>Leverage:</b> {$leverage}x\n\n";

        // Entry points
        $message .= "<b>📊 Entry Points:</b>\n";
        if (!empty($entries["entry_market"])) {
            $message .= "Market: $" . number_format($entries["entry_market"], 2) . "\n";
        }
        if (!empty($entries["entry_2"])) {
            $message .= "Entry 2: $" . number_format($entries["entry_2"], 2) . "\n";
        }
        if (!empty($entries["entry_3"])) {
            $message .= "Entry 3: $" . number_format($entries["entry_3"], 2) . "\n";
        }

        // Targets
        $message .= "\n<b>🎯 Targets:</b>\n";
        for ($i = 1; $i <= 5; $i++) {
            $targetKey = "take_profit_{$i}";
            if (!empty($targets[$targetKey])) {
                $message .= "TP{$i}: $" . number_format($targets[$targetKey], 2) . "\n";
            }
        }

        // Stop Loss
        $message .= "\n<b>🛑 Stop Loss:</b> $" . number_format($stopLoss, 2) . "\n";
        $message .= "\n⏰ <i>" . date("Y-m-d H:i:s") . " UTC</i>";

        return $this->sendMessage($message);
    }

    /**
     * Debug logging method
     */
    private function logDebug($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [TELEGRAM] $message\n";
        file_put_contents(__DIR__ . '/telegram_debug.log', $logEntry, FILE_APPEND | LOCK_EX);
    }
}

// Standalone function for backward compatibility
function sendTelegramMessage($message) {
    $telegram = new TelegramSender();
    $result = $telegram->sendMessage($message);
    return $result['success'];
}

?>