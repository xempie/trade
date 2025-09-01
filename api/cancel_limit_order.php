<?php
/**
 * API to cancel/ignore limit orders with token authentication
 * Used by Telegram bot links to cancel limit orders
 */

require_once 'auth_token.php';
// Database config loaded via loadEnv function below

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
    // Authenticate request
    $payload = authenticateApiRequest();
    
    if (!isset($payload['order_id']) || !isset($payload['action'])) {
        throw new Exception('Invalid token payload');
    }
    
    if ($payload['action'] !== 'cancel_order') {
        throw new Exception('Invalid action for this endpoint');
    }
    
    $orderId = intval($payload['order_id']);
    
    $pdo = getDbConnection();
    
    // Get the order details first
    $sql = "SELECT o.*, s.signal_type 
            FROM orders o 
            LEFT JOIN signals s ON o.signal_id = s.id 
            WHERE o.id = :order_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':order_id' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    // Check if order can be cancelled
    if (!in_array($order['status'], ['NEW', 'PENDING'])) {
        echo json_encode([
            'success' => true,
            'message' => 'Order was already processed or cancelled',
            'current_status' => $order['status']
        ]);
        exit;
    }
    
    // Update order status to cancelled
    $sql = "UPDATE orders SET 
            status = 'CANCELLED', 
            updated_at = NOW() 
            WHERE id = :order_id";
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([':order_id' => $orderId]);
    
    if (!$success) {
        throw new Exception('Failed to cancel order in database');
    }
    
    // Also remove from watchlist if it exists
    if ($order['type'] === 'LIMIT' && in_array($order['entry_level'], ['ENTRY_2', 'ENTRY_3'])) {
        $entryType = strtolower($order['entry_level']);
        $symbol = str_replace('-USDT', '', $order['symbol']);
        
        $sql = "UPDATE watchlist SET 
                status = 'cancelled', 
                updated_at = NOW() 
                WHERE symbol = :symbol 
                AND entry_type = :entry_type 
                AND entry_price = :entry_price 
                AND status = 'active'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':symbol' => $symbol,
            ':entry_type' => $entryType,
            ':entry_price' => floatval($order['price'])
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Limit order cancelled successfully',
        'order_id' => $orderId,
        'symbol' => $order['symbol'],
        'entry_level' => $order['entry_level'],
        'price' => floatval($order['price'])
    ]);
    
} catch (Exception $e) {
    error_log("Cancel Limit Order API Error: " . $e->getMessage());
    
    http_response_code($e->getCode() ?: 400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>