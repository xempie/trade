<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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

// Get limit orders with NEW or PENDING status
function getLimitOrders($pdo, $limit = 20) {
    $sql = "SELECT id, symbol, side, type, entry_level, quantity, price, leverage, status, created_at, signal_id
            FROM orders 
            WHERE type = 'LIMIT' AND status IN ('NEW', 'PENDING')
            ORDER BY created_at DESC 
            LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Update limit order status
function updateLimitOrderStatus($pdo, $id, $status) {
    $sql = "UPDATE orders SET status = :status WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        ':status' => $status,
        ':id' => $id
    ]);
}

// Delete limit order
function deleteLimitOrder($pdo, $id) {
    $sql = "DELETE FROM orders WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([':id' => $id]);
}

// Main execution
try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Debug logging
    error_log("Limit Orders API called with method: " . $method);
    
    $pdo = getDbConnection();
    
    switch ($method) {
        case 'GET':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $orders = getLimitOrders($pdo, $limit);
            
            // Format orders data to match watchlist structure for frontend compatibility
            $formattedOrders = [];
            foreach ($orders as $order) {
                $formattedOrders[] = [
                    'id' => $order['id'],
                    'symbol' => $order['symbol'],
                    'entry_price' => $order['price'],
                    'entry_type' => strtolower($order['entry_level']), // Convert ENTRY_2 to entry_2, etc.
                    'direction' => strtolower($order['side'] === 'BUY' ? 'long' : 'short'),
                    'margin_amount' => $order['quantity'], // Using quantity as margin amount equivalent
                    'percentage' => null, // Will be calculated on frontend
                    'initial_price' => null, // Not applicable for limit orders
                    'status' => strtolower($order['status']), // Convert to lowercase for consistency
                    'created_at' => $order['created_at'],
                    'leverage' => $order['leverage'],
                    'order_type' => $order['type'],
                    'signal_id' => $order['signal_id']
                ];
            }
            
            echo json_encode([
                'success' => true,
                'data' => $formattedOrders
            ]);
            break;
            
        case 'PUT':
            // Update limit order status
            $pathInfo = $_SERVER['PATH_INFO'] ?? '';
            $segments = explode('/', trim($pathInfo, '/'));
            
            if (count($segments) < 1 || !is_numeric($segments[0])) {
                throw new Exception('Order ID is required');
            }
            
            $id = (int)$segments[0];
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['status'])) {
                throw new Exception('Status is required');
            }
            
            $status = strtoupper($input['status']); // Convert to uppercase for database
            if (!in_array($status, ['NEW', 'PENDING', 'CANCELLED', 'FAILED'])) {
                throw new Exception("Status must be 'NEW', 'PENDING', 'CANCELLED', or 'FAILED'");
            }
            
            if (updateLimitOrderStatus($pdo, $id, $status)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Limit order updated'
                ]);
            } else {
                throw new Exception('Failed to update limit order');
            }
            break;
            
        case 'DELETE':
            // Delete limit order
            $pathInfo = $_SERVER['PATH_INFO'] ?? '';
            $segments = explode('/', trim($pathInfo, '/'));
            
            if (count($segments) < 1 || !is_numeric($segments[0])) {
                throw new Exception('Order ID is required');
            }
            
            $id = (int)$segments[0];
            
            if (deleteLimitOrder($pdo, $id)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Limit order deleted'
                ]);
            } else {
                throw new Exception('Failed to delete limit order');
            }
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    error_log("Limit Orders API Error: " . $e->getMessage());
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