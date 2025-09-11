<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
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

// Get current market price for P&L calculation
function getCurrentPrice($apiKey, $apiSecret, $symbol, $isDemo = false) {
    try {
        $baseUrl = $isDemo ? 
            (getenv('BINGX_DEMO_URL') ?: 'https://open-api-vst.bingx.com') : 
            (getenv('BINGX_LIVE_URL') ?: 'https://open-api.bingx.com');
        
        $url = $baseUrl . "/openApi/swap/v2/quote/ticker?symbol=" . urlencode($symbol);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-BX-APIKEY: ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpCode == 200) {
            $data = json_decode($response, true);
            if ($data && $data['code'] == 0 && isset($data['data']['lastPrice'])) {
                return floatval($data['data']['lastPrice']);
            }
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error getting current price for {$symbol}: " . $e->getMessage());
        return null;
    }
}

// Calculate realized P&L
function calculateRealizedPnL($entryPrice, $exitPrice, $size, $side, $leverage) {
    try {
        $entryPrice = floatval($entryPrice);
        $exitPrice = floatval($exitPrice);
        $size = floatval($size);
        $leverage = floatval($leverage);
        
        if ($entryPrice <= 0 || $exitPrice <= 0 || $size <= 0) {
            return 0;
        }
        
        // Calculate position value
        $positionValue = $size * $entryPrice;
        
        // Calculate price difference based on position side
        $priceDiff = 0;
        if (strtoupper($side) === 'LONG') {
            $priceDiff = $exitPrice - $entryPrice;
        } else { // SHORT
            $priceDiff = $entryPrice - $exitPrice;
        }
        
        // Calculate P&L: (price_difference / entry_price) * position_value * leverage
        $pnl = ($priceDiff / $entryPrice) * $positionValue * $leverage;
        
        return round($pnl, 4);
        
    } catch (Exception $e) {
        error_log("Error calculating P&L: " . $e->getMessage());
        return 0;
    }
}

// Update position with exit data
function updatePositionWithExit($pdo, $positionId, $exitPrice, $realizedPnL, $exitReason = 'MANUAL') {
    try {
        $sql = "UPDATE positions SET 
                status = 'CLOSED', 
                exit_price = :exit_price,
                realized_pnl = :realized_pnl,
                exit_reason = :exit_reason,
                closed_at = NOW() 
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':exit_price' => $exitPrice,
            ':realized_pnl' => $realizedPnL,
            ':exit_reason' => $exitReason,
            ':id' => $positionId
        ]);
    } catch (Exception $e) {
        error_log("Database error updating position with exit data: " . $e->getMessage());
        return false;
    }
}

// Update signal with win/loss status and final P&L
function updateSignalPerformance($pdo, $signalId, $totalPnL) {
    try {
        // Determine win/loss status
        $winStatus = 'BREAKEVEN';
        if ($totalPnL > 0) {
            $winStatus = 'WIN';
        } elseif ($totalPnL < 0) {
            $winStatus = 'LOSS';
        }
        
        // Check if all positions for this signal are closed
        $sql = "SELECT COUNT(*) as open_count FROM positions WHERE signal_id = :signal_id AND status = 'OPEN'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':signal_id' => $signalId]);
        $openCount = $stmt->fetchColumn();
        
        // If no more open positions, close the signal
        $signalStatus = ($openCount == 0) ? 'CLOSED' : 'ACTIVE';
        
        // Update signal with performance data
        $sql = "UPDATE signals SET 
                signal_status = :signal_status,
                win_status = :win_status,
                final_pnl = COALESCE(final_pnl, 0) + :pnl_increment,
                closed_at = CASE WHEN :signal_status = 'CLOSED' THEN NOW() ELSE closed_at END,
                closure_reason = CASE WHEN :signal_status = 'CLOSED' THEN 'MANUAL' ELSE closure_reason END
                WHERE id = :signal_id";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':signal_status' => $signalStatus,
            ':win_status' => $winStatus,
            ':pnl_increment' => $totalPnL,
            ':signal_id' => $signalId
        ]);
        
        // Trigger signal source statistics update if signal is closed
        if ($result && $signalStatus === 'CLOSED') {
            $sql = "SELECT source_id FROM signals WHERE id = :signal_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':signal_id' => $signalId]);
            $sourceId = $stmt->fetchColumn();
            
            if ($sourceId) {
                $sql = "CALL UpdateSignalSourceStats(:source_id)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':source_id' => $sourceId]);
            }
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Error updating signal performance: " . $e->getMessage());
        return false;
    }
}

