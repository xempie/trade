<?php
// Include authentication protection
require_once '../auth/api_protection.php';

// Protect this API endpoint
protectAPI();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Helper function to load settings
function loadSettings() {
    $settings = [
        'position_size_percent' => 3.3,
        'entry_2_percent' => 2.0,
        'entry_3_percent' => 4.0,
        'send_balance_alerts' => false,
        'send_profit_loss_alerts' => false
    ];
    
    $envPath = '../.env';
    if (file_exists($envPath)) {
        $envContent = file_get_contents($envPath);
        $lines = explode("\n", $envContent);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line && !str_starts_with($line, '#') && strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                switch ($key) {
                    case 'POSITION_SIZE_PERCENT':
                        $settings['position_size_percent'] = (float)$value;
                        break;
                    case 'ENTRY_2_PERCENT':
                        $settings['entry_2_percent'] = (float)$value;
                        break;
                    case 'ENTRY_3_PERCENT':
                        $settings['entry_3_percent'] = (float)$value;
                        break;
                    case 'SEND_BALANCE_ALERTS':
                        $settings['send_balance_alerts'] = $value === 'true';
                        break;
                    case 'SEND_PROFIT_LOSS_ALERTS':
                        $settings['send_profit_loss_alerts'] = $value === 'true';
                        break;
                }
            }
        }
    }
    
    return $settings;
}

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
$envPath = __DIR__ . '/../.env';
loadEnv($envPath);

// Load API helper for trading mode support
require_once __DIR__ . '/api_helper.php';

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
        throw new Exception('Database connection failed: ' . $e->getMessage());
    }
}

// Get BingX API credentials
$apiKey = getenv('BINGX_API_KEY') ?: '';
$apiSecret = getenv('BINGX_SECRET_KEY') ?: '';

// Get account balance for position sizing
function getAccountBalance($apiKey, $apiSecret) {
    try {
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
                // Find USDT balance
                foreach ($data['data'] as $balance) {
                    if (isset($balance['asset']) && $balance['asset'] === 'USDT') {
                        return floatval($balance['availableMargin'] ?? $balance['available'] ?? 0);
                    }
                }
            }
        }
        return 0;
    } catch (Exception $e) {
        return 0;
    }
}

// Calculate position size (dynamic % of available balance)
function calculatePositionSize($availableBalance, $leverage = 1) {
    $settings = loadSettings();
    $positionSizePercent = $settings['position_size_percent'] / 100; // Convert to decimal
    $riskAmount = $availableBalance * $positionSizePercent;
    $positionSize = ceil($riskAmount); // Round up, no decimals
    return $positionSize;
}

// Get minimum order size for different symbols
function getMinOrderSize($symbol) {
    // Remove USDT suffix to get base symbol
    $baseSymbol = str_replace(['-USDT', 'USDT'], '', strtoupper($symbol));
    
    // Common minimum order sizes for popular cryptocurrencies
    $minSizes = [
        'BTC' => 0.0001,
        'ETH' => 0.001,
        'BNB' => 0.01,
        'ADA' => 1.0,
        'XRP' => 1.0,
        'SOL' => 0.01,
        'DOT' => 0.1,
        'DOGE' => 1.0,
        'AVAX' => 0.01,
        'LINK' => 0.01,
        'UNI' => 0.01,
        'LTC' => 0.001,
        'BCH' => 0.001,
        'ATOM' => 0.01,
        'FIL' => 0.01,
        'TRX' => 10.0,
        'NEAR' => 0.1,
        'ALGO' => 1.0,
        'VET' => 100.0,
        'ICP' => 0.01,
        'THETA' => 0.1,
        'FTM' => 1.0,
        'HBAR' => 10.0,
        'EOS' => 0.1,
        'AAVE' => 0.001,
        'GRT' => 1.0,
        'SNX' => 0.01
    ];
    
    return $minSizes[$baseSymbol] ?? 0.1; // Default minimum
}

// Round to appropriate decimal places based on symbol
function roundToSymbolPrecision($quantity, $symbol) {
    // Remove USDT suffix to get base symbol
    $baseSymbol = str_replace(['-USDT', 'USDT'], '', strtoupper($symbol));
    
    // Decimal precision for different symbols
    $precisions = [
        'BTC' => 4,
        'ETH' => 3,
        'BNB' => 2,
        'ADA' => 0,
        'XRP' => 0,
        'SOL' => 2,
        'DOT' => 1,
        'DOGE' => 0,
        'AVAX' => 2,
        'LINK' => 2,
        'UNI' => 2,
        'LTC' => 3,
        'BCH' => 3,
        'ATOM' => 2,
        'FIL' => 2,
        'TRX' => 0,
        'NEAR' => 1,
        'ALGO' => 0,
        'VET' => 0,
        'ICP' => 2,
        'THETA' => 1,
        'FTM' => 0,
        'HBAR' => 0,
        'EOS' => 1,
        'AAVE' => 3,
        'GRT' => 0,
        'SNX' => 2
    ];
    
    $precision = $precisions[$baseSymbol] ?? 1; // Default precision
    return round($quantity, $precision);
}

