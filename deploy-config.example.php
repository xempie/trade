<?php
/**
 * Deployment Configuration Template
 * Copy this file to deploy-config.php and fill in your server details
 */

$deploymentConfig = [
    // FTP/SFTP Connection Settings
    'host' => '[REDACTED_HOST]',
    'username' => '[REDACTED_FTP_USER]',
    'password' => '[REDACTED_FTP_PASSWORD]',
    'port' => 21, // 21 for FTP, 22 for SFTP
    'use_sftp' => false, // Set to true if your server supports SFTP
    
    // Paths
    'local_path' => __DIR__, // Current directory (don't change this)
    'remote_path' => 'public_html/addons/brainity/ta', // Path to your app folder on server
    
    // Deployment Options
    'create_backup' => false,
    'set_permissions' => true,
    'max_backups_to_keep' => 5,
    
    // Safety Settings
    'dry_run' => false, // Set to true to test without actually uploading
    'confirm_before_deploy' => true,
    
    // Excluded Files (these won't be uploaded)
    'exclude_files' => [
        '.git',
        '.gitignore',
        'deploy.php',
        'deploy-config.php',
        'deploy-config.example.php',
        '.env.dev',
        '.env.prod',
        '.env.prod.example',
        'README.md',
        'Documentation.md',
        'CLAUDE.md',
        'CRONS.md',
        'run-cronjobs-windows.bat',
        'deployment.log',
        'debug_watchlist.html',
        'cleanup_storage.html'
    ],
    
    // File Permissions (only works with SFTP)
    'file_permissions' => [
        'php' => 0644,
        'html' => 0644,
        'css' => 0644,
        'js' => 0644,
        'sql' => 0644
    ],
    
    // Directory Permissions (only works with SFTP)
    'directory_permissions' => 0755
];

// Validate configuration
function validateConfig($config) {
    $required = ['host', 'username', 'password', 'remote_path'];
    
    foreach ($required as $field) {
        if (empty($config[$field]) || strpos($config[$field], 'your_') === 0) {
            throw new Exception("Please configure '{$field}' in deploy-config.php");
        }
    }
    
    return true;
}

// Only validate if this file is being included
if (basename($_SERVER['PHP_SELF']) === 'deploy.php') {
    validateConfig($deploymentConfig);
}