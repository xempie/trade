<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Watchlist API Debug Test</h2>";

// Test the actual API endpoint
echo "<h3>1. Testing get_watchlist_prices.php API directly</h3>";

$url = 'https://brainity.com.au/ta/api/get_watchlist_prices.php';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0;'>";
echo "<strong>HTTP Code:</strong> $httpCode<br>";
if ($curlError) {
    echo "<strong>cURL Error:</strong> $curlError<br>";
}
echo "<strong>Response:</strong><br>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";
echo "</div>";

// Test individual price fetching
echo "<h3>2. Testing individual BingX price fetch</h3>";

// Include the API functions (avoid redeclaration)
if (!function_exists('getBingXPrice')) {
    ob_start();
    include 'api/get_watchlist_prices.php';
    $output = ob_get_clean();
}

// Test with common symbols
$testSymbols = ['BTC', 'ETH', 'BTC-USDT', 'ETH-USDT'];

foreach ($testSymbols as $symbol) {
    echo "<h4>Testing symbol: $symbol</h4>";
    
    // Convert to BingX format
    $bingxSymbol = $symbol;
    if (strpos($bingxSymbol, 'USDT') === false) {
        $bingxSymbol = $bingxSymbol . '-USDT';
    }
    
    echo "<strong>Converted to:</strong> $bingxSymbol<br>";
    
    // Test direct API call
    $price = getBingXPrice($bingxSymbol);
    
    echo "<strong>Result:</strong> ";
    if ($price !== null) {
        echo "✅ $price";
    } else {
        echo "❌ Failed to get price";
    }
    echo "<br><br>";
}

// Test database connection and watchlist data
echo "<h3>3. Testing Database and Watchlist Data</h3>";

try {
    // Load environment (function already exists from included file)
    if (function_exists('loadEnv')) {
        loadEnv('.env');
    }
    
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: '';
    $password = getenv('DB_PASSWORD') ?: '';
    $database = getenv('DB_NAME') ?: 'crypto_trading';
    
    echo "<strong>Database Config:</strong><br>";
    echo "Host: $host<br>";
    echo "User: $user<br>";
    echo "Password: " . (empty($password) ? '(empty)' : '***') . "<br>";
    echo "Database: $database<br><br>";
    
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Database connection successful<br><br>";
    
    // Check watchlist table
    $sql = "SELECT COUNT(*) as count FROM watchlist WHERE status = 'active'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch();
    
    echo "<strong>Active watchlist items:</strong> " . $result['count'] . "<br>";
    
    if ($result['count'] > 0) {
        echo "<br><strong>Sample watchlist data:</strong><br>";
        $sql = "SELECT * FROM watchlist WHERE status = 'active' LIMIT 5";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Symbol</th><th>Entry Price</th><th>Direction</th><th>Status</th></tr>";
        foreach ($items as $item) {
            echo "<tr>";
            echo "<td>" . $item['id'] . "</td>";
            echo "<td>" . $item['symbol'] . "</td>";
            echo "<td>" . $item['entry_price'] . "</td>";
            echo "<td>" . $item['direction'] . "</td>";
            echo "<td>" . $item['status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "❌ Database Error: " . $e->getMessage() . "<br>";
}

echo "<h3>4. Environment Variables Check</h3>";
echo "<strong>BingX API Key:</strong> " . (getenv('BINGX_API_KEY') ? 'Present' : 'Missing') . "<br>";
echo "<strong>BingX Secret:</strong> " . (getenv('BINGX_SECRET_KEY') ? 'Present' : 'Missing') . "<br>";
?>