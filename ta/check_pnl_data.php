<?php
require_once __DIR__ . '/auth/config.php';

try {
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    $database = getenv('DB_NAME') ?: 'crypto_trading';

    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== CURRENT POSITION DATA IN DATABASE ===\n\n";

    $stmt = $pdo->query("SELECT id, symbol, side, entry_price, size, margin_used, leverage, status, opened_at FROM positions WHERE status='OPEN' ORDER BY id DESC LIMIT 5");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Position ID: {$row['id']}\n";
        echo "Symbol: {$row['symbol']}\n";
        echo "Side: {$row['side']}\n";
        echo "Entry Price: {$row['entry_price']}\n";
        echo "Size: {$row['size']} tokens\n";
        echo "Margin Used: {$row['margin_used']} USDT\n";
        echo "Leverage: {$row['leverage']}x\n";
        echo "Opened: {$row['opened_at']}\n";
        echo "\n";

        // Calculate what our P&L should be with current market price
        // We need to get current market price to calculate P&L
        echo "=== P&L CALCULATION CHECK ===\n";
        echo "Entry Price: {$row['entry_price']}\n";
        echo "Position Size: {$row['size']} tokens\n";
        echo "Position Value at Entry: " . ($row['entry_price'] * $row['size']) . " USDT\n";
        echo "Margin Used: {$row['margin_used']} USDT\n";
        echo "Leverage: {$row['leverage']}x\n";
        echo "\n";
        echo "For P&L calculation, we need current market price.\n";
        echo "P&L Formula for LONG: (Current Price - Entry Price) × Size\n";
        echo "P&L Formula for SHORT: (Entry Price - Current Price) × Size\n";
        echo "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>