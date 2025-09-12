<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=crypto_trading', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Tables in crypto_trading database:\n";
    $stmt = $pdo->query('SHOW TABLES');
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo "  - " . $row[0] . "\n";
    }
    
    // Check if watchlist table exists and has data
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM watchlist");
    $result = $stmt->fetch();
    echo "\nWatchlist table has " . $result['count'] . " rows\n";
    
    // Show sample data if any exists
    if ($result['count'] > 0) {
        echo "\nSample watchlist data:\n";
        $stmt = $pdo->query("SELECT * FROM watchlist LIMIT 3");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  ID: {$row['id']}, Symbol: {$row['symbol']}, Entry Price: {$row['entry_price']}, Status: {$row['status']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>