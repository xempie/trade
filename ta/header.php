<?php
// header.php - Shared header component
require_once 'api/api_helper.php';
?>
        <!-- Top Header -->
        <header class="pwa-header">
            <div class="header-left">
                <div class="logo">
                    <span class="logo-icon">₿</span>
                    <span class="logo-text">CryptoTrade<?php 
                        $tradingMode = strtolower(getenv('TRADING_MODE') ?: 'live');
                        if ($tradingMode === 'demo') echo ' <span class="demo-indicator">DEMO</span>'; 
                    ?></span>
                </div>
            </div>
            <div class="header-right">
                <div class="user-menu">
                    <button class="user-menu-btn" id="user-menu-button">
                        <?php if (!$isLocal && $user['picture']): ?>
                            <img src="<?php echo htmlspecialchars($user['picture']); ?>" alt="Profile" class="user-avatar-small">
                        <?php else: ?>
                            <div class="user-avatar-fallback">
                                <?php echo substr($user['name'] ?? 'U', 0, 1); ?>
                            </div>
                        <?php endif; ?>
                        <span class="user-name-small"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></span>
                        <svg class="dropdown-icon" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M7 10l5 5 5-5z"/>
                        </svg>
                    </button>
                    <div class="user-dropdown" id="user-dropdown">
                        <?php if (!$isLocal): ?>
                            <div class="user-info-dropdown">
                                <div class="user-email"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
                            </div>
                        <?php endif; ?>
                        <a href="settings.php" class="dropdown-item">
                            <svg class="dropdown-icon" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.07-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.74,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.8,11.69,4.8,12s0.02,0.64,0.07,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.47-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/>
                            </svg>
                            Settings
                        </a>
                        <button id="logout-btn" class="dropdown-item logout">
                            <svg class="dropdown-icon" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.59L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>
                            </svg>
                            Logout
                        </button>
                    </div>
                </div>
            </div>
        </header>