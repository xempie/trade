<?php
require_once 'auth/config.php';

// Require authentication
requireAuth();

// Get current user
$user = getCurrentUser();

// For localhost, use simplified display
$isLocal = isLocalhost();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Settings - Crypto Trading Manager</title>
    
    <!-- PWA Meta Tags -->
    <meta name="description" content="Professional crypto futures trading management with BingX integration">
    <meta name="theme-color" content="#1a1a1a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="CryptoTrade">
    <meta name="mobile-web-app-capable" content="yes">
    
    <!-- PWA Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="icons/icon-72x72.png">
    <link rel="apple-touch-icon" sizes="180x180" href="icons/icon-192x192.png">
    <link rel="icon" type="image/png" sizes="192x192" href="icons/icon-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="icons/icon-512x512.png">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="pwa-app settings">
    <!-- PWA App Shell -->
    <div class="pwa-container">
        <!-- Top Header -->
        <header class="pwa-header">
            <div class="header-left">
                <div class="logo">
                    <span class="logo-icon">₿</span>
                    <span class="logo-text">CryptoTrade</span>
                </div>
            </div>
            <div class="header-right">
                <div class="user-menu">
                    <button class="user-menu-btn" id="user-menu-button">
                        <?php if (!$isLocal && $user['picture']): ?>
                            <img src="<?php echo htmlspecialchars($user['picture']); ?>" alt="Profile" class="user-avatar-small">
                        <?php else: ?>
                            <div class="user-avatar-fallback">
                                <?php echo substr($user['name'] ?? 'U', 0, 1); ?>
                            </div>
                        <?php endif; ?>
                        <span class="user-name-small"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></span>
                        <svg class="dropdown-icon" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M7 10l5 5 5-5z"/>
                        </svg>
                    </button>
                    <div class="user-dropdown" id="user-dropdown">
                        <?php if (!$isLocal): ?>
                            <div class="user-info-dropdown">
                                <div class="user-email"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
                            </div>
                        <?php endif; ?>
                        <button id="install-btn" class="dropdown-item" style="display: none;">
                            📱 Install App
                        </button>
                        <button id="clear-cache-btn" class="dropdown-item">
                            🗑️ Clear Cache
                        </button>
                        <button id="logout-btn" class="dropdown-item logout">
                            🚪 Logout
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="pwa-main" style="padding-bottom: 150px;">
            <!-- Settings Form -->
            <div class="container">
                <div class="form-container">
                    <div class="header">
                        <h1>Application Settings</h1>
                        <p>Configure your trading preferences and API connections</p>
                    </div>

                    <form id="settings-form" class="trading-form">
                        <!-- Account Information -->
                        <div class="settings-group">
                            <h3>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 8px;">
                                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                </svg>
                                Account Information
                            </h3>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['email'] ?? 'Not available'); ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label>Name</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['name'] ?? 'Not available'); ?>" disabled>
                            </div>
                        </div>

                        <!-- BingX API Configuration -->
                        <div class="settings-group">
                            <h3>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 8px;">
                                    <path d="M6 10v-4a6 6 0 116 6v4h2a2 2 0 012 2v7a2 2 0 01-2 2H4a2 2 0 01-2-2v-7a2 2 0 012-2h2zm2-4a4 4 0 118 0v4H8V6z"/>
                                </svg>
                                BingX API Configuration
                            </h3>
                            <div class="form-group">
                                <label for="bingx_api_key">API Key</label>
                                <input type="text" id="bingx_api_key" name="bingx_api_key" placeholder="Enter your BingX API Key" required>
                            </div>
                            <div class="form-group">
                                <label for="bingx_secret_key">Secret Key</label>
                                <input type="password" id="bingx_secret_key" name="bingx_secret_key" placeholder="Enter your BingX Secret Key" required>
                                <button type="button" class="toggle-password" onclick="togglePassword('bingx_secret_key')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="form-group">
                                <label for="bingx_passphrase">Passphrase</label>
                                <input type="password" id="bingx_passphrase" name="bingx_passphrase" placeholder="Enter your BingX Passphrase" required>
                                <button type="button" class="toggle-password" onclick="togglePassword('bingx_passphrase')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Telegram Configuration -->
                        <div class="settings-group">
                            <h3>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 8px;">
                                    <path d="M20.665 3.717l-17.73 6.837c-1.21.486-1.203 1.161-.222 1.462l4.552 1.42 10.532-6.645c.498-.303.953-.14.579.192l-8.533 7.701h-.002l.002.001-.314 4.692c.46 0 .663-.211.924-.458l2.211-2.15 4.599 3.397c.848.467 1.457.227 1.668-.785L21.95 4.725c.309-1.239-.473-1.8-1.285-1.008z"/>
                                </svg>
                                Telegram Bot Configuration
                            </h3>
                            <div class="form-group">
                                <label for="telegram_bot_token">Bot Token</label>
                                <input type="text" id="telegram_bot_token" name="telegram_bot_token" placeholder="Enter Telegram Bot Token" required>
                            </div>
                            <div class="form-group">
                                <label for="telegram_chat_id">Chat ID</label>
                                <input type="text" id="telegram_chat_id" name="telegram_chat_id" placeholder="Enter Telegram Chat ID" required>
                            </div>
                        </div>

                        <!-- Trading Preferences -->
                        <div class="settings-group">
                            <h3>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 8px;">
                                    <path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.07-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.74,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.82,11.69,4.82,12s0.02,0.64,0.07,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.47-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/>
                                </svg>
                                Trading Preferences
                            </h3>
                            <div class="form-group">
                                <label for="position_size_percent">Position Size (%)</label>
                                <input type="number" id="position_size_percent" name="position_size_percent" 
                                       placeholder="3.3" step="0.1" min="0.1" max="50" required>
                                <small>Percentage of available balance to use per position</small>
                            </div>
                            <div class="form-group">
                                <label for="entry_2_percent">Default Entry 2 Percentage (%)</label>
                                <input type="number" id="entry_2_percent" name="entry_2_percent" 
                                       placeholder="2.0" step="0.1" min="0.1" max="20" required>
                                <small>Default percentage for Entry 2 calculations</small>
                            </div>
                            <div class="form-group">
                                <label for="entry_3_percent">Default Entry 3 Percentage (%)</label>
                                <input type="number" id="entry_3_percent" name="entry_3_percent" 
                                       placeholder="4.0" step="0.1" min="0.1" max="20" required>
                                <small>Default percentage for Entry 3 calculations</small>
                            </div>
                        </div>

                        <!-- Alert Preferences -->
                        <div class="settings-group">
                            <h3>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 8px;">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                </svg>
                                Alert Preferences
                            </h3>
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="send_balance_alerts" name="send_balance_alerts">
                                    <span class="checkbox-custom"></span>
                                    Send Balance Change Alerts
                                </label>
                                <small>Get notified when your account balance changes significantly</small>
                            </div>
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="send_profit_loss_alerts" name="send_profit_loss_alerts">
                                    <span class="checkbox-custom"></span>
                                    Send Profit/Loss Alerts
                                </label>
                                <small>Get notified about position profit and loss updates</small>
                            </div>
                        </div>

                        <!-- Trading Mode Configuration -->
                        <div class="settings-group">
                            <h3>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 8px;">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                </svg>
                                Trading Mode
                            </h3>
                            <div class="form-group">
                                <label>Trading Environment</label>
                                <div class="radio-group">
                                    <label class="radio-label">
                                        <input type="radio" name="trading_mode" value="live" id="trading_mode_live">
                                        <span class="radio-custom"></span>
                                        <strong>Live Trading</strong>
                                        <small>Real money trading with actual BingX account</small>
                                    </label>
                                    <label class="radio-label">
                                        <input type="radio" name="trading_mode" value="demo" id="trading_mode_demo">
                                        <span class="radio-custom"></span>
                                        <strong>Demo Trading</strong>
                                        <small>Paper trading for testing strategies</small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Trading Automation Settings -->
                        <div class="settings-group">
                            <h3>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 8px;">
                                    <path d="M12,8A4,4 0 0,1 16,12A4,4 0 0,1 12,16A4,4 0 0,1 8,12A4,4 0 0,1 12,8M12,10A2,2 0 0,0 10,12A2,2 0 0,0 12,14A2,2 0 0,0 14,12A2,2 0 0,0 12,10M10,22C9.75,22 9.54,21.82 9.5,21.58L9.13,18.93C8.5,18.68 7.96,18.34 7.44,17.94L4.95,18.95C4.73,19.03 4.46,18.95 4.34,18.73L2.34,15.27C2.21,15.05 2.27,14.78 2.46,14.63L4.57,12.97L4.5,12L4.57,11.03L2.46,9.37C2.27,9.22 2.21,8.95 2.34,8.73L4.34,5.27C4.46,5.05 4.73,4.96 4.95,5.05L7.44,6.05C7.96,5.66 8.5,5.32 9.13,5.07L9.5,2.42C9.54,2.18 9.75,2 10,2H14C14.25,2 14.46,2.18 14.5,2.42L14.87,5.07C15.5,5.32 16.04,5.66 16.56,6.05L19.05,5.05C19.27,4.96 19.54,5.05 19.66,5.27L21.66,8.73C21.79,8.95 21.73,9.22 21.54,9.37L19.43,11.03L19.5,12L19.43,12.97L21.54,14.63C21.73,14.78 21.79,15.05 21.66,15.27L19.66,18.73C19.54,18.95 19.27,19.04 19.05,18.95L16.56,17.95C16.04,18.34 15.5,18.68 14.87,18.93L14.5,21.58C14.46,21.82 14.25,22 14,22H10M11.25,4L10.88,6.61C9.68,6.86 8.62,7.5 7.85,8.39L5.44,7.35L4.69,8.65L6.8,10.2C6.4,11.37 6.4,12.64 6.8,13.8L4.68,15.36L5.43,16.66L7.86,15.62C8.63,16.5 9.68,17.14 10.87,17.38L11.24,20H12.76L13.13,17.39C14.32,17.14 15.37,16.5 16.14,15.62L18.57,16.66L19.32,15.36L17.2,13.81C17.6,12.64 17.6,11.37 17.2,10.2L19.31,8.65L18.56,7.35L16.15,8.39C15.38,7.5 14.32,6.86 13.12,6.62L12.75,4H11.25Z"/>
                                </svg>
                                Trading Automation
                            </h3>
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="auto_trading_enabled" name="auto_trading_enabled">
                                    <span class="checkbox-custom"></span>
                                    Enable Auto Trading
                                </label>
                            </div>
                            <div class="form-group">
                                <label>Limit Order Action</label>
                                <div class="radio-group">
                                    <label class="radio-label">
                                        <input type="radio" name="limit_order_action" value="auto_execute" id="limit_order_auto">
                                        <span class="radio-custom"></span>
                                        Auto Execute
                                        <small>Automatically open position at market price</small>
                                    </label>
                                    <label class="radio-label">
                                        <input type="radio" name="limit_order_action" value="telegram_approval" id="limit_order_telegram">
                                        <span class="radio-custom"></span>
                                        Telegram Approval
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Target & Stop Loss Automation -->
                        <div class="settings-group">
                            <h3>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 8px;">
                                    <path d="M12,2A10,10 0 0,1 22,12A10,10 0 0,1 12,22A10,10 0 0,1 2,12A10,10 0 0,1 12,2M7.07,18.28C7.5,17.38 8.12,16.5 8.91,15.77L9.5,16.36C8.75,17.13 8.15,18 7.75,18.87L7.07,18.28M5.72,16.93C5.38,16.14 5.17,15.29 5.1,14.47L5.96,14.35C6.03,15.04 6.21,15.71 6.5,16.32L5.72,16.93M5.09,10.91C5.62,9.29 6.69,7.95 8.1,7.06L8.61,7.85C7.5,8.55 6.63,9.63 6.21,10.89L5.09,10.91M12,5A7,7 0 0,0 5,12C5,13.64 5.55,15.15 6.46,16.36C6.55,16.13 6.65,15.91 6.77,15.69A6,6 0 1,1 18,12A6,6 0 0,1 12,18C11.68,18 11.37,17.97 11.06,17.92C11.32,18.19 11.61,18.45 11.91,18.69C12,18.69 12,18.7 12,18.7A7,7 0 0,0 19,12A7,7 0 0,0 12,5Z"/>
                                </svg>
                                Target & Stop Loss
                            </h3>
                            <div class="form-group">
                                <label for="target_percentage">Target Percentage (%)</label>
                                <input type="number" id="target_percentage" name="target_percentage" min="1" max="1000" step="0.1" placeholder="10">
                                <small>Default target percentage for positions</small>
                            </div>
                            <div class="form-group">
                                <label>Target Action</label>
                                <div class="radio-group">
                                    <label class="radio-label">
                                        <input type="radio" name="target_action" value="auto_close" id="target_auto_close">
                                        <span class="radio-custom"></span>
                                        Auto Close
                                        <small>Automatically close position at target</small>
                                    </label>
                                    <label class="radio-label">
                                        <input type="radio" name="target_action" value="telegram_notify" id="target_telegram_notify">
                                        <span class="radio-custom"></span>
                                        Telegram Notify
                                    </label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="auto_stop_loss" name="auto_stop_loss">
                                    <span class="checkbox-custom"></span>
                                    Auto Stop Loss
                                </label>
                            </div>
                        </div>

                        <!-- App Settings -->
                        <div class="settings-group">
                            <h3>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 8px;">
                                    <path d="M7 4V2C7 1.45 7.45 1 8 1H16C16.55 1 17 1.45 17 2V4H20C20.55 4 21 4.45 21 5S20.55 6 20 6H19V19C19 20.1 18.1 21 17 21H7C5.9 21 5 20.1 5 19V6H4C3.45 6 3 5.55 3 5S3.45 4 4 4H7ZM9 3V4H15V3H9ZM7 6V19H17V6H7Z"/>
                                </svg>
                                App Settings
                            </h3>
                            <div class="form-group">
                                <label>Cache Status</label>
                                <div class="info-item">
                                    <span class="value" id="cache-info">Loading...</span>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="clearAppCache()">Clear Cache</button>
                                <button type="button" class="btn btn-secondary" onclick="installPWA()">Install PWA</button>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="loadSettings()">Reset</button>
                            <button type="submit" class="btn btn-primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 6px;">
                                    <path d="M17 3H5C3.89 3 3 3.9 3 5V19C3 20.1 3.89 21 5 21H19C20.1 21 21 20.1 21 19V7L17 3ZM12 19C10.34 19 9 17.66 9 16S10.34 13 12 13 15 14.34 15 16 13.66 19 12 19ZM15 9H5V5H15V9Z"/>
                                </svg>
                                Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>

        <!-- Bottom Navigation -->
        <nav class="bottom-nav">
            <a href="home.php" class="nav-item">
                <svg class="nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                </svg>
                <span class="nav-label">Home</span>
            </a>
            
            <a href="trade.php" class="nav-item">
                <svg class="nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                </svg>
                <span class="nav-label">Trade</span>
            </a>
            
            <a href="orders.php" class="nav-item">
                <svg class="nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M9 11H7v9a2 2 0 002 2h8a2 2 0 002-2V9a2 2 0 00-2-2h-3V5a2 2 0 00-2-2H9a2 2 0 00-2 2v6zm0-6h3v2h-3V5z"/>
                </svg>
                <span class="nav-label">Orders</span>
            </a>
            
            <a href="limit-orders.php" class="nav-item">
                <svg class="nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M6 2v6h.01L6 8.01 10 12l-4 4 .01.01H6V22h12v-5.99h-.01L18 16l-4-4 4-4-.01-.01H18V2H6zm10 14.5V20H8v-3.5l4-4 4 4zM16 4v3.5l-4 4-4-4V4h8z"/>
                </svg>
                <span class="nav-label">Limits</span>
            </a>
            
            <a href="watch.php" class="nav-item">
                <svg class="nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                </svg>
                <span class="nav-label">Watch</span>
            </a>
            
            <a href="settings.php" class="nav-item active">
                <svg class="nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.07-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.74,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.82,11.69,4.82,12s0.02,0.64,0.07,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.47-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/>
                </svg>
                <span class="nav-label">Settings</span>
            </a>
        </nav>
    </div>

    <script src="script.js?v=<?php echo time(); ?>"></script>
    <script>
        // Settings management
        let currentSettings = {};

        // PWA Install functionality
        let deferredPrompt;
        
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            document.getElementById('install-btn').style.display = 'block';
        });

        function installPWA() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        showNotification('App installed successfully!', 'success');
                    }
                    deferredPrompt = null;
                    document.getElementById('install-btn').style.display = 'none';
                });
            } else {
                showNotification('App is already installed or installation is not available.', 'info');
            }
        }

        function clearAppCache() {
            if (!confirm('Clear all cached data? This will require re-downloading resources.')) {
                return;
            }

            if ('caches' in window) {
                caches.keys().then(names => {
                    names.forEach(name => {
                        caches.delete(name);
                    });
                    localStorage.clear();
                    showNotification('Cache cleared successfully!', 'success');
                    updateCacheInfo();
                });
            }
        }

        function updateCacheInfo() {
            const cacheInfoEl = document.getElementById('cache-info');
            if (!cacheInfoEl) return;

            if ('caches' in window) {
                caches.keys().then(names => {
                    cacheInfoEl.textContent = names.length > 0 ? `Active (${names.length} caches)` : 'No cache';
                });
            } else {
                cacheInfoEl.textContent = 'Not supported';
            }
        }

        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            const svg = button.querySelector('svg path');
            
            if (field.type === 'password') {
                field.type = 'text';
                // Change to "hide" icon (eye with slash)
                svg.setAttribute('d', 'M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92 1.41-1.41L3.51 1.93 2.1 3.34l2.36 2.36C4.06 6.26 3.73 6.61 3.73 7c0 4.39 6 7.5 11 7.5.79 0 1.57-.09 2.32-.27l.81.81c-.78.18-1.58.27-2.39.27-5.52 0-10-3.11-11-7.5.4-1.61 1.35-3.14 2.64-4.36l-.83-.83zm9.27 2.83c-.13-.13-.27-.26-.42-.38L12 7c2.76 0 5 2.24 5 5l-2.73-2.73c.13-1.09-.87-2.09-1.96-1.96z');
            } else {
                field.type = 'password';
                // Change back to normal eye icon
                svg.setAttribute('d', 'M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z');
            }
        }

        async function loadSettings() {
            try {
                const response = await fetch('api/get_settings.php');
                const data = await response.json();
                
                if (data.success) {
                    currentSettings = data.settings;
                    
                    // Populate form fields
                    document.getElementById('bingx_api_key').value = currentSettings.bingx_api_key;
                    document.getElementById('bingx_secret_key').value = currentSettings.bingx_secret_key;
                    document.getElementById('bingx_passphrase').value = currentSettings.bingx_passphrase;
                    document.getElementById('telegram_bot_token').value = currentSettings.telegram_bot_token;
                    document.getElementById('telegram_chat_id').value = currentSettings.telegram_chat_id;
                    document.getElementById('position_size_percent').value = currentSettings.position_size_percent;
                    document.getElementById('entry_2_percent').value = currentSettings.entry_2_percent;
                    document.getElementById('entry_3_percent').value = currentSettings.entry_3_percent;
                    document.getElementById('send_balance_alerts').checked = currentSettings.send_balance_alerts;
                    document.getElementById('send_profit_loss_alerts').checked = currentSettings.send_profit_loss_alerts;
                    
                } else {
                    showNotification('Error loading settings: ' + data.error, 'error');
                }
            } catch (error) {
                console.error('Load settings error:', error);
                showNotification('Failed to load settings', 'error');
            }
        }

        async function saveSettings(formData) {
            try {
                const response = await fetch('api/save_settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Settings saved successfully!', 'success');
                    
                    // Broadcast settings update to other pages
                    if ('BroadcastChannel' in window) {
                        const channel = new BroadcastChannel('settings-update');
                        channel.postMessage({ type: 'settings-updated' });
                        channel.close();
                    }
                    
                    return true;
                } else {
                    showNotification('Error saving settings: ' + data.error, 'error');
                    return false;
                }
            } catch (error) {
                console.error('Save settings error:', error);
                showNotification('Failed to save settings', 'error');
                return false;
            }
        }

        function showNotification(message, type) {
            // Create a simple notification
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed;
                top: 80px;
                right: 20px;
                background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                z-index: 1000;
                font-weight: 500;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            updateCacheInfo();
            loadSettings();
            
            // Setup user menu functionality
            const userMenuButton = document.getElementById('user-menu-button');
            const userDropdown = document.getElementById('user-dropdown');

            if (userMenuButton && userDropdown) {
                userMenuButton.addEventListener('click', (e) => {
                    e.stopPropagation();
                    userDropdown.classList.toggle('show');
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', (e) => {
                    if (!userMenuButton.contains(e.target) && !userDropdown.contains(e.target)) {
                        userDropdown.classList.remove('show');
                    }
                });

                // Handle dropdown menu items
                const logoutBtn = document.getElementById('logout-btn');
                const clearCacheBtn = document.getElementById('clear-cache-btn');
                const installBtn = document.getElementById('install-btn');

                if (logoutBtn) {
                    logoutBtn.addEventListener('click', () => {
                        if (confirm('Are you sure you want to logout?')) {
                            window.location.href = 'auth/logout.php';
                        }
                    });
                }

                if (clearCacheBtn) {
                    clearCacheBtn.addEventListener('click', () => clearAppCache());
                }

                if (installBtn) {
                    installBtn.addEventListener('click', () => installPWA());
                }
            }
            
            // Handle form submission
            document.getElementById('settings-form').addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const formData = new FormData(e.target);
                const settings = {
                    bingx_api_key: formData.get('bingx_api_key'),
                    bingx_secret_key: formData.get('bingx_secret_key'),
                    bingx_passphrase: formData.get('bingx_passphrase'),
                    telegram_bot_token: formData.get('telegram_bot_token'),
                    telegram_chat_id: formData.get('telegram_chat_id'),
                    position_size_percent: parseFloat(formData.get('position_size_percent')),
                    entry_2_percent: parseFloat(formData.get('entry_2_percent')),
                    entry_3_percent: parseFloat(formData.get('entry_3_percent')),
                    send_balance_alerts: formData.has('send_balance_alerts'),
                    send_profit_loss_alerts: formData.has('send_profit_loss_alerts')
                };
                
                await saveSettings(settings);
            });
        });
    </script>
</body>
</html>