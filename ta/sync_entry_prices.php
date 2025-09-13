<?php
require_once __DIR__ . '/auth/config.php';

echo "=== SYNCING ENTRY PRICES FROM BINGX TO DATABASE ===\n\n";

try {
    // Database connection
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    $database = getenv('DB_NAME') ?: 'crypto_trading';

    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get BingX API credentials
    $apiKey = getenv('BINGX_API_KEY');
    $apiSecret = getenv('BINGX_SECRET_KEY');
    $baseUrl = 'https://open-api-vst.bingx.com'; // Demo URL

    // Get current positions from BingX
    $timestamp = round(microtime(true) * 1000);
    $queryString = "timestamp={$timestamp}";
    $signature = hash_hmac('sha256', $queryString, $apiSecret);
    $getPositionsUrl = "{$baseUrl}/openApi/swap/v2/user/positions?{$queryString}&signature={$signature}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $getPositionsUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-BX-APIKEY: ' . $apiKey]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Failed to get positions from BingX: HTTP $httpCode");
    }

    $positionsResponse = json_decode($response, true);
    $positions = $positionsResponse['data'] ?? [];

    echo "Found " . count($positions) . " positions on BingX\n\n";

    // Get open positions from database
    $stmt = $pdo->query("SELECT id, symbol, side, entry_price FROM positions WHERE status='OPEN'");
    $dbPositions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updatedCount = 0;

    foreach ($dbPositions as $dbPos) {
        $symbol = $dbPos['symbol'];
        $bingxSymbol = strtoupper($symbol) . '-USDT';

        // Find matching position on BingX
        foreach ($positions as $exchangePos) {
            if ($exchangePos['symbol'] === $bingxSymbol &&
                $exchangePos['positionSide'] === strtoupper($dbPos['side']) &&
                abs(floatval($exchangePos['positionAmt'])) > 0) {

                $currentEntryPrice = floatval($dbPos['entry_price']);
                $correctEntryPrice = floatval($exchangePos['avgPrice']);

                echo "Position ID {$dbPos['id']} ($symbol {$dbPos['side']}):\n";
                echo "  Current entry price: $currentEntryPrice\n";
                echo "  BingX entry price: $correctEntryPrice\n";

                if (abs($currentEntryPrice - $correctEntryPrice) > 0.000001) {
                    echo "  ⚠️  MISMATCH DETECTED - Updating...\n";

                    $updateStmt = $pdo->prepare("UPDATE positions SET entry_price = ? WHERE id = ?");
                    $updateStmt->execute([$correctEntryPrice, $dbPos['id']]);

                    echo "  ✅ Updated entry price from $currentEntryPrice to $correctEntryPrice\n";
                    $updatedCount++;
                } else {
                    echo "  ✅ Entry price is correct\n";
                }
                echo "\n";
                break;
            }
        }
    }

    echo "=== SYNC COMPLETE ===\n";
    echo "Total positions updated: $updatedCount\n";

    if ($updatedCount > 0) {
        echo "\n🎯 Entry prices have been synced with BingX!\n";
        echo "P&L calculations should now be accurate.\n";
    } else {
        echo "\n✅ All entry prices were already accurate.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>