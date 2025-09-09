<?php
/**
 * Signal Source Tracking & Win Rate Migration Script
 * Safely adds signal source tracking and win rate calculation capabilities
 */

header('Content-Type: text/plain');
echo "=== Signal Source Tracking Migration ===\n\n";

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
    
    // Read and execute migration SQL
    echo "Reading migration SQL file...\n";
    $migrationSQL = file_get_contents('signal_source_migration.sql');
    
    if (!$migrationSQL) {
        throw new Exception('Could not read signal_source_migration.sql file');
    }
    
    // Split into individual statements (rough split - good enough for our migration)
    $statements = explode(';', $migrationSQL);
    
    echo "Executing migration statements...\n\n";
    $executed = 0;
    $failed = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        
        // Skip empty statements and comments
        if (empty($statement) || 
            strpos($statement, '--') === 0 || 
            strpos($statement, 'USE ') === 0 ||
            strpos($statement, 'SELECT \'') === 0) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            $executed++;
            
            // Show progress for major operations
            if (strpos($statement, 'CREATE TABLE') === 0) {
                $tableName = '';
                if (preg_match('/CREATE TABLE[^`]*`?([^`\s]+)`?\s*\(/i', $statement, $matches)) {
                    $tableName = $matches[1];
                }
                echo "✅ Created table: {$tableName}\n";
            } elseif (strpos($statement, 'ALTER TABLE') === 0) {
                $tableName = '';
                if (preg_match('/ALTER TABLE\s+`?([^`\s]+)`?\s+/i', $statement, $matches)) {
                    $tableName = $matches[1];
                }
                echo "✅ Modified table: {$tableName}\n";
            } elseif (strpos($statement, 'CREATE OR REPLACE VIEW') === 0) {
                echo "✅ Created view: signal_source_performance\n";
            } elseif (strpos($statement, 'CREATE OR REPLACE PROCEDURE') === 0) {
                echo "✅ Created procedure: UpdateSignalSourceStats\n";
            } elseif (strpos($statement, 'CREATE OR REPLACE TRIGGER') === 0) {
                echo "✅ Created trigger: signal_status_update_trigger\n";
            } elseif (strpos($statement, 'INSERT IGNORE') === 0) {
                echo "✅ Inserted default signal sources\n";
            }
            
        } catch (PDOException $e) {
            $failed++;
            echo "⚠️  SQL Error: " . $e->getMessage() . "\n";
            echo "Statement: " . substr($statement, 0, 100) . "...\n\n";
        }
    }
    
    echo "\n📊 Migration Summary:\n";
    echo "✅ Executed: {$executed} statements\n";
    echo "❌ Failed: {$failed} statements\n";
    
    if ($failed === 0) {
        echo "\n🎉 Migration completed successfully!\n\n";
    } else {
        echo "\n⚠️  Migration completed with some warnings\n\n";
    }
    
    // Verify migration results
    echo "=== Verification ===\n";
    
    // Check signal_sources table
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM signal_sources");
        $count = $stmt->fetch()['count'];
        echo "✅ signal_sources table: {$count} sources available\n";
        
        // Show sources
        $stmt = $pdo->query("SELECT name, type FROM signal_sources ORDER BY name");
        while ($row = $stmt->fetch()) {
            echo "   - {$row['name']} ({$row['type']})\n";
        }
    } catch (Exception $e) {
        echo "❌ signal_sources table verification failed\n";
    }
    
    // Check enhanced signals table
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM signals LIKE 'source_id'");
        if ($stmt->rowCount() > 0) {
            echo "✅ signals table enhanced with source_id column\n";
        } else {
            echo "❌ signals table missing source_id column\n";
        }
        
        $stmt = $pdo->query("SHOW COLUMNS FROM signals LIKE 'final_pnl'");
        if ($stmt->rowCount() > 0) {
            echo "✅ signals table enhanced with final_pnl column\n";
        } else {
            echo "❌ signals table missing final_pnl column\n";
        }
    } catch (Exception $e) {
        echo "❌ signals table verification failed\n";
    }
    
    // Check signal_performance table
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM signal_performance");
        echo "✅ signal_performance table created\n";
    } catch (Exception $e) {
        echo "❌ signal_performance table verification failed\n";
    }
    
    // Check view
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM signal_source_performance");
        echo "✅ signal_source_performance view created\n";
    } catch (Exception $e) {
        echo "❌ signal_source_performance view verification failed\n";
    }
    
    echo "\n=== Next Steps ===\n";
    echo "1. ✅ Database migration complete\n";
    echo "2. 🔄 Update signal creation form to include source selection\n";
    echo "3. 🔄 Add target/stop-loss input fields\n";
    echo "4. 🔄 Implement signal closure workflow\n";
    echo "5. 🔄 Create win rate analytics dashboard\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    echo "\nTroubleshooting:\n";
    echo "1. Make sure XAMPP is running\n";
    echo "2. Check database credentials in .env file\n";
    echo "3. Ensure database 'crypto_trading' exists\n";
    echo "4. Backup database before running migration\n";
}

echo "\n=== Migration Complete ===\n";
?>