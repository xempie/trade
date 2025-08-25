<?php
/**
 * API Protection Middleware
 * Include this at the top of all API endpoints to ensure authentication
 */

// Include authentication config
require_once dirname(__DIR__) . '/auth/config.php';

/**
 * Protect API endpoint - require authentication (bypassed for localhost)
 */
function protectAPI() {
    // Set JSON and CORS headers first
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        exit(0);
    }
    
    // Check if user is authenticated (localhost automatically passes)
    if (!isAuthenticated()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Authentication required',
            'message' => 'You must be logged in to access this endpoint'
        ]);
        exit;
    }
    
    // Log API access
    $user = getCurrentUser();
    $endpoint = $_SERVER['REQUEST_URI'];
    error_log("API Access: {$user['email']} -> {$endpoint}");
}

/**
 * Get current authenticated user for API responses
 */
function getAPIUser() {
    return getCurrentUser();
}

/**
 * API Response helper with authentication context
 */
function sendAPIResponse($success, $data = null, $message = null) {
    $response = [
        'success' => $success,
        'timestamp' => date('Y-m-d H:i:s'),
        'user' => getCurrentUser()['email'] ?? null
    ];
    
    if ($message) {
        $response['message'] = $message;
    }
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}