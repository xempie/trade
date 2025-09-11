<?php
/**
 * Signal Automation Cronjob
 * Processes PENDING signals and manages entry2 triggers for FILLED signals
 * 
 * Cron setup example: * * * * * /usr/bin/php /path/to/signal_automation.php >> /path/to/logs/signal_automation.log 2>&1
 */

// Include necessary files
require_once __DIR__ . '/api/api_helper.php';

// Load environment variables
if (!function_exists('loadEnv')) {
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
}

// Load .env file
loadEnv(__DIR__ . '/.env');

// Database connection
function getDbConnection() {
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: '';
    $password = getenv('DB_PASSWORD') ?: '';
    $database = getenv('DB_NAME') ?: 'crypto_trading';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        logMessage("Database connection failed: " . $e->getMessage());
        return null;
    }
}

// Logging function
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message\n";
    error_log("[$timestamp] Signal Automation: $message");
}

// Get current BingX price
function getCurrentPrice($symbol) {
    try {
        $baseUrl = getBingXApiUrl();
        $publicUrl = $baseUrl . "/openApi/swap/v2/quote/price";
        $params = ['symbol' => $symbol];
        
        $queryString = http_build_query($params);
        $url = $publicUrl . '?' . $queryString;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpCode == 200) {
            $data = json_decode($response, true);
            
            if ($data && $data['code'] == 0 && isset($data['data']['price'])) {
                return floatval($data['data']['price']);
            }
        }
        
        return null;
    } catch (Exception $e) {
        logMessage("Error getting price for $symbol: " . $e->getMessage());
        return null;
    }
}

// Get account balance (available margin)
function getAccountBalance() {
    try {
        $apiKey = getenv('BINGX_API_KEY') ?: '';
        $apiSecret = getenv('BINGX_SECRET_KEY') ?: '';
        
        if (empty($apiKey) || empty($apiSecret)) {
            logMessage("API credentials not configured");
            return 0;
        }
        
        $timestamp = round(microtime(true) * 1000);
        $queryString = "timestamp={$timestamp}";
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        
        $baseUrl = getBingXApiUrl();
        $url = $baseUrl . "/openApi/swap/v2/user/balance?" . $queryString . "&signature=" . $signature;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-BX-APIKEY: ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpCode == 200) {
            $data = json_decode($response, true);
            if ($data && $data['code'] == 0 && isset($data['data'])) {
                // Check new format first
                if (isset($data['data']['balance'])) {
                    $balance = $data['data']['balance'];
                    $asset = $balance['asset'] ?? '';
                    if ($asset === 'USDT' || $asset === 'VST') {
                        return floatval($balance['availableMargin'] ?? $balance['balance'] ?? 0);
                    }
                }
                
                // Legacy format - array of balances
                foreach ($data['data'] as $balance) {
                    if (isset($balance['asset']) && ($balance['asset'] === 'USDT' || $balance['asset'] === 'VST')) {
                        return floatval($balance['availableMargin'] ?? $balance['available'] ?? 0);
                    }
                }
            }
        }
        return 0;
    } catch (Exception $e) {
        logMessage("Error getting account balance: " . $e->getMessage());
        return 0;
    }
}

// Get total assets (total balance including used margin)
function getTotalAssets() {
    try {
        $apiKey = getenv('BINGX_API_KEY') ?: '';
        $apiSecret = getenv('BINGX_SECRET_KEY') ?: '';
        
        if (empty($apiKey) || empty($apiSecret)) {
            logMessage("API credentials not configured");
            return 0;
        }
        
        $timestamp = round(microtime(true) * 1000);
        $queryString = "timestamp={$timestamp}";
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        
        $baseUrl = getBingXApiUrl();
        $url = $baseUrl . "/openApi/swap/v2/user/balance?" . $queryString . "&signature=" . $signature;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-BX-APIKEY: ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpCode == 200) {
            $data = json_decode($response, true);
            if ($data && $data['code'] == 0 && isset($data['data'])) {
                // Check new format first
                if (isset($data['data']['balance'])) {
                    $balance = $data['data']['balance'];
                    $asset = $balance['asset'] ?? '';
                    if ($asset === 'USDT' || $asset === 'VST') {
                        // Use total balance (available + used margin)
                        return floatval($balance['balance'] ?? 0);
                    }
                }
                
                // Legacy format - array of balances
                foreach ($data['data'] as $balance) {
                    if (isset($balance['asset']) && ($balance['asset'] === 'USDT' || $balance['asset'] === 'VST')) {
                        // Use total balance (available + used)
                        return floatval($balance['balance'] ?? $balance['total'] ?? 0);
                    }
                }
            }
        }
        return 0;
    } catch (Exception $e) {
        logMessage("Error getting total assets: " . $e->getMessage());
        return 0;
    }
}

