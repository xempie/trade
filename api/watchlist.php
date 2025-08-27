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

// Add watchlist items
function addWatchlistItem($pdo, $data) {
    $sql = "INSERT INTO watchlist (symbol, entry_price, entry_type, direction, margin_amount, percentage, initial_price, status) 
            VALUES (:symbol, :entry_price, :entry_type, :direction, :margin_amount, :percentage, :initial_price, 'active')";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        ':symbol' => $data['symbol'],
        ':entry_price' => $data['entry_price'],
        ':entry_type' => $data['entry_type'],
        ':direction' => $data['direction'],
        ':margin_amount' => $data['margin_amount'],
        ':percentage' => $data['percentage'],
        ':initial_price' => $data['initial_price'] ?? null
    ]);
}

// Get watchlist items
function getWatchlistItems($pdo, $limit = 20) {
    $sql = "SELECT * FROM watchlist WHERE status = 'active' ORDER BY created_at DESC LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Update watchlist item status
function updateWatchlistStatus($pdo, $id, $status) {
    $sql = "UPDATE watchlist SET status = :status, triggered_at = :triggered_at WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        ':status' => $status,
        ':triggered_at' => $status === 'triggered' ? date('Y-m-d H:i:s') : null,
        ':id' => $id
    ]);
}

// Delete watchlist item
function deleteWatchlistItem($pdo, $id) {
    $sql = "DELETE FROM watchlist WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([':id' => $id]);
}

// Main execution
try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Debug logging
    error_log("Watchlist API called with method: " . $method);
    
    $pdo = getDbConnection();
    
    switch ($method) {
        case 'GET':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $items = getWatchlistItems($pdo, $limit);
            echo json_encode([
                'success' => true,
                'data' => $items
            ]);
            break;
            
        case 'POST':
            $rawInput = file_get_contents('php://input');
            error_log("Raw input: " . $rawInput);
            
            $input = json_decode($rawInput, true);
            
            if (!$input) {
                throw new Exception('Invalid JSON input: ' . json_last_error_msg());
            }
            
            error_log("Parsed input: " . print_r($input, true));
            
            // Validate required fields
            $required = ['symbol', 'direction', 'watchlist_items'];
            foreach ($required as $field) {
                if (!isset($input[$field]) || empty($input[$field])) {
                    throw new Exception("Field '$field' is required");
                }
            }
            
            $symbol = strtoupper(trim($input['symbol']));
            $direction = strtolower(trim($input['direction']));
            $watchlistItems = $input['watchlist_items'];
            
            if (!in_array($direction, ['long', 'short'])) {
                throw new Exception("Direction must be 'long' or 'short'");
            }
            
            $addedItems = [];
            
            // Add each watchlist item
            foreach ($watchlistItems as $item) {
                if (!isset($item['entry_type'], $item['entry_price'], $item['margin_amount'])) {
                    continue; // Skip incomplete items
                }
                
                $watchlistData = [
                    'symbol' => $symbol,
                    'entry_price' => floatval($item['entry_price']),
                    'entry_type' => $item['entry_type'],
                    'direction' => $direction,
                    'margin_amount' => floatval($item['margin_amount']),
                    'percentage' => isset($item['percentage']) ? floatval($item['percentage']) : null,
                    'initial_price' => isset($item['initial_price']) ? floatval($item['initial_price']) : null
                ];
                
                if (addWatchlistItem($pdo, $watchlistData)) {
                    $addedItems[] = [
                        'id' => $pdo->lastInsertId(),
                        'symbol' => $symbol,
                        'entry_type' => $item['entry_type'],
                        'entry_price' => $item['entry_price'],
                        'direction' => $direction,
                        'margin_amount' => $item['margin_amount']
                    ];
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => count($addedItems) . ' watchlist item(s) added',
                'data' => $addedItems
            ]);
            break;
            
        case 'PUT':
            // Update watchlist item status
            $pathInfo = $_SERVER['PATH_INFO'] ?? '';
            $segments = explode('/', trim($pathInfo, '/'));
            
            if (count($segments) < 1 || !is_numeric($segments[0])) {
                throw new Exception('Watchlist ID is required');
            }
            
            $id = (int)$segments[0];
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['status'])) {
                throw new Exception('Status is required');
            }
            
            $status = $input['status'];
            if (!in_array($status, ['active', 'triggered', 'cancelled'])) {
                throw new Exception("Status must be 'active', 'triggered', or 'cancelled'");
            }
            
            if (updateWatchlistStatus($pdo, $id, $status)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Watchlist item updated'
                ]);
            } else {
                throw new Exception('Failed to update watchlist item');
            }
            break;
            
        case 'DELETE':
            // Delete watchlist item
            $pathInfo = $_SERVER['PATH_INFO'] ?? '';
            $segments = explode('/', trim($pathInfo, '/'));
            
            if (count($segments) < 1 || !is_numeric($segments[0])) {
                throw new Exception('Watchlist ID is required');
            }
            
            $id = (int)$segments[0];
            
            if (deleteWatchlistItem($pdo, $id)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Watchlist item deleted'
                ]);
            } else {
                throw new Exception('Failed to delete watchlist item');
            }
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    error_log("Watchlist API Error: " . $e->getMessage());
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