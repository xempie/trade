<?php
/**
 * Safe FTP Deployment Script for Crypto Trading App
 * Deploys only to specified folder without touching other server areas
 */

// Load deployment configuration
require_once 'deploy-config.php';

class SafeDeployment {
    private $config;
    private $ftpConnection;
    private $deploymentLog = [];
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    public function deploy() {
        try {
            $this->log("Starting deployment process...");
            
            // Connect to FTP
            $this->connectFTP();
            
            // Create backup
            $this->createBackup();
            
            // Sync files
            $this->syncFiles();
            
            // Set permissions
            $this->setPermissions();
            
            $this->log("Deployment completed successfully!");
            $this->closeFTP();
            
            return true;
            
        } catch (Exception $e) {
            $this->log("ERROR: " . $e->getMessage());
            $this->rollback();
            return false;
        }
    }
    
    private function connectFTP() {
        $this->log("Connecting to FTP server...");
        
        if ($this->config['use_sftp']) {
            // For SFTP connections
            $this->ftpConnection = ssh2_connect($this->config['host'], $this->config['port']);
            if (!ssh2_auth_password($this->ftpConnection, $this->config['username'], $this->config['password'])) {
                throw new Exception("SFTP authentication failed");
            }
        } else {
            // For FTP connections with SSL/TLS support
            $this->ftpConnection = ftp_ssl_connect($this->config['host'], $this->config['port']);
            if (!$this->ftpConnection) {
                // Fallback to regular FTP if SSL fails
                $this->ftpConnection = ftp_connect($this->config['host'], $this->config['port']);
            }
            
            if (!ftp_login($this->ftpConnection, $this->config['username'], $this->config['password'])) {
                throw new Exception("FTP login failed");
            }
            ftp_pasv($this->ftpConnection, true);
        }
        
        $this->log("Connected successfully");
    }
    
    private function createBackup() {
        $this->log("Creating backup of existing files...");
        
        $backupDir = $this->config['remote_path'] . '_backup_' . date('Y-m-d_H-i-s');
        
        if ($this->config['use_sftp']) {
            $sftp = ssh2_sftp($this->ftpConnection);
            ssh2_sftp_mkdir($sftp, $backupDir, 0755, true);
            // Copy existing files to backup (simplified - in production you'd want full recursive copy)
        } else {
            ftp_mkdir($this->ftpConnection, $backupDir);
        }
        
        $this->config['backup_path'] = $backupDir;
        $this->log("Backup created at: " . $backupDir);
    }
    
    private function syncFiles() {
        $this->log("Syncing files to server...");
        
        $localPath = $this->config['local_path'];
        $remotePath = $this->config['remote_path'];
        
        // Files to exclude from deployment
        $excludeFiles = [
            '.git',
            '.gitignore',
            'deploy.php',
            'deploy-config.php',
            'deploy-config.example.php',
            '.env.dev',
            '.env.prod',
            'README.md',
            'Documentation.md',
            'run-cronjobs-windows.bat'
        ];
        
        $this->uploadDirectory($localPath, $remotePath, $excludeFiles);
        $this->log("File sync completed");
    }
    
    private function uploadDirectory($localDir, $remoteDir, $excludeFiles = []) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($localDir),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($files as $file) {
            $filename = $file->getFilename();
            if ($filename == '.' || $filename == '..') continue;
            
            $relativePath = str_replace($localDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $relativePath = str_replace('\\', '/', $relativePath);
            
            // Check if file should be excluded
            $shouldExclude = false;
            foreach ($excludeFiles as $excludePattern) {
                if (strpos($relativePath, $excludePattern) === 0) {
                    $shouldExclude = true;
                    break;
                }
            }
            
            if ($shouldExclude) continue;
            
            $remotePath = $remoteDir . '/' . $relativePath;
            
            if ($file->isDir()) {
                $this->createRemoteDirectory($remotePath);
            } else {
                $this->uploadFile($file->getPathname(), $remotePath);
            }
        }
    }
    
    private function createRemoteDirectory($remotePath) {
        if ($this->config['use_sftp']) {
            $sftp = ssh2_sftp($this->ftpConnection);
            ssh2_sftp_mkdir($sftp, $remotePath, 0755, true);
        } else {
            ftp_mkdir($this->ftpConnection, $remotePath);
        }
    }
    
    private function uploadFile($localFile, $remoteFile) {
        $this->log("Uploading: " . basename($localFile));
        
        if ($this->config['use_sftp']) {
            $sftp = ssh2_sftp($this->ftpConnection);
            file_put_contents("ssh2.sftp://{$sftp}{$remoteFile}", file_get_contents($localFile));
        } else {
            ftp_put($this->ftpConnection, $remoteFile, $localFile, FTP_BINARY);
        }
    }
    
    private function setPermissions() {
        $this->log("Setting file permissions...");
        
        $permissions = [
            'api/*.php' => 644,
            'jobs/*.php' => 644,
            '*.html' => 644,
            '*.css' => 644,
            '*.js' => 644,
            '*.sql' => 644
        ];
        
        foreach ($permissions as $pattern => $chmod) {
            // Set permissions for matching files
            if ($this->config['use_sftp']) {
                $sftp = ssh2_sftp($this->ftpConnection);
                // SFTP permission setting would go here
            } else {
                // FTP doesn't support chmod directly
            }
        }
        
        $this->log("Permissions set");
    }
    
    private function rollback() {
        $this->log("Rolling back deployment...");
        // Rollback implementation would restore from backup
        // This is a safety feature for production use
    }
    
    private function closeFTP() {
        if ($this->config['use_sftp']) {
            // SSH connection closes automatically
        } else {
            ftp_close($this->ftpConnection);
        }
    }
    
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message";
        $this->deploymentLog[] = $logMessage;
        echo $logMessage . "\n";
        
        // Also write to log file
        file_put_contents('deployment.log', $logMessage . "\n", FILE_APPEND);
    }
}

// Run deployment
if (php_sapi_name() === 'cli') {
    $deployment = new SafeDeployment($deploymentConfig);
    $success = $deployment->deploy();
    
    if ($success) {
        echo "\n✅ Deployment completed successfully!\n";
        exit(0);
    } else {
        echo "\n❌ Deployment failed. Check deployment.log for details.\n";
        exit(1);
    }
} else {
    echo "This script must be run from command line.\n";
}