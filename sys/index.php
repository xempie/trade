<?php
/**
 * System Administration Index
 * brainity.com.au/sys/
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Administration</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .admin-panel {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 3rem;
            max-width: 500px;
            width: 90%;
            text-align: center;
        }

        .admin-panel h1 {
            color: #333;
            margin-bottom: 2rem;
            font-size: 2rem;
        }

        .tool-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 2rem;
        }

        .tool-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            text-decoration: none;
            color: #495057;
            transition: all 0.3s ease;
        }

        .tool-card:hover {
            background: #e9ecef;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            color: #495057;
            text-decoration: none;
        }

        .tool-card .icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        .tool-card .title {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .tool-card .description {
            font-size: 0.875rem;
            color: #6c757d;
        }

        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 2rem;
            color: #856404;
        }

        @media (max-width: 768px) {
            .tool-grid {
                grid-template-columns: 1fr;
            }
            .admin-panel {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-panel">
        <h1>🛠️ System Administration</h1>

        <div class="warning">
            <strong>⚠️ Restricted Access</strong><br>
            This area is for system administrators only.
        </div>

        <div class="tool-grid">
            <a href="jobs.php" class="tool-card">
                <span class="icon">🕒</span>
                <div class="title">Cron Jobs</div>
                <div class="description">Manage scheduled tasks and automation</div>
            </a>

            <a href="logs.php" class="tool-card">
                <span class="icon">📝</span>
                <div class="title">System Logs</div>
                <div class="description">View system and error logs</div>
            </a>

            <a href="status.php" class="tool-card">
                <span class="icon">📊</span>
                <div class="title">System Status</div>
                <div class="description">Monitor system health and performance</div>
            </a>

            <a href="../ta/" class="tool-card">
                <span class="icon">🏠</span>
                <div class="title">Trading App</div>
                <div class="description">Return to main application</div>
            </a>
        </div>

        <div style="margin-top: 2rem; color: #6c757d; font-size: 0.875rem;">
            <p>Crypto Trading System v2.0</p>
            <p>Server: <?= gethostname() ?> | PHP: <?= PHP_VERSION ?></p>
        </div>
    </div>
</body>
</html>