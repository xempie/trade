<?php
/**
 * Cronjob Setup and Testing Script
 * Helps test cronjob functionality and validate configuration
 */

echo "Crypto Trading Management - Cronjob Setup\n";
echo "=========================================\n\n";

// Check if running from command line
if (php_sapi_name() !== 'cli') {
    echo "ERROR: This script must be run from command line\n";
    echo "Usage: php setup-cronjobs.php [test|validate|help]\n";
    exit(1);
}

$command = $argv[1] ?? 'help';

switch ($command) {
    case 'test':
        testCronjobs();
        break;
    case 'validate':
        validateConfiguration();
        break;
    case 'help':
    default:
        showHelp();
        break;
}

function showHelp() {
    echo "Available commands:\n\n";
    echo "php setup-cronjobs.php test      - Test all cronjob scripts\n";
    echo "php setup-cronjobs.php validate  - Validate configuration\n";
    echo "php setup-cronjobs.php help      - Show this help\n\n";
    
    echo "Setup Instructions:\n";
    echo "==================\n\n";
    
    echo "1. Linux/Production Setup:\n";
    echo "   - Copy crontab-config.txt to your server\n";
    echo "   - Update paths in crontab-config.txt\n";
    echo "   - Run: crontab crontab-config.txt\n";
    echo "   - Create log directory: mkdir -p /var/log/crypto-trading\n\n";
    
    echo "2. Windows/XAMPP Development:\n";
    echo "   - Use run-cronjobs-windows.bat for manual testing\n";
    echo "   - Set up Windows Task Scheduler for automation:\n";
    echo "     * Create task for each cronjob script\n";
    echo "     * Use: C:\\xampp\\php\\php.exe C:\\xampp\\htdocs\\trade\\jobs\\script-name.php\n\n";
    
    echo "3. Environment Setup:\n";
    echo "   - Ensure .env file exists with API credentials\n";
    echo "   - Test database connection\n";
    echo "   - Verify BingX API access\n";
    echo "   - Configure Telegram bot (optional)\n\n";
}

function validateConfiguration() {
    echo "Validating Configuration...\n";
    echo "===========================\n\n";
    
    $issues = [];
    $warnings = [];
    
    // Check .env file
    $envPath = __DIR__ . '/.env';
    if (!file_exists($envPath)) {
        $issues[] = ".env file not found at: $envPath";
    } else {
        echo "✓ .env file found\n";
        
        // Load and check environment variables
        loadEnv($envPath);
        
        $requiredVars = [
            'BINGX_API_KEY' => 'BingX API Key',
            'BINGX_SECRET_KEY' => 'BingX Secret Key',
            'DB_HOST' => 'Database Host',
            'DB_NAME' => 'Database Name'
        ];
        
        foreach ($requiredVars as $var => $desc) {
            $value = getenv($var);
            if (empty($value)) {
                $issues[] = "$desc not configured ($var)";
            } else {
                echo "✓ $desc configured\n";
            }
        }
        
        // Optional but recommended
        $optionalVars = [
            '[REDACTED_BOT_TOKEN]
            '[REDACTED_CHAT_ID]
            'DB_USER' => 'Database User',
            'DB_PASSWORD' => 'Database Password'
        ];
        
        foreach ($optionalVars as $var => $desc) {
            $value = getenv($var);
            if (empty($value)) {
                $warnings[] = "$desc not configured ($var) - Some features may not work";
            } else {
                echo "✓ $desc configured\n";
            }
        }
    }
    
    // Check database connection
    try {
        $host = getenv('DB_HOST') ?: 'localhost';
        $user = getenv('DB_USER') ?: '';
        $password = getenv('DB_PASSWORD') ?: '';
        $database = getenv('DB_NAME') ?: 'crypto_trading';
        
        $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "✓ Database connection successful\n";
        
        // Check required tables
        $requiredTables = ['signals', 'orders', 'watchlist', 'positions', 'account_balance'];
        foreach ($requiredTables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "✓ Table '$table' exists\n";
            } else {
                $issues[] = "Table '$table' not found - run database_setup.sql";
            }
        }
        
    } catch (Exception $e) {
        $issues[] = "Database connection failed: " . $e->getMessage();
    }
    
    // Check cronjob scripts
    $jobsDir = __DIR__ . '/jobs';
    $requiredJobs = [
        'price-monitor.php',
        'order-status.php', 
        'position-sync.php',
        'balance-sync.php'
    ];
    
    foreach ($requiredJobs as $job) {
        $jobPath = $jobsDir . '/' . $job;
        if (file_exists($jobPath)) {
            echo "✓ Cronjob script '$job' exists\n";
            
            if (is_executable($jobPath)) {
                echo "✓ Script '$job' is executable\n";
            } else {
                $warnings[] = "Script '$job' may not be executable - run: chmod +x $jobPath";
            }
        } else {
            $issues[] = "Cronjob script '$job' not found at: $jobPath";
        }
    }
    
    // Summary
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "VALIDATION SUMMARY\n";
    echo str_repeat("=", 50) . "\n";
    
    if (empty($issues) && empty($warnings)) {
        echo "✅ All checks passed! Configuration looks good.\n";
    } else {
        if (!empty($issues)) {
            echo "❌ ISSUES FOUND (" . count($issues) . "):\n";
            foreach ($issues as $i => $issue) {
                echo "   " . ($i + 1) . ". $issue\n";
            }
            echo "\n";
        }
        
        if (!empty($warnings)) {
            echo "⚠️  WARNINGS (" . count($warnings) . "):\n";
            foreach ($warnings as $i => $warning) {
                echo "   " . ($i + 1) . ". $warning\n";
            }
            echo "\n";
        }
    }
}

function testCronjobs() {
    echo "Testing Cronjob Scripts...\n";
    echo "==========================\n\n";
    
    $jobsDir = __DIR__ . '/jobs';
    $jobs = [
        'price-monitor.php' => 'Price Monitor',
        'order-status.php' => 'Order Status Check',
        'position-sync.php' => 'Position Sync', 
        'balance-sync.php' => 'Balance Sync'
    ];
    
    foreach ($jobs as $script => $name) {
        echo "Testing $name ($script)...\n";
        echo str_repeat("-", 40) . "\n";
        
        $scriptPath = $jobsDir . '/' . $script;
        
        if (!file_exists($scriptPath)) {
            echo "❌ Script not found: $scriptPath\n\n";
            continue;
        }
        
        // Capture output
        ob_start();
        $startTime = microtime(true);
        
        try {
            include $scriptPath;
            $output = ob_get_clean();
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);
            
            echo "✅ Script completed successfully in {$duration}ms\n";
            
            if (!empty($output)) {
                echo "Output:\n" . $output . "\n";
            }
            
        } catch (Exception $e) {
            ob_end_clean();
            echo "❌ Script failed: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
}

function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        if (strpos($line, '=') === false) {
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
?>