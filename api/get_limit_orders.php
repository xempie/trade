<?php
/**
 * Get Limit Orders API
 * Returns pending limit orders for the limit orders tab
 */

// Include authentication protection
require_once '../auth/api_protection.php';

// Protect this API endpoint
protectAPI();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

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

// Main execution
try {
    $pdo = getDbConnection();
    
    // Auto-cancel orders older than 2 hours
    $sql = "UPDATE orders SET 
            status = 'CANCELLED', 
            updated_at = NOW() 
            WHERE type = 'LIMIT' 
            AND status IN ('NEW', 'PENDING') 
            AND created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)";
    $pdo->exec($sql);
    
    // Get pending limit orders (not cancelled by auto-cancellation)
    $sql = "SELECT 
                o.id,
                o.symbol,
                o.side,
                o.entry_level,
                o.quantity,
                o.price as entry_price,
                o.leverage,
                o.status,
                o.created_at,
                s.signal_type as direction
            FROM orders o
            LEFT JOIN signals s ON o.signal_id = s.id
            WHERE o.type = 'LIMIT' 
            AND o.status IN ('NEW', 'PENDING', 'TRIGGERED')
            AND o.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
            ORDER BY o.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process orders to match watchlist format
    $processedOrders = [];
    foreach ($orders as $order) {
        // Calculate margin amount (approximation based on position size and leverage)
        $marginAmount = ($order['quantity'] * $order['entry_price']) / $order['leverage'];
        
        // Clean symbol (remove -USDT suffix)
        $symbol = str_replace('-USDT', '', $order['symbol']);
        
        // Convert to watchlist-compatible format
        $processedOrder = [
            'id' => $order['id'],
            'symbol' => $symbol,
            'entry_price' => floatval($order['entry_price']),
            'entry_type' => strtolower($order['entry_level']),
            'direction' => strtolower($order['direction'] ?: ($order['side'] === 'BUY' ? 'long' : 'short')),
            'margin_amount' => $marginAmount,
            'status' => $order['status'],
            'created_at' => $order['created_at'],
            'is_triggered' => $order['status'] === 'TRIGGERED',
            'percentage' => null, // Will be calculated on frontend like watchlist
            'initial_price' => null // Will be fetched on frontend like watchlist
        ];
        
        $processedOrders[] = $processedOrder;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $processedOrders,
        'count' => count($processedOrders)
    ]);
    
} catch (Exception $e) {
    error_log("Get Limit Orders API Error: " . $e->getMessage());
    
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>