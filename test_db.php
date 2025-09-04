<?php
// Test database connections

echo "Testing database connections...\n";

// Test 1: Root with no password
try {
    $pdo = new PDO('mysql:host=localhost', 'root', '');
    echo "✅ MySQL connection successful with root (no password)\n";
    $pdo = null;
} catch (Exception $e) {
    echo "❌ Root connection failed: " . $e->getMessage() . "\n";
}

// Test 2: Root with password
try {
    $pdo = new PDO('mysql:host=localhost', 'root', 'root');
    echo "✅ MySQL connection successful with root (password: root)\n";
    $pdo = null;
} catch (Exception $e) {
    echo "❌ Root/root connection failed: " . $e->getMessage() . "\n";
}

// Test 3: Current .env settings
try {
    $pdo = new PDO('mysql:host=localhost;dbname=vahid279_trade_assistant', 'vahid279_ashkan', '[$U#Zhq)SRHV');
    echo "✅ Production credentials work locally\n";
    $pdo = null;
} catch (Exception $e) {
    echo "❌ Production credentials failed: " . $e->getMessage() . "\n";
}

// Test 4: Check available databases with root
try {
    $pdo = new PDO('mysql:host=localhost', 'root', '');
    $stmt = $pdo->query('SHOW DATABASES');
    echo "📋 Available databases:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "   - " . $row['Database'] . "\n";
    }
    $pdo = null;
} catch (Exception $e) {
    echo "❌ Cannot list databases: " . $e->getMessage() . "\n";
}
?>