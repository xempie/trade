<?php
// nav.php - Shared bottom navigation component

// Determine which page is active based on current script name
$current_page = basename($_SERVER['PHP_SELF']);
?>
        <!-- Bottom Navigation -->
        <nav class="bottom-nav">
            <a href="home.php" class="nav-item <?php echo ($current_page == 'home.php') ? 'active' : ''; ?>">
                <svg class="nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                </svg>
                <span class="nav-label">Home</span>
            </a>
            
            <a href="trade.php" class="nav-item <?php echo ($current_page == 'trade.php') ? 'active' : ''; ?>">
                <svg class="nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                </svg>
                <span class="nav-label">Trade</span>
            </a>
            
            <a href="orders.php" class="nav-item <?php echo ($current_page == 'orders.php') ? 'active' : ''; ?>">
                <svg class="nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M9 11H7v9a2 2 0 002 2h8a2 2 0 002-2V9a2 2 0 00-2-2h-3V5a2 2 0 00-2 2v6zm0-6h3v2h-3V5z"/>
                </svg>
                <span class="nav-label">Orders</span>
            </a>
            
            <a href="limit_orders.php" class="nav-item <?php echo ($current_page == 'limit_orders.php') ? 'active' : ''; ?>">
                <svg class="nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M6 2v6h.01L6 8.01 10 12l-4 4 .01.01H6V22h12v-5.99h-.01L18 16l-4-4 4-4-.01-.01H18V2H6zm10 14.5V20H8v-3.5l4-4 4 4zM16 4v3.5l-4 4-4-4V4h8z"/>
                </svg>
                <span class="nav-label">Limits</span>
            </a>
            
            <a href="watch.php" class="nav-item <?php echo ($current_page == 'watch.php') ? 'active' : ''; ?>">
                <svg class="nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                </svg>
                <span class="nav-label">Watch</span>
            </a>
            
            <a href="history.php" class="nav-item <?php echo ($current_page == 'history.php') ? 'active' : ''; ?>">
                <svg class="nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/>
                </svg>
                <span class="nav-label">History</span>
            </a>
        </nav>