// Close position on BingX
function closeBingXPosition($apiKey, $apiSecret, $symbol, $side, $quantity, $isDemo = false) {
    try {
        $timestamp = round(microtime(true) * 1000);
        
        // Determine the opposite side for closing
        $closeSide = $side === 'LONG' ? 'SELL' : 'BUY';
        
        // Determine position side based on trade direction (for hedge mode)
        $positionSide = 'BOTH'; // Default for one-way mode
        // For closing positions, we need to use the same positionSide as when opening
        // BingX uses LONG/SHORT for hedge mode, BOTH for one-way mode
        $positionSide = strtoupper($side); // Use the direction as positionSide
        
        // Get the appropriate API URL based on demo/live mode
        $baseUrl = $isDemo ? 
            (getenv('BINGX_DEMO_URL') ?: 'https://open-api-vst.bingx.com') : 
            (getenv('BINGX_LIVE_URL') ?: 'https://open-api.bingx.com');
        
        $params = [
            'symbol' => $symbol,
            'side' => $closeSide,
            'type' => 'MARKET',
            'quantity' => $quantity,
            'positionSide' => $positionSide,
            'timestamp' => $timestamp
        ];
        
        $queryString = http_build_query($params);
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        
        // Debug logging
        $modeText = $isDemo ? 'DEMO' : 'LIVE';
        error_log("Closing BingX Position ({$modeText} MODE) Parameters: " . json_encode($params));
        error_log("Position Details: ID={$quantity}, Symbol={$symbol}, Side={$side}, CloseSide={$closeSide}, URL={$baseUrl}");
        
        $ch = curl_init();
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
            error_log("BingX API Error - HTTP {$httpCode}: " . $response);
            throw new Exception("BingX API HTTP error: {$httpCode}. Response: " . $response);
        }
        
        $data = json_decode($response, true);
        
        if (!$data) {
            throw new Exception('Invalid JSON response from BingX: ' . json_last_error_msg());
        }
        
        if (!isset($data['code']) || $data['code'] !== 0) {
            $errorMsg = isset($data['msg']) ? $data['msg'] : 'Unknown API error';
            $errorCode = isset($data['code']) ? $data['code'] : 'unknown';
            error_log("BingX Close Position API Error - Code: {$errorCode}, Message: {$errorMsg}, Full response: " . json_encode($data));
            throw new Exception('BingX Close Position Error: ' . $errorMsg . " (Code: {$errorCode})");
        }
        
        return [
            'success' => true,
            'order_id' => $data['data']['orderId'] ?? null,
            'data' => $data['data']
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Update position status in database
function updatePositionStatus($pdo, $positionId, $status = 'CLOSED') {
    try {
        $sql = "UPDATE positions SET status = :status, closed_at = NOW() WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':status' => $status,
            ':id' => $positionId
        ]);
    } catch (Exception $e) {
        error_log("Database error updating position status: " . $e->getMessage());
        return false;
    }
}

// Cancel pending limit orders related to the same signal/symbol
function cancelRelatedLimitOrders($pdo, $symbol, $signalId = null) {
    try {
        $cancelledOrders = 0;
        
        // Cancel pending limit orders for the same symbol
        $sql = "UPDATE orders SET 
                status = 'CANCELLED', 
                updated_at = NOW() 
                WHERE symbol = :symbol 
                AND type = 'LIMIT' 
                AND status IN ('NEW', 'PENDING')";
        
        $params = [':symbol' => $symbol];
        
        // If we have a signal_id, also filter by it for more precise cancellation
        if ($signalId) {
            $sql .= " AND signal_id = :signal_id";
            $params[':signal_id'] = $signalId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $cancelledOrders = $stmt->rowCount();
        
        // Also cancel related watchlist items
        if ($cancelledOrders > 0) {
            $symbolWithoutUSDT = str_replace('-USDT', '', $symbol);
            $sql = "UPDATE watchlist SET 
                    status = 'cancelled'
                    WHERE symbol = :symbol 
                    AND status = 'active'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':symbol' => $symbolWithoutUSDT]);
        }
        
        return $cancelledOrders;
        
    } catch (Exception $e) {
        error_log("Error cancelling related limit orders: " . $e->getMessage());
        return 0;
    }
}

