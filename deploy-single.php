<?php
/**
 * Deploy single file
 * Usage: php deploy-single.php filename.ext
 */

require_once 'deploy-config.php';

function deploySingleFile($config, $filename) {
    if (!file_exists($filename)) {
        die("❌ File not found: $filename\n");
    }
    
    echo "Deploying single file: $filename\n";
    
    // Connect to FTP
    $connection = @ftp_ssl_connect($config['host'], $config['port'], 90);
    if (!$connection) {
        die("❌ FTP connection failed\n");
    }
    
    if (!ftp_login($connection, $config['username'], $config['password'])) {
        die("❌ FTP login failed\n");
    }
    
    ftp_pasv($connection, true);
    
    // Upload file
    $remotePath = $config['remote_path'] . '/' . $filename;
    
    if (ftp_put($connection, $remotePath, $filename, FTP_BINARY)) {
        echo "✅ Uploaded: $filename\n";
    } else {
        echo "❌ Failed: $filename\n";
    }
    
    ftp_close($connection);
}

// Get filename from command line or default to style.css
$filename = $argv[1] ?? 'style.css';
deploySingleFile($deploymentConfig, $filename);
?>