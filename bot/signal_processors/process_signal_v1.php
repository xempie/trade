<?php

header('Content-Type: application/json');

// Include the env_loader.php file
require_once '../env_loader.php';

// Database connection
function getDbConnection() {
    $host = EnvLoader::get('DB_HOST');
    $name = EnvLoader::get('DB_NAME');
    $user = EnvLoader::get('DB_USER');
    $pass = EnvLoader::get('DB_PASS');
    
    try {
        $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}

// Save trigger order to database
function saveTriggerOrder($pdo, $orderData) {
    $sql = "INSERT INTO trigger_orders 
            (order_id, symbol, side, timeframe, position_side, quantity, trigger_price, current_price, 
             stop_loss_price, take_profit_price, leverage, status, created_at) 
            VALUES 
            (:order_id, :symbol, :side, :timeframe, :position_side, :quantity, :trigger_price, :current_price,
             :stop_loss_price, :take_profit_price, :leverage, 'NEW', NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($orderData);
    
    return $pdo->lastInsertId();
}

// Log activity
function logActivity($pdo, $triggerOrderId, $action, $message, $apiResponse = null) {
    if (!EnvLoader::getBool('ENABLE_LOGGING')) {
        return;
    }
    
    $sql = "INSERT INTO order_logs (trigger_order_id, action, message, api_response, created_at) 
            VALUES (:trigger_order_id, :action, :message, :api_response, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'trigger_order_id' => $triggerOrderId,
        'action' => $action,
        'message' => $message,
        'api_response' => $apiResponse ? json_encode($apiResponse) : null
    ]);
}

// Global API request function for BingX
function apiRequest(string $method, string $path, array $params = []): array {
    $API_KEY = EnvLoader::get('BINGX_API_KEY');
    $API_SECRET = EnvLoader::get('BINGX_API_SECRET');
    $BASE_URL = EnvLoader::get('BINGX_BASE_URL');
    
    logMessage('debug_log.txt', "apiRequest called: Method=$method, Path=$path");
    logMessage('debug_log.txt', "API params: " . json_encode($params, JSON_PRETTY_PRINT));
    
    $params['timestamp'] = round(microtime(true) * 1000);
    $params['recvWindow'] = EnvLoader::getInt('API_RECV_WINDOW', 5000);

    ksort($params, SORT_STRING);
    $qs = http_build_query($params);
    $sign = hash_hmac('sha256', $qs, $API_SECRET);

    $url = $BASE_URL . $path . '?' . $qs . '&signature=' . $sign;
    
    logMessage('debug_log.txt', "Request URL: $url");

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, EnvLoader::getInt('API_TIMEOUT', 30));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-BX-APIKEY: ' . $API_KEY]);
    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
    }

    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) {
        throw new Exception('cURL error: ' . curl_error($ch));
    }
    curl_close($ch);

    $body = json_decode($res, true);
    if (!is_array($body)) {
        throw new Exception('Invalid JSON response');
    }
    
    logMessage('debug_log.txt', "API Response: HTTP $httpCode - " . json_encode($body, JSON_PRETTY_PRINT));

    return ['httpCode' => $httpCode, 'body' => $body];
}

class BingXTrader {
    private $apiKey;
    private $secretKey;
    private $baseUrl;
    private $demoMode;
    private $demoMargin;
    private $paperTrading;
    
    public function __construct() {
        $this->apiKey = EnvLoader::get('BINGX_API_KEY');
        $this->secretKey = EnvLoader::get('BINGX_API_SECRET');
        $this->baseUrl = EnvLoader::get('BINGX_BASE_URL');
        $this->demoMode = EnvLoader::getBool('DEMO_MODE');
        $this->demoMargin = EnvLoader::getFloat('DEMO_MARGIN');
        $this->paperTrading = EnvLoader::getBool('PAPER_TRADING');
        
        // Debug: Log the demo margin value
        logMessage('debug_log.txt', "BingXTrader constructor: DEMO_MARGIN loaded as: '{$this->demoMargin}'");
        logMessage('debug_log.txt', "Raw DEMO_MARGIN from EnvLoader: '" . EnvLoader::get('DEMO_MARGIN') . "'");
        
        if (empty($this->apiKey) || empty($this->secretKey)) {
            throw new Exception('BingX API credentials not found in .env file');
        }
        
        if ($this->demoMargin <= 0) {
            logMessage('debug_log.txt', "WARNING: DEMO_MARGIN is zero or not set properly: {$this->demoMargin}");
        }
    }
    
