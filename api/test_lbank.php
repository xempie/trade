<?php
// Simple LBank API test script to debug signature issues
header('Content-Type: text/plain');

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

$envPath = __DIR__ . '/../.env';
loadEnv($envPath);

// Debug: Check if .env file exists and is being loaded
echo "Checking .env path: $envPath\n";
if (file_exists($envPath)) {
    echo ".env file found\n";
} else {
    echo ".env file NOT found\n";
}

$apiKey = getenv('LBANK_API_KEY') ?: '';
$apiSecret = getenv('LBANK_SECRET_KEY') ?: '';

// Debug: Show what we're getting from environment
echo "Raw LBANK_API_KEY from env: '" . getenv('LBANK_API_KEY') . "'\n";
echo "Raw LBANK_SECRET_KEY from env: '" . getenv('LBANK_SECRET_KEY') . "'\n";

echo "=== LBank API Debug Test ===\n";
echo "API Key: " . (empty($apiKey) ? "NOT SET" : substr($apiKey, 0, 8) . "...") . "\n";
echo "Secret: " . (empty($apiSecret) ? "NOT SET" : substr($apiSecret, 0, 8) . "...") . "\n\n";

if (empty($apiKey) || empty($apiSecret)) {
    echo "ERROR: API credentials not configured\n";
    exit;
}

// Test LBank API with correct HmacSHA256 method
$timestamp = time() * 1000;
$echostr = bin2hex(random_bytes(16)); // 32 character hex string

echo "Timestamp (milliseconds): $timestamp\n";
echo "Echostr: $echostr\n\n";

// Method 1: LBank HmacSHA256 method (correct)
echo "=== Method 1: LBank HmacSHA256 Method ===\n";
$params1 = [
    'api_key' => $apiKey,
    'echostr' => $echostr,
    'signature_method' => 'HmacSHA256',
    'timestamp' => $timestamp
];
ksort($params1);

$paramPairs = [];
foreach ($params1 as $key => $value) {
    $paramPairs[] = $key . '=' . $value;
}
$paramString = implode('&', $paramPairs);
$md5Hash = strtoupper(md5($paramString));
$signature1 = hash_hmac('sha256', $md5Hash, $apiSecret, true);
$signature1 = base64_encode($signature1);

echo "Parameter string: $paramString\n";
echo "MD5 hash: $md5Hash\n";
echo "HMAC-SHA256 signature: $signature1\n\n";

// Method 2: Try RSA method as alternative
echo "=== Method 2: RSA Method ===\n";
$params2 = [
    'api_key' => $apiKey,
    'signature_method' => 'RSA',
    'timestamp' => $timestamp,
    'echostr' => $echostr
];
ksort($params2);

$paramPairs2 = [];
foreach ($params2 as $key => $value) {
    $paramPairs2[] = $key . '=' . $value;
}
$paramString2 = implode('&', $paramPairs2);
$md5Digest2 = strtoupper(md5($paramString2));
// For RSA, we'd need a private key, so let's try simple MD5 for testing
$signature2 = strtoupper(md5($md5Digest2 . $apiSecret));

echo "Parameter string: $paramString2\n";
echo "MD5 digest: $md5Digest2\n";
echo "Signature: $signature2\n\n";

// Test the actual API call with Method 1 (HmacSHA256)
echo "=== Testing API Call (Method 1 - HmacSHA256) ===\n";
$testParams = [
    'api_key' => $apiKey,
    'echostr' => $echostr,
    'signature_method' => 'HmacSHA256',
    'timestamp' => $timestamp,
    'sign' => $signature1
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://www.lbkex.net/v2/supplement/user_info.do');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($testParams));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($curlError) {
    echo "cURL Error: $curlError\n";
}
echo "Response: $response\n\n";

// Parse response
$data = json_decode($response, true);
if ($data) {
    echo "Parsed Response:\n";
    print_r($data);
} else {
    echo "Failed to parse JSON response\n";
}

// If first method fails, try second method (RSA)
if ($data && isset($data['error_code']) && $data['error_code'] == 10007) {
    echo "\n=== Testing API Call (Method 2 - RSA) ===\n";
    $testParams2 = [
        'api_key' => $apiKey,
        'signature_method' => 'RSA',
        'timestamp' => $timestamp,
        'echostr' => $echostr,
        'sign' => $signature2
    ];
    
    $ch2 = curl_init();
    curl_setopt($ch2, CURLOPT_URL, 'https://api.lbkex.com/v1/user_info.do');
    curl_setopt($ch2, CURLOPT_POST, true);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query($testParams2));
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response2 = curl_exec($ch2);
    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    $curlError2 = curl_error($ch2);
    curl_close($ch2);
    
    echo "HTTP Code: $httpCode2\n";
    if ($curlError2) {
        echo "cURL Error: $curlError2\n";
    }
    echo "Response: $response2\n\n";
    
    $data2 = json_decode($response2, true);
    if ($data2) {
        echo "Parsed Response:\n";
        print_r($data2);
    } else {
        echo "Failed to parse JSON response\n";
    }
}
?>