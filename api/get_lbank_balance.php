<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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

// Get LBank server time for synchronization
function getLBankServerTime() {
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.lbkex.com/v1/timestamp.do');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200 && $response) {
            $data = json_decode($response, true);
            if ($data && isset($data['data'])) {
                return $data['data'];
            }
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Failed to get LBank server time: " . $e->getMessage());
        return false;
    }
}

// Get LBank balance
function getLBankBalance($apiKey, $apiSecret) {
    try {
        // Get LBank server time for proper synchronization
        $serverTime = getLBankServerTime();
        if ($serverTime) {
            $tonce = $serverTime;
        } else {
            $tonce = round(microtime(true) * 1000);
        }
        
        // Try the most basic LBank v1 API method
        $params = [
            'api_key' => $apiKey,
            'tonce' => $tonce
        ];
        
        // Sort parameters alphabetically by key name  
        ksort($params);
        
        // Create parameter string
        $paramPairs = [];
        foreach ($params as $key => $value) {
            $paramPairs[] = $key . '=' . $value;
        }
        $paramString = implode('&', $paramPairs);
        
        // LBank v1 signature: append secret_key and MD5 hash
        $signString = $paramString . '&secret_key=' . $apiSecret;
        $signature = strtoupper(md5($signString));
        
        // Debug logging
        error_log("LBank API Debug - Sign String: " . $signString);
        error_log("LBank API Debug - Signature: " . $signature);
        error_log("LBank API Debug - Tonce: " . $tonce);
        
        // Add signature to parameters for the request
        $params['sign'] = $signature;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.lbkex.com/v1/user_info.do');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception('cURL Error: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new Exception("LBank API HTTP error: {$httpCode}. Response: " . $response);
        }
        
        $data = json_decode($response, true);
        
        // Log the raw response for debugging
        error_log("LBank API Raw Response: " . $response);
        
        if (!$data) {
            throw new Exception('Invalid JSON response from LBank: ' . json_last_error_msg());
        }
        
        if (!isset($data['result']) || $data['result'] !== 'true') {
            $errorCode = isset($data['error_code']) ? $data['error_code'] : 'unknown';
            $errorMsg = isset($data['msg']) ? $data['msg'] : 'Unknown API error';
            
            // Common LBank error codes
            $errorMessages = [
                '10007' => 'Signature verification failed - check API key and secret',
                '10008' => 'Illegal parameter',
                '10009' => 'Order not found',
                '10010' => 'Insufficient balance',
                '10011' => 'Invalid API key',
                '10013' => 'Permission denied - check API permissions',
                '10016' => 'Invalid timestamp (tonce)'
            ];
            
            $detailedMsg = isset($errorMessages[$errorCode]) ? $errorMessages[$errorCode] : $errorMsg;
            throw new Exception("LBank API Error {$errorCode}: {$detailedMsg}");
        }
        
        // Extract balance information from LBank v1 API response
        $info = $data['info'] ?? [];
        $funds = $info['funds'] ?? [];
        
        $totalBalance = 0;
        $availableBalance = 0;
        $frozenBalance = 0;
        
        // LBank v1 API structure: funds.free and funds.freezed
        if (isset($funds['free']['usdt'])) {
            $availableBalance = floatval($funds['free']['usdt']);
        }
        
        if (isset($funds['freezed']['usdt'])) {
            $frozenBalance = floatval($funds['freezed']['usdt']);
        }
        
        $totalBalance = $availableBalance + $frozenBalance;
        
        return [
            'success' => true,
            'available_balance' => $availableBalance,
            'total_balance' => $totalBalance,
            'frozen_balance' => $frozenBalance,
            'margin_used' => $frozenBalance, // Frozen balance as margin used
            'unrealized_pnl' => 0, // LBank may not provide this in account summary
            'exchange' => 'LBank',
            'raw_data' => $data // Fixed variable name
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'exchange' => 'LBank'
        ];
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Only GET method allowed');
    }
    
    $apiKey = getenv('LBANK_API_KEY') ?: '';
    $apiSecret = getenv('LBANK_SECRET_KEY') ?: '';
    
    if (empty($apiKey) || empty($apiSecret)) {
        echo json_encode([
            'success' => false,
            'error' => 'LBank API credentials not configured',
            'exchange' => 'LBank'
        ]);
        exit;
    }
    
    $result = getLBankBalance($apiKey, $apiSecret);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'data' => [
                'available_balance' => $result['available_balance'],
                'total_balance' => $result['total_balance'],
                'frozen_balance' => $result['frozen_balance'],
                'margin_used' => $result['margin_used'],
                'unrealized_pnl' => $result['unrealized_pnl'],
                'exchange' => $result['exchange']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['error'],
            'exchange' => $result['exchange']
        ]);
    }
    
} catch (Exception $e) {
    error_log("LBank Balance API Error: " . $e->getMessage());
    
    http_response_code($e->getCode() ?: 400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'exchange' => 'LBank'
    ]);
}
?>