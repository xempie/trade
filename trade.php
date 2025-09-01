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
    <title>Trade - Crypto Trading Manager</title>
    
    <!-- PWA Meta Tags -->
    <meta name="description" content="Professional crypto futures trading management with BingX integration">
    <meta name="theme-color" content="#10b981">
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
<body class="pwa-app">
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
        <main class="pwa-main" style="padding-bottom: 100px;">
            <!-- Trade Section - Original Form -->
            <div class="container">
                <div class="form-container">
                    <div class="header">
                        <h1>Trading Form</h1>                
                    </div>

                    <form id="trading-form" class="trading-form">
                        <div class="form-group">
                            <label for="symbol">Symbol</label>
                            <div class="symbol-input-container">
                                <input type="text" id="symbol" name="symbol" placeholder="Enter crypto symbol (e.g., BTC, ADA, ETH)" required>
                                <button type="button" class="signal-pattern-btn" id="signal-pattern-btn" title="Parse signal pattern">
                                    📋
                                </button>
                            </div>
                            <div class="signal-pattern-container" id="signal-pattern-container" style="display: none;">
                                <textarea id="signal-pattern-input" placeholder="Paste your Persian signal pattern here..." rows="4"></textarea>
                                <div class="signal-pattern-actions">
                                    <button type="button" class="btn btn-small btn-telegram" id="parse-signal-btn">Extract Signal</button>
                                    <button type="button" class="btn btn-small btn-secondary" id="close-signal-pattern-btn">Close</button>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="direction">Direction</label>
                            <div class="radio-group">
                                <label class="radio-label">
                                    <input type="radio" name="direction" value="long" checked>
                                    <span class="radio-custom"></span>
                                    Long
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="direction" value="short">
                                    <span class="radio-custom"></span>
                                    Short
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="leverage">Leverage</label>
                            <select id="leverage" name="leverage" required>
                                <option value="1">1x</option>
                                <option value="2">2x</option>
                                <option value="3">3x</option>
                                <option value="4">4x</option>
                                <option value="5">5x</option>
                                <option value="6">6x</option>
                                <option value="7">7x</option>
                                <option value="8">8x</option>
                                <option value="9">9x</option>
                                <option value="10" selected="true">10x</option>
                            </select>
                        </div>

                        <div class="entry-points">
                            <h3>Entry Points</h3>
                            
                            <div class="form-group">
                                <label for="entry_market">Market Entry</label>
                                <div class="entry-row">
                                    <input type="number" id="entry_market_margin" name="entry_market_margin" placeholder="Order Value in $" step="0.01" class="margin-input">
                                    <input type="number" id="entry_market" name="entry_market" placeholder="Market price" step="0.00001">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="entry_2">Entry 2</label>
                                <div class="entry-row">
                                    <input type="number" id="entry_2_margin" name="entry_2_margin" placeholder="Order Value in $" step="0.01" class="margin-input">
                                    <input type="number" id="entry_2_percent" name="entry_2_percent" placeholder="%" step="0.1" class="percent-input">
                                    <input type="number" id="entry_2" name="entry_2" placeholder="Calculated price" step="0.00001" readonly>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="entry_3">Entry 3</label>
                                <div class="entry-row">
                                    <input type="number" id="entry_3_margin" name="entry_3_margin" placeholder="Order Value in $" step="0.01" class="margin-input">
                                    <input type="number" id="entry_3_percent" name="entry_3_percent" placeholder="%" step="0.1" class="percent-input">
                                    <input type="number" id="entry_3" name="entry_3" placeholder="Calculated price" step="0.00001" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="stop_loss">Stop Loss</label>
                            <input type="number" id="stop_loss" name="stop_loss" placeholder="Stop loss price" step="0.00001">
                        </div>

                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" placeholder="Add any notes about this signal..." rows="3"></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" id="add-to-watchlist">Add to Watch List</button>
                            <button type="button" class="btn btn-secondary" id="reset-form">Reset Form</button>
                            <button type="submit" class="btn btn-primary" id="submit-btn">Open Long Position</button>
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
            
            <a href="trade.php" class="nav-item active">
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
            
            <a href="watch.php" class="nav-item">
                <svg class="nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                </svg>
                <span class="nav-label">Watch</span>
            </a>
            
            <a href="settings.php" class="nav-item">
                <svg class="nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.07-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.74,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.82,11.69,4.82,12s0.02,0.64,0.07,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.47-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/>
                </svg>
                <span class="nav-label">Settings</span>
            </a>
        </nav>
    </div>

    <script src="script.js"></script>
    <script>
        // Initialize PWA navigation and user menu only
        document.addEventListener('DOMContentLoaded', () => {
            // Initialize trading form for this page (only once)
            if (!window.tradingForm) {
                window.tradingForm = new TradingForm();
                window.tradingForm.loadDraft();
                window.tradingForm.loadBalanceData();
                window.tradingForm.updateRecentSignals();
                window.tradingForm.updateWatchlistDisplay();
                window.tradingForm.initializeTabs();
            }
            
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
        });
        
        // PWA functionality
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
                        alert('App installed successfully!');
                    }
                    deferredPrompt = null;
                    document.getElementById('install-btn').style.display = 'none';
                });
            } else {
                alert('App is already installed or installation is not available.');
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
                    alert('Cache cleared successfully!');
                    location.reload();
                });
            }
        }
    </script>
</body>
</html>