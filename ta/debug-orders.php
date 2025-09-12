<?php
/**
 * Debug script to check orders in database
 */

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

loadEnv(__DIR__ . '/.env');

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
    
    echo "<h2>All Orders with PENDING or NEW status:</h2>";
    $sql = "SELECT id, symbol, side, type, entry_level, quantity, price, leverage, status, created_at, signal_id 
            FROM orders 
            WHERE status IN ('NEW', 'PENDING') 
            ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($orders)) {
        echo "No pending or new orders found.<br>";
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Symbol</th><th>Side</th><th>Type</th><th>Entry Level</th><th>Quantity</th><th>Price</th><th>Leverage</th><th>Status</th><th>Created At</th><th>Signal ID</th></tr>";
        foreach ($orders as $order) {
            echo "<tr>";
            echo "<td>{$order['id']}</td>";
            echo "<td>{$order['symbol']}</td>";
            echo "<td>{$order['side']}</td>";
            echo "<td>{$order['type']}</td>";
            echo "<td>{$order['entry_level']}</td>";
            echo "<td>{$order['quantity']}</td>";
            echo "<td>{$order['price']}</td>";
            echo "<td>{$order['leverage']}</td>";
            echo "<td>{$order['status']}</td>";
            echo "<td>{$order['created_at']}</td>";
            echo "<td>{$order['signal_id']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h2>Orders that match UPDATED get_limit_orders.php query:</h2>";
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
                s.signal_type as direction
            FROM orders o
            LEFT JOIN signals s ON o.signal_id = s.id
            WHERE o.type = 'LIMIT' 
            AND o.status IN ('NEW', 'PENDING', 'TRIGGERED', 'FAILED', 'CANCELLED')
            ORDER BY o.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $limitOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($limitOrders)) {
        echo "No limit orders found matching current query.<br>";
        echo "<p><strong>Possible reasons:</strong></p>";
        echo "<ul>";
        echo "<li>Order type is not 'LIMIT'</li>";
        echo "<li>Order status is not in: NEW, PENDING, TRIGGERED, FAILED, CANCELLED</li>";
        echo "</ul>";
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Symbol</th><th>Side</th><th>Type</th><th>Entry Level</th><th>Quantity</th><th>Price</th><th>Leverage</th><th>Status</th><th>Created At</th><th>Direction</th></tr>";
        foreach ($limitOrders as $order) {
            echo "<tr>";
            echo "<td>{$order['id']}</td>";
            echo "<td>{$order['symbol']}</td>";
            echo "<td>{$order['side']}</td>";
            echo "<td>{$order['type']}</td>";
            echo "<td>{$order['entry_level']}</td>";
            echo "<td>{$order['quantity']}</td>";
            echo "<td>{$order['entry_price']}</td>";
            echo "<td>{$order['leverage']}</td>";
            echo "<td>{$order['status']}</td>";
            echo "<td>{$order['created_at']}</td>";
            echo "<td>{$order['direction']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>