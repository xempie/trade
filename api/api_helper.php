<?php
/**
 * BingX API Helper Functions
 * Handles API URL selection based on trading mode (demo vs live)
 */

/**
 * Get the appropriate BingX API base URL based on trading mode
 */
function getBingXApiUrl() {
    $demoTrading = strtolower(getenv('DEMO_TRADING') ?: 'false') === 'true';
    $tradingMode = strtolower(getenv('TRADING_MODE') ?: 'live');
    $demoMode = strtolower(getenv('BINGX_DEMO_MODE') ?: 'false') === 'true';
    
    // Check if we're in demo mode
    $isDemo = $demoTrading || $tradingMode === 'demo' || $demoMode;
    
    if ($isDemo) {
        // Use demo URL if available, fallback to live URL
        $demoUrl = getenv('BINGX_DEMO_URL') ?: '';
        $liveUrl = getenv('BINGX_LIVE_URL') ?: 'https://open-api.bingx.com';
        
        // If demo URL is available, use it, otherwise use live URL with demo restrictions
        return !empty($demoUrl) ? $demoUrl : $liveUrl;
    } else {
        // Use live URL
        return getenv('BINGX_LIVE_URL') ?: 'https://open-api.bingx.com';
    }
}

/**
 * Check if we're in demo trading mode
 */
function isDemoMode() {
    $demoTrading = strtolower(getenv('DEMO_TRADING') ?: 'false') === 'true';
    $tradingMode = strtolower(getenv('TRADING_MODE') ?: 'live');
    $demoMode = strtolower(getenv('BINGX_DEMO_MODE') ?: 'false') === 'true';
    $enableRealTrading = strtolower(getenv('ENABLE_REAL_TRADING') ?: 'true') === 'true';
    
    return $demoTrading || $tradingMode === 'demo' || $demoMode || !$enableRealTrading;
}

/**
 * Get trading mode information for debugging
 */
function getTradingModeInfo() {
    $demoTrading = getenv('DEMO_TRADING') ?: 'false';
    $tradingMode = getenv('TRADING_MODE') ?: 'live';
    $demoMode = getenv('BINGX_DEMO_MODE') ?: 'false';
    $enableRealTrading = getenv('ENABLE_REAL_TRADING') ?: 'true';
    $apiUrl = getBingXApiUrl();
    $isDemo = isDemoMode();
    
    return [
        'is_demo_mode' => $isDemo,
        'api_url' => $apiUrl,
        'demo_trading' => $demoTrading,
        'trading_mode' => $tradingMode,
        'bingx_demo_mode' => $demoMode,
        'enable_real_trading' => $enableRealTrading,
        'live_url' => getenv('BINGX_LIVE_URL') ?: 'https://open-api.bingx.com',
        'demo_url' => getenv('BINGX_DEMO_URL') ?: 'Not configured'
    ];
}

/**
 * Log trading mode information
 */
function logTradingMode($context = '') {
    $info = getTradingModeInfo();
    $mode = $info['is_demo_mode'] ? 'DEMO' : 'LIVE';
    $prefix = !empty($context) ? "[$context] " : '';
    
    error_log("{$prefix}Trading Mode: {$mode}, API URL: {$info['api_url']}");
}

/**
 * Add trading mode headers to API responses
 */
function addTradingModeHeaders() {
    $info = getTradingModeInfo();
    $mode = $info['is_demo_mode'] ? 'DEMO' : 'LIVE';
    
    header("X-Trading-Mode: {$mode}");
    header("X-API-URL: {$info['api_url']}");
}

/**
 * Validate trading mode configuration
 */
function validateTradingMode() {
    $info = getTradingModeInfo();
    $warnings = [];
    
    // Check for potential configuration issues
    if ($info['is_demo_mode']) {
        if ($info['demo_url'] === 'Not configured') {
            $warnings[] = 'Demo mode enabled but BINGX_DEMO_URL not configured';
        }
        if (strtolower($info['enable_real_trading']) === 'true') {
            $warnings[] = 'Demo mode enabled but ENABLE_REAL_TRADING is still true';
        }
    } else {
        if (strtolower($info['demo_trading']) === 'true') {
            $warnings[] = 'DEMO_TRADING is true but other settings indicate live mode';
        }
    }
    
    return [
        'valid' => empty($warnings),
        'warnings' => $warnings,
        'info' => $info
    ];
}

?>