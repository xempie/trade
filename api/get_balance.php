<?php
// Ensure we always output JSON
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
loadEnv(__DIR__ . '/../.env');

// Get BingX API credentials
$apiKey = getenv('BINGX_API_KEY') ?: '';
$apiSecret = getenv('BINGX_SECRET_KEY') ?: '';

function getBingXBalance($apiKey, $apiSecret) {
    try {
        if (empty($apiKey) || empty($apiSecret)) {
            throw new Exception('BingX API credentials not configured. Please check your .env file.');
        }
        
        // Try multiple BingX API endpoints
        $endpoints = [
            '/openApi/swap/v2/user/balance',     // Futures balance
            '/openApi/swap/v3/user/balance',     // Futures balance v3
            '/openApi/swap/v1/user/balance',     // Futures balance v1
            '/openApi/spot/v1/account',          // Spot account
            '/openApi/swap/v2/account',          // Swap account
        ];
        
        $lastError = '';
        
        foreach ($endpoints as $endpoint) {
            try {
                $result = tryBingXEndpoint($apiKey, $apiSecret, $endpoint);
                if ($result['success']) {
                    return $result;
                }
                $lastError = $result['error'];
            } catch (Exception $e) {
                $lastError = $e->getMessage();
                continue;
            }
        }
        
        throw new Exception('All BingX endpoints failed. Last error: ' . $lastError);
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Balance API Error: ' . $e->getMessage()
        ];
    }
}

function tryBingXEndpoint($apiKey, $apiSecret, $endpoint) {
    try {
        $timestamp = round(microtime(true) * 1000);
        $queryString = "timestamp={$timestamp}";
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        
        $url = "https://open-api.bingx.com" . $endpoint . "?" . $queryString . "&signature=" . $signature;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-BX-APIKEY: ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception('cURL Error: ' . $curlError);
        }
        
        if (!$response) {
            throw new Exception('No response from BingX API');
        }
        
        if ($httpCode !== 200) {
            error_log("BingX API HTTP Error {$httpCode}: {$response}");
            throw new Exception("BingX API HTTP error: {$httpCode}");
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from BingX: ' . json_last_error_msg() . '. Response: ' . substr($response, 0, 200));
        }
        
        if (!is_array($data)) {
            throw new Exception('BingX API returned non-array response: ' . substr($response, 0, 200));
        }
        
        if (!isset($data['code']) || $data['code'] !== 0) {
            $errorMsg = isset($data['msg']) ? $data['msg'] : 'Unknown API error';
            throw new Exception('BingX API Error: ' . $errorMsg . '. Response: ' . substr($response, 0, 200));
        }
        
        if (!isset($data['data']) || !is_array($data['data'])) {
            throw new Exception('Invalid data structure in BingX response. Response: ' . substr($response, 0, 200));
        }
        
        // Handle different response formats from different endpoints
        $balanceData = null;
        $accountData = $data['data'];
        
        // Check if this is account endpoint response
        if (isset($accountData['balance'])) {
            $balanceData = $accountData['balance'];
        } elseif (isset($accountData['assets'])) {
            $balanceData = $accountData['assets'];
        } elseif (is_array($accountData) && isset($accountData[0]['asset'])) {
            $balanceData = $accountData;
        } elseif (isset($accountData['totalWalletBalance'])) {
            // Direct account info format
            $totalBalance = floatval($accountData['totalWalletBalance']);
            $availableBalance = floatval($accountData['availableBalance'] ?? $accountData['totalAvailableBalance'] ?? 0);
            $marginUsed = $totalBalance - $availableBalance;
            $positionSize = $totalBalance * 0.033;
            
            return [
                'success' => true,
                'data' => [
                    'total_balance' => $totalBalance,
                    'available_balance' => $availableBalance,
                    'margin_used' => $marginUsed,
                    'position_size' => $positionSize,
                    'currency' => 'USDT',
                    'last_updated' => date('Y-m-d H:i:s')
                ],
                'debug_info' => [
                    'response_format' => 'direct_account',
                    'available_fields' => array_keys($accountData)
                ]
            ];
        }
        
        if (!is_array($balanceData)) {
            throw new Exception('Balance data not found in BingX response. Available fields: ' . json_encode(array_keys($accountData)) . '. Response: ' . substr($response, 0, 300));
        }
        
        // Find USDT balance
        $usdtBalance = null;
        foreach ($balanceData as $balance) {
            if (!is_array($balance)) {
                continue; // Skip invalid balance entries
            }
            if (isset($balance['asset']) && $balance['asset'] === 'USDT') {
                $usdtBalance = $balance;
                break;
            }
        }
        
        if (!$usdtBalance) {
            // Try alternative approach - get all available assets for debugging
            $availableAssets = [];
            foreach ($balanceData as $balance) {
                if (is_array($balance) && isset($balance['asset'])) {
                    $availableAssets[] = $balance['asset'];
                }
            }
            throw new Exception('USDT balance not found in response. Available assets: ' . json_encode($availableAssets) . '. Response format: ' . substr($response, 0, 300));
        }
        
        // Calculate values with validation
        $totalBalance = isset($usdtBalance['balance']) ? floatval($usdtBalance['balance']) : 0;
        $availableBalance = isset($usdtBalance['availableMargin']) ? floatval($usdtBalance['availableMargin']) : 0;
        
        // Try multiple field names for available balance
        if ($availableBalance === 0 && isset($usdtBalance['available'])) {
            $availableBalance = floatval($usdtBalance['available']);
        }
        if ($availableBalance === 0 && isset($usdtBalance['free'])) {
            $availableBalance = floatval($usdtBalance['free']);
        }
        if ($availableBalance === 0 && isset($usdtBalance['crossWalletBalance'])) {
            $availableBalance = floatval($usdtBalance['crossWalletBalance']);
        }
        if ($availableBalance === 0 && isset($usdtBalance['maxWithdrawAmount'])) {
            $availableBalance = floatval($usdtBalance['maxWithdrawAmount']);
        }
        
        $marginUsed = $totalBalance - $availableBalance;
        $positionSize = $totalBalance * 0.033; // 3.3% of total balance
        
        return [
            'success' => true,
            'data' => [
                'total_balance' => $totalBalance,
                'available_balance' => $availableBalance,
                'margin_used' => $marginUsed,
                'position_size' => $positionSize,
                'currency' => 'USDT',
                'last_updated' => date('Y-m-d H:i:s')
            ],
            'debug_info' => [
                'usdt_balance_fields' => array_keys($usdtBalance),
                'total_assets' => count($balanceData)
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Balance API Error: ' . $e->getMessage()
        ];
    }
}

