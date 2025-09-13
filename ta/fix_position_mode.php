<?php
require_once __DIR__ . '/auth/config.php';

try {
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    $database = getenv('DB_NAME') ?: 'crypto_trading';

    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Revert position 19 back to demo mode
    $stmt = $pdo->prepare("UPDATE positions SET is_demo = 1 WHERE id = 19");
    $stmt->execute();

    echo "Reverted position 19 back to DEMO mode\n";

    // Verify the change
    $stmt = $pdo->query("SELECT id, symbol, side, status, is_demo FROM positions WHERE id = 19");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $demoStatus = $row['is_demo'] ? 'DEMO' : 'LIVE';
    echo "Position 19 is now: $demoStatus\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>