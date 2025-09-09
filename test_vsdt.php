<?php
// Test VSDT Balance API Direct
header('Content-Type: application/json');

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

loadEnv(__DIR__ . '/.env');

$apiKey = getenv('BINGX_API_KEY') ?: '';
$secretKey = getenv('BINGX_SECRET_KEY') ?: '';

echo "Testing VSDT Balance API...\n\n";
echo "API Key Present: " . (!empty($apiKey) ? 'Yes' : 'No') . "\n";
echo "Secret Key Present: " . (!empty($secretKey) ? 'Yes' : 'No') . "\n\n";

if (empty($apiKey) || empty($secretKey)) {
    die("Missing API credentials!\n");
}

// Demo API base URL (note the -vst)
$baseUrl = "https://open-api-vst.bingx.com";

// Try different endpoints that should work on demo
$endpoints = [
    "/openApi/swap/v2/user/balance",
    "/openApi/swap/v1/user/balance", 
    "/openApi/swap/v3/user/balance",
    "/api/v1/user/balance"
];

$success = false;
$finalResponse = '';

foreach ($endpoints as $endpoint) {
    echo "=== Trying endpoint: $endpoint ===\n";
    
    // Build query parameters
    $params = [
        "timestamp" => round(microtime(true) * 1000),
    ];

    // Sort parameters
    ksort($params);

    // Build query string
    $queryString = http_build_query($params, '', '&');

    // Generate signature
    $signature = hash_hmac('sha256', $queryString, $secretKey);

    // Final request URL
    $url = $baseUrl . $endpoint . "?" . $queryString . "&signature=" . $signature;

    echo "URL: " . $url . "\n";

    // Init cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-BX-APIKEY: $apiKey"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    echo "HTTP Code: " . $httpCode . "\n";
    echo "cURL Error: " . ($curlError ?: 'None') . "\n";
    echo "Response: " . substr($response, 0, 200) . "...\n";

    if ($response === false) {
        echo "Failed with cURL error: " . $curlError . "\n\n";
        continue;
    }

    // Decode JSON response
    $data = json_decode($response, true);

    if (isset($data['code']) && $data['code'] === 0) {
        echo "SUCCESS! Found working endpoint: $endpoint\n";
        echo "Full Response:\n";
        print_r($data);
        
        // Extract VSDT balance
        if (isset($data['data'])) {
            echo "\nLooking for VSDT balance...\n";
            
            // Handle different response formats
            $balanceData = $data['data'];
            if (isset($balanceData['balance']) && is_array($balanceData['balance'])) {
                $balanceData = $balanceData['balance'];
            }
            
            foreach ($balanceData as $balance) {
                if (is_array($balance)) {
                    echo "Asset: " . ($balance['asset'] ?? 'N/A') . " - Balance: " . ($balance['balance'] ?? 'N/A') . "\n";
                    if (($balance['asset'] ?? '') === 'VSDT') {
                        echo "🎉 FOUND VSDT Balance: " . $balance['balance'] . "\n";
                        $success = true;
                        break 2; // Break both loops
                    }
                }
            }
        }
        break; // Exit loop on success
    } else {
        echo "Failed - Code: " . ($data['code'] ?? 'N/A') . " - Message: " . ($data['msg'] ?? 'N/A') . "\n\n";
    }
}
?>