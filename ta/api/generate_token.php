<?php
/**
 * Generate Token API
 * Generates secure tokens for API access
 */

// Include authentication protection
require_once '../auth/api_protection.php';

// Protect this API endpoint
protectAPI();

require_once 'auth_token.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }
    
    // Validate required fields
    if (!isset($input['order_id']) || !isset($input['action'])) {
        throw new Exception('order_id and action are required');
    }
    
    $orderId = intval($input['order_id']);
    $action = trim($input['action']);
    
    // Validate action
    $allowedActions = ['open_position', 'cancel_order'];
    if (!in_array($action, $allowedActions)) {
        throw new Exception('Invalid action. Allowed: ' . implode(', ', $allowedActions));
    }
    
    // Generate token (1 hour expiry)
    $token = TokenAuth::generateToken($orderId, $action, 3600);
    
    echo json_encode([
        'success' => true,
        'token' => $token,
        'expires_in' => 3600,
        'order_id' => $orderId,
        'action' => $action
    ]);
    
} catch (Exception $e) {
    error_log("Generate Token API Error: " . $e->getMessage());
    
    http_response_code($e->getCode() ?: 400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>