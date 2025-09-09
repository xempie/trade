<?php
// Check if is_demo column exists in positions table and add if missing
header('Content-Type: text/plain');

echo "=== Checking is_demo Column in Positions Table ===\n\n";

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

// Database connection
try {
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: '';
    $password = getenv('DB_PASSWORD') ?: '';
    $database = getenv('DB_NAME') ?: 'crypto_trading';
    
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Database connection successful\n\n";
    
    // Check if is_demo column exists
    echo "Checking positions table structure...\n";
    $stmt = $pdo->query("DESCRIBE positions");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $isDemoExists = false;
    echo "Current columns in positions table:\n";
    foreach ($columns as $column) {
        echo "  - {$column['Field']} ({$column['Type']}) - {$column['Null']} - {$column['Default']}\n";
        if ($column['Field'] === 'is_demo') {
            $isDemoExists = true;
        }
    }
    
    if ($isDemoExists) {
        echo "\n✅ is_demo column exists!\n";
        
        // Check current data
        $stmt = $pdo->query("SELECT id, symbol, side, is_demo, status FROM positions WHERE status = 'OPEN' LIMIT 5");
        $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($positions) {
            echo "\nCurrent open positions with is_demo values:\n";
            foreach ($positions as $pos) {
                $demo_status = $pos['is_demo'] ? 'DEMO' : 'LIVE';
                echo "  - ID {$pos['id']}: {$pos['symbol']} {$pos['side']} - {$demo_status}\n";
            }
        } else {
            echo "\nNo open positions found in database.\n";
        }
    } else {
        echo "\n❌ is_demo column does NOT exist!\n";
        echo "Adding is_demo column to positions table...\n";
        
        try {
            $pdo->exec("ALTER TABLE positions ADD COLUMN is_demo BOOLEAN DEFAULT FALSE AFTER status");
            echo "✅ Successfully added is_demo column to positions table!\n";
            
            // Verify the column was added
            $stmt = $pdo->query("DESCRIBE positions");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "\nUpdated positions table structure:\n";
            foreach ($columns as $column) {
                echo "  - {$column['Field']} ({$column['Type']}) - {$column['Null']} - {$column['Default']}\n";
            }
            
        } catch (Exception $e) {
            echo "❌ Failed to add is_demo column: " . $e->getMessage() . "\n";
        }
    }
    
    // Also check orders table for consistency
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "Checking orders table for is_demo column...\n";
    
    $stmt = $pdo->query("DESCRIBE orders");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $ordersHasDemo = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'is_demo') {
            $ordersHasDemo = true;
            break;
        }
    }
    
    if ($ordersHasDemo) {
        echo "✅ orders table already has is_demo column\n";
    } else {
        echo "❌ orders table missing is_demo column - adding it...\n";
        try {
            $pdo->exec("ALTER TABLE orders ADD COLUMN is_demo BOOLEAN DEFAULT FALSE AFTER status");
            echo "✅ Successfully added is_demo column to orders table!\n";
        } catch (Exception $e) {
            echo "❌ Failed to add is_demo column to orders table: " . $e->getMessage() . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
}

echo "\n=== Check Complete ===\n";
?>