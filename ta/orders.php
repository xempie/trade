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

        <!-- Manage Popover -->
        <div class="manage-popover-overlay" id="manage-popover-overlay"></div>
        <div class="manage-popover" id="manage-popover">
            <div class="manage-popover-header">
                <h3 class="manage-popover-title">Manage Position</h3>
                <button class="manage-popover-close" id="manage-popover-close">×</button>
            </div>
            <div class="manage-popover-content">
                <div class="risk-free-section">
                    <div class="risk-free-header">
                        <span class="risk-free-title">Risk Free</span>
                        <span class="current-pnl" id="current-pnl">P&L: --</span>
                    </div>
                    <div class="risk-free-input-group">
                        <input 
                            type="number" 
                            class="risk-free-input" 
                            id="risk-free-percent" 
                            placeholder="Move SL % (0-100)" 
                            min="0" 
                            max="100" 
                            step="0.1"
                        />
                        <span style="color: var(--dark-text-secondary); font-size: 14px;">%</span>
                    </div>
                    <div class="risk-free-preview" id="risk-free-preview">
                        <div class="preview-item">
                            <span class="preview-label">Current SL:</span>
                            <span class="preview-value" id="preview-current-sl">--</span>
                        </div>
                        <div class="preview-item">
                            <span class="preview-label">New SL:</span>
                            <span class="preview-value" id="preview-new-sl">--</span>
                        </div>
                        <div class="preview-item">
                            <span class="preview-label">Protection:</span>
                            <span class="preview-value" id="preview-protection">--</span>
                        </div>
                    </div>
                    <div class="risk-free-actions">
                        <button class="cancel-btn" id="risk-free-cancel">Cancel</button>
                        <button class="risk-free-btn" id="risk-free-apply" disabled>Apply Risk Free</button>
                    </div>
                </div>
            </div>
        </div>

<?php include 'nav.php'; ?>
    </div>

    <script src="assets/js/timezone-helper.js?v=<?php echo time(); ?>"></script>
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