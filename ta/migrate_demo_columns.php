<?php
/**
 * Migration script to add is_demo columns to positions and orders tables
 * Run this when XAMPP is available to add demo/live trading support
 */

header('Content-Type: text/plain');
echo "=== Demo/Live Trading Migration Script ===\n\n";

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
loadEnv('.env');

try {
    // Database connection
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: '';
    $password = getenv('DB_PASSWORD') ?: '';
    $database = getenv('DB_NAME') ?: 'crypto_trading';
    
    echo "Connecting to database: {$host}/{$database}\n";
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Database connection successful\n\n";
    
    // Check and add is_demo column to positions table
    echo "1. Checking positions table...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM positions LIKE 'is_demo'");
    $columnExists = $stmt->rowCount() > 0;
    
    if ($columnExists) {
        echo "   ✅ is_demo column already exists in positions table\n";
    } else {
        echo "   ⚠️  is_demo column missing - adding it...\n";
        $pdo->exec("ALTER TABLE positions ADD COLUMN is_demo BOOLEAN DEFAULT FALSE AFTER status");
        echo "   ✅ Successfully added is_demo column to positions table\n";
    }
    
    // Check and add is_demo column to orders table  
    echo "\n2. Checking orders table...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'is_demo'");
    $columnExists = $stmt->rowCount() > 0;
    
    if ($columnExists) {
        echo "   ✅ is_demo column already exists in orders table\n";
    } else {
        echo "   ⚠️  is_demo column missing - adding it...\n";
        $pdo->exec("ALTER TABLE orders ADD COLUMN is_demo BOOLEAN DEFAULT FALSE AFTER status");
        echo "   ✅ Successfully added is_demo column to orders table\n";
    }
    
    // Display table structures
    echo "\n3. Final table structures:\n";
    echo "\nPositions table columns:\n";
    $stmt = $pdo->query("DESCRIBE positions");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $marker = $row['Field'] === 'is_demo' ? ' <-- NEW' : '';
        echo "   - {$row['Field']} ({$row['Type']}) {$row['Null']} {$row['Default']}{$marker}\n";
    }
    
    echo "\nOrders table columns:\n";
    $stmt = $pdo->query("DESCRIBE orders");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $marker = $row['Field'] === 'is_demo' ? ' <-- NEW' : '';
        echo "   - {$row['Field']} ({$row['Type']}) {$row['Null']} {$row['Default']}{$marker}\n";
    }
    
    // Check for existing positions/orders and provide next steps
    echo "\n4. Data analysis:\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM positions WHERE status = 'OPEN'");
    $openPositions = $stmt->fetch()['count'];
    echo "   - Open positions: {$openPositions}\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE status IN ('NEW', 'PENDING', 'FILLED')");
    $activeOrders = $stmt->fetch()['count'];
    echo "   - Active orders: {$activeOrders}\n";
    
    if ($openPositions > 0 || $activeOrders > 0) {
        echo "\n⚠️  IMPORTANT:\n";
        echo "   Existing positions/orders will default to LIVE mode (is_demo = FALSE)\n";
        echo "   New positions/orders will properly inherit demo/live mode based on trading settings\n";
        echo "   If you need to mark existing positions as demo, run:\n";
        echo "   UPDATE positions SET is_demo = TRUE WHERE /* your conditions */;\n";
        echo "   UPDATE orders SET is_demo = TRUE WHERE /* your conditions */;\n";
    }
    
    echo "\n✅ Migration completed successfully!\n";
    echo "🎉 Demo/Live trading indicators should now work properly\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    echo "\nTroubleshooting:\n";
    echo "1. Make sure XAMPP is running\n";
    echo "2. Check database credentials in .env file\n";
    echo "3. Ensure database 'crypto_trading' exists\n";
    echo "4. Run database_setup.sql first if tables don't exist\n";
}

echo "\n=== Migration Complete ===\n";
?>