// Set position mode to one-way (dual-side) if needed
function setBingXPositionMode($apiKey, $apiSecret, $dualSidePosition = 'false') {
    try {
        $timestamp = round(microtime(true) * 1000);
        
        $params = [
            'dualSidePosition' => $dualSidePosition, // 'false' = one-way, 'true' = hedge
            'timestamp' => $timestamp
        ];
        
        $queryString = http_build_query($params);
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        
        $ch = curl_init();
        $baseUrl = getBingXApiUrl();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . "/openApi/swap/v2/trade/positionSide/dual");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $queryString . "&signature=" . $signature);
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
            return $data && $data['code'] == 0;
        }
        return false;
        
    } catch (Exception $e) {
        error_log("Position mode setting error: " . $e->getMessage());
        return false;
    }
}

// Set leverage on BingX before placing order
function setBingXLeverage($apiKey, $apiSecret, $symbol, $leverage, $side = 'BOTH') {
    try {
        $timestamp = round(microtime(true) * 1000);
        
        $params = [
            'symbol' => $symbol,
            'side' => $side,
            'leverage' => $leverage,
            'timestamp' => $timestamp
        ];
        
        $queryString = http_build_query($params);
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        
        $ch = curl_init();
        $baseUrl = getBingXApiUrl();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . "/openApi/swap/v2/trade/leverage");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $queryString . "&signature=" . $signature);
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
        error_log("Leverage setting response for {$symbol} to {$leverage}x: HTTP {$httpCode}, Response: " . substr($response, 0, 200));
        
        if ($response && $httpCode == 200) {
            $data = json_decode($response, true);
            if ($data && $data['code'] == 0) {
                error_log("Leverage successfully set to {$leverage}x for {$symbol}");
                return true;
            } else {
                error_log("Leverage setting failed: " . json_encode($data));
                return false;
            }
        }
        error_log("Leverage setting failed with HTTP {$httpCode}");
        return false;
        
    } catch (Exception $e) {
        error_log("Leverage setting error: " . $e->getMessage());
        return false;
    }
}

// Place order on BingX
function placeBingXOrder($apiKey, $apiSecret, $orderData) {
    try {
        $timestamp = round(microtime(true) * 1000);
        
        // Determine position side based on trade direction (for hedge mode)
        $positionSide = 'BOTH'; // Default for one-way mode
        if (isset($orderData['direction'])) {
            $positionSide = strtoupper($orderData['direction']); // LONG or SHORT for hedge mode
        }
        
        $params = [
            'symbol' => $orderData['symbol'],
            'side' => $orderData['side'],
            'type' => $orderData['type'],
            'quantity' => $orderData['quantity'],
            'positionSide' => $positionSide,
            'timestamp' => $timestamp
        ];
        
        // Add price for limit orders
        if ($orderData['type'] === 'LIMIT' && isset($orderData['price'])) {
            $params['price'] = $orderData['price'];
        }
        
        $queryString = http_build_query($params);
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        
        // Debug logging
        error_log("BingX Order Parameters: " . json_encode($params));
        error_log("BingX Query String: " . $queryString);
        
        $ch = curl_init();
        $baseUrl = getBingXApiUrl();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . "/openApi/swap/v2/trade/order");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $queryString . "&signature=" . $signature);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'X-BX-APIKEY: ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception('cURL Error: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new Exception("BingX API HTTP error: {$httpCode}. Response: " . $response);
        }
        
        $data = json_decode($response, true);
        
        if (!$data) {
            throw new Exception('Invalid JSON response from BingX: ' . json_last_error_msg());
        }
        
        if (!isset($data['code']) || $data['code'] !== 0) {
            $errorMsg = isset($data['msg']) ? $data['msg'] : 'Unknown API error';
            throw new Exception('BingX Order Error: ' . $errorMsg);
        }
        
        return [
            'success' => true,
            'order_id' => $data['data']['orderId'] ?? null,
            'client_order_id' => $data['data']['clientOrderId'] ?? null,
            'data' => $data['data']
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Save order to database
function saveOrderToDb($pdo, $orderData, $bingxOrderId = null) {
    try {
        $sql = "INSERT INTO orders (
            signal_id, bingx_order_id, symbol, side, type, entry_level,
            quantity, price, leverage, status, created_at
        ) VALUES (
            :signal_id, :bingx_order_id, :symbol, :side, :type, :entry_level,
            :quantity, :price, :leverage, :status, NOW()
        )";
        
        $stmt = $pdo->prepare($sql);
        
        // Determine status based on order type and execution
        $status = 'FAILED'; // Default
        if ($orderData['type'] === 'MARKET') {
            $status = $bingxOrderId ? 'NEW' : 'FAILED';
        } else if ($orderData['type'] === 'LIMIT') {
            $status = 'PENDING'; // Limit orders start as pending until executed
        }
        
        return $stmt->execute([
            ':signal_id' => $orderData['signal_id'] ?? null,
            ':bingx_order_id' => $bingxOrderId,
            ':symbol' => $orderData['symbol'],
            ':side' => $orderData['side'],
            ':type' => $orderData['type'],
            ':entry_level' => $orderData['entry_level'],
            ':quantity' => $orderData['quantity'],
            ':price' => $orderData['price'] ?? null,
            ':leverage' => $orderData['leverage'],
            ':status' => $status
        ]);
    } catch (Exception $e) {
        error_log("Database error saving order: " . $e->getMessage());
        return false;
    }
}

