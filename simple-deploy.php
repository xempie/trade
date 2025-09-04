<?php
/**
 * Simple Deployment Script
 * Upload files one by one with better error handling
 */

require_once 'deploy-config.php';

function deployFiles($config) {
    echo "Starting simple deployment...\n";
    
    // Test FTP connection first
    echo "Testing FTP connection...\n";
    
    // Try different connection methods
    $connection = false;
    
    // Method 1: Try SSL FTP with proper error handling
    echo "Trying SSL FTP...\n";
    echo "Host: {$config['host']}, Port: {$config['port']}, User: {$config['username']}\n";
    
    $connection = @ftp_ssl_connect($config['host'], $config['port'], 90);
    if ($connection) {
        echo "SSL FTP connection successful\n";
        
        // Try login with explicit error suppression removed to see actual errors
        $login = ftp_login($connection, $config['username'], $config['password']);
        if ($login) {
            echo "✅ SSL FTP login successful!\n";
            ftp_pasv($connection, true);
        } else {
            echo "❌ SSL FTP login failed - trying without SSL...\n";
            ftp_close($connection);
            $connection = false;
        }
    } else {
        echo "SSL FTP connection failed\n";
    }
    
    // Method 2: Try regular FTP if SSL failed
    if (!$connection) {
        echo "Trying regular FTP...\n";
        $connection = @ftp_connect($config['host'], $config['port']);
        if ($connection) {
            echo "Regular FTP connection successful\n";
            $login = @ftp_login($connection, $config['username'], $config['password']);
            if ($login) {
                echo "✅ Regular FTP login successful!\n";
                ftp_pasv($connection, true);
            } else {
                $error = error_get_last();
                echo "❌ Regular FTP login failed: " . ($error['message'] ?? 'Unknown error') . "\n";
                ftp_close($connection);
                $connection = false;
            }
        } else {
            echo "Regular FTP connection failed\n";
        }
    }
    
    if (!$connection) {
        die("❌ Could not establish FTP connection\n");
    }
    
    // List files to upload
    $files = [
        'header.php',
        'nav.php',
        'index.php',
        'home.php',
        'debug-orders.php',
        'trade.php',
        'orders.php',
        'test-deploy.txt',
        'watch.php',
        'limit-orders.php',
        'settings.php',
        'style.css', 
        'script.js',
        'header.js',
        'manifest.json',
        'sw.js',
        'api/watchlist.php',
        'api/get_balance.php',
        'api/place_order.php',
        'api/get_price.php',
        'api/get_watchlist_prices.php',
        'api/api_helper.php',
        'api/auth_token.php',
        'api/open_limit_position.php',
        'api/cancel_limit_order.php',
        'api/generate_token.php',
        'api/telegram.php',
        'api/get_settings.php',
        'api/save_settings.php',
        'api/debug_orders.php',
        'api/test_simple.php',
        'api/debug_limit_prices.php',
        'auth/api_protection.php',
        'auth/config.php',
        'auth/login.php',
        'auth/callback.php',
        'auth/logout.php',
        'jobs/price-monitor.php',
        'jobs/balance-sync.php',
        'jobs/limit-order-monitor.php',
        'jobs/target-stoploss-monitor.php',
        'database_setup.sql',
        'test-limit-api.php',
        'icons/icon-72x72.png',
        'icons/icon-96x96.png',
        'icons/icon-128x128.png',
        'icons/icon-144x144.png',
        'icons/icon-152x152.png',
        'icons/icon-192x192.png',
        'icons/icon-384x384.png',
        'icons/icon-512x512.png'
    ];
    
    $uploaded = 0;
    $failed = 0;
    
    foreach ($files as $file) {
        if (file_exists($file)) {
            echo "Uploading $file...\n";
            $remotePath = $config['remote_path'] . '/' . $file;
            
            // Create directory if needed
            $dir = dirname($remotePath);
            if ($dir !== $config['remote_path']) {
                @ftp_mkdir($connection, $dir);
            }
            
            // Also create the main remote_path directory
            @ftp_mkdir($connection, $config['remote_path']);
            
            if (ftp_put($connection, $remotePath, $file, FTP_BINARY)) {
                echo "✅ Uploaded: $file\n";
                $uploaded++;
            } else {
                echo "❌ Failed: $file\n";
                $failed++;
            }
        } else {
            echo "⚠️  File not found: $file\n";
        }
    }
    
    ftp_close($connection);
    
    echo "\n📊 Deployment Summary:\n";
    echo "✅ Uploaded: $uploaded files\n";
    echo "❌ Failed: $failed files\n";
    
    if ($failed === 0) {
        echo "🎉 Deployment completed successfully!\n";
    } else {
        echo "⚠️  Deployment completed with some failures\n";
    }
}

// Run deployment
deployFiles($deploymentConfig);