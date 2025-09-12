<?php
// Simple migration runner
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment variables
if (file_exists('.env')) {
    $lines = file('.env');
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        putenv("$key=$value");
    }
}

// Database connection
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$database = getenv('DB_NAME') ?: 'crypto_trading';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "Connected to database successfully.\n";
    
    // Read and execute the migration
    $migrationSql = file_get_contents('migrations/add_demo_mode_support.sql');
    
    // Remove comments and split by semicolons
    $statements = explode(';', $migrationSql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '--') === 0 || strpos($statement, 'USE ') === 0) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            echo "Executed: " . substr($statement, 0, 50) . "...\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "Column already exists, skipping: " . substr($statement, 0, 50) . "...\n";
            } else {
                echo "Error executing: " . substr($statement, 0, 50) . "...\n";
                echo "Error: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "Migration completed!\n";
    
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
}
?>