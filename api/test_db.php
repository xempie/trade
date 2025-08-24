<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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

try {
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: '';
    $password = getenv('DB_PASSWORD') ?: '';
    $database = getenv('DB_NAME') ?: 'crypto_trading';
    
    echo json_encode([
        'env_check' => [
            'host' => $host,
            'user' => $user,
            'password' => $password ? 'SET' : 'EMPTY',
            'database' => $database
        ]
    ]);
    
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Test if watchlist table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'watchlist'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        // Get table structure
        $stmt = $pdo->query("DESCRIBE watchlist");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'connection' => 'OK',
        'database' => $database,
        'watchlist_table_exists' => $tableExists,
        'table_structure' => $tableExists ? $columns : null
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'env_vars' => [
            'DB_HOST' => getenv('DB_HOST'),
            'DB_USER' => getenv('DB_USER'), 
            'DB_PASSWORD' => getenv('DB_PASSWORD') ? 'SET' : 'EMPTY',
            'DB_NAME' => getenv('DB_NAME')
        ]
    ]);
}
?>