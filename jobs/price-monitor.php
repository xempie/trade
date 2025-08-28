<?php
/**
 * Price Monitor Cron Job
 * Runs every minute to check watchlist items against current prices
 * Sends Telegram notifications when price targets are reached
 */

// Prevent direct browser access
if (php_sapi_name() !== 'cli') {
    //die('This script can only be run from command line');
}

// Change to project directory
$projectDir = dirname(__DIR__);
chdir($projectDir);

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

loadEnv($projectDir . '/.env');

// Database connection
function getDbConnection() {
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: '';
    $password = getenv('DB_PASSWORD') ?: '';
    $database = getenv('DB_NAME') ?: 'crypto_trading';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Price Monitor - Database connection failed: " . $e->getMessage());
        return null;
    }
}

// Get current price from BingX
function getCurrentPrice($symbol, $apiKey, $apiSecret) {
    try {
        // Convert symbol to BingX format
        $bingxSymbol = $symbol;
        if (!strpos($bingxSymbol, 'USDT')) {
            $bingxSymbol = $symbol . '-USDT';
        }
        
        $timestamp = round(microtime(true) * 1000);
        $queryString = "symbol={$bingxSymbol}&timestamp={$timestamp}";
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        
        $url = "https://open-api.bingx.com/openApi/swap/v2/quote/price?" . $queryString . "&signature=" . $signature;
        
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
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP error: {$httpCode}");
        }
        
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['code']) || $data['code'] !== 0) {
            throw new Exception('Invalid API response');
        }
        
        return floatval($data['data']['price']);
        
    } catch (Exception $e) {
        error_log("Price Monitor - Failed to get price for {$symbol}: " . $e->getMessage());
        return null;
    }
}

// Include Telegram messaging class
require_once $projectDir . '/api/telegram.php';

// Mark watchlist item as triggered
function markTriggered($pdo, $watchlistId) {
    try {
        $sql = "UPDATE watchlist SET status = 'triggered', triggered_at = NOW() WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([':id' => $watchlistId]);
    } catch (Exception $e) {
        error_log("Price Monitor - Failed to mark watchlist item {$watchlistId} as triggered: " . $e->getMessage());
        return false;
    }
}

// Main execution
echo "Starting price monitor at " . date('Y-m-d H:i:s') . "\n";

try {
    $pdo = getDbConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    $apiKey = getenv('BINGX_API_KEY') ?: '';
    $apiSecret = getenv('BINGX_SECRET_KEY') ?: '';
    
    if (empty($apiKey) || empty($apiSecret)) {
        throw new Exception('BingX API credentials not configured');
    }
    
    // Get active watchlist items
    $sql = "SELECT * FROM watchlist WHERE status = 'active' ORDER BY created_at ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $watchlistItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($watchlistItems) . " active watchlist items\n";
    
    $checkedSymbols = [];
    $triggeredCount = 0;
    
    foreach ($watchlistItems as $item) {
        $symbol = $item['symbol'];
        $entryPrice = floatval($item['entry_price']);
        $entryType = $item['entry_type'];
        $direction = $item['direction'];
        $marginAmount = floatval($item['margin_amount']);
        
        // Get current price (cache per symbol to avoid multiple API calls)
        if (!isset($checkedSymbols[$symbol])) {
            $currentPrice = getCurrentPrice($symbol, $apiKey, $apiSecret);
            if ($currentPrice === null) {
                continue;
            }
            $checkedSymbols[$symbol] = $currentPrice;
        } else {
            $currentPrice = $checkedSymbols[$symbol];
        }
        
        // Check if price target is reached
        $triggered = false;
        
        if ($direction === 'long') {
            // For long positions, trigger when price goes down to entry level
            $triggered = $currentPrice <= $entryPrice;
        } else {
            // For short positions, trigger when price goes up to entry level
            $triggered = $currentPrice >= $entryPrice;
        }
        
        if ($triggered) {
            // Mark as triggered
            if (markTriggered($pdo, $item['id'])) {
                $triggeredCount++;
                
                // Send notification with interactive buttons
                $telegram = new TelegramMessenger();
                $telegram->sendPriceAlert(
                    $symbol,
                    $entryType, 
                    $entryPrice,
                    $currentPrice,
                    $direction,
                    $marginAmount,
                    $item['id']
                );
                
                echo "Triggered: {$symbol} {$direction} at {$currentPrice} (target: {$entryPrice})\n";
            }
        }
    }
    
    echo "Price monitoring completed. Triggered: {$triggeredCount} alerts\n";
    
} catch (Exception $e) {
    error_log("Price Monitor - Error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Price monitor finished at " . date('Y-m-d H:i:s') . "\n";
?>