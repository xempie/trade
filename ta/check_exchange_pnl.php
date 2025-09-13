<?php
require_once __DIR__ . '/auth/config.php';

// Get BingX API credentials
$apiKey = getenv('BINGX_API_KEY');
$apiSecret = getenv('BINGX_SECRET_KEY');
$baseUrl = 'https://open-api-vst.bingx.com'; // Demo URL

echo "=== CHECKING POSITION DATA ON BINGX EXCHANGE ===\n";
echo "Using demo exchange: $baseUrl\n\n";

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
    echo "❌ Failed to get positions: HTTP $httpCode\n";
    echo "Response: $response\n";
    exit;
}

$positionsResponse = json_decode($response, true);
$positions = $positionsResponse['data'] ?? [];

echo "📊 POSITIONS ON BINGX EXCHANGE:\n";
echo "Total positions found: " . count($positions) . "\n\n";

foreach ($positions as $position) {
    if (abs(floatval($position['positionAmt'] ?? 0)) > 0) {
        $symbol = $position['symbol'] ?? 'N/A';
        $side = $position['positionSide'] ?? 'N/A';
        $size = abs(floatval($position['positionAmt'] ?? 0));
        $entryPrice = floatval($position['avgPrice'] ?? 0);
        $markPrice = floatval($position['markPrice'] ?? 0);
        $unrealizedPnl = floatval($position['unrealizedProfit'] ?? 0);
        $percentage = floatval($position['percentage'] ?? 0);
        $margin = floatval($position['initialMargin'] ?? 0);
        $leverage = $position['leverage'] ?? 'N/A';

        echo "🔸 Position: $symbol\n";
        echo "   Side: $side\n";
        echo "   Size: $size tokens\n";
        echo "   Entry Price: $entryPrice USDT\n";
        echo "   Mark Price: $markPrice USDT\n";
        echo "   Unrealized P&L: $unrealizedPnl USDT\n";
        echo "   P&L Percentage: {$percentage}%\n";
        echo "   Initial Margin: $margin USDT\n";
        echo "   Leverage: {$leverage}x\n";
        echo "\n";

        // Calculate P&L manually to verify
        if ($side === 'LONG') {
            $calculatedPnl = ($markPrice - $entryPrice) * $size;
        } else {
            $calculatedPnl = ($entryPrice - $markPrice) * $size;
        }

        echo "   📊 MANUAL P&L CALCULATION:\n";
        echo "   Formula for $side: ";
        if ($side === 'LONG') {
            echo "($markPrice - $entryPrice) × $size\n";
        } else {
            echo "($entryPrice - $markPrice) × $size\n";
        }
        echo "   Manual P&L: $calculatedPnl USDT\n";
        echo "   BingX P&L: $unrealizedPnl USDT\n";
        echo "   Difference: " . ($calculatedPnl - $unrealizedPnl) . " USDT\n";
        echo "\n";

        // Compare with our database
        echo "   🔍 COMPARISON WITH DATABASE:\n";
        echo "   Our Entry Price: 0.27468000 USDT\n";
        echo "   BingX Entry Price: $entryPrice USDT\n";
        echo "   Entry Price Difference: " . (0.27468000 - $entryPrice) . " USDT\n";
        echo "\n";
        echo "   Our Size: 142347.50000000 tokens\n";
        echo "   BingX Size: $size tokens\n";
        echo "   Size Difference: " . (142347.50000000 - $size) . " tokens\n";
        echo "\n";
    }
}
?>