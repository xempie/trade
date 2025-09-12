<?php
/**
 * Deploy files from latest git commit
 * This script deploys all files that were changed in the most recent commit
 */

require_once 'deploy-config.php';

function getLatestCommitFiles() {
    echo "Getting files from latest commit...\n";
    
    // Get files changed in the last commit
    $commitFiles = shell_exec('git diff-tree --no-commit-id --name-only -r HEAD');
    if (!$commitFiles) {
        echo "No files found in latest commit\n";
        return [];
    }
    
    $files = array_filter(explode("\n", trim($commitFiles)));
    echo "Found " . count($files) . " files in latest commit\n";
    
    // Filter out excluded files
    $deployableFiles = [];
    $excludePatterns = ['.git/', '.gitignore', '*.md', '.env', 'node_modules/', '*.log', 'deploy-config.php'];
    
    foreach ($files as $file) {
        $shouldExclude = false;
        foreach ($excludePatterns as $pattern) {
            if (strpos($pattern, '*') !== false) {
                $regex = '/^' . str_replace(['*', '.'], ['.*', '\.'], $pattern) . '$/';
                if (preg_match($regex, $file)) {
                    $shouldExclude = true;
                    break;
                }
            } else {
                if (strpos($file, $pattern) === 0) {
                    $shouldExclude = true;
                    break;
                }
            }
        }
        
        if (!$shouldExclude && file_exists($file)) {
            $deployableFiles[] = $file;
        } else if (!file_exists($file)) {
            echo "⚠️  File not found locally: $file (may have been deleted)\n";
        }
    }
    
    return $deployableFiles;
}

function deployFiles($config) {
    echo "Starting deployment from latest commit...\n";
    
    // Get files from latest commit
    $files = getLatestCommitFiles();
    
    if (empty($files)) {
        echo "No files to deploy!\n";
        return;
    }
    
    echo "Files to deploy (" . count($files) . "):\n";
    foreach ($files as $file) {
        echo "  - $file\n";
    }
    echo "\n";
    
    // Test FTP connection first
    echo "Testing FTP connection...\n";
    
    // Try SSL FTP first
    $connection = @ftp_ssl_connect($config['host'], $config['port'], 90);
    if ($connection) {
        echo "SSL FTP connection successful\n";
        $login = @ftp_login($connection, $config['username'], $config['password']);
        if ($login) {
            echo "✅ SSL FTP login successful!\n";
            ftp_pasv($connection, true);
        } else {
            echo "❌ SSL FTP login failed - trying regular FTP...\n";
            ftp_close($connection);
            $connection = false;
        }
    } else {
        echo "SSL FTP connection failed\n";
    }
    
    // Try regular FTP if SSL failed
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
                echo "❌ Regular FTP login failed\n";
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

// Get latest commit info
$latestCommit = shell_exec('git log -1 --oneline');
echo "Latest commit: " . trim($latestCommit) . "\n\n";

// Run deployment
deployFiles($deploymentConfig);
?>