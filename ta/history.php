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
    <title>History - Crypto Trading Manager</title>
</head>
<body class="pwa-app history">
    <!-- PWA App Shell -->
    <div class="pwa-container">
        <?php include 'header.php'; ?>

        <!-- Main Content Area -->
        <main class="pwa-main" style="padding-bottom: 150px;">
            <!-- History Section -->
            <div class="container">
                <div class="form-container">
                    <div class="header">
                        <h1>Trading History</h1>
                        <p>Performance analytics and trade records</p>
                    </div>

                    <!-- History Tabs -->
                    <div class="history-tabs">
                        <button class="tab-btn active" data-tab="performance">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/>
                            </svg>
                            Performance
                        </button>
                        <button class="tab-btn" data-tab="pnl">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M7 15h2c0 1.08 1.37 2 3 2s3-.92 3-2c0-1.1-1.04-1.5-3.24-2.03C9.64 12.44 7 11.78 7 9c0-1.79 1.47-3.31 3.5-3.82V3h3v2.18C15.53 5.69 17 7.21 17 9h-2c0-1.08-1.37-2-3-2s-3 .92-3 2c0 1.1 1.04 1.5 3.24 2.03C14.36 11.56 17 12.22 17 15c0 1.79-1.47 3.31-3.5 3.82V21h-3v-2.18C8.47 18.31 7 16.79 7 15z"/>
                            </svg>
                            P&L
                        </button>
                        <button class="tab-btn" data-tab="trades">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
                            </svg>
                            Trades
                        </button>
                        <button class="tab-btn" data-tab="analytics">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                            Analytics
                        </button>
                    </div>

                    <!-- Tab Content -->
                    <div class="history-content">
                        <!-- Performance Tab -->
                        <div class="tab-content active" id="performance-tab">
                            <div class="settings-group">
                                <h3>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 8px;">
                                        <path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/>
                                    </svg>
                                    Performance Summary
                                </h3>
                                <div class="coming-soon">
                                    <p>📈 Overall trading performance metrics and statistics</p>
                                    <span class="status-badge">Coming Soon</span>
                                </div>
                            </div>
                        </div>

                        <!-- P&L Tab -->
                        <div class="tab-content" id="pnl-tab">
                            <div class="settings-group">
                                <h3>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 8px;">
                                        <path d="M7 15h2c0 1.08 1.37 2 3 2s3-.92 3-2c0-1.1-1.04-1.5-3.24-2.03C9.64 12.44 7 11.78 7 9c0-1.79 1.47-3.31 3.5-3.82V3h3v2.18C15.53 5.69 17 7.21 17 9h-2c0-1.08-1.37-2-3-2s-3 .92-3 2c0 1.1 1.04 1.5 3.24 2.03C14.36 11.56 17 12.22 17 15c0 1.79-1.47 3.31-3.5 3.82V21h-3v-2.18C8.47 18.31 7 16.79 7 15z"/>
                                    </svg>
                                    P&L History
                                </h3>
                                <div class="coming-soon">
                                    <p>💰 Detailed profit and loss breakdown by signal and time period</p>
                                    <span class="status-badge">Coming Soon</span>
                                </div>
                            </div>
                        </div>

                        <!-- Trades Tab -->
                        <div class="tab-content" id="trades-tab">
                            <div class="settings-group">
                                <h3>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 8px;">
                                        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
                                    </svg>
                                    Trade Log
                                </h3>
                                <div class="coming-soon">
                                    <p>📋 Complete history of all trades and signals</p>
                                    <span class="status-badge">Coming Soon</span>
                                </div>
                            </div>
                        </div>

                        <!-- Analytics Tab -->
                        <div class="tab-content" id="analytics-tab">
                            <div class="settings-group">
                                <h3>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 8px;">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                    </svg>
                                    Win Rate Analysis
                                </h3>
                                <div class="coming-soon">
                                    <p>🎯 Success rate analysis and pattern recognition</p>
                                    <span class="status-badge">Coming Soon</span>
                                </div>
                            </div>
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
        // History tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabBtns = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');

            tabBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    // Remove active class from all tabs and contents
                    tabBtns.forEach(b => b.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));

                    // Add active class to clicked tab
                    btn.classList.add('active');
                    
                    // Show corresponding content
                    const tabId = btn.dataset.tab + '-tab';
                    document.getElementById(tabId).classList.add('active');
                });
            });
        });
    </script>
</body>
</html>