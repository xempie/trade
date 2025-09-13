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
    
    // CRITICAL: Always use the position's demo/live mode for exchange selection
    // Demo positions should use demo exchange, live positions should use live exchange
    $baseUrl = $isDemo ?
        (getenv('BINGX_DEMO_URL') ?: 'https://open-api-vst.bingx.com') :
        (getenv('BINGX_LIVE_URL') ?: 'https://open-api.bingx.com');

    error_log("POSITION MODE DEBUG: Position is_demo=" . ($isDemo ? 'true' : 'false') . ", baseUrl='$baseUrl'");
    error_log("Global tradingMode='$tradingMode' (ignored - using position-specific mode)");
    
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

    // Stop loss should be 100% of the position size to close entire position
    // Use the actual position size from exchange or database
    $stopLossQuantity = max($currentPositionSize, $position['size']);
    error_log("Stop loss quantity set to full position size: $stopLossQuantity tokens");

    // For demo positions, still use full size but handle API errors gracefully
    if ($isDemo) {
        error_log("Demo position: Using full position size of $stopLossQuantity tokens for stop loss");
        error_log("Demo mode will fallback to mock order if BingX rejects due to balance issues");
    }

    error_log("Using stop loss quantity: " . $stopLossQuantity);

    // Remove balance checking - let the exchange handle insufficient balance errors
    // The system should always attempt to place orders on the appropriate exchange

    error_log("Final stop loss quantity to use: " . $stopLossQuantity);

    // Step 2: AGGRESSIVE STOP LOSS DELETION - Delete ALL stop losses for this symbol and position
    error_log("=== AGGRESSIVE STOP LOSS DELETION FOR POSITION ===");
    error_log("Position side: {$position['side']}, Symbol: $bingxSymbol");

    function deleteAllStopLossOrders($baseUrl, $bingxSymbol, $positionSide, $apiKey, $apiSecret) {
        $deletedOrders = [];

        // Get all open orders
        $timestamp = round(microtime(true) * 1000);
        $queryParams = ['timestamp' => $timestamp];
        $queryString = http_build_query($queryParams);
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        $getOrdersUrl = "{$baseUrl}/openApi/swap/v2/trade/openOrders?{$queryString}&signature={$signature}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $getOrdersUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-BX-APIKEY: ' . $apiKey]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("❌ Failed to get orders: HTTP $httpCode");
            return $deletedOrders;
        }

        $ordersResponse = json_decode($response, true);
        $ordersArray = $ordersResponse['data']['orders'] ?? $ordersResponse['data'] ?? [];

        error_log("🔍 Scanning " . count($ordersArray) . " orders for stop losses to delete");

        foreach ($ordersArray as $order) {
            // Check if this is a stop loss order for our symbol
            $isStopLoss = (
                isset($order['symbol']) && $order['symbol'] === $bingxSymbol &&
                isset($order['type']) && $order['type'] === 'STOP_MARKET' &&
                isset($order['orderId'])
            );

            if ($isStopLoss) {
                error_log("🎯 FOUND STOP LOSS: {$order['orderId']} for {$order['symbol']} - DELETING IMMEDIATELY");

                // Cancel this stop loss order immediately - FIXED VERSION
                $timestamp = round(microtime(true) * 1000);
                $cancelData = [
                    'symbol' => $bingxSymbol,
                    'orderId' => $order['orderId'],
                    'timestamp' => $timestamp
                ];

                $cancelQuery = http_build_query($cancelData);
                $cancelSig = hash_hmac('sha256', $cancelQuery, $apiSecret);

                // USE GET METHOD WITH URL PARAMETERS INSTEAD OF DELETE WITH BODY
                $cancelUrl = "{$baseUrl}/openApi/swap/v2/trade/order?" . $cancelQuery . "&signature=" . $cancelSig;

                error_log("🔧 FIXED Cancel URL: $cancelUrl");

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $cancelUrl);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                // NO POST FIELDS - USE URL PARAMETERS ONLY
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'X-BX-APIKEY: ' . $apiKey
                ]);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                $cancelResponse = curl_exec($ch);
                $cancelHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($cancelHttpCode === 200) {
                    $deletedOrders[] = $order['orderId'];
                    error_log("✅ DELETED stop loss order: {$order['orderId']}");
                } else {
                    error_log("❌ FAILED to delete stop loss order: {$order['orderId']} - HTTP $cancelHttpCode, Response: $cancelResponse");
                }
            }
        }

        return $deletedOrders;
    }

    // Execute aggressive deletion
    $deletedStopLossOrders = deleteAllStopLossOrders($baseUrl, $bingxSymbol, strtoupper($position['side']), $apiKey, $apiSecret);
    $stopLossOrdersToCancel = $deletedStopLossOrders; // For summary logging

    error_log("🧹 DELETION COMPLETE: " . count($deletedStopLossOrders) . " stop loss orders deleted");
    if (!empty($deletedStopLossOrders)) {
        error_log("🗑️  Deleted order IDs: " . implode(', ', $deletedStopLossOrders));
    }
    // Old cancellation logic removed - using aggressive deletion above
    
    // Determine order side (opposite of position side) - needed for both API and database
    $orderSide = ($position['side'] === 'LONG') ? 'SELL' : 'BUY';
    error_log("Position side: {$position['side']}, Stop loss order side: $orderSide");

    // For hedge mode, positionSide should match the original position side
    $positionSide = strtoupper($position['side']); // LONG or SHORT
    error_log("Position side for order: $positionSide");

    // Step 3: Always create new stop loss order on appropriate exchange
    error_log("=== CREATING NEW STOP LOSS ORDER ===");
    $timestamp = round(microtime(true) * 1000);

    $orderParams = [
        'symbol' => $bingxSymbol,
        'side' => $orderSide,
        'type' => 'STOP_MARKET',
        'quantity' => $stopLossQuantity,
        'stopPrice' => $newStopLoss,
        'positionSide' => $positionSide,
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

    // Check for BingX API errors first - handle balance errors gracefully for demo mode
    if (isset($orderResult['code']) && $orderResult['code'] != 0) {
        $errorMsg = isset($orderResult['msg']) ? $orderResult['msg'] : 'Unknown BingX API error';
        error_log("BingX API Error Code: " . $orderResult['code'] . ", Message: " . $errorMsg);

        // Handle balance/size errors gracefully for demo positions
        if (($orderResult['code'] == 110424 || $orderResult['code'] == 110422) && $isDemo) {
            error_log("=== DEMO MODE API ERROR - USING MOCK ORDER ===");
            error_log("Error code: " . $orderResult['code'] . ", isDemo: " . ($isDemo ? 'true' : 'false'));
            if ($orderResult['code'] == 110424) {
                error_log("Demo account has insufficient balance, creating mock order for database tracking");
            } elseif ($orderResult['code'] == 110422) {
                error_log("Demo order size too small for BingX requirements, creating mock order for database tracking");
            }
            $newOrderId = "DEMO_SL_" . time() . "_" . rand(1000, 9999);
            error_log("Successfully created mock order ID for demo mode: $newOrderId");
            error_log("Will proceed with database update using mock order ID");
        } else {
            error_log("Not handling error gracefully - code: " . $orderResult['code'] . ", isDemo: " . ($isDemo ? 'true' : 'false'));
            throw new Exception("BingX API Error: " . $errorMsg . " (Code: " . $orderResult['code'] . ")");
        }
    } else {
        // Success case - extract order ID from response
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
    }

    error_log("Successfully extracted order ID: " . $newOrderId);
    error_log("=== END DEBUG ===");

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