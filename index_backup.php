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
    <title>Crypto Trading Manager</title>
    
    <!-- PWA Meta Tags -->
    <meta name="description" content="Professional crypto futures trading management with BingX integration">
    <meta name="theme-color" content="#10b981">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="CryptoTrade">
    <meta name="mobile-web-app-capable" content="yes">
    
    <!-- PWA Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
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
                    <button class="user-menu-btn" onclick="toggleUserMenu()">
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
                        <button onclick="showInstallPrompt()" class="dropdown-item">
                            📱 Install App
                        </button>
                        <button onclick="window.location.href='auth/logout.php'" class="dropdown-item logout">
                            🚪 Logout
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="pwa-main">
            <!-- Home Section -->
            <section class="pwa-section active" id="home-section">
                <div class="section-header">
                    <h2>Dashboard</h2>
                </div>
                <div class="dashboard-grid">
                    <div class="account-info-card">
                        <div class="card-header">
                            <h3>Account Balance</h3>
                            <button class="refresh-btn" onclick="tradingForm.refreshBalance()" title="Refresh balance">
                                ↻
                            </button>
                        </div>
                        <div class="balance-grid">
                            <div class="balance-item">
                                <span class="balance-label">Total Assets</span>
                                <span class="balance-value" id="total-balance">$0.00</span>
                            </div>
                            <div class="balance-item">
                                <span class="balance-label">Available</span>
                                <span class="balance-value" id="available-balance">$0.00</span>
                            </div>
                            <div class="balance-item">
                                <span class="balance-label">Margin Used</span>
                                <span class="balance-value" id="margin-used">$0.00</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="positions-card">
                        <div class="card-header">
                            <h3>Open Positions</h3>
                            <button class="refresh-btn" onclick="tradingForm.updateRecentSignals()" title="Refresh positions">
                                ↻
                            </button>
                        </div>
                        <div class="signal-list" id="signal-list">
                            <p class="no-signals">No active positions</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Form Section -->
            <section class="pwa-section" id="form-section">
                <div class="section-header">
                    <h2>New Trade</h2>
                </div>
                <form id="trading-form" class="trading-form-pwa">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="symbol">Symbol</label>
                            <div class="symbol-input-container">
                                <input type="text" id="symbol" name="symbol" placeholder="BTC" required>
                                <div class="symbol-suggestion" id="symbol-suggestion">BTCUSDT</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="direction">Direction</label>
                            <select id="direction" name="direction" required>
                                <option value="">Select direction</option>
                                <option value="long">Long (Buy)</option>
                                <option value="short">Short (Sell)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="leverage">Leverage</label>
                            <select id="leverage" name="leverage" required>
                                <option value="">Select leverage</option>
                                <option value="1">1x</option>
                                <option value="2">2x</option>
                                <option value="3">3x</option>
                                <option value="4">4x</option>
                                <option value="5">5x</option>
                                <option value="6">6x</option>
                                <option value="7">7x</option>
                                <option value="8">8x</option>
                                <option value="9">9x</option>
                                <option value="10">10x</option>
                            </select>
                        </div>
                    </div>

                    <div class="entry-points-pwa">
                        <div class="entry-point-pwa" data-entry="market">
                            <h3>Market Entry</h3>
                            <div class="entry-inputs-grid">
                                <div class="form-group">
                                    <label for="entry_market_margin">Margin ($)</label>
                                    <input type="number" id="entry_market_margin" name="entry_market_margin" placeholder="35.00" step="0.01" min="1">
                                </div>
                                <div class="form-group">
                                    <label for="entry_market_price">Entry Price ($)</label>
                                    <input type="number" id="entry_market_price" name="entry_market_price" placeholder="Market" step="0.01" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="entry-point-pwa" data-entry="entry_2">
                            <h3>Entry 2</h3>
                            <div class="entry-inputs-grid">
                                <div class="form-group">
                                    <label for="entry_2_margin">Margin ($)</label>
                                    <input type="number" id="entry_2_margin" name="entry_2_margin" placeholder="35.00" step="0.01" min="1">
                                </div>
                                <div class="form-group">
                                    <label for="entry_2">Entry Price ($)</label>
                                    <input type="number" id="entry_2" name="entry_2" placeholder="43000.00" step="0.01">
                                </div>
                            </div>
                        </div>

                        <div class="entry-point-pwa" data-entry="entry_3">
                            <h3>Entry 3</h3>
                            <div class="entry-inputs-grid">
                                <div class="form-group">
                                    <label for="entry_3_margin">Margin ($)</label>
                                    <input type="number" id="entry_3_margin" name="entry_3_margin" placeholder="35.00" step="0.01" min="1">
                                </div>
                                <div class="form-group">
                                    <label for="entry_3">Entry Price ($)</label>
                                    <input type="number" id="entry_3" name="entry_3" placeholder="42000.00" step="0.01">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" placeholder="Add any notes about this signal..." rows="3"></textarea>
                    </div>

                    <div class="form-actions-pwa">
                        <button type="button" class="btn btn-secondary" id="add-to-watchlist">Add to Watchlist</button>
                        <button type="button" class="btn btn-secondary" id="reset-form">Reset</button>
                        <button type="submit" class="btn btn-primary" id="submit-btn">Open Position</button>
                    </div>
                </form>
            </section>

            <!-- Orders Section -->
            <section class="pwa-section" id="orders-section">
                <div class="section-header">
                    <h2>Orders</h2>
                </div>
                <div class="positions-list" id="orders-positions-list">
                    <!-- Will be populated by JavaScript -->
                </div>
            </section>

            <!-- Watch Section -->
            <section class="pwa-section" id="watch-section">
                <div class="section-header">
                    <h2>Watchlist</h2>
                </div>
                <div class="watchlist-tabs-pwa">
                    <button class="watchlist-tab-pwa active" data-tab="watchlist">Watchlist</button>
                    <button class="watchlist-tab-pwa" data-tab="limit-orders">Limit Orders</button>
                </div>
                
                <div class="watchlist-content-pwa">
                    <div class="watchlist-tab-content-pwa active" id="watchlist-tab-pwa">
                        <div class="watchlist-items-pwa" id="watchlist-items-pwa">
                            <p class="no-items">No watchlist items</p>
                        </div>
                    </div>
                    
                    <div class="watchlist-tab-content-pwa" id="limit-orders-tab-pwa">
                        <div class="watchlist-items-pwa" id="limit-orders-items-pwa">
                            <p class="no-items">No pending orders</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Settings Section -->
            <section class="pwa-section" id="settings-section">
                <div class="section-header">
                    <h2>Settings</h2>
                </div>
                <div class="settings-grid">
                    <div class="settings-card">
                        <h3>Account</h3>
                        <div class="settings-item">
                            <span>Email</span>
                            <span><?php echo htmlspecialchars($user['email'] ?? 'Not available'); ?></span>
                        </div>
                        <div class="settings-item">
                            <span>Name</span>
                            <span><?php echo htmlspecialchars($user['name'] ?? 'Not available'); ?></span>
                        </div>
                    </div>
                    
                    <div class="settings-card">
                        <h3>App</h3>
                        <div class="settings-item">
                            <button onclick="showInstallPrompt()" class="settings-btn">
                                📱 Install PWA
                            </button>
                        </div>
                        <div class="settings-item">
                            <button onclick="clearAppCache()" class="settings-btn">
                                🗑️ Clear Cache
                            </button>
                        </div>
                    </div>
                    
                    <div class="settings-card">
                        <h3>Trading</h3>
                        <div class="settings-item">
                            <span>Default Leverage</span>
                            <select id="default-leverage" onchange="saveDefaultLeverage()">
                                <option value="1">1x</option>
                                <option value="2">2x</option>
                                <option value="3">3x</option>
                                <option value="5" selected>5x</option>
                                <option value="10">10x</option>
                            </select>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- Bottom Navigation -->
        <nav class="bottom-nav">
            <button class="nav-item active" data-section="home">
                <svg class="nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                </svg>
                <span class="nav-label">Home</span>
            </button>
            
            <button class="nav-item" data-section="form">
                <svg class="nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                </svg>
                <span class="nav-label">Trade</span>
            </button>
            
            <button class="nav-item" data-section="orders">
                <svg class="nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M9 11H7v9a2 2 0 002 2h8a2 2 0 002-2V9a2 2 0 00-2-2h-3V5a2 2 0 00-2-2H9a2 2 0 00-2 2v6zm0-6h3v2h-3V5z"/>
                </svg>
                <span class="nav-label">Orders</span>
            </button>
            
            <button class="nav-item" data-section="watch">
                <svg class="nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                </svg>
                <span class="nav-label">Watch</span>
            </button>
            
            <button class="nav-item" data-section="settings">
                <svg class="nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.07-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.74,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.82,11.69,4.82,12s0.02,0.64,0.07,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.47-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/>
                </svg>
                <span class="nav-label">Settings</span>
            </button>
        </nav>
    </div>

    <!-- PWA Install Banner -->
    <div class="install-banner" id="install-banner" style="display: none;">
        <div class="install-content">
            <div class="install-icon">📱</div>
            <div class="install-text">
                <strong>Install CryptoTrade</strong>
                <span>Get the full app experience</span>
            </div>
            <div class="install-actions">
                <button onclick="installPWA()" class="install-btn">Install</button>
                <button onclick="dismissInstall()" class="dismiss-btn">Later</button>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        // PWA Registration and functionality
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/trade/sw.js')
                    .then(registration => {
                        console.log('PWA: Service Worker registered successfully');
                    })
                    .catch(error => {
                        console.log('PWA: Service Worker registration failed');
                    });
            });
        }

        // PWA Install prompt
        let deferredPrompt;
        
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            document.getElementById('install-banner').style.display = 'block';
        });

        function installPWA() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('PWA: User accepted the install prompt');
                    } else {
                        console.log('PWA: User dismissed the install prompt');
                    }
                    deferredPrompt = null;
                    document.getElementById('install-banner').style.display = 'none';
                });
            }
        }

        function dismissInstall() {
            document.getElementById('install-banner').style.display = 'none';
            deferredPrompt = null;
        }

        function toggleUserMenu() {
            const dropdown = document.getElementById('user-dropdown');
            dropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            const dropdown = document.getElementById('user-dropdown');
            const button = document.querySelector('.user-menu-btn');
            if (!button.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });

        function showInstallPrompt() {
            if (deferredPrompt) {
                installPWA();
            } else {
                alert('App is already installed or installation is not available.');
            }
        }

        function clearAppCache() {
            if ('caches' in window) {
                caches.keys().then(names => {
                    names.forEach(name => {
                        caches.delete(name);
                    });
                    alert('Cache cleared successfully!');
                    location.reload();
                });
            }
        }

        function saveDefaultLeverage() {
            const leverage = document.getElementById('default-leverage').value;
            localStorage.setItem('default_leverage', leverage);
            alert('Default leverage saved: ' + leverage + 'x');
        }

        // Load saved leverage
        document.addEventListener('DOMContentLoaded', () => {
            const savedLeverage = localStorage.getItem('default_leverage');
            if (savedLeverage) {
                document.getElementById('default-leverage').value = savedLeverage;
            }
        });
    </script>
</body>
</html>