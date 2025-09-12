<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

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

// Load .env file
loadEnv(__DIR__ . '/../.env');

// Database connection
function getDbConnection() {
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: '';
    $password = getenv('DB_PASSWORD') ?: '';
    $database = getenv('DB_NAME') ?: 'crypto_trading';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception('Database connection failed: ' . $e->getMessage());
    }
}

// Get BingX API credentials
$apiKey = getenv('BINGX_API_KEY') ?: '';
$apiSecret = getenv('BINGX_SECRET_KEY') ?: '';

// Get all positions from BingX exchange (demo or live)
function getBingXPositions($apiKey, $apiSecret, $isDemo = false) {
    try {
        $timestamp = round(microtime(true) * 1000);
        $queryString = "timestamp={$timestamp}";
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        
        // Use appropriate API URL based on demo/live mode
        $baseUrl = $isDemo ? 
            (getenv('BINGX_DEMO_URL') ?: 'https://open-api-vst.bingx.com') : 
            (getenv('BINGX_LIVE_URL') ?: 'https://open-api.bingx.com');
            
        $url = $baseUrl . "/openApi/swap/v2/user/positions?" . $queryString . "&signature=" . $signature;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-BX-APIKEY: ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("BingX API HTTP error: {$httpCode}");
        }
        
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['code']) || $data['code'] !== 0) {
            throw new Exception('Failed to fetch positions from BingX');
        }
        
        return $data['data'] ?? [];
        
    } catch (Exception $e) {
        error_log("BingX positions fetch error: " . $e->getMessage());
        return [];
    }
}

// Main execution
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Only GET method allowed');
    }
    
    if (empty($apiKey) || empty($apiSecret)) {
        throw new Exception('BingX API credentials not configured');
    }
    
    $pdo = getDbConnection();
    
    // Get positions from database with is_demo column
    // First check if is_demo column exists
    $hasIsDemoColumn = false;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM positions LIKE 'is_demo'");
        $hasIsDemoColumn = $stmt->rowCount() > 0;
    } catch (Exception $e) {
        // Column doesn't exist, continue with fallback
    }
    
    $sql = "SELECT id, symbol, side, size" . ($hasIsDemoColumn ? ", is_demo" : "") . " FROM positions WHERE status = 'OPEN'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $dbPositions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get positions from both demo and live exchanges
    $livePositions = getBingXPositions($apiKey, $apiSecret, false);  // Live exchange
    $demoPositions = getBingXPositions($apiKey, $apiSecret, true);   // Demo exchange
    
    // Create separate maps for live and demo positions
    $liveExchangeMap = [];
    $demoExchangeMap = [];
    
    // Process live positions
    foreach ($livePositions as $pos) {
        if (isset($pos['symbol']) && isset($pos['positionSide']) && floatval($pos['positionAmt']) != 0) {
            $key = $pos['symbol'] . '_' . $pos['positionSide'];
            
            // Map BingX field names to our expected field names
            $normalizedPosition = $pos;
            $normalizedPosition['unrealized_pnl'] = $pos['unrealizedProfit'] ?? 0;
            $normalizedPosition['mark_price'] = $pos['markPrice'] ?? 0;
            $normalizedPosition['entry_price'] = $pos['avgPrice'] ?? 0;
            $normalizedPosition['position_value'] = $pos['positionValue'] ?? 0;
            $normalizedPosition['margin_used'] = $pos['margin'] ?? $pos['initialMargin'] ?? 0;
            $normalizedPosition['side'] = $pos['positionSide'] ?? '';
            $normalizedPosition['quantity'] = $pos['positionAmt'] ?? 0;
            
            $liveExchangeMap[$key] = $normalizedPosition;
        }
    }
    
    // Process demo positions  
    foreach ($demoPositions as $pos) {
        if (isset($pos['symbol']) && isset($pos['positionSide']) && floatval($pos['positionAmt']) != 0) {
            $key = $pos['symbol'] . '_' . $pos['positionSide'];
            
            // Map BingX field names to our expected field names
            $normalizedPosition = $pos;
            $normalizedPosition['unrealized_pnl'] = $pos['unrealizedProfit'] ?? 0;
            $normalizedPosition['mark_price'] = $pos['markPrice'] ?? 0;
            $normalizedPosition['entry_price'] = $pos['avgPrice'] ?? 0;
            $normalizedPosition['position_value'] = $pos['positionValue'] ?? 0;
            $normalizedPosition['margin_used'] = $pos['margin'] ?? $pos['initialMargin'] ?? 0;
            $normalizedPosition['side'] = $pos['positionSide'] ?? '';
            $normalizedPosition['quantity'] = $pos['positionAmt'] ?? 0;
            
            $demoExchangeMap[$key] = $normalizedPosition;
        }
    }
    
    $positionStatus = [];
    
    // Check each database position against the correct exchange (demo or live)
    foreach ($dbPositions as $dbPos) {
        // Convert symbol to BingX format
        $symbol = $dbPos['symbol'];
        if (!strpos($symbol, 'USDT')) {
            $symbol = $symbol . '-USDT';
        }
        
        // Create key for lookup
        $side = strtoupper($dbPos['side']);
        $key = $symbol . '_' . $side;
        
        // Determine if this is a demo position
        $isDemo = false;
        if ($hasIsDemoColumn && isset($dbPos['is_demo'])) {
            $isDemo = $dbPos['is_demo'] == 1 || $dbPos['is_demo'] === '1' || $dbPos['is_demo'] === true;
        }
        // If no is_demo column, default to live (false)
        
        // Check against the appropriate exchange
        $exchangeMap = $isDemo ? $demoExchangeMap : $liveExchangeMap;
        $existsOnExchange = isset($exchangeMap[$key]);
        
        $positionStatus[$dbPos['id']] = [
            'exists_on_exchange' => $existsOnExchange,
            'symbol' => $dbPos['symbol'], 
            'side' => $dbPos['side'],
            'is_demo' => $isDemo,
            'checked_exchange' => $isDemo ? 'demo' : 'live'
        ];
    }
    
    $response = [
        'success' => true,
        'position_status' => $positionStatus,
        'debug' => [
            'has_is_demo_column' => $hasIsDemoColumn,
            'db_positions_count' => count($dbPositions),
            'live_exchange_positions' => count($livePositions),
            'demo_exchange_positions' => count($demoPositions)
        ]
    ];
    
    // Add detailed debug info if requested
    if (isset($_GET['debug'])) {
        $response['debug']['live_position_keys'] = array_keys($liveExchangeMap);
        $response['debug']['demo_position_keys'] = array_keys($demoExchangeMap);
        $response['debug']['database_positions'] = $dbPositions;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Check Positions API Error: " . $e->getMessage());
    
    http_response_code($e->getCode() ?: 400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>