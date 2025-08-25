<?php
/**
 * Test the protectAPI function with debugging
 */

header('Content-Type: application/json');

// Include authentication config
require_once dirname(__DIR__) . '/auth/config.php';

// Debug version of protectAPI function
function debugProtectAPI() {
    $debug = [
        'step1_headers_set' => true,
        'step2_auth_check' => [
            'is_authenticated_result' => isAuthenticated(),
            'session_user_authenticated' => $_SESSION['user_authenticated'] ?? 'NOT SET',
            'session_data' => $_SESSION ?? []
        ],
        'step3_current_user' => getCurrentUser()
    ];
    
    // Check if user is authenticated (localhost automatically passes)
    if (!isAuthenticated()) {
        $debug['step4_auth_failed'] = true;
        $debug['error_response'] = [
            'success' => false,
            'error' => 'Authentication required',
            'message' => 'You must be logged in to access this endpoint'
        ];
        
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Authentication required - DEBUG VERSION',
            'message' => 'You must be logged in to access this endpoint',
            'debug' => $debug
        ]);
        exit;
    }
    
    $debug['step4_auth_passed'] = true;
    
    // Log API access
    $user = getCurrentUser();
    $endpoint = $_SERVER['REQUEST_URI'];
    error_log("API Access: {$user['email']} -> {$endpoint}");
    
    return $debug;
}

// Test the debug function
$debugResult = debugProtectAPI();

// If we get here, authentication passed
echo json_encode([
    'success' => true,
    'message' => 'protectAPI function passed!',
    'debug' => $debugResult,
    'user' => getCurrentUser()
]);
?>