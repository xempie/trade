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
ini_set('session.cookie_lifetime', 86400 * 30); // 30 days

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate a secure random token
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Create persistent login cookie
 */
function createPersistentLogin($userEmail, $userName, $userPicture) {
    $cookieName = 'trade_persistent_login';
    $expiry = time() + (86400 * 30); // 30 days
    
    // Create secure token
    $token = generateSecureToken();
    $signature = hash_hmac('sha256', $userEmail . '|' . $expiry, getAppSecretKey());
    
    $cookieValue = base64_encode(json_encode([
        'email' => $userEmail,
        'name' => $userName,
        'picture' => $userPicture,
        'token' => $token,
        'expiry' => $expiry,
        'signature' => $signature
    ]));
    
    // Set secure cookie
    $secure = !isLocalhost() && isset($_SERVER['HTTPS']);
    setcookie($cookieName, $cookieValue, [
        'expires' => $expiry,
        'path' => getAppBasePath() ?: '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    return true;
}

/**
 * Check persistent login cookie
 */
function checkPersistentLogin() {
    $cookieName = 'trade_persistent_login';
    
    if (!isset($_COOKIE[$cookieName])) {
        return false;
    }
    
    try {
        $cookieData = json_decode(base64_decode($_COOKIE[$cookieName]), true);
        
        if (!$cookieData || !isset($cookieData['email'], $cookieData['expiry'], $cookieData['signature'])) {
            clearPersistentLogin();
            return false;
        }
        
        // Check expiry
        if (time() > $cookieData['expiry']) {
            clearPersistentLogin();
            return false;
        }
        
        // Verify signature
        $expectedSignature = hash_hmac('sha256', $cookieData['email'] . '|' . $cookieData['expiry'], getAppSecretKey());
        if (!hash_equals($expectedSignature, $cookieData['signature'])) {
            clearPersistentLogin();
            return false;
        }
        
        // Check if email is still allowed
        if (!isEmailAllowed($cookieData['email'])) {
            clearPersistentLogin();
            return false;
        }
        
        // Restore session
        $_SESSION['user_authenticated'] = true;
        $_SESSION['user_email'] = $cookieData['email'];
        $_SESSION['user_name'] = $cookieData['name'] ?? '';
        $_SESSION['user_picture'] = $cookieData['picture'] ?? '';
        $_SESSION['login_time'] = time();
        $_SESSION['persistent_login'] = true;
        
        return true;
        
    } catch (Exception $e) {
        clearPersistentLogin();
        return false;
    }
}

/**
 * Clear persistent login cookie
 */
function clearPersistentLogin() {
    $cookieName = 'trade_persistent_login';
    if (isset($_COOKIE[$cookieName])) {
        $secure = !isLocalhost() && isset($_SERVER['HTTPS']);
        setcookie($cookieName, '', [
            'expires' => time() - 3600,
            'path' => getAppBasePath() ?: '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        unset($_COOKIE[$cookieName]);
    }
}

/**
 * Get application secret key for HMAC
 */
function getAppSecretKey() {
    // Use environment variable or fallback to a default (change this in production)
    $secret = $_ENV['APP_SECRET_KEY'] ?? 'crypto_trade_secret_2024_change_in_production';
    return $secret;
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
    
    // Check session authentication
    if (isset($_SESSION['user_authenticated']) && $_SESSION['user_authenticated'] === true) {
        return true;
    }
    
    // Check for persistent login cookie
    return checkPersistentLogin();
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
    clearPersistentLogin();
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