    // Generate signature for BingX API
    private function generateSignature($params, $timestamp) {
        $params['timestamp'] = $timestamp;
        $params['recvWindow'] = EnvLoader::getInt('API_RECV_WINDOW', 5000);
        
        ksort($params);
        $queryString = http_build_query($params);
        
        return hash_hmac('sha256', $queryString, $this->secretKey);
    }
    
    // Make API request to BingX
    private function makeRequest(string $endpoint, array $params = [], string $method = 'POST'): array {
        $timestamp = round(microtime(true) * 1000);
        
        logMessage('debug_log.txt', "makeRequest called: Endpoint=$endpoint, Method=$method, Timestamp=$timestamp");
        logMessage('debug_log.txt', "Input parameters: " . json_encode($params, JSON_PRETTY_PRINT));
        
        // Generate signature
        $signature = $this->generateSignature($params, $timestamp);
        logMessage('debug_log.txt', "Generated signature: $signature");
        
        // Add timestamp and signature to params
        $params['timestamp'] = $timestamp;
        $params['recvWindow'] = EnvLoader::getInt('API_RECV_WINDOW', 5000);
        $params['signature'] = $signature;
        
        // Build URL and headers
        $url = $this->baseUrl . $endpoint;
        $headers = [
            'X-BX-APIKEY: ' . $this->apiKey,
            'Content-Type: application/x-www-form-urlencoded'
        ];
        
        if ($this->paperTrading) {
            $headers[] = 'X-BX-PAPERTRADING: true';
            logMessage('debug_log.txt', "Paper trading header added");
        }
        
        logMessage('debug_log.txt', "Request URL: $url");
        logMessage('debug_log.txt', "Request headers: " . json_encode($headers));
        logMessage('debug_log.txt', "Final parameters: " . json_encode($params, JSON_PRETTY_PRINT));
        
        // cURL setup
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => EnvLoader::getInt('API_TIMEOUT', 30),
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        
        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            logMessage('debug_log.txt', "POST data: " . http_build_query($params));
        } else {
            $fullUrl = $url . '?' . http_build_query($params);
            curl_setopt($ch, CURLOPT_URL, $fullUrl);
            logMessage('debug_log.txt', "GET URL: $fullUrl");
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        logMessage('debug_log.txt', "cURL response: HTTP Code=$httpCode, Response=$response");
        
        if ($curlError) {
            logMessage('debug_log.txt', "cURL Error: $curlError");
            throw new Exception('cURL Error: ' . $curlError);
        }
        curl_close($ch);
        
        $decoded = json_decode($response, true);
        logMessage('debug_log.txt', "Decoded response: " . json_encode($decoded, JSON_PRETTY_PRINT));
        
        if ($httpCode !== 200) {
            logMessage('debug_log.txt', "HTTP Error: Code=$httpCode, Response=$response");
            throw new Exception("BingX API Error (HTTP $httpCode): $response");
        }
        
        return ['httpCode' => $httpCode, 'body' => $decoded];
    }
    
    // Set leverage for symbol
    public function setLeverage($symbol, $leverage) {
        try {
            $params = [
                'symbol' => $symbol,
                'leverage' => $leverage,
                'side' => 'BOTH' // Required parameter for BingX leverage API
            ];
            
            // Use the global apiRequest function
            return apiRequest('POST', '/openApi/swap/v2/trade/leverage', $params);
        } catch (Exception $e) {
            throw new Exception('Failed to set leverage: ' . $e->getMessage());
        }
    }
    
