<?php
/**
 * Test script for signal automation logic
 * Simulates the automation behavior without database connection
 */

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

echo "=== Signal Automation Test ===\n";
echo "Trading Mode: " . (isDemoMode() ? 'DEMO' : 'LIVE') . "\n";
echo "API URL: " . getBingXApiUrl() . "\n";

// Test price fetching
echo "\n=== Testing Price Fetching ===\n";
$testSymbols = ['BTC-USDT', 'ETH-USDT'];

foreach ($testSymbols as $symbol) {
    echo "Testing $symbol: ";
    $price = getCurrentPrice($symbol);
    if ($price !== null) {
        echo "Success - Price: $" . number_format($price, 2) . "\n";
    } else {
        echo "Failed to get price\n";
    }
}

// Test signal logic
// Test position sizing logic
echo "\n=== Testing Position Sizing ===\n";
echo "Position sizing uses MINIMUM of:\n";
echo "1. AUTO_MARGIN_PER_ENTRY from signal_automation_settings table (default: 50.00 USDT)\n";
echo "2. 5% of total account assets\n";
echo "\nThis ensures position size never exceeds 5% of total assets, even if AUTO_MARGIN_PER_ENTRY is higher.\n";
echo "Configure via: UPDATE signal_automation_settings SET setting_value = '100.00' WHERE setting_key = 'AUTO_MARGIN_PER_ENTRY';\n";

echo "\n=== Testing Signal Logic ===\n";

// Simulate PENDING LONG signal
$mockSignal = [
    'id' => 1,
    'symbol' => 'BTC-USDT',
    'signal_type' => 'LONG',
    'entry_market_price' => 65000,
    'leverage' => 5
];

$currentPrice = getCurrentPrice($mockSignal['symbol']);
if ($currentPrice !== null) {
    echo "Mock LONG Signal Test:\n";
    echo "  Symbol: " . $mockSignal['symbol'] . "\n";
    echo "  Entry Price: $" . number_format($mockSignal['entry_market_price'], 2) . "\n";
    echo "  Current Price: $" . number_format($currentPrice, 2) . "\n";
    
    $shouldTrigger = $currentPrice >= $mockSignal['entry_market_price'];
    echo "  Should Trigger: " . ($shouldTrigger ? 'YES' : 'NO') . "\n";
}

// Simulate PENDING SHORT signal
$mockSignalShort = [
    'id' => 2,
    'symbol' => 'ETH-USDT',
    'signal_type' => 'SHORT',
    'entry_market_price' => 3500,
    'leverage' => 3
];

$currentPriceEth = getCurrentPrice($mockSignalShort['symbol']);
if ($currentPriceEth !== null) {
    echo "\nMock SHORT Signal Test:\n";
    echo "  Symbol: " . $mockSignalShort['symbol'] . "\n";
    echo "  Entry Price: $" . number_format($mockSignalShort['entry_market_price'], 2) . "\n";
    echo "  Current Price: $" . number_format($currentPriceEth, 2) . "\n";
    
    $shouldTrigger = $currentPriceEth <= $mockSignalShort['entry_market_price'];
    echo "  Should Trigger: " . ($shouldTrigger ? 'YES' : 'NO') . "\n";
}

// Test entry2 logic
echo "\n=== Testing Entry2 Logic ===\n";

$mockFilledSignal = [
    'id' => 3,
    'symbol' => 'BTC-USDT',
    'signal_type' => 'LONG',
    'entry_2' => 63000,
    'leverage' => 5
];

if ($currentPrice !== null) {
    echo "Mock FILLED LONG Signal Entry2 Test:\n";
    echo "  Symbol: " . $mockFilledSignal['symbol'] . "\n";
    echo "  Entry2 Price: $" . number_format($mockFilledSignal['entry_2'], 2) . "\n";
    echo "  Current Price: $" . number_format($currentPrice, 2) . "\n";
    
    $shouldTriggerEntry2 = $currentPrice <= $mockFilledSignal['entry_2'];
    echo "  Should Trigger Entry2: " . ($shouldTriggerEntry2 ? 'YES' : 'NO') . "\n";
}

echo "\n=== Test Completed ===\n";

// Get current price function (copied from main script)
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
        echo "Error getting price for $symbol: " . $e->getMessage() . "\n";
        return null;
    }
}

?>