// Get automation setting from database
function getAutomationSetting($pdo, $key, $default = null) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value, data_type FROM signal_automation_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return $default;
        }
        
        $value = $result['setting_value'];
        $type = $result['data_type'];
        
        switch ($type) {
            case 'BOOLEAN':
                return strtolower($value) === 'true';
            case 'INTEGER':
                return (int) $value;
            case 'DECIMAL':
                return (float) $value;
            case 'JSON':
                return json_decode($value, true);
            default:
                return $value;
        }
    } catch (Exception $e) {
        logMessage("Failed to get setting $key: " . $e->getMessage());
        return $default;
    }
}

// Calculate position size using minimum of 5% total assets and AUTO_MARGIN_PER_ENTRY setting
function calculatePositionSize($pdo, $currentPrice, $leverage = 1) {
    // Get AUTO_MARGIN_PER_ENTRY setting from database (this is the margin amount in USD)
    $autoMarginPerEntry = getAutomationSetting($pdo, 'AUTO_MARGIN_PER_ENTRY', 50.00);
    
    // Get total assets and calculate 5%
    $totalAssets = getTotalAssets();
    $fivePercentOfAssets = $totalAssets * 0.05;
    
    // Use minimum of the two values (this is the margin amount in USD)
    $marginAmount = min($autoMarginPerEntry, $fivePercentOfAssets);
    
    // Calculate actual quantity based on margin amount, price, and leverage
    // Formula: quantity = (margin_amount * leverage) / current_price
    $quantity = ($marginAmount * $leverage) / $currentPrice;
    
    logMessage("Position sizing calculation: AUTO_MARGIN_PER_ENTRY=$autoMarginPerEntry, Total Assets=$totalAssets, 5% of Assets=$fivePercentOfAssets, Final Margin Amount=$marginAmount");
    logMessage("Quantity calculation: Margin=$marginAmount, Leverage=$leverage, Price=$currentPrice, Final Quantity=$quantity");
    
    return floatval($quantity);
}

