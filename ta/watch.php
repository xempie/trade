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
    <?php include 'meta.php'; ?>
    <title>Watch - Crypto Trading Manager</title>
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
                        <h2>Watch List</h2>
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

    <script src="assets/js/timezone-helper.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/script.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/header.js?v=<?php echo time(); ?>"></script>
    <script>
        // Initialize watchlist functionality
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof TradingForm !== 'undefined') {
                if (!window.tradingForm) {
                    window.tradingForm = new TradingForm();
                }
                window.tradingForm.updateWatchlistDisplay();
            } else {
                console.error('TradingForm is not defined - script.js may not have loaded');
                setTimeout(() => {
                    if (typeof TradingForm !== 'undefined') {
                        window.tradingForm = new TradingForm();
                        window.tradingForm.updateWatchlistDisplay();
                    }
                }, 1000);
            }
        });
    </script>
</body>
</html>