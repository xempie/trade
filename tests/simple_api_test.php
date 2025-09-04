<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Simple API Test</h2>";

// Test 1: Check if the API file exists
echo "<h3>1. File Existence Check</h3>";
$apiFile = __DIR__ . '/api/get_watchlist_prices.php';
if (file_exists($apiFile)) {
    echo "✅ API file exists at: $apiFile<br>";
    echo "File size: " . filesize($apiFile) . " bytes<br>";
    echo "File permissions: " . substr(sprintf('%o', fileperms($apiFile)), -4) . "<br>";
} else {
    echo "❌ API file NOT found at: $apiFile<br>";
}

// Test 2: Direct PHP execution
echo "<h3>2. Direct PHP Execution Test</h3>";
if (file_exists($apiFile)) {
    echo "<strong>Attempting to execute API file directly...</strong><br>";
    
    // Capture output
    ob_start();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    try {
        include $apiFile;
        $output = ob_get_clean();
        
        echo "<strong>Output:</strong><br>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
        
    } catch (Exception $e) {
        ob_end_clean();
        echo "❌ Error executing API: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Cannot execute - file not found<br>";
}

// Test 3: URL accessibility test
echo "<h3>3. URL Accessibility Test</h3>";
$testUrls = [
    'https://brainity.com.au/ta/api/get_watchlist_prices.php',
    'https://brainity.com.au/ta/test_watchlist_api.php',
    'https://brainity.com.au/ta/simple_api_test.php'
];

foreach ($testUrls as $url) {
    echo "<strong>Testing: $url</strong><br>";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode<br>";
    if ($error) {
        echo "cURL Error: $error<br>";
    }
    
    if ($httpCode == 200) {
        echo "✅ URL accessible<br>";
        if (strpos($response, '{"success"') === 0 || strpos($response, '{"') === 0) {
            echo "✅ Returns JSON response<br>";
        } else {
            echo "⚠️ Response doesn't look like JSON<br>";
            echo "First 200 chars: " . htmlspecialchars(substr($response, 0, 200)) . "<br>";
        }
    } else {
        echo "❌ URL not accessible<br>";
        echo "Response: " . htmlspecialchars(substr($response, 0, 300)) . "<br>";
    }
    echo "<br>";
}

// Test 4: Environment check
echo "<h3>4. Environment Check</h3>";
if (file_exists('.env')) {
    echo "✅ .env file exists<br>";
    $envContent = file_get_contents('.env');
    echo "File size: " . strlen($envContent) . " bytes<br>";
    
    // Check for key environment variables (without showing values)
    $hasDbHost = strpos($envContent, 'DB_HOST=') !== false;
    $hasBingxKey = strpos($envContent, 'BINGX_API_KEY=') !== false;
    
    echo "Has DB_HOST: " . ($hasDbHost ? "✅" : "❌") . "<br>";
    echo "Has BINGX_API_KEY: " . ($hasBingxKey ? "✅" : "❌") . "<br>";
} else {
    echo "❌ .env file not found<br>";
}
?>