    // Place trigger order
    public function placeTriggerOrder($symbol, $side, $quantity, $triggerPrice, $leverage) {
        logMessage('debug_log.txt', "placeTriggerOrder called with: Symbol=$symbol, Side=$side, Quantity=$quantity, TriggerPrice=$triggerPrice, Leverage=$leverage");
        
        if ($this->paperTrading) {
            logMessage('debug_log.txt', "Paper trading mode - simulating order");
            // Simulate successful order placement
            $orderId = 'PAPER_' . time() . '_' . rand(1000, 9999);
            
            return [
                'httpCode' => 200,
                'body' => [
                    'code' => 0,
                    'msg' => 'Simulated successful order placement',
                    'data' => [
                        'order' => [
                            'orderId' => $orderId,
                            'symbol' => $symbol,
                            'status' => 'NEW',
                            'quantity' => $quantity,
                            'stopPrice' => $triggerPrice,
                            'type' => 'TRIGGER_MARKET',
                            'side' => $side,
                            'positionSide' => $side === 'BUY' ? 'LONG' : 'SHORT'
                        ]
                    ]
                ]
            ];
        }
        
        try {
            // First, let's check if the symbol exists by getting contract info
            logMessage('debug_log.txt', "Checking if contract exists for symbol: $symbol");
            try {
                $contractInfo = apiRequest('GET', '/openApi/swap/v2/quote/contracts', ['symbol' => $symbol]);
                logMessage('debug_log.txt', "Contract info: " . json_encode($contractInfo, JSON_PRETTY_PRINT));
            } catch (Exception $e) {
                logMessage('debug_log.txt', "Contract info check failed: " . $e->getMessage());
                
                // Try getting all available contracts to see what's available
                try {
                    $allContracts = apiRequest('GET', '/openApi/swap/v2/quote/contracts', []);
                    logMessage('debug_log.txt', "Available contracts: " . json_encode($allContracts, JSON_PRETTY_PRINT));
                } catch (Exception $e2) {
                    logMessage('debug_log.txt', "Failed to get available contracts: " . $e2->getMessage());
                }
            }
            
            logMessage('debug_log.txt', "Setting leverage to $leverage for $symbol");
            // Set leverage first
            $leverageResult = $this->setLeverage($symbol, $leverage);
            logMessage('debug_log.txt', "Leverage result: " . json_encode($leverageResult, JSON_PRETTY_PRINT));
            
            // Main trigger order parameters
            $orderParams = [
                'symbol' => $symbol,
                'side' => $side, // 'BUY' or 'SELL'
                'positionSide' => $side === 'BUY' ? 'LONG' : 'SHORT',
                'type' => 'TRIGGER_MARKET',
                'quantity' => $quantity,
                'stopPrice' => $triggerPrice,
                'timeInForce' => 'GTC',
                'workingType' => 'MARK_PRICE'
            ];
            
            logMessage('debug_log.txt', "Order parameters: " . json_encode($orderParams, JSON_PRETTY_PRINT));
            
            // REAL TRADING: Send order to exchange using the global apiRequest function
            logMessage('debug_log.txt', 'Sending order to exchange');
            
            $entry = apiRequest('POST', '/openApi/swap/v2/trade/order', $orderParams);
            
            if (($entry['body']['code'] ?? -1) !== 0) {
                throw new Exception('Exchange order placement failed: ' . ($entry['body']['msg'] ?? 'Unknown error'));
            }
            
            // Extract order ID from exchange response
            $orderId = $entry['body']['data']['order']['orderId']
                     ?? $entry['body']['data']['orderId']
                     ?? $entry['body']['data']['order']['orderID']
                     ?? null;
                     
            if (!$orderId) {
                throw new Exception('No orderId found in exchange response. Response: ' . json_encode($entry['body']));
            }
            
            logMessage('debug_log.txt', "Order successfully placed with ID: $orderId");
            
            return $entry;
            
        } catch (Exception $e) {
            logMessage('debug_log.txt', "Exception in placeTriggerOrder: " . $e->getMessage());
            throw new Exception('Failed to place trigger order: ' . $e->getMessage());
        }
    }
    
