<?php
/**
 * Demo Mode Verification Script
 * Ensures all systems are configured for demo trading before testing
 */

// Include necessary files
require_once __DIR__ . '/api/api_helper.php';

// Load environment variables
if (!function_exists('loadEnv')) {
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
}

// Load .env file
loadEnv(__DIR__ . '/.env');

echo "=================================================\n";
echo "          DEMO MODE VERIFICATION SCRIPT          \n";
echo "=================================================\n\n";

$allGood = true;
$warnings = [];
$errors = [];

// Check 1: Environment file exists
echo "✓ Checking .env file...\n";
if (!file_exists('.env')) {
    $errors[] = ".env file not found!";
    $allGood = false;
} else {
    echo "  ✅ .env file exists\n";
}

// Check 2: Trading mode configuration
echo "\n✓ Checking trading mode configuration...\n";
$tradingMode = getenv('TRADING_MODE') ?: 'not_set';
$isDemo = isDemoMode();
$apiUrl = getBingXApiUrl();

echo "  TRADING_MODE: $tradingMode\n";
echo "  Is Demo Mode: " . ($isDemo ? 'YES' : 'NO') . "\n";
echo "  API URL: $apiUrl\n";

if (!$isDemo) {
    $errors[] = "NOT IN DEMO MODE! Current mode: $tradingMode";
    $allGood = false;
} else {
    echo "  ✅ Demo mode confirmed\n";
}

// Check 3: API credentials
echo "\n✓ Checking BingX API credentials...\n";
$apiKey = getenv('BINGX_API_KEY') ?: '';
$apiSecret = getenv('BINGX_SECRET_KEY') ?: '';

if (empty($apiKey) || empty($apiSecret)) {
    $errors[] = "BingX API credentials not configured";
    $allGood = false;
} else {
    echo "  ✅ API Key: " . substr($apiKey, 0, 8) . "..." . substr($apiKey, -4) . "\n";
    echo "  ✅ Secret Key: " . substr($apiSecret, 0, 8) . "...\n";
}

// Check 4: Database connection
echo "\n✓ Checking database connection...\n";
try {
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: '';
    $password = getenv('DB_PASSWORD') ?: '';
    $database = getenv('DB_NAME') ?: 'crypto_trading';
    
    if (empty($user) || empty($database)) {
        $errors[] = "Database credentials not configured";
        $allGood = false;
    } else {
        $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "  ✅ Database connection successful\n";
        echo "  Database: $database on $host\n";
        
        // Check if required tables exist
        $tables = ['signals', 'signal_automation_settings'];
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() == 0) {
                $errors[] = "Required table '$table' not found";
                $allGood = false;
            } else {
                echo "  ✅ Table '$table' exists\n";
            }
        }
    }
} catch (Exception $e) {
    $errors[] = "Database connection failed: " . $e->getMessage();
    $allGood = false;
}

// Check 5: API connectivity (demo)
if ($isDemo && !empty($apiKey)) {
    echo "\n✓ Testing BingX Demo API connectivity...\n";
    
    // Test price fetching
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl . "/openApi/swap/v2/quote/price?symbol=BTC-USDT");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpCode == 200) {
            $data = json_decode($response, true);
            if ($data && $data['code'] == 0) {
                echo "  ✅ Price API working - BTC price: $" . number_format($data['data']['price'], 2) . "\n";
            } else {
                $warnings[] = "Price API returned error: " . ($data['msg'] ?? 'Unknown');
            }
        } else {
            $warnings[] = "Price API HTTP error: $httpCode";
        }
    } catch (Exception $e) {
        $warnings[] = "Price API test failed: " . $e->getMessage();
    }
    
    // Test balance API (authenticated)
    try {
        $timestamp = round(microtime(true) * 1000);
        $queryString = "timestamp={$timestamp}";
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        $url = $apiUrl . "/openApi/swap/v2/user/balance?" . $queryString . "&signature=" . $signature;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-BX-APIKEY: ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpCode == 200) {
            $data = json_decode($response, true);
            if ($data && $data['code'] == 0) {
                echo "  ✅ Balance API working\n";
                
                // Check currency type
                if (isset($data['data']['balance']['asset'])) {
                    $asset = $data['data']['balance']['asset'];
                    if ($asset === 'VST') {
                        echo "  ✅ Using demo currency (VST)\n";
                    } elseif ($asset === 'USDT') {
                        $warnings[] = "Using live currency (USDT) - verify this is a demo account!";
                    } else {
                        $warnings[] = "Unknown currency: $asset";
                    }
                }
            } else {
                $warnings[] = "Balance API returned error: " . ($data['msg'] ?? 'Unknown');
            }
        } else {
            $warnings[] = "Balance API HTTP error: $httpCode";
        }
    } catch (Exception $e) {
        $warnings[] = "Balance API test failed: " . $e->getMessage();
    }
}

// Check 6: Position sizing settings
if (isset($pdo)) {
    echo "\n✓ Checking position sizing settings...\n";
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM signal_automation_settings WHERE setting_key = 'AUTO_MARGIN_PER_ENTRY'");
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result) {
            $autoMargin = $result['setting_value'];
            echo "  ✅ AUTO_MARGIN_PER_ENTRY: $autoMargin USDT\n";
        } else {
            $warnings[] = "AUTO_MARGIN_PER_ENTRY setting not found in database";
        }
    } catch (Exception $e) {
        $warnings[] = "Could not check position sizing settings: " . $e->getMessage();
    }
}

// Summary
echo "\n=================================================\n";
echo "                   SUMMARY                       \n";
echo "=================================================\n";

if ($allGood && empty($errors)) {
    echo "🎉 ALL CHECKS PASSED! 🎉\n\n";
    echo "Your system is properly configured for DEMO trading.\n";
    echo "You can safely proceed with testing.\n\n";
    
    echo "Next steps:\n";
    echo "1. Run: php create_test_signals.php\n";
    echo "2. Run: php signal_automation.php\n";
    echo "3. Check results in BingX demo account\n\n";
} else {
    echo "❌ ISSUES FOUND - DO NOT PROCEED WITH TESTING!\n\n";
}

if (!empty($errors)) {
    echo "ERRORS (must fix):\n";
    foreach ($errors as $error) {
        echo "  ❌ $error\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "WARNINGS (should review):\n";
    foreach ($warnings as $warning) {
        echo "  ⚠️  $warning\n";
    }
    echo "\n";
}

if (!$allGood) {
    echo "FIXES NEEDED:\n";
    if (!$isDemo) {
        echo "  1. Set TRADING_MODE=demo in your .env file\n";
    }
    if (empty($apiKey) || empty($apiSecret)) {
        echo "  2. Configure BINGX_API_KEY and BINGX_SECRET_KEY in .env\n";
    }
    if (isset($errors) && strpos(implode(' ', $errors), 'Database') !== false) {
        echo "  3. Configure database settings (DB_HOST, DB_USER, DB_PASSWORD, DB_NAME)\n";
    }
    echo "\n";
}

echo "Configuration file location: " . realpath('.env') . "\n";
echo "=================================================\n";

?>