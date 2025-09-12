<?php
// Include authentication protection
require_once __DIR__ . '/../auth/api_protection.php';

// Protect this API endpoint
protectAPI();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

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
$envPath = __DIR__ . '/../.env';
loadEnv($envPath);

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
        throw new Exception('Database connection failed: ' . $e->getMessage());
    }
}

// Parse percentage value
function parsePercentage($value) {
    if (is_string($value) && strpos($value, '%') !== false) {
        return floatval(str_replace('%', '', $value)) / 100;
    }
    return null;
}

// Calculate price from percentage
function calculatePriceFromPercentage($entryPrice, $percentage, $isLong, $isTarget = true) {
    if ($isTarget) {
        if ($isLong) {
            return $entryPrice * (1 + $percentage); // LONG targets: price goes up
        } else {
            return $entryPrice * (1 - $percentage); // SHORT targets: price goes down
        }
    } else {
        // Stop loss calculation
        if ($isLong) {
            return $entryPrice * (1 - $percentage); // LONG stop: price goes down
        } else {
            return $entryPrice * (1 + $percentage); // SHORT stop: price goes up
        }
    }
}

// Validate required fields
function validateSignalData($data) {
    $required = ['symbol', 'side', 'leverage', 'entries', 'targets', 'stop_loss'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }
    
    // Validate side
    if (!in_array(strtoupper($data['side']), ['LONG', 'SHORT'])) {
        throw new Exception("Field 'side' must be 'LONG' or 'SHORT'");
    }
    
    // Validate arrays
    if (!is_array($data['entries']) || empty($data['entries'])) {
        throw new Exception("Field 'entries' must be a non-empty array");
    }
    
    if (!is_array($data['targets']) || empty($data['targets'])) {
        throw new Exception("Field 'targets' must be a non-empty array");
    }
    
    // Validate array sizes
    if (count($data['entries']) > 3) {
        throw new Exception("Maximum 3 entries allowed");
    }
    
    if (count($data['targets']) > 5) {
        throw new Exception("Maximum 5 targets allowed");
    }
    
    // Validate leverage
    $leverage = intval($data['leverage']);
    if ($leverage < 1 || $leverage > 100) {
        throw new Exception("Leverage must be between 1 and 100");
    }
    
    return true;
}

// Save signal to database
function saveSignalToDb($pdo, $signalData) {
    try {
        $sql = "INSERT INTO signals (
            symbol, signal_type, 
            entry_market_price, entry_2, entry_3,
            take_profit_1, take_profit_2, take_profit_3, take_profit_4, take_profit_5,
            stop_loss, leverage, 
            source_id, source_name, external_signal_id,
            confidence_score, notes, risk_reward_ratio,
            auto_created, status, signal_status,
            created_at
        ) VALUES (
            :symbol, :signal_type,
            :entry_market_price, :entry_2, :entry_3,
            :take_profit_1, :take_profit_2, :take_profit_3, :take_profit_4, :take_profit_5,
            :stop_loss, :leverage,
            :source_id, :source_name, :external_signal_id,
            :confidence_score, :notes, :risk_reward_ratio,
            :auto_created, :status, :signal_status,
            NOW()
        )";
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute($signalData);
        
        if ($success) {
            return $pdo->lastInsertId();
        }
        return null;
    } catch (Exception $e) {
        error_log("Database error saving signal: " . $e->getMessage());
        throw new Exception("Failed to save signal: " . $e->getMessage());
    }
}

// Main execution
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }
    
    // Validate input data
    validateSignalData($input);
    
    $pdo = getDbConnection();
    
    // Extract and process data
    $symbol = strtoupper(trim($input['symbol']));
    $side = strtoupper(trim($input['side']));
    $leverage = intval($input['leverage']);
    $entries = $input['entries'];
    $targets = $input['targets'];
    $stopLoss = $input['stop_loss'];
    
    $isLong = ($side === 'LONG');
    $entryPrice = floatval($entries[0]); // Use first entry as base for percentage calculations
    
    // Process entries
    $entryMarket = isset($entries[0]) ? floatval($entries[0]) : null;
    $entry2 = isset($entries[1]) ? floatval($entries[1]) : null;
    $entry3 = isset($entries[2]) ? floatval($entries[2]) : null;
    
    // Process targets
    $takeProfits = [null, null, null, null, null];
    for ($i = 0; $i < count($targets) && $i < 5; $i++) {
        $target = $targets[$i];
        $percentage = parsePercentage($target);
        
        if ($percentage !== null) {
            // Calculate from percentage
            $takeProfits[$i] = calculatePriceFromPercentage($entryPrice, $percentage, $isLong, true);
        } else {
            // Use absolute price
            $takeProfits[$i] = floatval($target);
        }
    }
    
    // Process stop loss
    $stopLossPrice = null;
    $stopPercentage = parsePercentage($stopLoss);
    if ($stopPercentage !== null) {
        // Calculate from percentage
        $stopLossPrice = calculatePriceFromPercentage($entryPrice, $stopPercentage, $isLong, false);
    } else {
        // Use absolute price
        $stopLossPrice = floatval($stopLoss);
    }
    
    // Prepare database data
    $dbData = [
        ':symbol' => $symbol,
        ':signal_type' => $side,
        ':entry_market_price' => $entryMarket,
        ':entry_2' => $entry2,
        ':entry_3' => $entry3,
        ':take_profit_1' => $takeProfits[0],
        ':take_profit_2' => $takeProfits[1],
        ':take_profit_3' => $takeProfits[2],
        ':take_profit_4' => $takeProfits[3],
        ':take_profit_5' => $takeProfits[4],
        ':stop_loss' => $stopLossPrice,
        ':leverage' => $leverage,
        ':source_id' => 2, // JSON Import source
        ':source_name' => 'JSON Import',
        ':external_signal_id' => $input['external_signal_id'] ?? null,
        ':confidence_score' => isset($input['confidence_score']) ? floatval($input['confidence_score']) : 0.0,
        ':notes' => $input['notes'] ?? null,
        ':risk_reward_ratio' => isset($input['risk_reward_ratio']) ? floatval($input['risk_reward_ratio']) : 0.0,
        ':auto_created' => 1,
        ':status' => 'ACTIVE',
        ':signal_status' => 'ACTIVE'
    ];
    
    // Save to database
    $signalId = saveSignalToDb($pdo, $dbData);
    
    if (!$signalId) {
        throw new Exception('Failed to save signal to database');
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'signal_id' => $signalId,
        'message' => 'Signal imported successfully',
        'processed_data' => [
            'symbol' => $symbol,
            'side' => $side,
            'leverage' => $leverage,
            'entries' => [
                'entry_market' => $entryMarket,
                'entry_2' => $entry2,
                'entry_3' => $entry3
            ],
            'targets' => [
                'take_profit_1' => $takeProfits[0],
                'take_profit_2' => $takeProfits[1],
                'take_profit_3' => $takeProfits[2],
                'take_profit_4' => $takeProfits[3],
                'take_profit_5' => $takeProfits[4]
            ],
            'stop_loss' => $stopLossPrice,
            'source' => 'JSON Import'
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Import Signal API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Log the input data for debugging
    $rawInput = file_get_contents('php://input');
    error_log("Input data causing error: " . $rawInput);
    
    http_response_code($e->getCode() ?: 400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'method' => $_SERVER['REQUEST_METHOD'],
            'input_received' => $rawInput ?? 'No input received'
        ]
    ]);
}
?>