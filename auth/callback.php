<?php
require_once 'config.php';

// Check for errors
if (isset($_GET['error'])) {
    header('Location: login.php?error=oauth_error');
    exit;
}

// Verify state to prevent CSRF attacks
if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    header('Location: login.php?error=invalid_state');
    exit;
}

// Get authorization code
$authCode = $_GET['code'] ?? '';
if (empty($authCode)) {
    header('Location: login.php?error=no_code');
    exit;
}

// Exchange authorization code for access token
$tokenData = [
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code',
    'code' => $authCode
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

$tokenResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$tokenResponse) {
    header('Location: login.php?error=token_request_failed');
    exit;
}

$tokens = json_decode($tokenResponse, true);
if (!isset($tokens['access_token'])) {
    header('Location: login.php?error=no_access_token');
    exit;
}

// Get user information from Google
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/oauth2/v2/userinfo');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $tokens['access_token']
]);

$userResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$userResponse) {
    header('Location: login.php?error=user_info_failed');
    exit;
}

$userInfo = json_decode($userResponse, true);

// Verify email is allowed
$userEmail = $userInfo['email'] ?? '';
if (!isEmailAllowed($userEmail)) {
    header('Location: login.php?error=email_not_allowed');
    exit;
}

// Create session
$_SESSION['user_authenticated'] = true;
$_SESSION['user_email'] = $userEmail;
$_SESSION['user_name'] = $userInfo['name'] ?? '';
$_SESSION['user_picture'] = $userInfo['picture'] ?? '';
$_SESSION['login_time'] = time();

// Clean up OAuth state
unset($_SESSION['oauth_state']);

// Log successful login
error_log("Crypto Trading App: Successful login for {$userEmail}");

// Redirect to intended page or dashboard
$basePath = getAppBasePath();
$redirectUrl = $_SESSION['intended_url'] ?? $basePath . '/index.php';
unset($_SESSION['intended_url']);

header('Location: ' . $redirectUrl);
exit;