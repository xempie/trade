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
    <title>Pending Orders - Crypto Trading Manager</title>
    
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
<?php include 'header.php'; ?>

        <!-- Main Content Area -->
        <main class="pwa-main" style="padding-bottom: 150px;">
            <!-- Watch Section - Original Watchlist -->
            <div class="container">
                <div class="form-container">
                    <div class="watchlist-panel">
                    <div class="watchlist-header">
                        <h2>Pending Orders</h2>
                        <button class="refresh-watchlist-btn" onclick="tradingForm.refreshWatchlist()" title="Refresh prices from BingX">
                            ↻
                        </button>
                    </div>
                    
                    <div class="watchlist-items" id="watchlist-items">
                        <p class="no-watchlist">No watchlist items</p>
                    </div>
                    </div>
                </div>
            </div>
        </main>

<?php include 'nav.php'; ?>
    </div>

    <script src="script.js?v=<?php echo time(); ?>"></script>
    <script src="header.js?v=<?php echo time(); ?>"></script>
    <script>
        // Initialize limit orders page functionality
        document.addEventListener('DOMContentLoaded', () => {
            // Initialize trading form for this page (only once)
            if (!window.tradingForm && typeof TradingForm !== 'undefined') {
                window.tradingForm = new TradingForm();
                // Don't load balance data - only do this on home page
                window.tradingForm.updateWatchlistDisplay();
            }
        });
    </script>
</body>
</html>