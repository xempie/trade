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
    <title>Limits - Crypto Trading Manager</title>
    
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
        <main class="pwa-main" style="padding-bottom: 150px;">
            <!-- Limit Orders Section - Same structure as Watchlist -->
            <div class="container">
                <div class="form-container">
                    <div class="watchlist-panel">
                    <div class="watchlist-header">
                        <h2>Limit Orders</h2>
                        <button class="refresh-watchlist-btn" onclick="tradingForm.refreshLimitOrders()" title="Refresh limit orders from BingX">
                            ↻
                        </button>
                    </div>
                    
                    <div class="watchlist-items" id="watchlist-items">
                        <p class="no-watchlist">No limit orders</p>
                    </div>
                    </div>
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
            
            <a href="limit-orders.php" class="nav-item active">
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
            
            <a href="settings.php" class="nav-item">
                <svg class="nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 15.5A3.5 3.5 0 018.5 12 3.5 3.5 0 0112 8.5a3.5 3.5 0 013.5 3.5 3.5 3.5 0 01-3.5 3.5m7.43-2.53c.04-.32.07-.64.07-.97 0-.33-.03-.66-.07-1l2.11-1.63c.19-.15.24-.42.12-.64l-2-3.46c-.12-.22-.39-.31-.61-.22l-2.49 1c-.52-.39-1.06-.73-1.69-.98l-.37-2.65A.506.506 0 0014 2h-4c-.25 0-.46.18-.5.42l-.37 2.65c-.63.25-1.17.59-1.69.98l-2.49-1c-.22-.09-.49 0-.61.22l-2 3.46c-.13.22-.07.49.12.64L4.57 11c-.04.34-.07.67-.07 1 0 .33.03.65.07.97l-2.11 1.66c-.19.15-.25.42-.12.64l2 3.46c.12.22.39.3.61.22l2.49-1.01c.52.4 1.06.74 1.69.99l.37 2.65c.04.24.25.42.5.42h4c.25 0 .46-.18.5-.42l.37-2.65c.63-.26 1.17-.59 1.69-.99l2.49 1.01c.22.08.49 0 .61-.22l2-3.46c.12-.22.07-.49-.12-.64l-2.11-1.66Z"/>
                </svg>
                <span class="nav-label">Settings</span>
            </a>
        </nav>
    </div>

    <script src="script.js?v=<?php echo time(); ?>"></script>
    <script>
        // Initialize PWA navigation and user menu only
        document.addEventListener('DOMContentLoaded', () => {
            // Initialize trading form for this page (only once)
            if (!window.tradingForm) {
                window.tradingForm = new TradingForm();
                // Don't load balance data - only do this on home page
                window.tradingForm.updateLimitOrdersDisplay();
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