// Place order on BingX
function placeOrder($symbol, $side, $quantity, $leverage = 1, $signalType = 'LONG') {
    try {
        $apiKey = getenv('BINGX_API_KEY') ?: '';
        $apiSecret = getenv('BINGX_SECRET_KEY') ?: '';
        
        if (empty($apiKey) || empty($apiSecret)) {
            logMessage("API credentials not configured");
            return ['success' => false, 'error' => 'API credentials not configured'];
        }
        
        $timestamp = round(microtime(true) * 1000);
        
        // Set positionSide based on signal type
        $positionSide = ($signalType === 'LONG') ? 'LONG' : 'SHORT';
        
        $params = [
            'symbol' => $symbol,
            'side' => $side, // BUY for LONG, SELL for SHORT
            'type' => 'MARKET',
            'quantity' => $quantity,
            'positionSide' => $positionSide, // LONG or SHORT based on signal type
            'timestamp' => $timestamp
        ];
        
        // Set leverage first
        if ($leverage > 1) {
            $leverageSet = setLeverage($symbol, $leverage);
            if ($leverageSet) {
                // Verify actual leverage set by exchange
                usleep(500000); // Wait 0.5s for leverage to be applied
                $actualLeverage = getCurrentLeverage($symbol);
                if ($actualLeverage !== null && $actualLeverage !== $leverage) {
                    logMessage("⚠️  WARNING: Requested leverage {$leverage}x but BingX set {$actualLeverage}x for {$symbol}. Exchange may have maximum leverage limits.");
                    // Log this for analysis - don't change the leverage variable as it affects position calculations
                } else if ($actualLeverage === $leverage) {
                    logMessage("✅ Leverage confirmed: {$leverage}x successfully set for {$symbol}");
                }
            }
        }
        
        $queryString = http_build_query($params);
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        
        $baseUrl = getBingXApiUrl();
        $url = $baseUrl . "/openApi/swap/v2/trade/order";
        
        $postData = $queryString . "&signature=" . $signature;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'X-BX-APIKEY: ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpCode == 200) {
            $data = json_decode($response, true);
            
            if ($data && $data['code'] == 0) {
                return [
                    'success' => true,
                    'orderId' => $data['data']['orderId'] ?? '',
                    'data' => $data['data']
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $data['msg'] ?? 'Unknown API error',
                    'code' => $data['code'] ?? 0
                ];
            }
        }
        
        return ['success' => false, 'error' => 'HTTP request failed'];
        
    } catch (Exception $e) {
        logMessage("Error placing order: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Get current leverage for symbol from BingX
function getCurrentLeverage($symbol) {
    try {
        $apiKey = getenv('BINGX_API_KEY') ?: '';
        $apiSecret = getenv('BINGX_SECRET_KEY') ?: '';
        
        $timestamp = round(microtime(true) * 1000);
        $queryString = "symbol={$symbol}&timestamp={$timestamp}";
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        
        $baseUrl = getBingXApiUrl();
        $url = $baseUrl . "/openApi/swap/v2/user/leverage?" . $queryString . "&signature=" . $signature;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-BX-APIKEY: ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpCode == 200) {
            $data = json_decode($response, true);
            if ($data && $data['code'] == 0 && isset($data['data']['leverage'])) {
                return intval($data['data']['leverage']);
            }
        }
        
        return null;
    } catch (Exception $e) {
        logMessage("Error getting current leverage: " . $e->getMessage());
        return null;
    }
}

// Set leverage for symbol
function setLeverage($symbol, $leverage) {
    try {
        $apiKey = getenv('BINGX_API_KEY') ?: '';
        $apiSecret = getenv('BINGX_SECRET_KEY') ?: '';
        
        $timestamp = round(microtime(true) * 1000);
        $params = [
            'symbol' => $symbol,
            'leverage' => $leverage,
            'timestamp' => $timestamp
        ];
        
        $queryString = http_build_query($params);
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        
        $baseUrl = getBingXApiUrl();
        $url = $baseUrl . "/openApi/swap/v2/trade/leverage";
        
        $postData = $queryString . "&signature=" . $signature;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'X-BX-APIKEY: ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Log the leverage setting response for debugging
        logMessage("Leverage setting response for {$symbol} to {$leverage}x: HTTP {$httpCode}, Response: " . substr($response, 0, 300));
        
        if ($response && $httpCode == 200) {
            $data = json_decode($response, true);
            if ($data && $data['code'] == 0) {
                logMessage("✅ Successfully set leverage for {$symbol} to {$leverage}x");
                return true;
            } else {
                $errorMsg = isset($data['msg']) ? $data['msg'] : 'Unknown API error';
                logMessage("❌ Failed to set leverage for {$symbol}: " . $errorMsg);
                return false;
            }
        }
        
        logMessage("❌ HTTP request failed for setting leverage: HTTP {$httpCode}");
        return false;
    } catch (Exception $e) {
        logMessage("Error setting leverage: " . $e->getMessage());
        return false;
    }
}

// Process PENDING signals
function processPendingSignals($pdo) {
    try {
        // Get all PENDING signals
        $stmt = $pdo->prepare("SELECT * FROM signals WHERE status = 'PENDING' AND signal_status = 'ACTIVE'");
        $stmt->execute();
        $pendingSignals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        logMessage("Found " . count($pendingSignals) . " PENDING signals to process");
        
        foreach ($pendingSignals as $signal) {
            logMessage("Processing signal ID: " . $signal['id'] . " for " . $signal['symbol']);
            
            // Get current price
            $currentPrice = getCurrentPrice($signal['symbol']);
            if ($currentPrice === null) {
                logMessage("Could not get current price for " . $signal['symbol']);
                continue;
            }
            
            $entryPrice = floatval($signal['entry_market_price']);
            $shouldTrigger = false;
            
            // Check trigger conditions
            if ($signal['signal_type'] === 'LONG') {
                // For LONG: trigger when current price >= entry price
                $shouldTrigger = $currentPrice >= $entryPrice;
                logMessage("LONG signal: Current=$currentPrice, Entry=$entryPrice, Trigger=" . ($shouldTrigger ? 'YES' : 'NO'));
            } elseif ($signal['signal_type'] === 'SHORT') {
                // For SHORT: trigger when current price <= entry price
                $shouldTrigger = $currentPrice <= $entryPrice;
                logMessage("SHORT signal: Current=$currentPrice, Entry=$entryPrice, Trigger=" . ($shouldTrigger ? 'YES' : 'NO'));
            }
            
            if ($shouldTrigger) {
                // Calculate position size from settings
                $positionSize = calculatePositionSize($pdo, $currentPrice, $signal['leverage']);
                $side = $signal['signal_type'] === 'LONG' ? 'BUY' : 'SELL';
                
                logMessage("Placing order: Symbol=" . $signal['symbol'] . ", Side=$side, Quantity=$positionSize, Leverage=" . $signal['leverage']);
                
                // Place order
                $orderResult = placeOrder($signal['symbol'], $side, $positionSize, $signal['leverage'], $signal['signal_type']);
                
                if ($orderResult['success']) {
                    // Place stop loss and take profit orders on BingX for 100% of the position first
                    $slTpResults = placeStopLossAndTakeProfit(
                        $signal['symbol'], 
                        $signal['signal_type'], 
                        $positionSize, 
                        $currentPrice, 
                        $signal['leverage']
                    );
                    
                    // Extract SL/TP order IDs and prices for database storage
                    $stopLossOrderId = null;
                    $takeProfitOrderId = null;
                    $stopLossPrice = null;
                    $takeProfitPrice = null;
                    
                    if ($slTpResults['stopLoss'] && $slTpResults['stopLoss']['success']) {
                        $stopLossOrderId = $slTpResults['stopLoss']['orderId'];
                        // Calculate stop loss price (2% from entry)
                        $stopLossPercent = 0.02;
                        if ($signal['signal_type'] === 'LONG') {
                            $stopLossPrice = $currentPrice * (1 - $stopLossPercent);
                        } else {
                            $stopLossPrice = $currentPrice * (1 + $stopLossPercent);
                        }
                    }
                    
                    if ($slTpResults['takeProfit'] && $slTpResults['takeProfit']['success']) {
                        $takeProfitOrderId = $slTpResults['takeProfit']['orderId'];
                        // Calculate take profit price (5% from entry)
                        $takeProfitPercent = 0.05;
                        if ($signal['signal_type'] === 'LONG') {
                            $takeProfitPrice = $currentPrice * (1 + $takeProfitPercent);
                        } else {
                            $takeProfitPrice = $currentPrice * (1 - $takeProfitPercent);
                        }
                    }
                    
                    // Create order record in database with SL/TP order IDs
                    $bingxOrderId = $orderResult['orderId'] ?? '';
                    $orderId = createOrderRecord(
                        $pdo, 
                        $signal['id'], 
                        $bingxOrderId, 
                        $signal['symbol'], 
                        $side, 
                        'MARKET', 
                        $positionSize, 
                        $currentPrice, 
                        $signal['leverage'], 
                        'FILLED',
                        $stopLossOrderId,
                        $takeProfitOrderId,
                        $stopLossPrice,
                        $takeProfitPrice
                    );
                    
                    // Create position record in positions table
                    $positionId = createPositionRecord(
                        $pdo, 
                        $signal['id'], 
                        $signal['symbol'], 
                        $signal['signal_type'], 
                        $positionSize, 
                        $currentPrice, 
                        $signal['leverage']
                    );
                    
                    // Update signal status to FILLED
                    $updateStmt = $pdo->prepare("UPDATE signals SET status = 'FILLED', updated_at = NOW() WHERE id = ?");
                    $updateStmt->execute([$signal['id']]);
                    
                    logMessage("Successfully placed order for signal ID: " . $signal['id'] . ", BingX Order ID: " . $bingxOrderId . ", DB Order ID: " . $orderId . ", Position ID: " . $positionId);
                    
                    // Create limit orders for entry2 and entry3 if they exist (database only)
                    $limitOrdersCreated = createLimitOrderRecords($pdo, $signal);
                    logMessage("Created $limitOrdersCreated limit order records for signal ID: " . $signal['id']);
                    
                    $slTpText = "";
                    if ($slTpResults['stopLoss'] && $slTpResults['stopLoss']['success']) {
                        $slTpText .= "\nStop Loss Order: " . $slTpResults['stopLoss']['orderId'];
                    }
                    if ($slTpResults['takeProfit'] && $slTpResults['takeProfit']['success']) {
                        $slTpText .= "\nTake Profit Order: " . $slTpResults['takeProfit']['orderId'];
                    }
                    
                    // Send Telegram notification if configured
                    $limitOrderText = $limitOrdersCreated > 0 ? "\nLimit Orders Created: $limitOrdersCreated" : "";
                    sendTelegramNotification("🎯 Signal Triggered!\n\nSymbol: " . $signal['symbol'] . "\nDirection: " . $signal['signal_type'] . "\nEntry Price: $entryPrice\nCurrent Price: $currentPrice\nQuantity: " . number_format($positionSize, 4) . "\nLeverage: " . $signal['leverage'] . "x\nOrder ID: " . $bingxOrderId . $limitOrderText . $slTpText);
                    
                } else {
                    logMessage("Failed to place order for signal ID: " . $signal['id'] . " - " . $orderResult['error']);
                    
                    // Create failed order record
                    createOrderRecord(
                        $pdo, 
                        $signal['id'], 
                        '', 
                        $signal['symbol'], 
                        $side, 
                        'MARKET', 
                        $positionSize, 
                        $currentPrice, 
                        $signal['leverage'], 
                        'FAILED'
                    );
                }
            }
        }
        
    } catch (Exception $e) {
        logMessage("Error processing pending signals: " . $e->getMessage());
    }
}

// Process FILLED signals for entry2 triggers - DISABLED
function processFilledSignals($pdo) {
    logMessage("Entry 2 processing is DISABLED - skipping entry2 triggers");
    return; // Exit early to prevent entry 2 processing
    try {
        // Get all FILLED signals that have entry_2 price set and haven't triggered entry2 yet
        $stmt = $pdo->prepare("SELECT * FROM signals WHERE status = 'FILLED' AND signal_status = 'ACTIVE' AND entry_2 IS NOT NULL AND entry_2 > 0");
        $stmt->execute();
        $filledSignals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        logMessage("Found " . count($filledSignals) . " FILLED signals to check for entry2");
        
        foreach ($filledSignals as $signal) {
            logMessage("Checking entry2 for signal ID: " . $signal['id'] . " (" . $signal['symbol'] . ")");
            
            // Get current price
            $currentPrice = getCurrentPrice($signal['symbol']);
            if ($currentPrice === null) {
                logMessage("Could not get current price for " . $signal['symbol']);
                continue;
            }
            
            $entry2Price = floatval($signal['entry_2']);
            $shouldTriggerEntry2 = false;
            
            // Check entry2 trigger conditions
            if ($signal['signal_type'] === 'LONG') {
                // For LONG: trigger entry2 when current price <= entry2 price
                $shouldTriggerEntry2 = $currentPrice <= $entry2Price;
                logMessage("LONG entry2: Current=$currentPrice, Entry2=$entry2Price, Trigger=" . ($shouldTriggerEntry2 ? 'YES' : 'NO'));
            } elseif ($signal['signal_type'] === 'SHORT') {
                // For SHORT: trigger entry2 when current price >= entry2 price
                $shouldTriggerEntry2 = $currentPrice >= $entry2Price;
                logMessage("SHORT entry2: Current=$currentPrice, Entry2=$entry2Price, Trigger=" . ($shouldTriggerEntry2 ? 'YES' : 'NO'));
            }
            
            if ($shouldTriggerEntry2) {
                // Calculate position size from settings
                $positionSize = calculatePositionSize($pdo, $currentPrice, $signal['leverage']);
                $side = $signal['signal_type'] === 'LONG' ? 'BUY' : 'SELL';
                
                logMessage("Placing entry2 order: Symbol=" . $signal['symbol'] . ", Side=$side, Quantity=$positionSize, Leverage=" . $signal['leverage']);
                
                // Place order
                $orderResult = placeOrder($signal['symbol'], $side, $positionSize, $signal['leverage'], $signal['signal_type']);
                
                if ($orderResult['success']) {
                    // Create order record in database
                    $bingxOrderId = $orderResult['orderId'] ?? '';
                    $orderId = createOrderRecord(
                        $pdo, 
                        $signal['id'], 
                        $bingxOrderId, 
                        $signal['symbol'], 
                        $side, 
                        'ENTRY_2', 
                        $positionSize, 
                        $currentPrice, 
                        $signal['leverage'], 
                        'FILLED'
                    );
                    
                    // Update signal status to ENTRY2
                    $updateStmt = $pdo->prepare("UPDATE signals SET status = 'ENTRY2', updated_at = NOW() WHERE id = ?");
                    $updateStmt->execute([$signal['id']]);
                    
                    logMessage("Successfully placed entry2 order for signal ID: " . $signal['id'] . ", BingX Order ID: " . $bingxOrderId . ", DB Order ID: " . $orderId);
                    
                    // Send Telegram notification if configured
                    sendTelegramNotification("📈 Entry2 Triggered!\n\nSymbol: " . $signal['symbol'] . "\nDirection: " . $signal['signal_type'] . "\nEntry2 Price: $entry2Price\nCurrent Price: $currentPrice\nQuantity: " . number_format($positionSize, 4) . "\nLeverage: " . $signal['leverage'] . "x\nOrder ID: " . $bingxOrderId);
                    
                } else {
                    logMessage("Failed to place entry2 order for signal ID: " . $signal['id'] . " - " . $orderResult['error']);
                    
                    // Create failed order record
                    createOrderRecord(
                        $pdo, 
                        $signal['id'], 
                        '', 
                        $signal['symbol'], 
                        $side, 
                        'ENTRY_2', 
                        $positionSize, 
                        $currentPrice, 
                        $signal['leverage'], 
                        'FAILED'
                    );
                }
            }
        }
        
    } catch (Exception $e) {
        logMessage("Error processing filled signals: " . $e->getMessage());
    }
}

// NOTE: placeLimitOrder function removed - no longer placing limit orders on BingX
// All limit orders are now database-only records for internal tracking

// Place stop loss and take profit orders on BingX
function placeStopLossAndTakeProfit($symbol, $positionSide, $quantity, $entryPrice, $leverage = 1) {
    try {
        logMessage("Placing stop loss and take profit for Symbol: $symbol, Side: $positionSide, Quantity: $quantity, Entry: $entryPrice");
        
        // Calculate stop loss and take profit prices
        $stopLossPercent = 0.02; // 2% stop loss
        $takeProfitPercent = 0.05; // 5% take profit
        
        if ($positionSide === 'LONG') {
            $stopLossPrice = $entryPrice * (1 - $stopLossPercent);
            $takeProfitPrice = $entryPrice * (1 + $takeProfitPercent);
            $stopLossSide = 'SELL';
            $takeProfitSide = 'SELL';
        } else { // SHORT
            $stopLossPrice = $entryPrice * (1 + $stopLossPercent);
            $takeProfitPrice = $entryPrice * (1 - $takeProfitPercent);
            $stopLossSide = 'BUY';
            $takeProfitSide = 'BUY';
        }
        
        logMessage("Calculated prices - Stop Loss: $stopLossPrice, Take Profit: $takeProfitPrice");
        
        $results = ['stopLoss' => null, 'takeProfit' => null];
        
        // Place stop loss order
        $stopLossResult = placeStopOrder($symbol, $stopLossSide, $quantity, $stopLossPrice, $leverage, $positionSide);
        if ($stopLossResult['success']) {
            logMessage("Stop loss order placed successfully: " . $stopLossResult['orderId']);
            $results['stopLoss'] = $stopLossResult;
        } else {
            logMessage("Failed to place stop loss order: " . $stopLossResult['error']);
        }
        
        // Skip take profit order placement on BingX (database tracking only)
        logMessage("Skipping take profit order placement on BingX - using database tracking only");
        $results['takeProfit'] = [
            'success' => false,
            'orderId' => null,
            'note' => 'Database tracking only, no BingX order placed'
        ];
        
        return $results;
        
    } catch (Exception $e) {
        logMessage("Error placing stop loss and take profit: " . $e->getMessage());
        return ['stopLoss' => null, 'takeProfit' => null];
    }
}

// Place stop order on BingX
function placeStopOrder($symbol, $side, $quantity, $stopPrice, $leverage = 1, $positionSide = 'LONG') {
    try {
        $apiKey = getenv('BINGX_API_KEY') ?: '';
        $apiSecret = getenv('BINGX_SECRET_KEY') ?: '';
        
        if (empty($apiKey) || empty($apiSecret)) {
            logMessage("API credentials not configured");
            return ['success' => false, 'error' => 'API credentials not configured'];
        }
        
        $timestamp = round(microtime(true) * 1000);
        
        $params = [
            'symbol' => $symbol,
            'side' => $side,
            'type' => 'STOP_MARKET',
            'quantity' => $quantity,
            'stopPrice' => $stopPrice,
            'positionSide' => $positionSide,
            'timeInForce' => 'GTC',
            'timestamp' => $timestamp
        ];
        
        // Set leverage first
        if ($leverage > 1) {
            setLeverage($symbol, $leverage);
        }
        
        $queryString = http_build_query($params);
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        
        $baseUrl = getBingXApiUrl();
        $url = $baseUrl . "/openApi/swap/v2/trade/order";
        
        $postData = $queryString . "&signature=" . $signature;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'X-BX-APIKEY: ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpCode == 200) {
            $data = json_decode($response, true);
            
            if ($data && $data['code'] == 0) {
                return [
                    'success' => true,
                    'orderId' => $data['data']['orderId'] ?? '',
                    'data' => $data['data']
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $data['msg'] ?? 'Unknown API error',
                    'code' => $data['code'] ?? 0
                ];
            }
        }
        
        return ['success' => false, 'error' => 'HTTP request failed'];
        
    } catch (Exception $e) {
        logMessage("Error placing stop order: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Create database-only limit order records for entry2/entry3 levels
function createLimitOrderRecords($pdo, $signal) {
    $limitOrdersCreated = 0;
    $side = $signal['signal_type'] === 'LONG' ? 'BUY' : 'SELL';
    
    // Create entry2 limit order record (database only) - DISABLED
    if (!empty($signal['entry_2']) && $signal['entry_2'] > 0) {
        logMessage("Entry 2 processing is DISABLED - skipping ENTRY_2 limit order record creation");
        // Disabled - no longer creating entry 2 records
    }
    
    // Create entry3 limit order record (database only) - DISABLED
    if (!empty($signal['entry_3']) && $signal['entry_3'] > 0) {
        logMessage("Entry 3 processing is DISABLED - skipping ENTRY_3 limit order record creation");
        // Disabled - no longer creating entry 3 records
    }
    
    return $limitOrdersCreated;
}

// Create position record in positions table
function createPositionRecord($pdo, $signalId, $symbol, $side, $quantity, $entryPrice, $leverage) {
    try {
        $isDemo = isDemoMode();
        
        // Calculate margin used (quantity * entryPrice / leverage)
        $positionValue = $quantity * $entryPrice;
        $marginUsed = $positionValue / $leverage;
        
        $sql = "INSERT INTO positions (
            signal_id, symbol, side, size, entry_price, leverage,
            margin_used, status, is_demo, opened_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'OPEN', ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $signalId,
            $symbol,
            $side,
            $quantity,
            $entryPrice,
            $leverage,
            $marginUsed,
            $isDemo
        ]);
        
        if ($result) {
            $positionId = $pdo->lastInsertId();
            logMessage("Created position record ID: $positionId for signal ID: $signalId (Side: $side, Size: $quantity, Entry: $entryPrice, Margin: $marginUsed)");
            return $positionId;
        } else {
            logMessage("Failed to create position record for signal ID: $signalId");
            return false;
        }
        
    } catch (Exception $e) {
        logMessage("Error creating position record: " . $e->getMessage());
        return false;
    }
}

// Update position when additional entries are filled
function updatePositionWithAdditionalEntry($pdo, $signalId, $additionalQuantity, $additionalEntryPrice, $additionalLeverage) {
    try {
        // Get existing position for this signal
        $stmt = $pdo->prepare("SELECT * FROM positions WHERE signal_id = ? AND status = 'OPEN' ORDER BY opened_at DESC LIMIT 1");
        $stmt->execute([$signalId]);
        $position = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$position) {
            logMessage("No existing position found for signal ID: $signalId, creating new position");
            return createPositionRecord($pdo, $signalId, '', '', $additionalQuantity, $additionalEntryPrice, $additionalLeverage);
        }
        
        // Calculate new average entry price and total size
        $existingSize = floatval($position['size']);
        $existingEntryPrice = floatval($position['entry_price']);
        $existingLeverage = intval($position['leverage']);
        
        $totalSize = $existingSize + $additionalQuantity;
        $totalValue = ($existingSize * $existingEntryPrice) + ($additionalQuantity * $additionalEntryPrice);
        $avgEntryPrice = $totalValue / $totalSize;
        
        // Use higher leverage if different
        $newLeverage = max($existingLeverage, $additionalLeverage);
        
        // Calculate new margin used
        $newMarginUsed = $totalValue / $newLeverage;
        
        // Update position record
        $updateSql = "UPDATE positions SET 
            size = ?, 
            entry_price = ?, 
            leverage = ?, 
            margin_used = ? 
            WHERE id = ?";
        
        $updateStmt = $pdo->prepare($updateSql);
        $result = $updateStmt->execute([
            $totalSize,
            $avgEntryPrice,
            $newLeverage,
            $newMarginUsed,
            $position['id']
        ]);
        
        if ($result) {
            logMessage("Updated position ID: {$position['id']} - New size: $totalSize, Avg entry: $avgEntryPrice, Margin: $newMarginUsed");
            return $position['id'];
        } else {
            logMessage("Failed to update position ID: {$position['id']}");
            return false;
        }
        
    } catch (Exception $e) {
        logMessage("Error updating position with additional entry: " . $e->getMessage());
        return false;
    }
}

