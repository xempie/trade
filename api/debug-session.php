<?php
/**
 * Debug API session and authentication status
 */

header('Content-Type: application/json');

// Include authentication config
require_once dirname(__DIR__) . '/auth/config.php';

$debug = [
    'session_status' => session_status(),
    'session_id' => session_id(),
    'session_data' => $_SESSION ?? [],
    'is_authenticated' => isAuthenticated(),
    'current_user' => getCurrentUser(),
    'cookies' => $_COOKIE ?? [],
    'server_info' => [
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'Not set',
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'Not set',
        'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? 'Not set',
        'HTTPS' => isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : 'Not set'
    ],
    'php_session_settings' => [
        'session.cookie_path' => ini_get('session.cookie_path'),
        'session.cookie_domain' => ini_get('session.cookie_domain'),
        'session.cookie_secure' => ini_get('session.cookie_secure'),
        'session.cookie_httponly' => ini_get('session.cookie_httponly')
    ]
];

echo json_encode($debug, JSON_PRETTY_PRINT);
?>