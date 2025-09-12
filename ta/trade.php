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
    <title>Trade - Crypto Trading Manager</title>
</head>
<body class="pwa-app trade-page">
    <!-- PWA App Shell -->
    <div class="pwa-container">
    <?php include 'header.php'; ?>

        <!-- Main Content Area -->
        <main class="pwa-main" style="padding-bottom: 150px;">
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
                                <label class="checkbox-label">
                                    <input type="checkbox" id="entry_2_enabled" name="entry_2_enabled">
                                    <span class="checkbox-custom"></span>
                                    Entry 2
                                </label>
                                <div class="entry-row">
                                    <input type="number" id="entry_2_margin" name="entry_2_margin" placeholder="Order Value in $" step="0.01" class="margin-input">
                                    <input type="number" id="entry_2_percent" name="entry_2_percent" placeholder="%" step="0.1" class="percent-input">
                                    <input type="number" id="entry_2" name="entry_2" placeholder="Calculated price" step="0.00001" readonly>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="entry_3_enabled" name="entry_3_enabled">
                                    <span class="checkbox-custom"></span>
                                    Entry 3
                                </label>
                                <div class="entry-row">
                                    <input type="number" id="entry_3_margin" name="entry_3_margin" placeholder="Order Value in $" step="0.01" class="margin-input">
                                    <input type="number" id="entry_3_percent" name="entry_3_percent" placeholder="%" step="0.1" class="percent-input">
                                    <input type="number" id="entry_3" name="entry_3" placeholder="Calculated price" step="0.00001" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="take_profit">Take Profit</label>
                            <div class="entry-row">
                                <input type="number" id="take_profit_percent" name="take_profit_percent" placeholder="%" step="0.1" class="percent-input">
                                <input type="number" id="take_profit" name="take_profit" placeholder="Take profit price" step="0.00001">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="stop_loss">Stop Loss</label>
                            <div class="entry-row">
                                <input type="number" id="stop_loss_percent" name="stop_loss_percent" placeholder="%" step="0.1" class="percent-input">
                                <input type="number" id="stop_loss" name="stop_loss" placeholder="Stop loss price" step="0.00001">
                            </div>
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

<?php include 'nav.php'; ?>
    </div>

    <script src="assets/js/script.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/header.js?v=<?php echo time(); ?>"></script>
    <script>
        // Initialize trade page functionality
        document.addEventListener('DOMContentLoaded', () => {
            // Initialize trading form for this page (only once)
            if (!window.tradingForm) {
                window.tradingForm = new TradingForm();
                window.tradingForm.loadDraft();
                // Don't load balance data - only do this on home page
                window.tradingForm.updateRecentSignals();
                window.tradingForm.updateWatchlistDisplay();
                window.tradingForm.initializeTabs();
            }
        });
    </script>
</body>
</html>