// Create order record in database
function createOrderRecord($pdo, $signalId, $bingxOrderId, $symbol, $side, $entryLevel, $quantity, $price, $leverage, $orderStatus = 'NEW', $stopLossOrderId = null, $takeProfitOrderId = null, $stopLossPrice = null, $takeProfitPrice = null) {
    try {
        $isDemo = isDemoMode();
        
        // Determine order type based on entry level
        if ($entryLevel === 'MARKET') {
            $orderType = 'MARKET';
        } elseif ($entryLevel === 'STOP_LOSS') {
            $orderType = 'STOP_MARKET';
        } else {
            $orderType = 'LIMIT';
        }
        
        $sql = "INSERT INTO orders (
            signal_id, bingx_order_id, symbol, side, type, entry_level,
            quantity, price, leverage, status, is_demo, 
            stop_loss_order_id, take_profit_order_id, stop_loss_price, take_profit_price,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $signalId,
            $bingxOrderId,
            $symbol,
            $side,
            $orderType,
            $entryLevel,
            $quantity,
            $price,
            $leverage,
            $orderStatus,
            $isDemo,
            $stopLossOrderId,
            $takeProfitOrderId,
            $stopLossPrice,
            $takeProfitPrice
        ]);
        
        if ($result) {
            $orderId = $pdo->lastInsertId();
            logMessage("Created order record ID: $orderId for signal ID: $signalId");
            return $orderId;
        } else {
            logMessage("Failed to create order record for signal ID: $signalId");
            return false;
        }
        
    } catch (Exception $e) {
        logMessage("Error creating order record: " . $e->getMessage());
        return false;
    }
}

