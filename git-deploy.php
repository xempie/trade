<?php
/**
 * Git-Based Deployment Script
 * Only deploys files that are staged in git or have been modified
 */

require_once 'deploy-config.php';

function getGitFiles() {
    echo "Getting files from git...\n";
    
    $files = [];
    
    // Get staged files (ready to be committed)
    $stagedFiles = shell_exec('git diff --cached --name-only');
    if ($stagedFiles) {
        $staged = array_filter(explode("\n", trim($stagedFiles)));
        echo "Found " . count($staged) . " staged files\n";
        $files = array_merge($files, $staged);
    }
    
    // Get modified files (not yet staged)
    $modifiedFiles = shell_exec('git diff --name-only');
    if ($modifiedFiles) {
        $modified = array_filter(explode("\n", trim($modifiedFiles)));
        echo "Found " . count($modified) . " modified files\n";
        $files = array_merge($files, $modified);
    }
    
    // Get untracked files
    $untrackedFiles = shell_exec('git ls-files --others --exclude-standard');
    if ($untrackedFiles) {
        $untracked = array_filter(explode("\n", trim($untrackedFiles)));
        echo "Found " . count($untracked) . " untracked files\n";
        $files = array_merge($files, $untracked);
    }
    
    // Remove duplicates
    $files = array_unique($files);
    
    // Filter out non-deployable files
    $deployableFiles = [];
    $excludePatterns = [
        '.git/',
        '.gitignore',
        '*.md',
        '!README.md',
        '.env',
        '.env.*',
        'node_modules/',
        'vendor/',
        '*.log',
        'deploy-config.php',
        'git-deploy.php',
        'simple-deploy.php'
    ];
    
    foreach ($files as $file) {
        $shouldExclude = false;
        
        foreach ($excludePatterns as $pattern) {
            if ($pattern === '!README.md' && $file === 'README.md') {
                continue; // Don't exclude README.md
            }
            
            if (strpos($pattern, '*') !== false) {
                // Pattern matching
                $regex = '/^' . str_replace(['*', '.'], ['.*', '\.'], $pattern) . '$/';
                if (preg_match($regex, $file)) {
                    $shouldExclude = true;
                    break;
                }
            } else {
                // Exact or prefix matching
                if (strpos($file, $pattern) === 0) {
                    $shouldExclude = true;
                    break;
                }
            }
        }
        
        if (!$shouldExclude && file_exists($file)) {
            $deployableFiles[] = $file;
        }
    }
    
    echo "Total deployable files: " . count($deployableFiles) . "\n";
    
    if (empty($deployableFiles)) {
        echo "No files to deploy!\n";
        return [];
    }
    
    echo "Files to deploy:\n";
    foreach ($deployableFiles as $file) {
        echo "  - $file\n";
    }
    
    return $deployableFiles;
}

function deployFiles($config) {
    echo "Starting git-based deployment...\n";
    
    // Get files from git
    $files = getGitFiles();
    
    if (empty($files)) {
        echo "Nothing to deploy.\n";
        return;
    }
    
    // Ask for confirmation
    echo "\nProceed with deployment? (y/N): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim(strtolower($line)) !== 'y') {
        echo "Deployment cancelled.\n";
        return;
    }
    
    // Test FTP connection first
    echo "\nTesting FTP connection...\n";
    
    // Try SSL FTP first
    echo "Trying SSL FTP...\n";
    echo "Host: {$config['host']}, Port: {$config['port']}, User: {$config['username']}\n";
    
    $connection = @ftp_ssl_connect($config['host'], $config['port'], 90);
    if ($connection) {
        echo "SSL FTP connection successful\n";
        
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
                return;
            }
        } else {
            echo "❌ Could not establish any FTP connection\n";
            return;
        }
    }
    
    $uploaded = 0;
    $failed = 0;
    
    foreach ($files as $file) {
        if (!file_exists($file)) {
            echo "⚠️  File not found: $file\n";
            $failed++;
            continue;
        }
        
        echo "Uploading $file...\n";
        
        $remoteFile = $config['remote_path'] . '/' . $file;
        $remoteDir = dirname($remoteFile);
        
        // Create remote directory if it doesn't exist
        $dirs = explode('/', str_replace($config['remote_path'] . '/', '', $remoteDir));
        $currentPath = $config['remote_path'];
        
        foreach ($dirs as $dir) {
            if (!empty($dir)) {
                $currentPath .= '/' . $dir;
                @ftp_mkdir($connection, $currentPath);
            }
        }
        
        // Upload file
        $success = ftp_put($connection, $remoteFile, $file, FTP_BINARY);
        
        if ($success) {
            echo "✅ Uploaded: $file\n";
            $uploaded++;
        } else {
            echo "❌ Failed: $file\n";
            $failed++;
        }
    }
    
    ftp_close($connection);
    
    echo "\n📊 Deployment Summary:\n";
    echo "✅ Uploaded: $uploaded files\n";
    echo "❌ Failed: $failed files\n";
    
    if ($uploaded > 0) {
        echo "🎉 Deployment completed successfully!\n";
        
        // Ask if user wants to commit the changes
        if (!empty(shell_exec('git diff --cached --name-only'))) {
            echo "\nCommit staged changes? (y/N): ";
            $handle = fopen("php://stdin", "r");
            $line = fgets($handle);
            fclose($handle);
            
            if (trim(strtolower($line)) === 'y') {
                echo "Enter commit message: ";
                $handle = fopen("php://stdin", "r");
                $message = trim(fgets($handle));
                fclose($handle);
                
                if (!empty($message)) {
                    $fullMessage = $message . "\n\n🤖 Generated with [Claude Code](https://claude.ai/code)\n\nCo-Authored-By: Claude <noreply@anthropic.com>";
                    shell_exec('git commit -m "' . addslashes($fullMessage) . '"');
                    echo "✅ Changes committed!\n";
                }
            }
        }
    } else {
        echo "❌ Deployment failed!\n";
    }
}

// Run deployment
deployFiles($deployConfig);
?>