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
                        <option value="10" selected>10x</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="market-entry">Market Entry</label>
                    <input type="number" step="0.00001" id="market-entry" name="market-entry" placeholder="Market entry price" required>
                </div>

                <div class="form-group">
                    <label for="entry2">Entry 2</label>
                    <input type="number" step="0.00001" id="entry2" name="entry2" placeholder="Second entry price">
                </div>

                <div class="form-group">
                    <label for="entry3">Entry 3</label>
                    <input type="number" step="0.00001" id="entry3" name="entry3" placeholder="Third entry price">
                </div>

                <div class="form-group">
                    <label for="target1">Target 1</label>
                    <input type="number" step="0.00001" id="target1" name="target1" placeholder="First target price">
                </div>

                <div class="form-group">
                    <label for="target2">Target 2</label>
                    <input type="number" step="0.00001" id="target2" name="target2" placeholder="Second target price">
                </div>

                <div class="form-group">
                    <label for="target3">Target 3</label>
                    <input type="number" step="0.00001" id="target3" name="target3" placeholder="Third target price">
                </div>

                <div class="form-group">
                    <label for="stop-loss">Stop Loss</label>
                    <input type="number" step="0.00001" id="stop-loss" name="stop-loss" placeholder="Stop loss price">
                </div>

                <div class="button-group">
                    <button type="submit" class="submit-btn">Create Signal</button>
                    <button type="button" class="watchlist-btn" onclick="addToWatchlist()">Add to Watchlist</button>
                    <button type="button" class="clear-btn" onclick="clearForm()">Clear</button>
                </div>
            </form>
        </div>

        <!-- Balance and Account Info -->
        <div class="account-info">
            <h2>Account Information</h2>
            <div id="balance-info" class="balance-container">
                <div class="balance-item">
                    <span class="balance-label">Available Balance:</span>
                    <span class="balance-value" id="available-balance">Loading...</span>
                </div>
                <div class="balance-item">
                    <span class="balance-label">Used Margin:</span>
                    <span class="balance-value" id="used-margin">Loading...</span>
                </div>
                <div class="balance-item">
                    <span class="balance-label">Unrealized PnL:</span>
                    <span class="balance-value" id="unrealized-pnl">Loading...</span>
                </div>
            </div>
            <button onclick="refreshBalance()" class="refresh-btn">Refresh Balance</button>
        </div>

        <!-- Watchlist Section -->
        <div class="watchlist-section">
            <h2>Watchlist</h2>
            <div id="watchlist-container" class="watchlist-container">
                <!-- Watchlist items will be loaded here -->
            </div>
            <button onclick="loadWatchlist()" class="refresh-btn">Refresh Watchlist</button>
        </div>

        <!-- Active Positions Section -->
        <div class="positions-section">
            <h2>Active Positions</h2>
            <div id="positions-container" class="positions-container">
                <!-- Positions will be loaded here -->
                <p class="no-signals">No active positions</p>
            </div>
            <button onclick="loadPositions()" class="refresh-btn">Refresh Positions</button>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>