// Update order status when filled
function updateOrderStatus($pdo, $orderId, $status, $fillPrice = null, $fillTime = null) {
    try {
        if ($fillPrice && $fillTime) {
            $sql = "UPDATE orders SET status = ?, fill_price = ?, fill_time = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$status, $fillPrice, $fillTime]);
        } else {
            $sql = "UPDATE orders SET status = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$status, $orderId]);
        }
        
        if ($result) {
            logMessage("Updated order ID: $orderId to status: $status");
            return true;
        } else {
            logMessage("Failed to update order ID: $orderId");
            return false;
        }
        
    } catch (Exception $e) {
        logMessage("Error updating order status: " . $e->getMessage());
        return false;
    }
}

// Send Telegram notification
function sendTelegramNotification($message) {
    try {
        $botToken = getenv('TELEGRAM_BOT_TOKEN_NOTIF') ?: '';
        $chatId = getenv('TELEGRAM_CHAT_ID_NOTIF') ?: '';
        
        if (empty($botToken) || empty($chatId)) {
            return; // Telegram not configured
        }
        
        $url = "https://api.telegram.org/bot$botToken/sendMessage";
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        logMessage("Telegram notification sent");
        
    } catch (Exception $e) {
        logMessage("Failed to send Telegram notification: " . $e->getMessage());
    }
}

