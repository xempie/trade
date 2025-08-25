<?php
/**
 * Google OAuth Configuration
 * Handles Google Sign-In authentication
 */

// Load environment variables for auth
function loadAuthEnv($filePath) {
    if (!file_exists($filePath)) {
        return;
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        
        if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
            putenv(sprintf('%s=%s', $key, $value));
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Load environment file
$envFile = dirname(__DIR__) . '/.env';
if (file_exists(dirname(__DIR__) . '/.env.prod')) {
    $envFile = dirname(__DIR__) . '/.env.prod';
} elseif (file_exists(dirname(__DIR__) . '/.env.dev')) {
    $envFile = dirname(__DIR__) . '/.env.dev';
}

loadAuthEnv($envFile);

// Google OAuth Configuration - require environment variables
$googleClientId = $_ENV['GOOGLE_CLIENT_ID'] ?? '';
$googleClientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? '';
$appUrl = $_ENV['APP_URL'] ?? 'https://[REDACTED_HOST]/ta';

if (empty($googleClientId) || empty($googleClientSecret)) {
    error_log('Google OAuth credentials not configured in environment variables');
}

define('GOOGLE_CLIENT_ID', $googleClientId);
define('GOOGLE_CLIENT_SECRET', $googleClientSecret);
define('GOOGLE_REDIRECT_URI', $appUrl . '/auth/callback.php');

// Allowed emails (only these Gmail accounts can access) with fallback
$allowedEmailsStr = $_ENV['ALLOWED_EMAILS'] ?? 'afhayati@gmail.com';
$ALLOWED_EMAILS = explode(',', $allowedEmailsStr);
$ALLOWED_EMAILS = array_map('trim', $ALLOWED_EMAILS);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.use_strict_mode', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if running on localhost/development
 */
function isLocalhost() {
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    return in_array($host, ['localhost', '127.0.0.1', '::1']) || 
           strpos($host, 'localhost:') === 0 ||
           strpos($host, '127.0.0.1:') === 0;
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    // Skip authentication for localhost/development
    if (isLocalhost()) {
        return true;
    }
    
    return isset($_SESSION['user_authenticated']) && $_SESSION['user_authenticated'] === true;
}

/**
 * Check if email is allowed to access the system
 */
function isEmailAllowed($email) {
    global $ALLOWED_EMAILS;
    return in_array($email, $ALLOWED_EMAILS);
}

/**
 * Get current user info
 */
function getCurrentUser() {
    if (!isAuthenticated()) {
        return null;
    }
    
    // Return localhost user for development
    if (isLocalhost()) {
        return [
            'email' => 'dev@localhost',
            'name' => 'Local Developer',
            'picture' => ''
        ];
    }
    
    return [
        'email' => $_SESSION['user_email'] ?? '',
        'name' => $_SESSION['user_name'] ?? '',
        'picture' => $_SESSION['user_picture'] ?? ''
    ];
}

/**
 * Get the base path for the application
 */
function getAppBasePath() {
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $basePath = dirname($scriptName);
    
    // If we're in a subdirectory like /auth/, go one level up
    if (basename($basePath) === 'auth') {
        $basePath = dirname($basePath);
    }
    
    return rtrim($basePath, '/');
}

/**
 * Redirect to login page
 */
function redirectToLogin() {
    $currentUrl = $_SERVER['REQUEST_URI'];
    $_SESSION['intended_url'] = $currentUrl;
    $basePath = getAppBasePath();
    header('Location: ' . $basePath . '/auth/login.php?redirect=' . urlencode($currentUrl));
    exit;
}

/**
 * Logout user
 */
function logout() {
    session_unset();
    session_destroy();
    $basePath = getAppBasePath();
    header('Location: ' . $basePath . '/auth/login.php?logged_out=1');
    exit;
}

/**
 * Require authentication for current page
 */
function requireAuth() {
    if (!isAuthenticated()) {
        redirectToLogin();
    }
}