// Save signal to database
function saveSignalToDb($pdo, $signalData) {
    try {
        $sql = "INSERT INTO signals (
            symbol, signal_type, entry_market_price, entry_2, entry_3,
            leverage, status, created_at
        ) VALUES (
            :symbol, :signal_type, :entry_market_price, :entry_2, :entry_3,
            :leverage, :status, NOW()
        )";
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            ':symbol' => $signalData['symbol'],
            ':signal_type' => strtoupper($signalData['direction']),
            ':entry_market_price' => $signalData['entry_market'] ?? null,
            ':entry_2' => $signalData['entry_2'] ?? null,
            ':entry_3' => $signalData['entry_3'] ?? null,
            ':leverage' => $signalData['leverage'],
            ':status' => 'ACTIVE'
        ]);
        
        if ($success) {
            return $pdo->lastInsertId();
        }
        return null;
    } catch (Exception $e) {
        error_log("Database error saving signal: " . $e->getMessage());
        return null;
    }
}

// Save position to database
function savePositionToDb($pdo, $positionData) {
    try {
        $sql = "INSERT INTO positions (
            symbol, side, size, entry_price, leverage, margin_used,
            signal_id, status, opened_at, notes
        ) VALUES (
            :symbol, :side, :size, :entry_price, :leverage, :margin_used,
            :signal_id, :status, NOW(), :notes
        )";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':symbol' => $positionData['symbol'],
            ':side' => $positionData['side'],
            ':size' => $positionData['size'],
            ':entry_price' => $positionData['entry_price'],
            ':leverage' => $positionData['leverage'],
            ':margin_used' => $positionData['margin_used'],
            ':signal_id' => $positionData['signal_id'],
            ':status' => 'OPEN',
            ':notes' => $positionData['notes'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Database error saving position: " . $e->getMessage());
        return false;
    }
}

