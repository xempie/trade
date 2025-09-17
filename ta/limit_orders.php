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
    <title>Limit Orders - Crypto Trading Manager</title>
</head>
<body class="pwa-app">
    <!-- PWA App Shell -->
    <div class="pwa-container">
    <?php include 'header.php'; ?>

        <!-- Main Content Area -->
        <main class="pwa-main" style="padding-bottom: 150px;">
            <!-- Limit Orders Section -->
            <div class="container">
                <div class="form-container">
                    <div class="watchlist-panel">
                    <div class="watchlist-header">
                        <h2>Limit Orders</h2>
                        <button class="refresh-watchlist-btn" onclick="limitOrdersManager.refreshLimitOrders()" title="Refresh prices from BingX">
                            ↻
                        </button>
                    </div>
                    
                    <div class="watchlist-items" id="limit-orders-items">
                        <p class="no-watchlist">No limit orders</p>
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
    <script src="assets/js/limit-orders.js?v=<?php echo time(); ?>"></script>
    <script>
        // Initialize limit orders functionality
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof LimitOrdersManager !== 'undefined') {
                if (!window.limitOrdersManager) {
                    window.limitOrdersManager = new LimitOrdersManager();
                }
                window.limitOrdersManager.updateLimitOrdersDisplay();
            } else {
                console.error('LimitOrdersManager is not defined - limit-orders.js may not have loaded');
                setTimeout(() => {
                    if (typeof LimitOrdersManager !== 'undefined') {
                        window.limitOrdersManager = new LimitOrdersManager();
                        window.limitOrdersManager.updateLimitOrdersDisplay();
                    }
                }, 1000);
            }
        });
    </script>
</body>
</html>