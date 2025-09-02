<?php
/**
 * Debug API to check orders in database
 */

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

try {
    $pdo = getDbConnection();
    
    // Get all orders with PENDING or NEW status
    $sql = "SELECT id, symbol, side, type, entry_level, quantity, price, leverage, status, created_at, signal_id, bingx_order_id 
            FROM orders 
            WHERE status IN ('NEW', 'PENDING') 
            ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $allOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get orders that match current get_limit_orders.php query
    $sql = "SELECT 
                o.id,
                o.symbol,
                o.side,
                o.type,
                o.entry_level,
                o.quantity,
                o.price as entry_price,
                o.leverage,
                o.status,
                o.created_at,
                o.bingx_order_id,
                s.signal_type as direction
            FROM orders o
            LEFT JOIN signals s ON o.signal_id = s.id
            WHERE o.type = 'LIMIT' 
            AND o.status IN ('NEW', 'PENDING', 'TRIGGERED')
            AND o.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY o.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $limitOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'all_pending_orders' => $allOrders,
        'limit_orders_matching_query' => $limitOrders,
        'all_pending_count' => count($allOrders),
        'limit_orders_count' => count($limitOrders),
        'query_conditions' => [
            'type' => 'LIMIT',
            'status' => 'NEW, PENDING, TRIGGERED',
            'created_within' => '24 HOUR',
            'current_time' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>