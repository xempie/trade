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
    <title>Orders - Crypto Trading Manager</title>
</head>
<body class="pwa-app">
    <!-- PWA App Shell -->
    <div class="pwa-container">
<?php include 'header.php'; ?>

        <!-- Main Content Area -->
        <main class="pwa-main" style="padding-bottom: 150px;">
            <!-- Orders Section - Open Positions Only -->
            <div class="container">
                <div class="form-container">
                    <div class="recent-signals">
                    <div class="positions-header">
                        <h3>Active Positions</h3>
                        <div class="positions-actions">
                            <button class="close-all-positions-btn" onclick="tradingForm.closeAllPositions()" title="Close all active positions">
                                Close All
                            </button>
                            <button class="refresh-positions-btn" onclick="tradingForm.refreshPositions()" title="Refresh positions and P&L">
                                ↻
                            </button>
                        </div>
                    </div>
                    <div class="signal-list" id="signal-list">
                        <p class="no-signals">No active positions</p>
                    </div>
                    </div>
                </div>
            </div>
        </main>

<?php include 'nav.php'; ?>
    </div>

    <script src="assets/js/script.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/header.js?v=<?php echo time(); ?>"></script>
    <script>
        // Initialize orders page functionality
        document.addEventListener('DOMContentLoaded', () => {
            // Initialize trading form for this page (only once)
            if (!window.tradingForm) {
                window.tradingForm = new TradingForm();
                // Don't load balance data - only do this on home page
                window.tradingForm.updateRecentSignals();
            }
        });
    </script>
</body>
</html>