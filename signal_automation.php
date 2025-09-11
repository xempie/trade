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
function calculatePositionSize($pdo) {
    // Get AUTO_MARGIN_PER_ENTRY setting from database
    $autoMarginPerEntry = getAutomationSetting($pdo, 'AUTO_MARGIN_PER_ENTRY', 50.00);
    
    // Get total assets and calculate 5%
    $totalAssets = getTotalAssets();
    $fivePercentOfAssets = $totalAssets * 0.05;
    
    // Use minimum of the two values
    $positionSize = min($autoMarginPerEntry, $fivePercentOfAssets);
    
    logMessage("Position sizing calculation: AUTO_MARGIN_PER_ENTRY=$autoMarginPerEntry, Total Assets=$totalAssets, 5% of Assets=$fivePercentOfAssets, Final Position Size=$positionSize");
    
    return floatval($positionSize);
}

// Place order on BingX
function placeOrder($symbol, $side, $quantity, $leverage = 1) {
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
            'side' => $side, // BUY for LONG, SELL for SHORT
            'type' => 'MARKET',
            'quantity' => $quantity,
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
        logMessage("Error placing order: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
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
        curl_close($ch);
        
        return $response;
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
                $positionSize = calculatePositionSize($pdo);
                $side = $signal['signal_type'] === 'LONG' ? 'BUY' : 'SELL';
                
                logMessage("Placing order: Symbol=" . $signal['symbol'] . ", Side=$side, Quantity=$positionSize, Leverage=" . $signal['leverage']);
                
                // Place order
                $orderResult = placeOrder($signal['symbol'], $side, $positionSize, $signal['leverage']);
                
                if ($orderResult['success']) {
                    // Update signal status to FILLED
                    $updateStmt = $pdo->prepare("UPDATE signals SET status = 'FILLED', updated_at = NOW() WHERE id = ?");
                    $updateStmt->execute([$signal['id']]);
                    
                    logMessage("Successfully placed order for signal ID: " . $signal['id'] . ", Order ID: " . $orderResult['orderId']);
                    
                    // Send Telegram notification if configured
                    sendTelegramNotification("🎯 Signal Triggered!\n\nSymbol: " . $signal['symbol'] . "\nDirection: " . $signal['signal_type'] . "\nEntry Price: $entryPrice\nCurrent Price: $currentPrice\nPosition Size: $positionSize USDT\nLeverage: " . $signal['leverage'] . "x");
                    
                } else {
                    logMessage("Failed to place order for signal ID: " . $signal['id'] . " - " . $orderResult['error']);
                }
            }
        }
        
    } catch (Exception $e) {
        logMessage("Error processing pending signals: " . $e->getMessage());
    }
}

// Process FILLED signals for entry2 triggers
function processFilledSignals($pdo) {
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
                $positionSize = calculatePositionSize($pdo);
                $side = $signal['signal_type'] === 'LONG' ? 'BUY' : 'SELL';
                
                logMessage("Placing entry2 order: Symbol=" . $signal['symbol'] . ", Side=$side, Quantity=$positionSize, Leverage=" . $signal['leverage']);
                
                // Place order
                $orderResult = placeOrder($signal['symbol'], $side, $positionSize, $signal['leverage']);
                
                if ($orderResult['success']) {
                    // Update signal status to ENTRY2
                    $updateStmt = $pdo->prepare("UPDATE signals SET status = 'ENTRY2', updated_at = NOW() WHERE id = ?");
                    $updateStmt->execute([$signal['id']]);
                    
                    logMessage("Successfully placed entry2 order for signal ID: " . $signal['id'] . ", Order ID: " . $orderResult['orderId']);
                    
                    // Send Telegram notification if configured
                    sendTelegramNotification("📈 Entry2 Triggered!\n\nSymbol: " . $signal['symbol'] . "\nDirection: " . $signal['signal_type'] . "\nEntry2 Price: $entry2Price\nCurrent Price: $currentPrice\nPosition Size: $positionSize USDT\nLeverage: " . $signal['leverage'] . "x");
                    
                } else {
                    logMessage("Failed to place entry2 order for signal ID: " . $signal['id'] . " - " . $orderResult['error']);
                }
            }
        }
        
    } catch (Exception $e) {
        logMessage("Error processing filled signals: " . $e->getMessage());
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