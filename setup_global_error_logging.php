<?php
/**
 * Global Error Logging Setup
 * Add this code to the top of your main PHP files or create an auto-prepend file
 */

// Function to set up directory-specific error logging
function setupDirectoryErrorLogging() {
    $currentDir = dirname($_SERVER['SCRIPT_FILENAME']);
    $errorLogFile = $currentDir . '/error_log';
    
    // Set error logging parameters
    ini_set('log_errors', 1);
    ini_set('error_log', $errorLogFile);
    ini_set('error_reporting', E_ALL);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    
    // Create error log file if it doesn't exist
    if (!file_exists($errorLogFile)) {
        touch($errorLogFile);
        chmod($errorLogFile, 0666);
    }
    
    return $errorLogFile;
}

// Auto-setup error logging for current directory
$errorLogPath = setupDirectoryErrorLogging();

// Optional: Log script execution start
error_log("Script executed: " . $_SERVER['SCRIPT_NAME'] . " at " . date('Y-m-d H:i:s'), 0);

/**
 * Usage: Include this file at the top of your PHP files:
 * require_once __DIR__ . '/path/to/setup_global_error_logging.php';
 */
?>