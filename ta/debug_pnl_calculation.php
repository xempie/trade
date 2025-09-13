<?php
require_once __DIR__ . '/auth/config.php';

echo "=== P&L CALCULATION DEBUG ===\n";

try {
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    $database = getenv('DB_NAME') ?: 'crypto_trading';

    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get open positions
    $stmt = $pdo->query("SELECT * FROM positions WHERE status='OPEN'");
    $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($positions as $position) {
        $symbol = $position['symbol'];
        $entryPrice = floatval($position['entry_price']);
        $side = $position['side'];

        echo "\n=== Position ID: {$position['id']} ===\n";
        echo "Symbol: $symbol\n";
        echo "Side: $side\n";
        echo "Entry Price: $entryPrice\n";

        // Get BingX current price
        $apiKey = getenv('BINGX_API_KEY');
        $apiSecret = getenv('BINGX_SECRET_KEY');
        $tradingMode = getenv('TRADING_MODE') ?: 'demo';
        $baseUrl = ($tradingMode === 'demo') ?
            (getenv('BINGX_DEMO_URL') ?: 'https://open-api-vst.bingx.com') :
            (getenv('BINGX_LIVE_URL') ?: 'https://open-api.bingx.com');

        $bingxSymbol = strtoupper($symbol) . '-USDT';
        $timestamp = round(microtime(true) * 1000);
        $queryString = "timestamp={$timestamp}";
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        $url = "{$baseUrl}/openApi/swap/v2/user/positions?{$queryString}&signature={$signature}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-BX-APIKEY: ' . $apiKey]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $bingxPositions = $data['data'] ?? [];

            foreach ($bingxPositions as $bingxPos) {
                if ($bingxPos['symbol'] === $bingxSymbol && abs(floatval($bingxPos['positionAmt'])) > 0) {
                    $currentPrice = floatval($bingxPos['markPrice']);
                    $bingxPnlPercent = floatval($bingxPos['percentage'] ?? 0);

                    echo "BingX Current Price: $currentPrice\n";
                    echo "BingX P&L Percentage: $bingxPnlPercent%\n";
                    echo "RAW BingX Position Data: " . json_encode($bingxPos, JSON_PRETTY_PRINT) . "\n";

                    // Calculate our percentage
                    if ($side === 'LONG') {
                        $ourCalculation = (($currentPrice - $entryPrice) / $entryPrice) * 100;
                    } else {
                        $ourCalculation = (($entryPrice - $currentPrice) / $entryPrice) * 100;
                    }

                    echo "Our Calculation: $ourCalculation%\n";
                    echo "Difference: " . ($ourCalculation - $bingxPnlPercent) . "%\n";

                    // Test manual calculation
                    echo "\nMANUAL TEST:\n";
                    echo "Entry: $entryPrice\n";
                    echo "Current: $currentPrice\n";
                    echo "Price difference: " . ($currentPrice - $entryPrice) . "\n";
                    echo "Percentage: " . ((($currentPrice - $entryPrice) / $entryPrice) * 100) . "%\n";
                    break;
                }
            }
        } else {
            echo "Failed to get BingX data: HTTP $httpCode\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>