    // Calculate position size based on risk management
        public function calculatePositionSize($entryPrice, $stopLossPrice, $leverage) {
        $margin = $this->demoMargin;
    
        // Validate inputs
        if ($entryPrice <= 0 || $stopLossPrice <= 0 || $margin <= 0) {
            logMessage('debug_log.txt', "ERROR: Invalid input values for position size calculation. Entry=$entryPrice, StopLoss=$stopLossPrice, Margin=$margin");
            return 0;
        }
    
        // Calculate price distance
        $priceDistance = abs($entryPrice - $stopLossPrice);
        if ($priceDistance == 0) {
            logMessage('debug_log.txt', "ERROR: Price distance is zero (Entry=$entryPrice, StopLoss=$stopLossPrice)");
            return 0;
        }
    
        // Use full margin and leverage to get position value
        $positionValue = $margin * $leverage;
    
        // Quantity = position value / entry price
        $quantity = $positionValue / $entryPrice;
    
        // Debug log
        logMessage('debug_log.txt', "CALC: margin=$margin, leverage=$leverage, entryPrice=$entryPrice, stopLoss=$stopLossPrice, positionValue=$positionValue, quantity=$quantity");
    
        return round($quantity, 6);
    }

    
    // Format symbol for BingX API
    public function formatSymbol($symbol) {
        logMessage('debug_log.txt', "formatSymbol input: $symbol");
        
        // Handle symbols that already have hyphens
        if (strpos($symbol, '-USDT') !== false) {
            // Already properly formatted, just remove any BINGX prefix
            $cleanSymbol = str_replace('BINGX', '', $symbol);
            $cleanSymbol = str_replace('.P', '', $cleanSymbol);
            logMessage('debug_log.txt', "formatSymbol output (already formatted): $cleanSymbol");
            return $cleanSymbol;
        }
        
        // Format symbol for BingX - Remove extra parts and ensure proper format
        $cleanSymbol = str_replace('BINGX', '', $symbol); // Remove BingX prefix
        $cleanSymbol = str_replace('.P', '', $cleanSymbol);      // Remove .P suffix
        $cleanSymbol = str_replace('USDT', '-USDT', $cleanSymbol); // Add hyphen
        
        logMessage('debug_log.txt', "formatSymbol output: $cleanSymbol");
        return $cleanSymbol;
    }
    
    public function getDemoMargin() {
        return $this->demoMargin;
    }
    
    public function isDemoMode() {
        return $this->demoMode;
    }
    
    public function isPaperTrading() {
        return $this->paperTrading;
    }
}

// Utility functions
function sendTelegramMessage($message) {
    if (!EnvLoader::getBool('ENABLE_TELEGRAM')) {
        return;
    }
    
    $botToken = EnvLoader::get('TELEGRAM_BOT_TOKEN');
    $chatId = EnvLoader::get('TELEGRAM_CHAT_ID');
    
    if (empty($botToken) || empty($chatId)) {
        return;
    }
    
    $telegramUrl = "https://api.telegram.org/bot$botToken/sendMessage";
    $params = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($params),
        ],
    ];
    
    $context = stream_context_create($options);
    return file_get_contents($telegramUrl, false, $context);
}

function logMessage($filename, $message) {
    if (EnvLoader::getBool('ENABLE_LOGGING')) {
        file_put_contents($filename, date('Y-m-d H:i:s') . " - " . $message . "\n\n", FILE_APPEND);
    }
}

function logError($message) {
    $logFile = EnvLoader::get('LOG_FILE', __DIR__ . '/errors.log');
    $timestamp = date('Y-m-d H:i:s');
    $fullMessage = "[$timestamp] $message\n";
    
    file_put_contents($logFile, $fullMessage, FILE_APPEND | LOCK_EX);
}

// -------------------- Main Processing --------------------