// Main execution
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }
    
    // Validate required fields
    $required = ['symbol', 'direction', 'leverage', 'enabled_entries'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }
    
    if (empty($apiKey) || empty($apiSecret)) {
        throw new Exception('BingX API credentials not configured');
    }
    
    $pdo = getDbConnection();
    $availableBalance = getAccountBalance($apiKey, $apiSecret);
    
    if ($availableBalance <= 0) {
        throw new Exception('Unable to get account balance or insufficient funds');
    }
    
    $symbol = strtoupper(trim($input['symbol']));
    $direction = strtolower($input['direction']);
    $leverage = intval($input['leverage']);
    $enabledEntries = $input['enabled_entries'];
    $notes = trim($input['notes'] ?? '');
    
    // Log the received leverage value for debugging
    error_log("Received order request: Symbol={$symbol}, Direction={$direction}, Leverage={$leverage}");
    
    // Convert symbol to BingX format
    $bingxSymbol = $symbol;
    if (!strpos($bingxSymbol, 'USDT')) {
        $bingxSymbol = $bingxSymbol . '-USDT';
    }
    
    // Save signal first
    $signalId = saveSignalToDb($pdo, [
        'symbol' => $symbol,
        'direction' => $direction,
        'entry_market' => $input['entry_market'] ?? null,
        'entry_2' => $input['entry_2'] ?? null,
        'entry_3' => $input['entry_3'] ?? null,
        'leverage' => $leverage
    ]);
    
    if (!$signalId) {
        throw new Exception('Failed to save signal to database');
    }
    
    $results = [];
    $totalMarginUsed = 0;
    
    // Process each enabled entry
    foreach ($enabledEntries as $entry) {
        try {
            $entryType = $entry['type'];
            $price = floatval($entry['price']);
            $margin = floatval($entry['margin']);
            
            // Calculate position size: margin * leverage = total position value
            // Then convert total position value to base currency quantity
            if ($price > 0) {
                $totalPositionValue = $margin * $leverage; // $35 * 10x = $350 total position
                $positionSize = $totalPositionValue / $price; // Convert USD value to base currency quantity
                
                // Apply minimum order size requirements for different symbols
                $minOrderSize = getMinOrderSize($symbol);
                if ($positionSize < $minOrderSize) {
                    $positionSize = $minOrderSize; // Use minimum required size
                    error_log("Position size adjusted to minimum: {$minOrderSize} {$symbol}");
                }
                
                // Round to appropriate decimal places based on symbol
                $positionSize = roundToSymbolPrecision($positionSize, $symbol);
            } else {
                // Fallback for market orders - use margin * leverage
                $totalPositionValue = $margin * $leverage;
                $positionSize = $totalPositionValue; // Fallback value
            }
            $marginUsed = $margin; // The margin entered by user is the actual margin used
            
            // Log the order sizing calculation
            error_log("Order sizing: Margin={$margin}, Leverage={$leverage}, TotalValue={$totalPositionValue}, PositionSize={$positionSize}, Price={$price}");
            
            // Determine order side based on direction
            $side = $direction === 'long' ? 'BUY' : 'SELL';
            
            // Create order data
            $orderData = [
                'signal_id' => $signalId,
                'symbol' => $bingxSymbol,
                'side' => $side,
                'type' => $entryType === 'market' ? 'MARKET' : 'LIMIT',
                'entry_level' => strtoupper($entryType),
                'quantity' => $positionSize,
                'leverage' => $leverage,
                'direction' => $direction // Pass direction for positionSide
            ];
            
            if ($orderData['type'] === 'LIMIT') {
                $orderData['price'] = $price;
            }
            
            // Place order on BingX (only for market orders, limit orders would be placed later)
            if ($orderData['type'] === 'MARKET') {
                // Set leverage first (convert order side to position side for leverage API)
                $positionSide = ($direction === 'long') ? 'LONG' : 'SHORT';
                $leverageSet = setBingXLeverage($apiKey, $apiSecret, $bingxSymbol, $leverage, $positionSide);
                if (!$leverageSet) {
                    error_log("Warning: Failed to set leverage for {$bingxSymbol} to {$leverage}x");
                } else {
                    error_log("Successfully set leverage for {$bingxSymbol} to {$leverage}x");
                    // Small delay to ensure leverage is applied before order
                    usleep(500000); // 0.5 second delay
                }
                
                $orderResult = placeBingXOrder($apiKey, $apiSecret, $orderData);
                
                if ($orderResult['success']) {
                    $bingxOrderId = $orderResult['order_id'];
                    
                    // Save position to database for market orders
                    $positionSaved = savePositionToDb($pdo, [
                        'symbol' => $symbol,
                        'side' => strtoupper($direction),
                        'size' => $positionSize,
                        'entry_price' => $price,
                        'leverage' => $leverage,
                        'margin_used' => $marginUsed,
                        'signal_id' => $signalId,
                        'notes' => $notes
                    ]);
                    
                    if ($positionSaved) {
                        $totalMarginUsed += $marginUsed;
                    }
                } else {
                    $bingxOrderId = null;
                }
            } else {
                // For limit orders, just save to database for now
                $bingxOrderId = null;
                $orderResult = ['success' => true, 'message' => 'Limit order saved for monitoring'];
            }
            
            // Save order to database
            $orderSaved = saveOrderToDb($pdo, $orderData, $bingxOrderId);
            
            $results[] = [
                'entry_type' => $entryType,
                'order_type' => $orderData['type'],
                'position_size' => $positionSize,
                'margin_used' => $marginUsed,
                'price' => $price,
                'bingx_order_id' => $bingxOrderId,
                'success' => $orderResult['success'],
                'message' => $orderResult['success'] ? 'Order placed successfully' : $orderResult['error'],
                'saved_to_db' => $orderSaved
            ];
            
        } catch (Exception $e) {
            $results[] = [
                'entry_type' => $entry['type'] ?? 'unknown',
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Update account balance cache
    try {
        $sql = "INSERT INTO account_balance (total_balance, available_balance, margin_used, updated_at) 
                VALUES (:total, :available, :margin, NOW()) 
                ON DUPLICATE KEY UPDATE 
                total_balance = :total, available_balance = :available, margin_used = :margin, updated_at = NOW()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':total' => $availableBalance + $totalMarginUsed,
            ':available' => $availableBalance - $totalMarginUsed,
            ':margin' => $totalMarginUsed
        ]);
    } catch (Exception $e) {
        error_log("Failed to update account balance cache: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'signal_id' => $signalId,
        'orders' => $results,
        'total_margin_used' => $totalMarginUsed,
        'available_balance' => $availableBalance,
        'message' => 'Signal processed successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Place Order API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code($e->getCode() ?: 400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'method' => $_SERVER['REQUEST_METHOD']
        ]
    ]);
}
?>