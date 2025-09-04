<?php
// Direct BingX API test
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>BingX API Direct Test</h2>";

// Test different endpoints and symbol formats
$testSymbols = ['BTC-USDT', 'BTCUSDT', 'BTC', 'ETH-USDT', 'ETHUSDT', 'ETH'];
$endpoints = [
    'https://open-api.bingx.com/openApi/swap/v2/quote/price',
    'https://open-api.bingx.com/openApi/swap/v2/quote/ticker', 
    'https://open-api.bingx.com/openApi/swap/v3/quote/ticker',
    'https://open-api.bingx.com/openApi/spot/v1/ticker/24hr'
];

foreach ($endpoints as $endpoint) {
    echo "<h3>Testing: $endpoint</h3>";
    
    foreach ($testSymbols as $symbol) {
        $url = $endpoint . '?symbol=' . urlencode($symbol);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: Mozilla/5.0 (compatible; CryptoTrader/1.0)',
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        echo "<div style='margin: 10px; padding: 10px; border: 1px solid #ccc;'>";
        echo "<strong>Symbol:</strong> $symbol<br>";
        echo "<strong>HTTP Code:</strong> $httpCode<br>";
        
        if ($error) {
            echo "<strong>cURL Error:</strong> $error<br>";
        }
        
        if ($response) {
            $data = json_decode($response, true);
            echo "<strong>Response:</strong><br>";
            echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT) . "</pre>";
            
            // Try to extract price
            if ($data && isset($data['code']) && $data['code'] == 0 && isset($data['data'])) {
                $dataObj = $data['data'];
                $priceFields = ['price', 'lastPrice', 'close', 'last', 'c'];
                foreach ($priceFields as $field) {
                    if (isset($dataObj[$field])) {
                        echo "<strong>Found Price ($field):</strong> " . $dataObj[$field] . "<br>";
                        break;
                    }
                }
            }
        } else {
            echo "<strong>No Response</strong><br>";
        }
        
        echo "</div>";
        
        // Stop after first successful response for this endpoint
        if ($httpCode == 200 && $response) {
            break;
        }
    }
    
    echo "<hr>";
}
?>