<?php
require_once 'config.php';

// If already authenticated, redirect to main app
if (isAuthenticated()) {
    $basePath = getAppBasePath();
    $redirectUrl = $_GET['redirect'] ?? $basePath . '/index.php';
    header('Location: ' . $redirectUrl);
    exit;
}

// Generate state for CSRF protection
if (!isset($_SESSION['oauth_state'])) {
    $_SESSION['oauth_state'] = bin2hex(random_bytes(32));
}

$googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'email profile',
    'state' => $_SESSION['oauth_state'],
    'access_type' => 'online'
]);

$showError = isset($_GET['error']);
$showLoggedOut = isset($_GET['logged_out']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Crypto Trading App</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>Crypto Trading App</h1>
                <p>Secure access required</p>
            </div>
            
            <?php if ($showError): ?>
                <div class="error-message">
                    <p>❌ Access denied. Your Gmail account is not authorized to access this system.</p>
                </div>
            <?php endif; ?>
            
            <?php if ($showLoggedOut): ?>
                <div class="success-message">
                    <p>✅ You have been logged out successfully.</p>
                </div>
            <?php endif; ?>
            
            <div class="login-form">
                <a href="<?php echo htmlspecialchars($googleAuthUrl); ?>" class="google-signin-btn">
                    <svg class="google-icon" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Sign in with Google
                </a>
                
                <div class="login-info">
                    <p>Only authorized Gmail accounts can access this system.</p>
                    <p>Contact the administrator if you need access.</p>
                    <p><small>✓ Stay logged in for 30 days</small></p>
                </div>
            </div>
        </div>
        
        <div class="login-footer">
            <p>&copy; <?php echo date('Y'); ?> Crypto Trading App - Secure Access</p>
        </div>
    </div>
</body>
</html>