<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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
$envPath = __DIR__ . '/../.env';
loadEnv($envPath);

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

// Close position on BingX
function closeBingXPosition($apiKey, $apiSecret, $symbol, $side, $quantity) {
    try {
        $timestamp = round(microtime(true) * 1000);
        
        // Determine the opposite side for closing
        $closeSide = $side === 'LONG' ? 'SELL' : 'BUY';
        
        $params = [
            'symbol' => $symbol,
            'side' => $closeSide,
            'type' => 'MARKET',
            'quantity' => $quantity,
            'positionSide' => strtoupper($side),
            'timestamp' => $timestamp
        ];
        
        $queryString = http_build_query($params);
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        
        // Debug logging
        error_log("Closing BingX Position Parameters: " . json_encode($params));
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://open-api.bingx.com/openApi/swap/v2/trade/order");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $queryString . "&signature=" . $signature);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'X-BX-APIKEY: ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception('cURL Error: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new Exception("BingX API HTTP error: {$httpCode}. Response: " . $response);
        }
        
        $data = json_decode($response, true);
        
        if (!$data) {
            throw new Exception('Invalid JSON response from BingX: ' . json_last_error_msg());
        }
        
        if (!isset($data['code']) || $data['code'] !== 0) {
            $errorMsg = isset($data['msg']) ? $data['msg'] : 'Unknown API error';
            throw new Exception('BingX Close Position Error: ' . $errorMsg);
        }
        
        return [
            'success' => true,
            'order_id' => $data['data']['orderId'] ?? null,
            'data' => $data['data']
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Update position status in database
function updatePositionStatus($pdo, $positionId, $status = 'CLOSED') {
    try {
        $sql = "UPDATE positions SET status = :status, closed_at = NOW() WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':status' => $status,
            ':id' => $positionId
        ]);
    } catch (Exception $e) {
        error_log("Database error updating position status: " . $e->getMessage());
        return false;
    }
}

// Get position details
function getPositionDetails($pdo, $positionId) {
    try {
        $sql = "SELECT * FROM positions WHERE id = :id AND status = 'OPEN'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $positionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Database error getting position details: " . $e->getMessage());
        return null;
    }
}

// Main execution
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }
    
    // Validate required fields
    $required = ['position_id', 'symbol', 'direction'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }
    
    if (empty($apiKey) || empty($apiSecret)) {
        throw new Exception('BingX API credentials not configured');
    }
    
    $pdo = getDbConnection();
    $positionId = intval($input['position_id']);
    $symbol = strtoupper(trim($input['symbol']));
    $direction = strtoupper(trim($input['direction']));
    
    // Get position details from database
    $position = getPositionDetails($pdo, $positionId);
    if (!$position) {
        throw new Exception('Position not found or already closed');
    }
    
    // Convert symbol to BingX format
    $bingxSymbol = $symbol;
    if (!strpos($bingxSymbol, 'USDT')) {
        $bingxSymbol = $bingxSymbol . '-USDT';
    }
    
    // Close position on BingX
    $closeResult = closeBingXPosition(
        $apiKey, 
        $apiSecret, 
        $bingxSymbol, 
        $direction,
        $position['size']
    );
    
    if ($closeResult['success']) {
        // Update position status in database
        $updated = updatePositionStatus($pdo, $positionId, 'CLOSED');
        
        if ($updated) {
            echo json_encode([
                'success' => true,
                'message' => "Position closed successfully",
                'bingx_order_id' => $closeResult['order_id'],
                'position_id' => $positionId
            ]);
        } else {
            // Position closed on exchange but database update failed
            echo json_encode([
                'success' => true,
                'message' => "Position closed on exchange, but database update failed",
                'bingx_order_id' => $closeResult['order_id'],
                'warning' => 'Database sync issue'
            ]);
        }
    } else {
        throw new Exception($closeResult['error']);
    }
    
} catch (Exception $e) {
    error_log("Close Position API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code($e->getCode() ?: 400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'method' => $_SERVER['REQUEST_METHOD']
        ]
    ]);
}
?>