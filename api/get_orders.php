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

// Get orders from database
function getOrders($pdo, $filters = []) {
    try {
        $sql = "SELECT o.*, s.signal_type, s.symbol as signal_symbol 
                FROM orders o 
                LEFT JOIN signals s ON o.signal_id = s.id 
                WHERE 1=1";
        $params = [];
        
        // Add filters
        if (!empty($filters['status'])) {
            $sql .= " AND o.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['symbol'])) {
            $sql .= " AND o.symbol LIKE :symbol";
            $params[':symbol'] = '%' . $filters['symbol'] . '%';
        }
        
        if (!empty($filters['signal_id'])) {
            $sql .= " AND o.signal_id = :signal_id";
            $params[':signal_id'] = $filters['signal_id'];
        }
        
        $sql .= " ORDER BY o.created_at DESC";
        
        // Add limit
        $limit = isset($filters['limit']) ? intval($filters['limit']) : 50;
        $sql .= " LIMIT :limit";
        $params[':limit'] = $limit;
        
        $stmt = $pdo->prepare($sql);
        
        // Bind limit parameter separately
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        
        // Bind other parameters
        foreach ($params as $key => $value) {
            if ($key !== ':limit') {
                $stmt->bindValue($key, $value);
            }
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch orders: ' . $e->getMessage());
    }
}

// Get positions from database
function getPositions($pdo, $filters = []) {
    try {
        $sql = "SELECT p.*, s.signal_type 
                FROM positions p 
                LEFT JOIN signals s ON p.signal_id = s.id 
                WHERE 1=1";
        $params = [];
        
        // Add filters
        if (!empty($filters['status'])) {
            $sql .= " AND p.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['symbol'])) {
            $sql .= " AND p.symbol LIKE :symbol";
            $params[':symbol'] = '%' . $filters['symbol'] . '%';
        }
        
        $sql .= " ORDER BY p.opened_at DESC";
        
        // Add limit
        $limit = isset($filters['limit']) ? intval($filters['limit']) : 20;
        $sql .= " LIMIT :limit";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        
        // Bind other parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch positions: ' . $e->getMessage());
    }
}

// Get signals from database
function getSignals($pdo, $filters = []) {
    try {
        $sql = "SELECT s.*, 
                COUNT(o.id) as total_orders,
                COUNT(CASE WHEN o.status = 'FILLED' THEN 1 END) as filled_orders,
                SUM(CASE WHEN o.status = 'FILLED' THEN o.quantity ELSE 0 END) as total_filled_quantity
                FROM signals s 
                LEFT JOIN orders o ON s.id = o.signal_id 
                WHERE 1=1";
        $params = [];
        
        // Add filters
        if (!empty($filters['status'])) {
            $sql .= " AND s.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['symbol'])) {
            $sql .= " AND s.symbol LIKE :symbol";
            $params[':symbol'] = '%' . $filters['symbol'] . '%';
        }
        
        $sql .= " GROUP BY s.id ORDER BY s.created_at DESC";
        
        // Add limit
        $limit = isset($filters['limit']) ? intval($filters['limit']) : 20;
        $sql .= " LIMIT :limit";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        
        // Bind other parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch signals: ' . $e->getMessage());
    }
}

// Main execution
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Only GET method allowed');
    }
    
    $pdo = getDbConnection();
    
    // Get request type
    $type = $_GET['type'] ?? 'orders';
    
    // Build filters from query parameters
    $filters = [
        'status' => $_GET['status'] ?? null,
        'symbol' => $_GET['symbol'] ?? null,
        'signal_id' => $_GET['signal_id'] ?? null,
        'limit' => $_GET['limit'] ?? null
    ];
    
    // Remove null values
    $filters = array_filter($filters, function($value) {
        return $value !== null && $value !== '';
    });
    
    $data = [];
    
    switch ($type) {
        case 'orders':
            $data = getOrders($pdo, $filters);
            break;
            
        case 'positions':
            $data = getPositions($pdo, $filters);
            break;
            
        case 'signals':
            $data = getSignals($pdo, $filters);
            break;
            
        default:
            throw new Exception('Invalid type parameter. Use: orders, positions, or signals');
    }
    
    echo json_encode([
        'success' => true,
        'type' => $type,
        'data' => $data,
        'count' => count($data),
        'filters_applied' => $filters
    ]);
    
} catch (Exception $e) {
    error_log("Get Orders API Error: " . $e->getMessage());
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