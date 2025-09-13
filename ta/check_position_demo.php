<?php
require_once __DIR__ . '/auth/config.php';

try {
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    $database = getenv('DB_NAME') ?: 'crypto_trading';

    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT id, symbol, side, status, is_demo FROM positions WHERE status='OPEN' ORDER BY id DESC LIMIT 5");

    echo "Current open positions:\n";
    echo "ID | Symbol | Side | Status | Demo Mode\n";
    echo "---|--------|------|--------|-----------\n";

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $demoStatus = $row['is_demo'] ? 'DEMO' : 'LIVE';
        echo "{$row['id']} | {$row['symbol']} | {$row['side']} | {$row['status']} | $demoStatus\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>