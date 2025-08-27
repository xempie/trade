<?php
/**
 * Test Demo Trading Mode Configuration
 * Verifies that demo mode settings are properly loaded and applied
 */

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

loadEnv('.env');

// Load API helper
require_once 'api/api_helper.php';

echo "=== Dual Trading Mode Configuration Test ===\n\n";

// Get trading mode information
$modeInfo = getTradingModeInfo();

echo "✅ Environment Variables:\n";
echo "  - DEMO_TRADING: {$modeInfo['demo_trading']}\n";
echo "  - TRADING_MODE: {$modeInfo['trading_mode']}\n";
echo "  - ENABLE_REAL_TRADING: {$modeInfo['enable_real_trading']}\n";
echo "  - BINGX_DEMO_MODE: {$modeInfo['bingx_demo_mode']}\n";
echo "  - BINGX_LIVE_URL: {$modeInfo['live_url']}\n";
echo "  - BINGX_DEMO_URL: {$modeInfo['demo_url']}\n";
echo "  - CURRENT_API_URL: {$modeInfo['api_url']}\n\n";

// Validate configuration
$validation = validateTradingMode();
$currentMode = $modeInfo['is_demo_mode'] ? 'DEMO' : 'LIVE';

echo "🔍 Configuration Analysis:\n";
echo "  📊 Current Mode: {$currentMode}\n";
echo "  🌐 API Endpoint: {$modeInfo['api_url']}\n";

if ($validation['valid']) {
    echo "  ✅ Configuration is VALID\n";
} else {
    echo "  ⚠️ Configuration Warnings:\n";
    foreach ($validation['warnings'] as $warning) {
        echo "     - {$warning}\n";
    }
}

echo "\n";

// Test API connectivity (non-authenticated endpoint)
echo "🌐 Testing API Connectivity:\n";
$testUrl = $modeInfo['api_url'] . "/openApi/swap/v2/server/time";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data && isset($data['code']) && $data['code'] === 0) {
        echo "  ✅ API endpoint is reachable\n";
        echo "  📡 Server time: " . ($data['data']['serverTime'] ?? 'N/A') . "\n";
    } else {
        echo "  ⚠️ API responded but returned error: " . ($data['msg'] ?? 'Unknown') . "\n";
    }
} else {
    echo "  ❌ API endpoint unreachable (HTTP $httpCode)\n";
    echo "  🔗 Tested URL: $testUrl\n";
}

echo "\n=== Trading Mode Summary ===\n";
if ($modeInfo['is_demo_mode']) {
    echo "🔒 DEMO MODE ACTIVE\n";
    echo "   Current API: {$modeInfo['api_url']}\n";
    echo "   Real trading disabled: " . (strtolower($modeInfo['enable_real_trading']) === 'false' ? 'Yes' : 'No') . "\n";
    echo "   Using live API with demo trading restrictions\n";
    echo "   ℹ️ Note: BingX uses same API for demo/live, safety enforced by app logic\n";
} else {
    echo "⚡ LIVE TRADING MODE ACTIVE\n";
    echo "   Current API: {$modeInfo['api_url']}\n";
    echo "   ⚠️ REAL FUNDS AT RISK\n";
}

echo "\n🔄 To switch modes:\n";
echo "DEMO MODE:\n";
echo "  DEMO_TRADING=true\n";
echo "  TRADING_MODE=demo\n";
echo "  ENABLE_REAL_TRADING=false\n";
echo "  BINGX_DEMO_MODE=true\n\n";

echo "LIVE MODE:\n";
echo "  DEMO_TRADING=false\n";
echo "  TRADING_MODE=live\n";
echo "  ENABLE_REAL_TRADING=true\n";
echo "  BINGX_DEMO_MODE=false\n\n";

echo "ℹ️ Note: Both modes use https://open-api.bingx.com\n";
echo "Demo safety is enforced by ENABLE_REAL_TRADING=false\n\n";

?>