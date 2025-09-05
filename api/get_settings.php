<?php
require_once '../auth/config.php';
requireAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Default values
    $settings = [
        'bingx_api_key' => '',
        'bingx_secret_key' => '',
        'bingx_passphrase' => '',
        'telegram_bot_token' => '',
        'telegram_chat_id' => '',
        'position_size_percent' => 3.3,
        'entry_2_percent' => 2.0,
        'entry_3_percent' => 4.0,
        'send_balance_alerts' => false,
        'send_profit_loss_alerts' => false
    ];
    
    // Read .env file if it exists
    $envPath = '../.env';
    if (file_exists($envPath)) {
        $envContent = file_get_contents($envPath);
        $lines = explode("\n", $envContent);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line && strpos($line, '#') !== 0 && strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                switch ($key) {
                    case 'BINGX_API_KEY':
                        $settings['bingx_api_key'] = $value;
                        break;
                    case 'BINGX_SECRET_KEY':
                        $settings['bingx_secret_key'] = $value;
                        break;
                    case 'BINGX_PASSPHRASE':
                        $settings['bingx_passphrase'] = $value;
                        break;
                    case 'TELEGRAM_BOT_TOKEN_NOTIF':
                        $settings['telegram_bot_token'] = $value;
                        break;
                    case 'TELEGRAM_CHAT_ID_NOTIF':
                        $settings['telegram_chat_id'] = $value;
                        break;
                    case 'POSITION_SIZE_PERCENT':
                        $settings['position_size_percent'] = (float)$value;
                        break;
                    case 'ENTRY_2_PERCENT':
                        $settings['entry_2_percent'] = (float)$value;
                        break;
                    case 'ENTRY_3_PERCENT':
                        $settings['entry_3_percent'] = (float)$value;
                        break;
                    case 'SEND_BALANCE_ALERTS':
                        $settings['send_balance_alerts'] = $value === 'true';
                        break;
                    case 'SEND_PROFIT_LOSS_ALERTS':
                        $settings['send_profit_loss_alerts'] = $value === 'true';
                        break;
                    case 'TRADING_MODE':
                        $settings['trading_mode'] = $value;
                        break;
                    case 'AUTO_TRADING_ENABLED':
                        $settings['auto_trading_enabled'] = $value === 'true';
                        break;
                    case 'LIMIT_ORDER_ACTION':
                        $settings['limit_order_action'] = $value;
                        break;
                    case 'TARGET_PERCENTAGE':
                        $settings['target_percentage'] = (float)$value;
                        break;
                    case 'TARGET_ACTION':
                        $settings['target_action'] = $value;
                        break;
                    case 'AUTO_STOP_LOSS':
                        $settings['auto_stop_loss'] = $value === 'true';
                        break;
                }
            }
        }
    }
    
    // Mask API keys for security (show only last 4 characters)
    if (strlen($settings['bingx_api_key']) > 4) {
        $settings['bingx_api_key_masked'] = str_repeat('*', strlen($settings['bingx_api_key']) - 4) . substr($settings['bingx_api_key'], -4);
    } else {
        $settings['bingx_api_key_masked'] = $settings['bingx_api_key'];
    }
    
    if (strlen($settings['bingx_secret_key']) > 4) {
        $settings['bingx_secret_key_masked'] = str_repeat('*', strlen($settings['bingx_secret_key']) - 4) . substr($settings['bingx_secret_key'], -4);
    } else {
        $settings['bingx_secret_key_masked'] = $settings['bingx_secret_key'];
    }
    
    if (strlen($settings['telegram_bot_token']) > 8) {
        $settings['telegram_bot_token_masked'] = substr($settings['telegram_bot_token'], 0, 4) . str_repeat('*', strlen($settings['telegram_bot_token']) - 8) . substr($settings['telegram_bot_token'], -4);
    } else {
        $settings['telegram_bot_token_masked'] = $settings['telegram_bot_token'];
    }
    
    echo json_encode([
        'success' => true,
        'settings' => $settings
    ]);
    
} catch (Exception $e) {
    error_log("Settings load error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load settings: ' . $e->getMessage()
    ]);
}
?>