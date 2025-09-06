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
    <title>Home - Crypto Trading Manager</title>
</head>
<body class="pwa-app">
    <!-- PWA App Shell -->
    <div class="pwa-container">
<?php include 'header.php'; ?>

        <!-- Main Content Area -->
        <main class="pwa-main" style="padding-bottom: 150px;">
            <!-- Home Section - Account Info -->
            <div class="container">
                <!-- BingX Account Info -->
                <div class="form-container">
                    <div class="account-info">
                    <div class="account-header">
                        <h3>BingX Account Info</h3>
                        <button class="refresh-balance-btn" onclick="tradingForm.refreshBalance()" title="Refresh balance from BingX">
                            ↻
                        </button>
                    </div>
                    <div class="info-item">
                        <span class="label">Total Assets:</span>
                        <span class="value total-assets" id="total-assets">$0.00</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Available Balance:</span>
                        <span class="value" id="available-balance">$0.00</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Margin Used:</span>
                        <span class="value" id="margin-used">$0.00</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Position Size (<span id="position-size-percent">3.3</span>%):</span>
                        <span class="value" id="position-size">$0.00</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Unrealized PnL:</span>
                        <span class="value" id="unrealized-pnl">$0.00</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Last Updated:</span>
                        <span class="value" id="last-updated">Loading...</span>
                    </div>
                    </div>
                </div>

                <!-- LBank Account Info -->
                <div class="form-container">
                    <div class="account-info">
                    <div class="account-header">
                        <h3>LBank Account Info</h3>
                        <button class="refresh-balance-btn" onclick="tradingForm.refreshLBankBalance()" title="Refresh balance from LBank">
                            ↻
                        </button>
                    </div>
                    <div class="info-item">
                        <span class="label">Total Assets:</span>
                        <span class="value total-assets" id="lbank-total-assets">$0.00</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Available Balance:</span>
                        <span class="value" id="lbank-available-balance">$0.00</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Frozen Balance:</span>
                        <span class="value" id="lbank-frozen-balance">$0.00</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Position Size (<span id="lbank-position-size-percent">3.3</span>%):</span>
                        <span class="value" id="lbank-position-size">$0.00</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Unrealized PnL:</span>
                        <span class="value" id="lbank-unrealized-pnl">$0.00</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Last Updated:</span>
                        <span class="value" id="lbank-last-updated">Not loaded</span>
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
        // Initialize home page functionality
        document.addEventListener('DOMContentLoaded', () => {
            // Initialize trading form for this page (only once)
            if (!window.tradingForm) {
                window.tradingForm = new TradingForm();
                // Balance data loading is handled automatically in constructor
            }
        });
    </script>
</body>
</html>