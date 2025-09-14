<?php
/**
 * AGGRESSIVE EXCHANGE SYNC SYSTEM
 * Immediately syncs database with BingX exchange after every critical action
 */

class ExchangeSync {
    private $pdo;
    private $apiKey;
    private $apiSecret;
    private $baseUrl;

    public function __construct() {
        // Database connection
        $host = getenv('DB_HOST') ?: 'localhost';
        $user = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASSWORD') ?: '';
        $database = getenv('DB_NAME') ?: 'crypto_trading';

        $this->pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // BingX API credentials
        $this->apiKey = getenv('BINGX_API_KEY');
        $this->apiSecret = getenv('BINGX_SECRET_KEY');

        // Determine base URL (demo vs live)
        $tradingMode = getenv('TRADING_MODE') ?: 'live';
        $this->baseUrl = ($tradingMode === 'demo') ?
            (getenv('BINGX_DEMO_URL') ?: 'https://open-api-vst.bingx.com') :
            (getenv('BINGX_LIVE_URL') ?: 'https://open-api.bingx.com');
    }

    /**
     * SYNC POINT 1: After order placement/fill
     * Syncs entry price, position size, order IDs
     */
    public function syncAfterOrderPlacement($positionId, $symbol) {
        error_log("🔄 AGGRESSIVE SYNC: After order placement - Position ID: $positionId, Symbol: $symbol");

        try {
            // Get fresh position data from BingX
            $exchangePosition = $this->getBingXPosition($symbol);

            if ($exchangePosition) {
                // Update database with EXACT exchange values
                $stmt = $this->pdo->prepare("
                    UPDATE positions
                    SET entry_price = ?,
                        size = ?,
                        unrealized_pnl = ?,
                        last_sync = NOW()
                    WHERE id = ?
                ");

                $stmt->execute([
                    $exchangePosition['avgPrice'],
                    abs(floatval($exchangePosition['positionAmt'])),
                    $exchangePosition['unrealizedProfit'],
                    $positionId
                ]);

                error_log("✅ SYNCED Position $positionId: Entry={$exchangePosition['avgPrice']}, Size={$exchangePosition['positionAmt']}, PnL={$exchangePosition['unrealizedProfit']}");
                return true;
            }

        } catch (Exception $e) {
            error_log("❌ SYNC FAILED after order placement: " . $e->getMessage());
        }

        return false;
    }

    /**
     * SYNC POINT 2: After SL/TP modification
     * Syncs stop loss and take profit prices from exchange orders
     */
    public function syncAfterSLTPModification($positionId, $symbol) {
        error_log("🔄 AGGRESSIVE SYNC: After SL/TP modification - Position ID: $positionId, Symbol: $symbol");

        try {
            // Get all open orders for this symbol from BingX
            $openOrders = $this->getBingXOpenOrders($symbol);

            $stopLossPrice = null;
            $takeProfitPrice = null;

            foreach ($openOrders as $order) {
                if ($order['type'] === 'STOP_MARKET') {
                    $stopLossPrice = $order['stopPrice'];
                    error_log("📍 Found SL on exchange: $stopLossPrice");
                } elseif ($order['type'] === 'TAKE_PROFIT_MARKET') {
                    $takeProfitPrice = $order['stopPrice'];
                    error_log("📍 Found TP on exchange: $takeProfitPrice");
                }
            }

            // Update database with exchange values
            if ($stopLossPrice !== null || $takeProfitPrice !== null) {
                $updates = [];
                $params = [];

                if ($stopLossPrice !== null) {
                    $updates[] = "stop_loss = ?";
                    $params[] = $stopLossPrice;
                }

                if ($takeProfitPrice !== null) {
                    $updates[] = "take_profit_1 = ?";
                    $params[] = $takeProfitPrice;
                }

                $updates[] = "last_sync = NOW()";
                $params[] = $positionId;

                // Update positions table
                $stmt = $this->pdo->prepare("UPDATE positions SET " . implode(', ', $updates) . " WHERE id = ?");
                $stmt->execute($params);

                // Also update signals table
                $stmt = $this->pdo->prepare("
                    UPDATE signals
                    SET stop_loss = COALESCE(?, stop_loss),
                        take_profit_1 = COALESCE(?, take_profit_1),
                        updated_at = NOW()
                    WHERE id = (SELECT signal_id FROM positions WHERE id = ?)
                ");
                $stmt->execute([$stopLossPrice, $takeProfitPrice, $positionId]);

                error_log("✅ SYNCED SL/TP for Position $positionId: SL=$stopLossPrice, TP=$takeProfitPrice");
                return true;
            }

        } catch (Exception $e) {
            error_log("❌ SYNC FAILED after SL/TP modification: " . $e->getMessage());
        }

        return false;
    }

    /**
     * SYNC POINT 3: After risk-free update
     * Verifies new stop loss price is correctly set on exchange
     */
    public function syncAfterRiskFree($positionId, $symbol, $expectedSLPrice) {
        error_log("🔄 AGGRESSIVE SYNC: After risk-free update - Position ID: $positionId, Expected SL: $expectedSLPrice");

        try {
            // Get current stop loss from exchange
            $openOrders = $this->getBingXOpenOrders($symbol);
            $actualSLPrice = null;

            foreach ($openOrders as $order) {
                if ($order['type'] === 'STOP_MARKET') {
                    $actualSLPrice = $order['stopPrice'];
                    break;
                }
            }

            if ($actualSLPrice !== null) {
                // Update database with actual exchange price
                $stmt = $this->pdo->prepare("
                    UPDATE positions
                    SET stop_loss = ?, last_sync = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$actualSLPrice, $positionId]);

                // Update signals table too
                $stmt = $this->pdo->prepare("
                    UPDATE signals
                    SET stop_loss = ?, updated_at = NOW()
                    WHERE id = (SELECT signal_id FROM positions WHERE id = ?)
                ");
                $stmt->execute([$actualSLPrice, $positionId]);

                error_log("✅ SYNCED Risk-Free SL for Position $positionId: Expected=$expectedSLPrice, Actual=$actualSLPrice");

                // Verify accuracy
                $difference = abs($expectedSLPrice - $actualSLPrice);
                if ($difference > 0.00001) {
                    error_log("⚠️ SL PRICE MISMATCH: Expected $expectedSLPrice, Got $actualSLPrice (Diff: $difference)");
                }

                return true;
            } else {
                error_log("❌ NO STOP LOSS FOUND on exchange after risk-free update");
            }

        } catch (Exception $e) {
            error_log("❌ SYNC FAILED after risk-free update: " . $e->getMessage());
        }

        return false;
    }

    /**
     * SYNC POINT 4: Full position sync
     * Complete sync of position data including P&L, status, etc.
     */
    public function syncFullPosition($positionId, $symbol = null) {
        error_log("🔄 AGGRESSIVE SYNC: Full position sync - Position ID: $positionId");

        try {
            // Get position from database if symbol not provided
            if (!$symbol) {
                $stmt = $this->pdo->prepare("SELECT symbol FROM positions WHERE id = ?");
                $stmt->execute([$positionId]);
                $pos = $stmt->fetch(PDO::FETCH_ASSOC);
                $symbol = $pos['symbol'] ?? null;
            }

            if (!$symbol) {
                throw new Exception("Symbol not found for position $positionId");
            }

            // Get fresh data from exchange
            $exchangePosition = $this->getBingXPosition($symbol);
            $openOrders = $this->getBingXOpenOrders($symbol);

            if ($exchangePosition) {
                // Extract SL/TP from orders
                $stopLossPrice = null;
                $takeProfitPrice = null;

                foreach ($openOrders as $order) {
                    if ($order['type'] === 'STOP_MARKET') {
                        $stopLossPrice = $order['stopPrice'];
                    } elseif ($order['type'] === 'TAKE_PROFIT_MARKET') {
                        $takeProfitPrice = $order['stopPrice'];
                    }
                }

                // Update database with ALL exchange data
                $stmt = $this->pdo->prepare("
                    UPDATE positions
                    SET entry_price = ?,
                        size = ?,
                        unrealized_pnl = ?,
                        stop_loss = COALESCE(?, stop_loss),
                        status = CASE
                            WHEN ABS(?) < 0.0001 THEN 'CLOSED'
                            ELSE 'OPEN'
                        END,
                        last_sync = NOW()
                    WHERE id = ?
                ");

                $positionSize = abs(floatval($exchangePosition['positionAmt']));

                $stmt->execute([
                    $exchangePosition['avgPrice'],
                    $positionSize,
                    $exchangePosition['unrealizedProfit'],
                    $stopLossPrice,
                    $positionSize, // For status check
                    $positionId
                ]);

                error_log("✅ FULL SYNC Complete for Position $positionId: Entry={$exchangePosition['avgPrice']}, Size=$positionSize, PnL={$exchangePosition['unrealizedProfit']}, SL=$stopLossPrice");
                return true;
            }

        } catch (Exception $e) {
            error_log("❌ FULL SYNC FAILED for position $positionId: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Get position data from BingX exchange
     */
    private function getBingXPosition($symbol) {
        $bingxSymbol = strtoupper($symbol) . '-USDT';

        $timestamp = round(microtime(true) * 1000);
        $queryString = "timestamp={$timestamp}";
        $signature = hash_hmac('sha256', $queryString, $this->apiSecret);

        $url = "{$this->baseUrl}/openApi/swap/v2/user/positions?{$queryString}&signature={$signature}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-BX-APIKEY: ' . $this->apiKey]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $positions = $data['data'] ?? [];

            foreach ($positions as $pos) {
                if ($pos['symbol'] === $bingxSymbol && abs(floatval($pos['positionAmt'])) > 0) {
                    return $pos;
                }
            }
        }

        return null;
    }

    /**
     * Get open orders from BingX exchange
     */
    private function getBingXOpenOrders($symbol) {
        $bingxSymbol = strtoupper($symbol) . '-USDT';

        $timestamp = round(microtime(true) * 1000);
        $queryString = "timestamp={$timestamp}";
        $signature = hash_hmac('sha256', $queryString, $this->apiSecret);

        $url = "{$this->baseUrl}/openApi/swap/v2/trade/openOrders?{$queryString}&signature={$signature}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-BX-APIKEY: ' . $this->apiKey]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $orders = $data['data']['orders'] ?? $data['data'] ?? [];

            // Filter for our symbol
            $symbolOrders = [];
            foreach ($orders as $order) {
                if ($order['symbol'] === $bingxSymbol) {
                    $symbolOrders[] = $order;
                }
            }

            return $symbolOrders;
        }

        return [];
    }
}

?>