// Get position details
function getPositionDetails($pdo, $positionId) {
    try {
        $sql = "SELECT * FROM positions WHERE id = :id AND status = 'OPEN'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $positionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Database error getting position details: " . $e->getMessage());
        return null;
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
    $required = ['position_id', 'symbol', 'direction'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }
    
    if (empty($apiKey) || empty($apiSecret)) {
        throw new Exception('BingX API credentials not configured');
    }
    
    $pdo = getDbConnection();
    $positionId = intval($input['position_id']);
    $symbol = strtoupper(trim($input['symbol']));
    $direction = strtoupper(trim($input['direction']));
    $isDemo = isset($input['is_demo']) ? (bool)$input['is_demo'] : false;
    
    // Get position details from database
    $position = getPositionDetails($pdo, $positionId);
    if (!$position) {
        throw new Exception('Position not found or already closed');
    }
    
    // Convert symbol to BingX format
    $bingxSymbol = $symbol;
    if (!strpos($bingxSymbol, 'USDT')) {
        $bingxSymbol = $bingxSymbol . '-USDT';
    }
    
    // Close position on BingX (demo or live based on is_demo parameter)
    $closeResult = closeBingXPosition(
        $apiKey, 
        $apiSecret, 
        $bingxSymbol, 
        $direction,
        $position['size'],
        $isDemo
    );
    
    if ($closeResult['success']) {
        // Position successfully closed on exchange - calculate P&L
        $exitPrice = getCurrentPrice($apiKey, $apiSecret, $bingxSymbol, $isDemo);
        $realizedPnL = 0;
        $updated = false;
        
        if ($exitPrice) {
            // Calculate realized P&L
            $realizedPnL = calculateRealizedPnL(
                $position['entry_price'], 
                $exitPrice, 
                $position['size'], 
                $position['side'], 
                $position['leverage']
            );
            
            // Update position with exit data including P&L
            $updated = updatePositionWithExit($pdo, $positionId, $exitPrice, $realizedPnL, 'MANUAL');
            
            // Update signal performance if position has a signal_id
            if ($updated && $position['signal_id']) {
                updateSignalPerformance($pdo, $position['signal_id'], $realizedPnL);
            }
        } else {
            // Fallback to simple status update if we can't get current price
            $updated = updatePositionStatus($pdo, $positionId, 'CLOSED');
            error_log("Warning: Could not get exit price for P&L calculation on position {$positionId}");
        }
        
        // Cancel any pending limit orders for the same symbol
        $cancelledOrders = cancelRelatedLimitOrders($pdo, $bingxSymbol, $position['signal_id'] ?? null);
        
        if ($updated) {
            $message = "Position closed successfully";
            if ($realizedPnL != 0) {
                $pnlText = $realizedPnL > 0 ? '+$' . number_format($realizedPnL, 2) : '-$' . number_format(abs($realizedPnL), 2);
                $message .= " (P&L: {$pnlText})";
            }
            if ($cancelledOrders > 0) {
                $message .= " and {$cancelledOrders} pending limit orders cancelled";
            }
            
            echo json_encode([
                'success' => true,
                'message' => $message,
                'bingx_order_id' => $closeResult['order_id'],
                'position_id' => $positionId,
                'exit_price' => $exitPrice ?? null,
                'realized_pnl' => $realizedPnL,
                'cancelled_orders' => $cancelledOrders,
                'pnl_display' => $realizedPnL != 0 ? ($realizedPnL > 0 ? '+$' . number_format($realizedPnL, 2) : '-$' . number_format(abs($realizedPnL), 2)) : '$0.00'
            ]);
        } else {
            // Position closed on exchange but database update failed
            echo json_encode([
                'success' => true,
                'message' => "Position closed on exchange, but database update failed",
                'bingx_order_id' => $closeResult['order_id'],
                'warning' => 'Database sync issue',
                'cancelled_orders' => $cancelledOrders
            ]);
        }
    } else {
        // Check if the error is "No position to close" (80001)
        // This means position was already closed manually outside our app
        if (strpos($closeResult['error'], '80001') !== false || 
            strpos($closeResult['error'], 'No position to close') !== false) {
            
            // Mark position as closed in database since it's already closed on exchange
            $updated = updatePositionStatus($pdo, $positionId, 'CLOSED');
            
            // Cancel any pending limit orders for the same symbol
            $cancelledOrders = cancelRelatedLimitOrders($pdo, $bingxSymbol, $position['signal_id'] ?? null);
            
            $message = "Position was already closed manually. Database updated.";
            if ($cancelledOrders > 0) {
                $message .= " Also cancelled {$cancelledOrders} pending limit orders.";
            }
            
            echo json_encode([
                'success' => true,
                'message' => $message,
                'position_id' => $positionId,
                'note' => 'Position closed outside of app',
                'cancelled_orders' => $cancelledOrders
            ]);
        } else {
            // Other errors - actual API failures
            throw new Exception($closeResult['error']);
        }
    }
    
} catch (Exception $e) {
    error_log("Close Position API Error: " . $e->getMessage());
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