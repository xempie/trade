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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crypto Trading Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- User Header (only show if not localhost) -->
    <?php if (!$isLocal && $user['picture']): ?>
    <div class="user-header">
        <div class="user-info">
            <img src="<?php echo htmlspecialchars($user['picture']); ?>" alt="Profile" class="user-avatar">
            <span class="user-name"><?php echo htmlspecialchars($user['name']); ?></span>
            <span class="user-email"><?php echo htmlspecialchars($user['email']); ?></span>
        </div>
        <a href="auth/logout.php" class="logout-btn">Logout</a>
    </div>
    <?php endif; ?>

    <div class="container">
        <div class="form-container">
            <div class="header">
                <h1>Trading Form</h1>                
            </div>

            <form id="trading-form" class="trading-form">
                <div class="form-group">
                    <label for="symbol">Symbol</label>
                    <input type="text" id="symbol" name="symbol" placeholder="Enter crypto symbol (e.g., BTC, ADA, ETH)" required>
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
                            <input type="number" id="entry_market_margin" name="entry_market_margin" placeholder="$" step="0.01" class="margin-input">
                            <input type="number" id="entry_market" name="entry_market" placeholder="Market price" step="0.00001">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="entry_2">Entry 2</label>
                        <div class="entry-row">
                            <input type="number" id="entry_2_margin" name="entry_2_margin" placeholder="$" step="0.01" class="margin-input">
                            <input type="number" id="entry_2_percent" name="entry_2_percent" placeholder="%" step="0.1" class="percent-input">
                            <input type="number" id="entry_2" name="entry_2" placeholder="Price" step="0.00001">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="entry_3">Entry 3</label>
                        <div class="entry-row">
                            <input type="number" id="entry_3_margin" name="entry_3_margin" placeholder="$" step="0.01" class="margin-input">
                            <input type="number" id="entry_3_percent" name="entry_3_percent" placeholder="%" step="0.1" class="percent-input">
                            <input type="number" id="entry_3" name="entry_3" placeholder="Price" step="0.00001">
                        </div>
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
                    <button type="submit" class="btn btn-primary">Create Signal</button>
                </div>
            </form>
        </div>

        <div class="info-panel">
            <div class="account-info">
                <div class="account-header">
                    <h3>Account Info</h3>
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
                    <span class="label">Position Size (3.3%):</span>
                    <span class="value" id="position-size">$0.00</span>
                </div>
                <div class="info-item">
                    <span class="label">Last Updated:</span>
                    <span class="value" id="last-updated">Loading...</span>
                </div>
            </div>

            <div class="recent-signals">
                <div class="positions-header">
                    <h3>Active Positions</h3>
                    <button class="refresh-positions-btn" onclick="tradingForm.refreshPositions()" title="Refresh positions and P&L">
                        ↻
                    </button>
                </div>
                <div class="signal-list" id="signal-list">
                    <p class="no-signals">No active positions</p>
                </div>
            </div>

            <div class="watchlist-panel">
                <div class="watchlist-header">
                    <h3>Watch List</h3>
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

    <script src="script.js"></script>
</body>
</html>