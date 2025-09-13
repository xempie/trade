<?php
require_once __DIR__ . '/../auth/api_protection.php';
protectAPI();

require_once __DIR__ . '/../auth/config.php';

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    $required_fields = ['position_id', 'symbol', 'new_stop_loss'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $positionId = (int)$input['position_id'];
    $symbol = $input['symbol'];
    $newStopLoss = (float)$input['new_stop_loss'];
    $isDemo = isset($input['is_demo']) ? (bool)$input['is_demo'] : false;
    
    // Database connection
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: '';
    $password = getenv('DB_PASSWORD') ?: '';
    $database = getenv('DB_NAME') ?: 'crypto_trading';
    
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get position details
    $stmt = $pdo->prepare("SELECT * FROM positions WHERE id = ? AND status = 'OPEN'");
    $stmt->execute([$positionId]);
    $position = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$position) {
        throw new Exception('Position not found or not active');
    }
    
    // Get BingX API credentials
    $apiKey = getenv('BINGX_API_KEY');
    $apiSecret = getenv('BINGX_SECRET_KEY');
    $tradingMode = getenv('TRADING_MODE') ?: 'demo';
    
    if (empty($apiKey) || empty($apiSecret)) {
        throw new Exception('BingX API credentials not configured');
    }
    
    // Determine base URL based on trading mode and demo flag
    $baseUrl = ($tradingMode === 'demo' || $isDemo) ? 
        (getenv('BINGX_DEMO_URL') ?: 'https://open-api-vst.bingx.com') : 
        (getenv('BINGX_LIVE_URL') ?: 'https://open-api.bingx.com');
    
    // Format symbol for BingX (ensure it has -USDT suffix)
    $bingxSymbol = strtoupper($symbol);
    if (!strpos($bingxSymbol, 'USDT')) {
        $bingxSymbol = $bingxSymbol . '-USDT';
    }
    
    // Step 1: Get current position size from BingX to ensure we have the right quantity
    $timestamp = time() * 1000;
    $queryString = "symbol={$bingxSymbol}&timestamp={$timestamp}";
    $signature = hash_hmac('sha256', $queryString, $apiSecret);
    
    $getPositionsUrl = "{$baseUrl}/openApi/swap/v2/user/positions?{$queryString}&signature={$signature}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $getPositionsUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-BX-APIKEY: ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $posResponse = curl_exec($ch);
    $posHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($posHttpCode !== 200) {
        error_log("BingX get positions failed: HTTP $posHttpCode, Response: $posResponse");
        throw new Exception('Failed to get current position from exchange');
    }
    
    $exchangePositions = json_decode($posResponse, true);
    $currentPositionSize = 0;
    
    // Find the matching position on the exchange
    if (isset($exchangePositions['data']) && is_array($exchangePositions['data'])) {
        foreach ($exchangePositions['data'] as $pos) {
            if (isset($pos['symbol']) && $pos['symbol'] === $bingxSymbol && 
                isset($pos['positionSide']) && $pos['positionSide'] === strtoupper($position['side']) &&
                isset($pos['positionAmt']) && abs(floatval($pos['positionAmt'])) > 0) {
                $currentPositionSize = abs(floatval($pos['positionAmt']));
                break;
            }
        }
    }
    
    if ($currentPositionSize <= 0) {
        throw new Exception('No active position found on exchange or position size is 0');
    }

    error_log("Found current position size from exchange: " . $currentPositionSize);
    error_log("Database position size: " . $position['size']);
    error_log("Position entry price: " . $position['entry_price']);
    error_log("Position margin used: " . $position['margin_used']);

    // Calculate position size in tokens: margin * leverage / entry_price
    $calculatedTokens = ($position['margin_used'] * $position['leverage']) / $position['entry_price'];
    error_log("Calculated token quantity from margin: " . $calculatedTokens);

    // Use the smaller of calculated tokens or database position size
    $stopLossQuantity = min($calculatedTokens, $position['size']);

    // For demo mode, use a much smaller test amount since demo accounts have limited balance
    // BingX demo accounts often have very low available balance
    if ($tradingMode === 'demo' || $isDemo) {
        $originalQuantity = $stopLossQuantity;
        $stopLossQuantity = 0.1; // Use just 0.1 token for demo testing (about $0.03)
        error_log("Demo mode: Using minimal test quantity of $stopLossQuantity instead of $originalQuantity");
    }

    error_log("Using stop loss quantity: " . $stopLossQuantity);

    // Step 1.5: Check available balance for demo mode to ensure we can place the order
    if ($tradingMode === 'demo' || $isDemo) {
        error_log("=== CHECKING DEMO ACCOUNT BALANCE ===");

        $timestamp = time() * 1000;
        $queryString = "timestamp={$timestamp}";
        $signature = hash_hmac('sha256', $queryString, $apiSecret);

        $balanceUrl = "{$baseUrl}/openApi/swap/v2/user/balance?{$queryString}&signature={$signature}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $balanceUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-BX-APIKEY: ' . $apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $balanceResponse = curl_exec($ch);
        $balanceHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($balanceHttpCode === 200) {
            $balanceData = json_decode($balanceResponse, true);
            error_log("Balance response: " . json_encode($balanceData));

            // Find USDT available balance
            $availableUSDT = 0;
            if (isset($balanceData['data']['balance']) && is_array($balanceData['data']['balance'])) {
                foreach ($balanceData['data']['balance'] as $asset) {
                    if (isset($asset['asset']) && $asset['asset'] === 'USDT') {
                        $availableUSDT = floatval($asset['availableMargin'] ?? $asset['balance'] ?? 0);
                        break;
                    }
                }
            }

            error_log("Available USDT balance: $availableUSDT");

            // If available balance is very low, adjust quantity accordingly
            if ($availableUSDT <= 0) {
                error_log("DEMO MODE WARNING: No available USDT balance ($availableUSDT). Cannot place stop loss order.");
                error_log("Will proceed with database-only update for demo mode.");

                // For demo mode with zero balance, skip the actual order creation
                // but still update the database for testing purposes
                $skipOrderCreation = true;
            } elseif ($availableUSDT < 1) {
                // Calculate max quantity based on available balance (use 90% to be safe)
                $maxValueInUSDT = $availableUSDT * 0.9;
                $maxQuantity = $maxValueInUSDT / $newStopLoss; // Approximate token quantity

                if ($maxQuantity > 0 && $maxQuantity < $stopLossQuantity) {
                    $originalQuantity = $stopLossQuantity;
                    $stopLossQuantity = max(0.01, $maxQuantity); // Minimum 0.01 tokens
                    error_log("Demo mode balance-adjusted quantity: $stopLossQuantity (was $originalQuantity), based on $availableUSDT USDT available");
                }
            }
        } else {
            error_log("Failed to get balance: HTTP $balanceHttpCode, Response: $balanceResponse");
        }
    }

    error_log("Final stop loss quantity to use: " . $stopLossQuantity);

    // Initialize order creation flag
    $skipOrderCreation = false;

    // Step 2: ALWAYS check BingX for existing stop loss orders and cancel them
    // This handles cases where stop loss exists on exchange but not tracked in DB
    error_log("=== CHECKING BINGX FOR EXISTING STOP LOSS ORDERS ===");
    $timestamp = time() * 1000;
    $queryString = "symbol={$bingxSymbol}&timestamp={$timestamp}";
    $signature = hash_hmac('sha256', $queryString, $apiSecret);
    
    $getOrdersUrl = "{$baseUrl}/openApi/swap/v2/trade/openOrders?{$queryString}&signature={$signature}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $getOrdersUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-BX-APIKEY: ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $stopLossOrdersToCancel = [];

    if ($httpCode === 200) {
        $existingOrders = json_decode($response, true);
        error_log("Retrieved existing orders from BingX: " . json_encode($existingOrders));

        // Find ALL stop loss orders for this symbol (regardless of DB state)
        // Check both possible response structures: data.orders[] or data[]
        $ordersArray = null;
        if (isset($existingOrders['data']['orders']) && is_array($existingOrders['data']['orders'])) {
            $ordersArray = $existingOrders['data']['orders'];
            error_log("Found orders in data.orders structure");
        } elseif (isset($existingOrders['data']) && is_array($existingOrders['data'])) {
            $ordersArray = $existingOrders['data'];
            error_log("Found orders in data structure");
        }

        if ($ordersArray !== null) {
            $totalOrders = count($ordersArray);
            error_log("Found $totalOrders total open orders on BingX for symbol $bingxSymbol");

            foreach ($ordersArray as $order) {
                error_log("Checking order: " . json_encode($order));

                if (isset($order['symbol']) && isset($order['type']) && isset($order['side']) && isset($order['orderId']) &&
                    $order['symbol'] === $bingxSymbol &&
                    $order['type'] === 'STOP_MARKET') {

                    // Log details about this stop loss order
                    error_log("Found STOP_MARKET order - ID: {$order['orderId']}, Side: {$order['side']}, Position Side: " . ($order['positionSide'] ?? 'N/A'));

                    // Cancel any stop loss orders for this position side
                    if (isset($order['positionSide']) && $order['positionSide'] === strtoupper($position['side'])) {
                        $stopLossOrdersToCancel[] = $order['orderId'];
                        error_log("WILL CANCEL: Stop loss order {$order['orderId']} matches position side {$position['side']}");
                    } else {
                        error_log("SKIPPING: Stop loss order {$order['orderId']} - different position side");
                    }
                }
            }
        } else {
            error_log("No orders data found in BingX response or data is not an array");
        }
    } else {
        error_log("Failed to get existing orders from BingX: HTTP $httpCode, Response: $response");
    }

    error_log("Total stop loss orders to cancel: " . count($stopLossOrdersToCancel) . " - IDs: " . implode(', ', $stopLossOrdersToCancel));
        
    // Cancel existing stop loss orders if any exist
    error_log("=== CANCELLING EXISTING STOP LOSS ORDERS ===");
    $cancelledCount = 0;
    $failedCancellations = [];

    foreach ($stopLossOrdersToCancel as $orderId) {
        error_log("Attempting to cancel stop loss order: $orderId");

        $timestamp = time() * 1000;
        $cancelData = [
            'symbol' => $bingxSymbol,
            'orderId' => $orderId,
            'timestamp' => $timestamp
        ];

        $queryString = http_build_query($cancelData);
        $signature = hash_hmac('sha256', $queryString, $apiSecret);

        $cancelUrl = "{$baseUrl}/openApi/swap/v2/trade/order";

        error_log("Cancel request URL: $cancelUrl");
        error_log("Cancel request data: " . $queryString . "&signature=" . $signature);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $cancelUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $queryString . "&signature=" . $signature);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-BX-APIKEY: ' . $apiKey,
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $cancelResponse = curl_exec($ch);
        $cancelHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        error_log("Cancel response for order $orderId: HTTP $cancelHttpCode, Response: $cancelResponse");

        if ($cancelHttpCode === 200) {
            $cancelledCount++;
            error_log("SUCCESS: Cancelled stop loss order $orderId");
        } else {
            $failedCancellations[] = $orderId;
            error_log("FAILED: Could not cancel stop loss order $orderId - HTTP $cancelHttpCode");
        }
    }

    error_log("Cancellation summary: $cancelledCount successful, " . count($failedCancellations) . " failed");
    if (!empty($failedCancellations)) {
        error_log("Failed to cancel orders: " . implode(', ', $failedCancellations));
    }
    
    // Step 3: Create new stop loss order (unless skipping for demo mode)
    if ($skipOrderCreation) {
        error_log("=== SKIPPING ORDER CREATION (DEMO MODE - NO BALANCE) ===");
        $newOrderId = "DEMO_" . time(); // Create a fake order ID for database tracking
        error_log("Using fake order ID for demo mode: $newOrderId");
    } else {
        error_log("=== CREATING NEW STOP LOSS ORDER ===");
        $timestamp = time() * 1000;
    
    // Determine order side (opposite of position side)
    $orderSide = ($position['side'] === 'LONG') ? 'SELL' : 'BUY';
    error_log("Position side: {$position['side']}, Stop loss order side: $orderSide");

    // For hedge mode, positionSide should match the original position side
    $positionSide = strtoupper($position['side']); // LONG or SHORT
    error_log("Position side for order: $positionSide");

    $orderParams = [
        'symbol' => $bingxSymbol,
        'side' => $orderSide,
        'type' => 'STOP_MARKET',
        'quantity' => $stopLossQuantity, // Use calculated reasonable quantity
        'stopPrice' => $newStopLoss,
        'positionSide' => $positionSide, // LONG or SHORT for hedge mode
        'timestamp' => $timestamp
    ];

    error_log("New stop loss order parameters: " . json_encode($orderParams, JSON_PRETTY_PRINT));
    
    $queryString = http_build_query($orderParams);
    $signature = hash_hmac('sha256', $queryString, $apiSecret);
    
    $orderUrl = "{$baseUrl}/openApi/swap/v2/trade/order";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $orderUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $queryString . "&signature=" . $signature);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'X-BX-APIKEY: ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $orderResponse = curl_exec($ch);
    $orderHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($orderHttpCode !== 200) {
        error_log("=== STOP LOSS ORDER CREATION FAILED ===");
        error_log("HTTP Status Code: " . $orderHttpCode);
        error_log("Request URL: " . $orderUrl);
        error_log("Request Body: " . $queryString . "&signature=" . $signature);
        error_log("Response Body: " . $orderResponse);
        error_log("cURL Error (if any): " . curl_error($ch));
        error_log("=== END FAILED REQUEST DEBUG ===");
        throw new Exception("Failed to create new stop loss order on exchange. HTTP Code: $orderHttpCode");
    }
    
    $orderResult = json_decode($orderResponse, true);

    // Comprehensive logging for debugging
    error_log("=== RISK FREE STOP LOSS ORDER CREATION DEBUG ===");
    error_log("HTTP Status Code: " . $orderHttpCode);
    error_log("Request URL: " . $orderUrl);
    error_log("Request Body: " . $queryString . "&signature=" . $signature);
    error_log("Raw Response: " . $orderResponse);
    error_log("Parsed Response: " . json_encode($orderResult, JSON_PRETTY_PRINT));
    error_log("Order Parameters Used: " . json_encode([
        'symbol' => $bingxSymbol,
        'side' => $orderSide,
        'type' => 'STOP_MARKET',
        'quantity' => $stopLossQuantity,
        'stopPrice' => $newStopLoss,
        'positionSide' => $positionSide,
        'trading_mode' => $tradingMode,
        'is_demo' => $isDemo,
        'base_url' => $baseUrl
    ], JSON_PRETTY_PRINT));

    // Check for BingX API errors first
    if (isset($orderResult['code']) && $orderResult['code'] != 0) {
        $errorMsg = isset($orderResult['msg']) ? $orderResult['msg'] : 'Unknown BingX API error';
        error_log("BingX API Error Code: " . $orderResult['code'] . ", Message: " . $errorMsg);
        throw new Exception("BingX API Error: " . $errorMsg . " (Code: " . $orderResult['code'] . ")");
    }

    // Try multiple possible response structures
    $newOrderId = null;
    if (isset($orderResult['data']['orderId'])) {
        $newOrderId = $orderResult['data']['orderId'];
        error_log("Found order ID in data.orderId: " . $newOrderId);
    } elseif (isset($orderResult['data']['order']['orderId'])) {
        $newOrderId = $orderResult['data']['order']['orderId'];
        error_log("Found order ID in data.order.orderId: " . $newOrderId);
    } elseif (isset($orderResult['orderId'])) {
        $newOrderId = $orderResult['orderId'];
        error_log("Found order ID in orderId: " . $newOrderId);
    } elseif (isset($orderResult['data']['clientOrderId'])) {
        $newOrderId = $orderResult['data']['clientOrderId'];
        error_log("Found order ID in data.clientOrderId: " . $newOrderId);
    } elseif (isset($orderResult['clientOrderId'])) {
        $newOrderId = $orderResult['clientOrderId'];
        error_log("Found order ID in clientOrderId: " . $newOrderId);
    }

    // Log all available fields in the response for analysis
    if (isset($orderResult['data']) && is_array($orderResult['data'])) {
        error_log("Available fields in response data: " . implode(', ', array_keys($orderResult['data'])));
    }
    if (is_array($orderResult)) {
        error_log("Available fields in response root: " . implode(', ', array_keys($orderResult)));
    }

    if (!$newOrderId) {
        error_log("BingX stop loss order creation failed - no order ID found in any expected field");
        error_log("Complete response structure analysis: " . print_r($orderResult, true));
        throw new Exception('Failed to get new stop loss order ID from exchange. Please check the error logs for the complete API response structure.');
    }

        error_log("Successfully extracted order ID: " . $newOrderId);
        error_log("=== END DEBUG ===");
    } // End of order creation else block

    // Step 4: Update database with new stop loss information
    error_log("=== UPDATING DATABASE ===");
    error_log("Updating signals table - Position ID: $positionId, New stop loss: $newStopLoss");

    // Step 4a: Update signals table with new stop loss
    $stmt = $pdo->prepare("
        UPDATE signals
        SET stop_loss = ?, updated_at = NOW()
        WHERE id = (SELECT signal_id FROM positions WHERE id = ?)
    ");
    $stmt->execute([$newStopLoss, $positionId]);
    $signalsUpdated = $stmt->rowCount();
    error_log("Signals table update result: $signalsUpdated rows affected");

    // Step 4b: Save new stop loss order to orders table with stop loss fields
    error_log("Inserting new stop loss order into orders table - Order ID: $newOrderId, Quantity: $stopLossQuantity");
    $stmt = $pdo->prepare("
        INSERT INTO orders (
            signal_id, bingx_order_id, symbol, side, type, entry_level,
            price, quantity, leverage, status, stop_loss_order_id, stop_loss_price, created_at
        ) VALUES (
            (SELECT signal_id FROM positions WHERE id = ?),
            ?, ?, ?, 'STOP_MARKET', 'STOP_LOSS',
            ?, ?, ?, 'NEW', ?, ?, NOW()
        )
    ");

    $stmt->execute([
        $positionId,
        $newOrderId,
        $symbol,
        $orderSide,
        $newStopLoss,
        $stopLossQuantity,
        $position['leverage'],
        $newOrderId, // stop_loss_order_id
        $newStopLoss // stop_loss_price
    ]);

    $ordersInserted = $stmt->rowCount();
    error_log("Orders table insert result: $ordersInserted rows affected");

    error_log("=== RISK FREE STOP LOSS COMPLETE ===");
    error_log("SUCCESS Summary:");
    error_log("- Position ID: $positionId");
    error_log("- Symbol: $symbol");
    error_log("- New stop loss price: $newStopLoss");
    error_log("- New order ID: $newOrderId");
    error_log("- Stop loss quantity: $stopLossQuantity");
    error_log("- Cancelled existing orders: " . count($stopLossOrdersToCancel));
    error_log("- Signals table updates: $signalsUpdated");
    error_log("- Orders table inserts: $ordersInserted");
    error_log("=== END SUCCESS SUMMARY ===");

    sendAPIResponse(true, [
        'new_stop_loss' => $newStopLoss,
        'new_order_id' => $newOrderId,
        'cancelled_orders' => count($stopLossOrdersToCancel),
        'position_id' => $positionId,
        'symbol' => $symbol
    ], 'Risk Free stop loss updated successfully');
    
} catch (Exception $e) {
    error_log("Risk Free SL Update Error: " . $e->getMessage());
    sendAPIResponse(false, null, "Error updating stop loss: " . $e->getMessage());
}
?>