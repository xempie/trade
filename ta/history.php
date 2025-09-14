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
                                
                                <div id="performance-loading" class="loading-placeholder">
                                    <div class="loading-spinner"></div>
                                    <p>Loading performance data...</p>
                                </div>
                                
                                <div id="performance-content" style="display: none;">
                                    <!-- Overall Performance Cards -->
                                    <div class="performance-cards">
                                        <div class="perf-card">
                                            <div class="perf-card-header">
                                                <span class="perf-card-title">Total Trades</span>
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
                                                </svg>
                                            </div>
                                            <div class="perf-card-value" id="total-trades">-</div>
                                            <div class="perf-card-subtitle" id="total-orders">- Orders Placed</div>
                                        </div>
                                        
                                        <div class="perf-card">
                                            <div class="perf-card-header">
                                                <span class="perf-card-title">Win Rate</span>
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                                </svg>
                                            </div>
                                            <div class="perf-card-value win-rate" id="win-rate">-%</div>
                                            <div class="perf-card-subtitle">
                                                <span id="win-loss-ratio">- Wins / - Losses</span>
                                            </div>
                                        </div>
                                        
                                        <div class="perf-card">
                                            <div class="perf-card-header">
                                                <span class="perf-card-title">Net P&L</span>
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M7 15h2c0 1.08 1.37 2 3 2s3-.92 3-2c0-1.1-1.04-1.5-3.24-2.03C9.64 12.44 7 11.78 7 9c0-1.79 1.47-3.31 3.5-3.82V3h3v2.18C15.53 5.69 17 7.21 17 9h-2c0-1.08-1.37-2-3-2s-3 .92-3 2c0 1.1 1.04 1.5 3.24 2.03C14.36 11.56 17 12.22 17 15c0 1.79-1.47 3.31-3.5 3.82V21h-3v-2.18C8.47 18.31 7 16.79 7 15z"/>
                                                </svg>
                                            </div>
                                            <div class="perf-card-value pnl" id="net-pnl">$-</div>
                                            <div class="perf-card-subtitle" id="roi-display">- ROI</div>
                                        </div>
                                        
                                        <div class="perf-card">
                                            <div class="perf-card-header">
                                                <span class="perf-card-title">Profit Factor</span>
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/>
                                                </svg>
                                            </div>
                                            <div class="perf-card-value" id="profit-factor">-</div>
                                            <div class="perf-card-subtitle">Total Profit / Total Loss</div>
                                        </div>
                                    </div>
                                    
                                    <!-- Performance Highlights -->
                                    <div class="performance-highlights">
                                        <div class="highlight-card best-month">
                                            <h4>🏆 Best Month</h4>
                                            <div class="highlight-content" id="best-month-content">
                                                <div class="highlight-month">-</div>
                                                <div class="highlight-pnl">$-</div>
                                            </div>
                                        </div>
                                        
                                        <div class="highlight-card worst-month">
                                            <h4>📉 Worst Month</h4>
                                            <div class="highlight-content" id="worst-month-content">
                                                <div class="highlight-month">-</div>
                                                <div class="highlight-pnl">$-</div>
                                            </div>
                                        </div>
                                        
                                        <div class="highlight-card avg-monthly">
                                            <h4>📊 Avg Monthly P&L</h4>
                                            <div class="highlight-content">
                                                <div class="highlight-pnl" id="avg-monthly-pnl">$-</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Monthly Performance Chart -->
                                    <div class="chart-container">
                                        <h4>Monthly Performance (Last 12 Months)</h4>
                                        <canvas id="monthly-pnl-chart" width="400" height="200"></canvas>
                                    </div>
                                    
                                    <!-- Monthly Performance Table -->
                                    <div class="monthly-table-container">
                                        <h4>Monthly Breakdown</h4>
                                        <div class="table-responsive">
                                            <table class="performance-table">
                                                <thead>
                                                    <tr>
                                                        <th>Month</th>
                                                        <th>Trades</th>
                                                        <th>Win Rate</th>
                                                        <th>Net P&L</th>
                                                        <th>Volume</th>
                                                        <th>Profit Factor</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="monthly-performance-table">
                                                    <!-- Monthly data will be populated here -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
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
                                    Trade History
                                </h3>
                                
                                <div id="trades-loading" class="loading-placeholder" style="display: none;">
                                    <div class="loading-spinner"></div>
                                    <p>Loading trades data...</p>
                                </div>
                                
                                <div id="trades-content">
                                    <!-- Trades Summary Cards -->
                                    <div class="trades-summary-cards">
                                        <div class="perf-card">
                                            <div class="perf-card-header">
                                                <span class="perf-card-title">Total Trades</span>
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
                                                </svg>
                                            </div>
                                            <div class="perf-card-value" id="trades-total">-</div>
                                            <div class="perf-card-subtitle">Closed Positions</div>
                                        </div>
                                        
                                        <div class="perf-card">
                                            <div class="perf-card-header">
                                                <span class="perf-card-title">Win Rate</span>
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                                </svg>
                                            </div>
                                            <div class="perf-card-value win-rate" id="trades-win-rate">-%</div>
                                            <div class="perf-card-subtitle" id="trades-win-loss">- Wins / - Losses</div>
                                        </div>
                                        
                                        <div class="perf-card">
                                            <div class="perf-card-header">
                                                <span class="perf-card-title">Best Trade</span>
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M7 15h2c0 1.08 1.37 2 3 2s3-.92 3-2c0-1.1-1.04-1.5-3.24-2.03C9.64 12.44 7 11.78 7 9c0-1.79 1.47-3.31 3.5-3.82V3h3v2.18C15.53 5.69 17 7.21 17 9h-2c0-1.08-1.37-2-3-2s-3 .92-3 2c0 1.1 1.04 1.5 3.24 2.03C14.36 11.56 17 12.22 17 15c0 1.79-1.47 3.31-3.5 3.82V21h-3v-2.18C8.47 18.31 7 16.79 7 15z"/>
                                                </svg>
                                            </div>
                                            <div class="perf-card-value" id="trades-best" style="color: var(--success);">$-</div>
                                            <div class="perf-card-subtitle">Highest Profit</div>
                                        </div>
                                        
                                        <div class="perf-card">
                                            <div class="perf-card-header">
                                                <span class="perf-card-title">Worst Trade</span>
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M7 15h2c0 1.08 1.37 2 3 2s3-.92 3-2c0-1.1-1.04-1.5-3.24-2.03C9.64 12.44 7 11.78 7 9c0-1.79 1.47-3.31 3.5-3.82V3h3v2.18C15.53 5.69 17 7.21 17 9h-2c0-1.08-1.37-2-3-2s-3 .92-3 2c0 1.1 1.04 1.5 3.24 2.03C14.36 11.56 17 12.22 17 15c0 1.79-1.47 3.31-3.5 3.82V21h-3v-2.18C8.47 18.31 7 16.79 7 15z"/>
                                                </svg>
                                            </div>
                                            <div class="perf-card-value" id="trades-worst" style="color: var(--danger);">$-</div>
                                            <div class="perf-card-subtitle">Largest Loss</div>
                                        </div>
                                    </div>
                                    
                                    <!-- Trades Table -->
                                    <div class="trades-table-container">
                                        <div class="table-header">
                                            <h4>Recent Trades</h4>
                                            <div class="table-filters">
                                                <input type="text" id="symbol-filter" placeholder="Filter by symbol..." style="padding: 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--surface); color: var(--text);">
                                            </div>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="trades-table">
                                                <thead>
                                                    <tr>
                                                        <th>Symbol</th>
                                                        <th>Date/Time</th>
                                                        <th>Side</th>
                                                        <th>Entry Price</th>
                                                        <th>Close Price</th>
                                                        <th>Leverage</th>
                                                        <th>Margin</th>
                                                        <th>P&L</th>
                                                        <th>Duration</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="trades-table-body">
                                                    <!-- Trades data will be populated here -->
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <!-- Load More Button -->
                                        <div class="table-pagination" id="trades-pagination" style="text-align: center; margin-top: 20px;">
                                            <button id="load-more-trades" class="btn-secondary" style="display: none;">Load More Trades</button>
                                        </div>
                                    </div>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        let monthlyChart = null;
        
        // History tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabBtns = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');

            // Load performance data on page load
            loadPerformanceData();

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
                    
                    // Load data for the selected tab
                    loadTabData(btn.dataset.tab);
                });
            });
        });
        
        function loadTabData(tab) {
            switch(tab) {
                case 'performance':
                    loadPerformanceData();
                    break;
                case 'pnl':
                    // loadPnLData();
                    break;
                case 'trades':
                    loadTradesData();
                    break;
                case 'analytics':
                    // loadAnalyticsData();
                    break;
            }
        }
        
        async function loadPerformanceData() {
            try {
                const response = await fetch('api_proxy.php?endpoint=get_performance_summary.php', {
                    credentials: 'same-origin'
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    displayPerformanceData(result.data);
                } else {
                    throw new Error(result.message || 'Failed to load performance data');
                }
            } catch (error) {
                console.error('Error loading performance data:', error);
                document.getElementById('performance-loading').innerHTML = 
                    '<p style="color: var(--danger); text-align: center;">Failed to load performance data</p>';
            }
        }
        
        function displayPerformanceData(data) {
            // Hide loading, show content
            document.getElementById('performance-loading').style.display = 'none';
            document.getElementById('performance-content').style.display = 'block';
            
            const overall = data.overall;
            
            // Update overall metrics
            document.getElementById('total-trades').textContent = overall.total_trades.toLocaleString();
            document.getElementById('total-orders').textContent = overall.total_orders.toLocaleString() + ' Orders Placed';
            document.getElementById('win-rate').textContent = overall.win_rate + '%';
            document.getElementById('win-loss-ratio').textContent = overall.total_wins + ' Wins / ' + overall.total_losses + ' Losses';
            document.getElementById('net-pnl').textContent = '$' + overall.net_pnl.toLocaleString();
            document.getElementById('roi-display').textContent = overall.roi + '% ROI';
            document.getElementById('profit-factor').textContent = overall.profit_factor;
            
            // Color code win rate
            const winRateEl = document.getElementById('win-rate');
            if (overall.win_rate >= 70) {
                winRateEl.style.color = 'var(--success)';
            } else if (overall.win_rate >= 50) {
                winRateEl.style.color = 'var(--warning)';
            } else {
                winRateEl.style.color = 'var(--danger)';
            }
            
            // Color code net P&L
            const pnlEl = document.getElementById('net-pnl');
            if (overall.net_pnl > 0) {
                pnlEl.style.color = 'var(--success)';
            } else if (overall.net_pnl < 0) {
                pnlEl.style.color = 'var(--danger)';
            }
            
            // Update highlights
            if (data.best_month) {
                document.getElementById('best-month-content').innerHTML = 
                    '<div class="highlight-month">' + data.best_month.month + '</div>' +
                    '<div class="highlight-pnl" style="color: var(--success);">$' + data.best_month.net_pnl.toLocaleString() + '</div>';
            }
            
            if (data.worst_month) {
                document.getElementById('worst-month-content').innerHTML = 
                    '<div class="highlight-month">' + data.worst_month.month + '</div>' +
                    '<div class="highlight-pnl" style="color: var(--danger);">$' + data.worst_month.net_pnl.toLocaleString() + '</div>';
            }
            
            document.getElementById('avg-monthly-pnl').textContent = '$' + overall.avg_monthly_pnl.toLocaleString();
            document.getElementById('avg-monthly-pnl').style.color = overall.avg_monthly_pnl > 0 ? 'var(--success)' : 'var(--danger)';
            
            // Create monthly performance chart
            createMonthlyChart(data.monthly_data);
            
            // Populate monthly table
            populateMonthlyTable(data.monthly_data);
        }
        
        function createMonthlyChart(monthlyData) {
            const ctx = document.getElementById('monthly-pnl-chart').getContext('2d');
            
            // Destroy existing chart if it exists
            if (monthlyChart) {
                monthlyChart.destroy();
            }
            
            const labels = monthlyData.map(month => month.month).reverse();
            const pnlData = monthlyData.map(month => month.net_pnl).reverse();
            
            monthlyChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Monthly P&L',
                        data: pnlData,
                        backgroundColor: pnlData.map(value => value >= 0 ? 'rgba(34, 197, 94, 0.7)' : 'rgba(239, 68, 68, 0.7)'),
                        borderColor: pnlData.map(value => value >= 0 ? 'rgba(34, 197, 94, 1)' : 'rgba(239, 68, 68, 1)'),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: 'rgba(255, 255, 255, 0.7)',
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: 'rgba(255, 255, 255, 0.7)'
                            }
                        }
                    }
                }
            });
        }
        
        function populateMonthlyTable(monthlyData) {
            const tbody = document.getElementById('monthly-performance-table');
            tbody.innerHTML = '';
            
            monthlyData.forEach(month => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${month.month}</td>
                    <td>${month.total_trades}</td>
                    <td class="${month.win_rate >= 50 ? 'positive' : 'negative'}">${month.win_rate}%</td>
                    <td class="${month.net_pnl >= 0 ? 'positive' : 'negative'}">$${month.net_pnl.toLocaleString()}</td>
                    <td>$${month.total_volume.toLocaleString()}</td>
                    <td>${month.profit_factor}</td>
                `;
                tbody.appendChild(row);
            });
        }
        
        // Trades data management
        let tradesData = {
            trades: [],
            offset: 0,
            hasMore: true,
            loading: false
        };
        
        async function loadTradesData(reset = true) {
            if (tradesData.loading) return;
            
            if (reset) {
                tradesData.offset = 0;
                tradesData.trades = [];
                tradesData.hasMore = true;
            }
            
            tradesData.loading = true;
            
            try {
                // Show loading indicator
                const loadingEl = document.getElementById('trades-loading');
                if (reset) {
                    loadingEl.style.display = 'block';
                }
                
                // Get filter value
                const symbolFilter = document.getElementById('symbol-filter')?.value || '';
                
                // Build API URL
                let apiUrl = `api_proxy.php?endpoint=get_trades_history.php&limit=20&offset=${tradesData.offset}`;
                if (symbolFilter) {
                    apiUrl += `&symbol=${encodeURIComponent(symbolFilter)}`;
                }
                
                const response = await fetch(apiUrl, {
                    credentials: 'same-origin'
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success && result.data) {
                    const newTrades = result.data.trades || [];
                    const pagination = result.data.pagination || {};
                    const summary = result.data.summary || {};
                    
                    if (reset) {
                        tradesData.trades = newTrades;
                        displayTradesSummary(summary);
                    } else {
                        tradesData.trades = [...tradesData.trades, ...newTrades];
                    }
                    
                    tradesData.offset += newTrades.length;
                    tradesData.hasMore = pagination.has_more || false;
                    
                    displayTrades();
                    updateLoadMoreButton();
                    
                } else {
                    throw new Error(result.message || 'Failed to load trades data');
                }
            } catch (error) {
                console.error('Error loading trades data:', error);
                if (reset) {
                    document.getElementById('trades-table-body').innerHTML = 
                        '<tr><td colspan="9" style="text-align: center; color: var(--danger);">Failed to load trades data</td></tr>';
                }
            } finally {
                tradesData.loading = false;
                document.getElementById('trades-loading').style.display = 'none';
            }
        }
        
        function displayTradesSummary(summary) {
            document.getElementById('trades-total').textContent = summary.total_trades?.toLocaleString() || '0';
            document.getElementById('trades-win-rate').textContent = (summary.win_rate || 0) + '%';
            document.getElementById('trades-win-loss').textContent = 
                (summary.winning_trades || 0) + ' Wins / ' + (summary.losing_trades || 0) + ' Losses';
            document.getElementById('trades-best').textContent = '$' + (summary.best_trade?.toLocaleString() || '0');
            document.getElementById('trades-worst').textContent = '$' + (summary.worst_trade?.toLocaleString() || '0');
            
            // Color code win rate
            const winRateEl = document.getElementById('trades-win-rate');
            const winRate = summary.win_rate || 0;
            if (winRate >= 70) {
                winRateEl.style.color = 'var(--success)';
            } else if (winRate >= 50) {
                winRateEl.style.color = 'var(--warning)';
            } else {
                winRateEl.style.color = 'var(--danger)';
            }
        }
        
        function displayTrades() {
            const tbody = document.getElementById('trades-table-body');
            
            // Clear table on first load/reset
            if (tradesData.offset <= tradesData.trades.length) {
                tbody.innerHTML = '';
                
                // Add all trades
                tradesData.trades.forEach((trade) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>
                            <div class="trade-symbol">
                                ${trade.symbol}
                                ${trade.is_demo ? '<span class="demo-badge">DEMO</span>' : ''}
                            </div>
                        </td>
                        <td class="trade-date">${trade.closed_at}</td>
                        <td>
                            <span class="side-badge ${trade.side.toLowerCase()}">
                                ${trade.side}
                            </span>
                        </td>
                        <td class="price-cell">${trade.entry_price}</td>
                        <td class="price-cell">${trade.close_price}</td>
                        <td class="leverage-cell">${trade.leverage}</td>
                        <td class="margin-cell">${trade.margin_used}</td>
                        <td class="pnl-cell ${trade.is_profitable ? 'positive' : 'negative'}">
                            ${trade.pnl_display}
                        </td>
                        <td class="duration-cell">${trade.duration}</td>
                    `;
                    tbody.appendChild(row);
                });
            }
            
            // Show empty state if no trades
            if (tradesData.trades.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 40px; color: var(--dark-text-secondary);">No trades found</td></tr>';
            }
        }
        
        function updateLoadMoreButton() {
            const loadMoreBtn = document.getElementById('load-more-trades');
            if (tradesData.hasMore && tradesData.trades.length > 0) {
                loadMoreBtn.style.display = 'inline-block';
                loadMoreBtn.textContent = tradesData.loading ? 'Loading...' : 'Load More Trades';
                loadMoreBtn.disabled = tradesData.loading;
            } else {
                loadMoreBtn.style.display = 'none';
            }
        }
        
        // Event listeners for trades functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Load more button
            document.getElementById('load-more-trades')?.addEventListener('click', () => {
                loadTradesData(false);
            });
            
            // Symbol filter with debounce
            let filterTimeout;
            document.getElementById('symbol-filter')?.addEventListener('input', (e) => {
                clearTimeout(filterTimeout);
                filterTimeout = setTimeout(() => {
                    loadTradesData(true);
                }, 500);
            });
        });
    </script>
</body>
</html>