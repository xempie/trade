<?php
/**
 * Debug position matching between database and BingX API
 */

require_once '../auth/api_protection.php';
protectAPI();

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

// Get BingX positions from exchange
function getBingXPositions() {
    $apiKey = getenv('BINGX_API_KEY') ?: '';
    $apiSecret = getenv('BINGX_SECRET_KEY') ?: '';
    
    if (empty($apiKey) || empty($apiSecret)) {
        return [];
    }
    
    try {
        $timestamp = round(microtime(true) * 1000);
        $queryString = "timestamp={$timestamp}";
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        
        $url = "https://open-api.bingx.com/openApi/swap/v2/user/positions?" . $queryString . "&signature=" . $signature;
        
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

try {
    $pdo = getDbConnection();
    
    // Get OPEN positions from database
    $sql = "SELECT * FROM positions WHERE status = 'OPEN'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $dbPositions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get BingX positions
    $bingxPositions = getBingXPositions();
    
    // Create debugging output
    $debug = [
        'database_positions' => [],
        'bingx_positions' => [],
        'bingx_map' => [],
        'matching_attempts' => []
    ];
    
    // Process database positions
    foreach ($dbPositions as $dbPos) {
        $originalSymbol = $dbPos['symbol'];
        $convertedSymbol = $originalSymbol;
        if (!strpos($originalSymbol, 'USDT')) {
            $convertedSymbol = $originalSymbol . '-USDT';
        }
        
        $side = strtoupper($dbPos['side']);
        $key = $convertedSymbol . '_' . $side;
        
        $debug['database_positions'][] = [
            'id' => $dbPos['id'],
            'original_symbol' => $originalSymbol,
            'converted_symbol' => $convertedSymbol,
            'side' => $side,
            'lookup_key' => $key
        ];
    }
    
    // Process BingX positions
    foreach ($bingxPositions as $bingxPos) {
        $debug['bingx_positions'][] = [
            'symbol' => $bingxPos['symbol'] ?? 'N/A',
            'positionSide' => $bingxPos['positionSide'] ?? 'N/A',
            'positionAmt' => $bingxPos['positionAmt'] ?? 'N/A',
            'unrealizedProfit' => $bingxPos['unrealizedProfit'] ?? 'N/A',
            'markPrice' => $bingxPos['markPrice'] ?? 'N/A'
        ];
        
        if (isset($bingxPos['symbol']) && isset($bingxPos['positionSide']) && floatval($bingxPos['positionAmt']) != 0) {
            $key = $bingxPos['symbol'] . '_' . $bingxPos['positionSide'];
            $debug['bingx_map'][$key] = [
                'unrealizedProfit' => $bingxPos['unrealizedProfit'] ?? 0,
                'markPrice' => $bingxPos['markPrice'] ?? 0,
                'positionAmt' => $bingxPos['positionAmt'] ?? 0
            ];
        }
    }
    
    // Test matching
    foreach ($dbPositions as $dbPos) {
        $originalSymbol = $dbPos['symbol'];
        $convertedSymbol = $originalSymbol;
        if (!strpos($originalSymbol, 'USDT')) {
            $convertedSymbol = $originalSymbol . '-USDT';
        }
        
        $side = strtoupper($dbPos['side']);
        $key = $convertedSymbol . '_' . $side;
        
        $matched = isset($debug['bingx_map'][$key]);
        
        $debug['matching_attempts'][] = [
            'db_position_id' => $dbPos['id'],
            'lookup_key' => $key,
            'matched' => $matched,
            'live_data' => $matched ? $debug['bingx_map'][$key] : null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'debug' => $debug
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Debug Position Matching Error: " . $e->getMessage());
    
    http_response_code($e->getCode() ?: 400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>