function getPositionsData($apiKey, $apiSecret) {
    try {
        if (empty($apiKey) || empty($apiSecret)) {
            return null;
        }
        
        $timestamp = round(microtime(true) * 1000);
        $queryString = "timestamp={$timestamp}";
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        
        $url = "https://open-api.bingx.com/openApi/swap/v2/user/positions?" . $queryString . "&signature=" . $signature;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-BX-APIKEY: ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpCode === 200) {
            $data = json_decode($response, true);
            if ($data && $data['code'] === 0) {
                return $data['data'] ?? [];
            }
        }
        
        return null;
        
    } catch (Exception $e) {
        return null;
    }
}

// Main execution
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Only GET method allowed');
    }
    
    // Debug information
    $debug = [
        'api_key_present' => !empty($apiKey),
        'api_secret_present' => !empty($apiSecret),
        'api_key_length' => strlen($apiKey ?: ''),
        'api_secret_length' => strlen($apiSecret ?: ''),
        'curl_available' => function_exists('curl_init')
    ];
    
    $result = getBingXBalance($apiKey, $apiSecret);
    
    if ($result['success']) {
        // Try to get additional positions data
        $positions = getPositionsData($apiKey, $apiSecret);
        if ($positions) {
            $result['data']['active_positions'] = count($positions);
            
            // Calculate total unrealized PnL
            $totalUnrealizedPnL = 0;
            foreach ($positions as $position) {
                $totalUnrealizedPnL += floatval($position['unrealizedProfit'] ?? 0);
            }
            $result['data']['unrealized_pnl'] = $totalUnrealizedPnL;
        }
        
        $result['debug'] = $debug;
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Balance API Exception: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => $debug ?? [],
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
} catch (Error $e) {
    error_log("Balance API Error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'PHP Error: ' . $e->getMessage(),
        'debug' => [],
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
?>