// Safety check for trading mode
if (!function_exists('validateTradingMode')) {
    function validateTradingMode() {
    $tradingMode = getenv('TRADING_MODE') ?: 'live';
    $isDemo = isDemoMode();
    $apiUrl = getBingXApiUrl();
    
    logMessage("Trading Mode Validation:");
    logMessage("  TRADING_MODE setting: $tradingMode");
    logMessage("  Is Demo Mode: " . ($isDemo ? 'YES' : 'NO'));
    logMessage("  API URL: $apiUrl");
    
    // Warning for live mode
    if (!$isDemo) {
        logMessage("⚠️  WARNING: Running in LIVE mode - real trades will be placed!");
        logMessage("⚠️  Set TRADING_MODE=demo in .env for testing");
        
        // Add 5 second delay in live mode for safety
        logMessage("Starting live trading in 5 seconds... Press Ctrl+C to cancel");
        sleep(5);
    } else {
        logMessage("✅ Demo mode confirmed - safe for testing");
    }
    
    return true;
    }
}

// Main execution
function main() {
    logMessage("=== Signal Automation Started ===");
    
    // Validate trading mode first
    if (!validateTradingMode()) {
        logMessage("Trading mode validation failed, exiting");
        return;
    }
    
    logTradingMode('Signal Automation');
    
    $pdo = getDbConnection();
    if (!$pdo) {
        logMessage("Could not connect to database, exiting");
        return;
    }
    
    // Process PENDING signals
    processPendingSignals($pdo);
    
    // Process FILLED signals for entry2
    processFilledSignals($pdo);
    
    logMessage("=== Signal Automation Completed ===");
}

// Run the automation if called directly
if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    main();
} else {
    echo "Signal automation script - use CLI or add ?run=1 to URL\n";
}

?>