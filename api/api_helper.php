<?php
/**
 * BingX API Helper Functions
 * Handles API URL selection based on trading mode (demo vs live)
 */

/**
 * Get the appropriate BingX API base URL based on trading mode
 */
function getBingXApiUrl() {
    $tradingMode = strtolower(getenv('TRADING_MODE') ?: 'live');
    
    if ($tradingMode === 'demo') {
        // Use demo URL if available, otherwise live URL
        return getenv('BINGX_DEMO_URL') ?: getenv('BINGX_LIVE_URL') ?: 'https://open-api.bingx.com';
    } else {
        // Use live URL
        return getenv('BINGX_LIVE_URL') ?: 'https://open-api.bingx.com';
    }
}

/**
 * Check if we're in demo trading mode
 */
function isDemoMode() {
    $tradingMode = strtolower(getenv('TRADING_MODE') ?: 'live');
    return $tradingMode === 'demo';
}

/**
 * Get trading mode information for debugging
 */
function getTradingModeInfo() {
    $tradingMode = getenv('TRADING_MODE') ?: 'live';
    $apiUrl = getBingXApiUrl();
    $isDemo = isDemoMode();
    
    return [
        'is_demo_mode' => $isDemo,
        'api_url' => $apiUrl,
        'trading_mode' => $tradingMode,
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
        if (strtolower($info['enable_real_trading']) === 'true') {
            $warnings[] = 'Demo mode enabled but ENABLE_REAL_TRADING is still true';
        }
        // Note: BingX uses same API for both demo and live, difference is in account type/restrictions
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