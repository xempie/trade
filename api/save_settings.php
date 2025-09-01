<?php
require_once '../auth/config.php';
requireAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$requiredFields = [
    'bingx_api_key',
    'bingx_secret_key', 
    'bingx_passphrase',
    'telegram_bot_token',
    'telegram_chat_id',
    'position_size_percent',
    'entry_2_percent',
    'entry_3_percent'
];

foreach ($requiredFields as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
        exit;
    }
}

// Validate numeric fields
$numericFields = ['position_size_percent', 'entry_2_percent', 'entry_3_percent'];
foreach ($numericFields as $field) {
    if (!is_numeric($input[$field]) || $input[$field] <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "$field must be a positive number"]);
        exit;
    }
}

// Validate position size percent is reasonable (0.1% to 50%)
if ($input['position_size_percent'] < 0.1 || $input['position_size_percent'] > 50) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Position size must be between 0.1% and 50%']);
    exit;
}

// Validate entry percentages are reasonable (0.1% to 20%)
if ($input['entry_2_percent'] < 0.1 || $input['entry_2_percent'] > 20) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Entry 2 percent must be between 0.1% and 20%']);
    exit;
}

if ($input['entry_3_percent'] < 0.1 || $input['entry_3_percent'] > 20) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Entry 3 percent must be between 0.1% and 20%']);
    exit;
}

try {
    // Read current .env file if it exists
    $envPath = '../.env';
    $envVars = [];
    
    if (file_exists($envPath)) {
        $envContent = file_get_contents($envPath);
        $lines = explode("\n", $envContent);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line && !str_starts_with($line, '#') && strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $envVars[trim($key)] = trim($value);
            }
        }
    }
    
    // Update with new values
    $envVars['BINGX_API_KEY'] = $input['bingx_api_key'];
    $envVars['BINGX_SECRET_KEY'] = $input['bingx_secret_key'];
    $envVars['BINGX_PASSPHRASE'] = $input['bingx_passphrase'];
    $envVars['[REDACTED_BOT_TOKEN]
    $envVars['[REDACTED_CHAT_ID]
    $envVars['POSITION_SIZE_PERCENT'] = $input['position_size_percent'];
    $envVars['ENTRY_2_PERCENT'] = $input['entry_2_percent'];
    $envVars['ENTRY_3_PERCENT'] = $input['entry_3_percent'];
    $envVars['SEND_BALANCE_ALERTS'] = isset($input['send_balance_alerts']) && $input['send_balance_alerts'] ? 'true' : 'false';
    $envVars['SEND_PROFIT_LOSS_ALERTS'] = isset($input['send_profit_loss_alerts']) && $input['send_profit_loss_alerts'] ? 'true' : 'false';
    
    // Add database settings if they don't exist
    if (!isset($envVars['DB_HOST'])) $envVars['DB_HOST'] = 'localhost';
    if (!isset($envVars['DB_USER'])) $envVars['DB_USER'] = '';
    if (!isset($envVars['DB_PASSWORD'])) $envVars['DB_PASSWORD'] = '';
    if (!isset($envVars['DB_NAME'])) $envVars['DB_NAME'] = 'crypto_trading';
    
    // Build new .env content
    $newEnvContent = "# Crypto Trading App Configuration\n";
    $newEnvContent .= "# Updated: " . date('Y-m-d H:i:s') . "\n\n";
    
    $newEnvContent .= "# BingX API Configuration\n";
    $newEnvContent .= "[REDACTED_API_KEY]
    $newEnvContent .= "[REDACTED_SECRET_KEY] 
    $newEnvContent .= "[REDACTED_PASSPHRASE]
    
    $newEnvContent .= "# Telegram Bot Configuration\n";
    $newEnvContent .= "[REDACTED_BOT_TOKEN]
    $newEnvContent .= "[REDACTED_CHAT_ID]
    
    $newEnvContent .= "# Trading Configuration\n";
    $newEnvContent .= "POSITION_SIZE_PERCENT={$envVars['POSITION_SIZE_PERCENT']}\n";
    $newEnvContent .= "ENTRY_2_PERCENT={$envVars['ENTRY_2_PERCENT']}\n";
    $newEnvContent .= "ENTRY_3_PERCENT={$envVars['ENTRY_3_PERCENT']}\n\n";
    
    $newEnvContent .= "# Alert Configuration\n";
    $newEnvContent .= "SEND_BALANCE_ALERTS={$envVars['SEND_BALANCE_ALERTS']}\n";
    $newEnvContent .= "SEND_PROFIT_LOSS_ALERTS={$envVars['SEND_PROFIT_LOSS_ALERTS']}\n\n";
    
    $newEnvContent .= "# Database Configuration\n";
    $newEnvContent .= "DB_HOST={$envVars['DB_HOST']}\n";
    $newEnvContent .= "DB_USER={$envVars['DB_USER']}\n";
    $newEnvContent .= "[REDACTED_DB_PASSWORD]
    $newEnvContent .= "DB_NAME={$envVars['DB_NAME']}\n";
    
    // Write to .env file
    if (file_put_contents($envPath, $newEnvContent) === false) {
        throw new Exception('Failed to write .env file');
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Settings saved successfully',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Settings save error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Failed to save settings: ' . $e->getMessage()
    ]);
}
?>