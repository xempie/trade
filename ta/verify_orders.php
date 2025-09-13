<?php
require_once __DIR__ . '/auth/config.php';

// Get BingX API credentials
$apiKey = getenv('BINGX_API_KEY');
$apiSecret = getenv('BINGX_SECRET_KEY');
$baseUrl = 'https://open-api-vst.bingx.com'; // Demo URL

echo "=== VERIFYING CURRENT ORDERS ON BINGX EXCHANGE ===\n";
echo "Using demo exchange: $baseUrl\n\n";

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
    echo "❌ Failed to get orders: HTTP $httpCode\n";
    echo "Response: $response\n";
    exit;
}

$ordersResponse = json_decode($response, true);
$ordersArray = $ordersResponse['data']['orders'] ?? $ordersResponse['data'] ?? [];

echo "📊 CURRENT ORDERS ON EXCHANGE:\n";
echo "Total orders found: " . count($ordersArray) . "\n\n";

$stopLossCount = 0;
$takeProfitCount = 0;

foreach ($ordersArray as $order) {
    $symbol = $order['symbol'] ?? 'N/A';
    $type = $order['type'] ?? 'N/A';
    $side = $order['side'] ?? 'N/A';
    $orderId = $order['orderId'] ?? 'N/A';
    $stopPrice = $order['stopPrice'] ?? 'N/A';
    $quantity = $order['origQty'] ?? 'N/A';

    echo "📋 Order ID: $orderId\n";
    echo "   Symbol: $symbol\n";
    echo "   Type: $type\n";
    echo "   Side: $side\n";
    echo "   Stop Price: $stopPrice\n";
    echo "   Quantity: $quantity\n";

    if ($type === 'STOP_MARKET') {
        $stopLossCount++;
        echo "   ⚠️  This is a STOP LOSS order\n";
    } elseif ($type === 'TAKE_PROFIT_MARKET') {
        $takeProfitCount++;
        echo "   💰 This is a TAKE PROFIT order\n";
    }

    echo "\n";
}

echo "📈 SUMMARY:\n";
echo "Stop Loss Orders: $stopLossCount\n";
echo "Take Profit Orders: $takeProfitCount\n";
echo "Other Orders: " . (count($ordersArray) - $stopLossCount - $takeProfitCount) . "\n\n";

if ($stopLossCount > 0) {
    echo "⚠️  WARNING: You still have $stopLossCount stop loss orders on the exchange!\n";
    echo "The cancellation may not have worked properly.\n";
} else {
    echo "✅ SUCCESS: No stop loss orders found on the exchange!\n";
    echo "The stop loss cancellation worked correctly.\n";
}
?>