try {
    // Get JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Debug: Check if .env file exists and is readable
    $envPath = __DIR__ . '/.env';
    logMessage('debug_log.txt', "Checking .env file at: $envPath");
    logMessage('debug_log.txt', ".env file exists: " . (file_exists($envPath) ? 'YES' : 'NO'));
    if (file_exists($envPath)) {
        logMessage('debug_log.txt', ".env file is readable: " . (is_readable($envPath) ? 'YES' : 'NO'));
        logMessage('debug_log.txt', ".env file size: " . filesize($envPath) . " bytes");
    }
    
    // Debug: Check what EnvLoader is actually loading
    logMessage('debug_log.txt', "All loaded env vars: " . json_encode(EnvLoader::getAll(), JSON_PRETTY_PRINT));
    
    // Enhanced debugging - log everything
    $debugLog = [
        'timestamp' => date('Y-m-d H:i:s'),
        'raw_input' => $json,
        'parsed_data' => $data,
        'json_decode_error' => json_last_error_msg(),
        'content_length' => strlen($json),
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unknown'
    ];
    
    logMessage('debug_log.txt', "=== WEBHOOK DEBUG START ===\n" . json_encode($debugLog, JSON_PRETTY_PRINT) . "\n=== END ===\n");
    
    // Log incoming data
    logMessage('bingx_signals_log.json', "INCOMING:\n" . json_encode($data, JSON_PRETTY_PRINT));
    
    // Validate required fields
    if (!$data) {
        logMessage('debug_log.txt', "ERROR: No data received or JSON decode failed");
        throw new Exception('No data received or invalid JSON');
    }
    
    if (!isset($data['symbol']) || !isset($data['side']) || !isset($data['type'])) {
        logMessage('debug_log.txt', "ERROR: Missing required fields. Available fields: " . implode(', ', array_keys($data)));
        throw new Exception('Missing required fields (symbol, side, type)');
    }
    
    $symbol = $data['symbol'];
    $side = strtoupper($data['side']);
    $type = $data['type'];
    
    logMessage('debug_log.txt', "Processing signal: Symbol=$symbol, Side=$side, Type=$type");
    
    $pdo = getDbConnection();
    logMessage('debug_log.txt', "Database connection established");
    
    $trader = new BingXTrader();
    logMessage('debug_log.txt', "BingXTrader initialized. Demo mode: " . ($trader->isDemoMode() ? 'true' : 'false') . ", Paper trading: " . ($trader->isPaperTrading() ? 'true' : 'false'));
    
    if ($type === 'FVG') {
        logMessage('debug_log.txt', "Processing FVG signal");
        
        // Extract FVG signal data
        $entry = floatval($data['entry']);
        $trigger = floatval($data['trigger']);
        $stoploss = floatval($data['stoploss']); 
        $target = floatval($data['target']);
        $leverage = intval($data['leverage']);
        $setup = $data['setup'] ?? 'Unknown';
        $timeframe = $data['timeframe'] ?? '0';
        
        logMessage('debug_log.txt', "Signal data: Entry=$entry, StopLoss=$stoploss, Target=$target, Leverage=$leverage");
        
        // Validate numeric values
        if ($entry <= 0 || $stoploss <= 0 || $target <= 0 || $leverage <= 0) {
            logMessage('debug_log.txt', "ERROR: Invalid values - Entry=$entry, StopLoss=$stoploss, Target=$target, Leverage=$leverage");
            throw new Exception("Invalid price values or leverage");
        }
        
        // Format symbol for BingX
        $bingxSymbol = $trader->formatSymbol($symbol);
        logMessage('debug_log.txt', "Symbol formatted: $symbol -> $bingxSymbol");
        
        // Calculate position size
        $quantity = $trader->calculatePositionSize($entry, $stoploss, $leverage);
        logMessage('debug_log.txt', "Position size calculated: $quantity (Entry=$entry, StopLoss=$stoploss, Leverage=$leverage)");
        
        // Check if quantity is valid
        if ($quantity <= 0) {
            logMessage('debug_log.txt', "ERROR: Invalid quantity calculated: $quantity");
            throw new Exception("Invalid position size calculated: $quantity. Check entry price, leverage, and demo margin settings.");
        }
        
        // Convert side to BingX format
        $bingxSide = $side === 'LONG' ? 'BUY' : 'SELL';
        $positionSide = $side; // Keep as LONG/SHORT for database
        logMessage('debug_log.txt', "Side conversion: $side -> $bingxSide");
        
        // Place trigger order on exchange
        logMessage('debug_log.txt', "Placing trigger order on exchange...");
        $orderResult = $trader->placeTriggerOrder(
            $bingxSymbol,
            $bingxSide, 
            $quantity,
            $entry,
            $leverage
        );
        
        logMessage('debug_log.txt', "Order result: " . json_encode($orderResult, JSON_PRETTY_PRINT));
        
        // Check if order was successful
        if (($orderResult['body']['code'] ?? -1) !== 0) {
            throw new Exception('Exchange order placement failed: ' . ($orderResult['body']['msg'] ?? 'Unknown error'));
        }
        
        // Extract order ID from exchange response
        $orderId = $orderResult['body']['data']['order']['orderId'] 
                 ?? $orderResult['body']['data']['orderId'] 
                 ?? null;

        if (!$orderId) {
            throw new Exception('No orderId found in exchange response');
        }
        
        // Save to database
        $orderData = [
            'order_id' => $orderId,
            'symbol' => $bingxSymbol,
            'side' => $bingxSide,
            'timeframe' =>$timeframe,
            'position_side' => $positionSide,
            'quantity' => $quantity,
            'trigger_price' => $entry,
            'current_price' => $entry,
            'stop_loss_price' => $stoploss,
            'take_profit_price' => $target,
            'leverage' => $leverage
        ];

        $dbId = saveTriggerOrder($pdo, $orderData);
        
        if (!$dbId) {
            throw new Exception("Failed to save order to database");
        }
        
        // Log the activity
        logActivity($pdo, $dbId, 'ORDER_PLACED', 'FVG trigger order placed successfully', $orderResult['body']);

        // Calculate risk metrics for display
        $marginUsed = $trader->getDemoMargin();
        $positionValue = $quantity * $entry;
        
        // Send Telegram notification
        $mode = $trader->isPaperTrading() ? 'PAPER' : ($trader->isDemoMode() ? 'VST' : 'LIVE');
        $telegramMsg = "<b>✅ BingX FVG Order Placed [{$mode}]</b>\n\n" .
                      "Symbol: <b>$symbol</b>\n" .
                      "Side: <b>$side</b>\n" .
                      "Setup: <b>$setup</b>\n" .
                      "Timeframe: <b>$timeframe</b>\n\n" .
                      "📊 <b>Order Details:</b>\n" .
                      "Order ID: <code>$orderId</code>\n" .
                      "DB ID: <code>$dbId</code>\n\n" .
                      "Entry Trigger: <code>$entry</code>\n" .
                      "Stop Loss: <code>$stoploss</code>\n" .
                      "Take Profit: <code>$target</code>\n" .
                      "Quantity: <code>$quantity</code>\n" .
                      "Leverage: <b>{$leverage}x</b>\n\n" .
                      "💰 <b>Position Info:</b>\n" .
                      "Demo Margin: <code>$marginUsed</code>\n" .
                      "Position Value: <code>$" . round($positionValue, 2) . "</code>";
        
        sendTelegramMessage($telegramMsg);
        
        // Return success response
        echo json_encode([
            'success' => true,
            'mode' => $mode,
            'database_id' => $dbId,
            'order_id' => $orderId,
            'symbol' => $bingxSymbol,
            'trigger_price' => $entry,
            'quantity' => $quantity,
            'stop_loss_price' => $stoploss,
            'take_profit_price' => $target,
            'leverage' => $leverage,
            'message' => 'FVG trigger order successfully placed and saved to database',
            'status' => 'NEW'
        ], JSON_PRETTY_PRINT);
        
    } elseif ($type === 'FVG_MITIGATE') {
        // Handle mitigation - log and notify only
        $telegramMsg = "<b>⚠️ FVG Mitigation Alert</b>\n\n" .
                      "Symbol: <b>$symbol</b>\n" .
                      "Side: <b>$side</b>\n\n" .
                      "💡 <i>Consider closing BingX position manually</i>";
        
        sendTelegramMessage($telegramMsg);
        
        echo json_encode([
            'success' => true,
            'message' => 'FVG mitigation notification sent',
            'type' => 'MITIGATION'
        ]);
    } else {
        throw new Exception("Unsupported signal type: $type");
    }
    
} catch (Exception $e) {
    // Enhanced error logging
    $errorDetails = [
        'timestamp' => date('Y-m-d H:i:s'),
        'error_message' => $e->getMessage(),
        'error_line' => $e->getLine(),
        'error_file' => $e->getFile(),
        'stack_trace' => $e->getTraceAsString(),
        'input_data' => $data ?? 'No data',
        'env_vars' => [
            'BINGX_API_KEY' => EnvLoader::get('BINGX_API_KEY') ? 'SET' : 'NOT SET',
            'BINGX_API_SECRET' => EnvLoader::get('BINGX_API_SECRET') ? 'SET' : 'NOT SET',
            'BINGX_BASE_URL' => EnvLoader::get('BINGX_BASE_URL'),
            'DEMO_MODE' => EnvLoader::get('DEMO_MODE'),
            'PAPER_TRADING' => EnvLoader::get('PAPER_TRADING'),
            'DEMO_MARGIN' => EnvLoader::get('DEMO_MARGIN'),
        ]
    ];
    
    logMessage('debug_log.txt', "=== ERROR DETAILS ===\n" . json_encode($errorDetails, JSON_PRETTY_PRINT) . "\n=== END ERROR ===");
    
    // Log error
    logError("Trading error: " . $e->getMessage());
    
    // Send error notification
    $telegramMsg = "<b>❌ BingX Trading Error</b>\n\n" .
                  "Error: <code>" . $e->getMessage() . "</code>\n" .
                  "Line: " . $e->getLine();
    
    sendTelegramMessage($telegramMsg);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => $errorDetails
    ], JSON_PRETTY_PRINT);
}

?>