<?php
/**
 * Debug authentication function behavior
 */

header('Content-Type: application/json');

// Include authentication config
require_once dirname(__DIR__) . '/auth/config.php';

$debug = [
    'hostname_check' => [
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'Not set',
        'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? 'Not set',
        'is_localhost_detected' => isLocalhost(),
        'localhost_check_logic' => [
            'host' => $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '',
            'in_localhost_array' => in_array($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1']),
            'starts_with_localhost' => strpos(($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''), 'localhost:') === 0,
            'starts_with_127' => strpos(($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''), '127.0.0.1:') === 0
        ]
    ],
    'authentication_check' => [
        'session_user_authenticated' => isset($_SESSION['user_authenticated']) ? $_SESSION['user_authenticated'] : 'NOT SET',
        'session_user_authenticated_type' => isset($_SESSION['user_authenticated']) ? gettype($_SESSION['user_authenticated']) : 'NOT SET',
        'session_user_authenticated_strict' => isset($_SESSION['user_authenticated']) && $_SESSION['user_authenticated'] === true,
        'is_authenticated_result' => isAuthenticated(),
        'current_user' => getCurrentUser()
    ],
    'session_full' => $_SESSION ?? []
];

echo json_encode($debug, JSON_PRETTY_PRINT);
?>