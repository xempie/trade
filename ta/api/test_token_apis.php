<?php
/**
 * Test Token-Based APIs
 * This file demonstrates how the new token-based API system works
 */

require_once 'auth_token.php';

header('Content-Type: application/json');

echo "<h1>Token-Based API System Test</h1>\n";
echo "<p>This demonstrates the secure token-based API system for position management.</p>\n";

try {
    // Generate test tokens
    $testOrderId = 123;
    
    // Generate tokens for different actions
    $openPositionToken = TokenAuth::generateToken($testOrderId, 'open_position', 3600);
    $cancelOrderToken = TokenAuth::generateToken($testOrderId, 'cancel_order', 3600);
    
    echo "<h2>Generated Tokens (Valid for 1 hour)</h2>\n";
    echo "<h3>Open Position Token:</h3>\n";
    echo "<code>{$openPositionToken}</code><br><br>\n";
    
    echo "<h3>Cancel Order Token:</h3>\n";
    echo "<code>{$cancelOrderToken}</code><br><br>\n";
    
    // Create example URLs
    $baseUrl = "http://" . $_SERVER['HTTP_HOST'] . "/trade";
    
    $openUrl = $baseUrl . "/api/open_limit_position.php?token=" . urlencode($openPositionToken);
    $cancelUrl = $baseUrl . "/api/cancel_limit_order.php?token=" . urlencode($cancelOrderToken);
    
    echo "<h2>Example API URLs</h2>\n";
    echo "<h3>Open Position URL:</h3>\n";
    echo "<a href='{$openUrl}' target='_blank'>{$openUrl}</a><br><br>\n";
    
    echo "<h3>Cancel Order URL:</h3>\n";
    echo "<a href='{$cancelUrl}' target='_blank'>{$cancelUrl}</a><br><br>\n";
    
    // Validate the tokens to show they work
    echo "<h2>Token Validation Test</h2>\n";
    
    $openPayload = TokenAuth::validateToken($openPositionToken);
    $cancelPayload = TokenAuth::validateToken($cancelOrderToken);
    
    echo "<h3>Open Position Token Payload:</h3>\n";
    echo "<pre>" . json_encode($openPayload, JSON_PRETTY_PRINT) . "</pre>\n";
    
    echo "<h3>Cancel Order Token Payload:</h3>\n";
    echo "<pre>" . json_encode($cancelPayload, JSON_PRETTY_PRINT) . "</pre>\n";
    
    echo "<h2>How It Works</h2>\n";
    echo "<ul>\n";
    echo "<li>When a limit order price is reached, the price monitor sends a Telegram message</li>\n";
    echo "<li>The message includes two buttons with secure URLs containing tokens</li>\n";
    echo "<li>Clicking 'Open Position' calls the open_limit_position.php API</li>\n";
    echo "<li>Clicking 'Ignore/Cancel' calls the cancel_limit_order.php API</li>\n";
    echo "<li>Each token is valid for 1 hour and can only be used for its specific purpose</li>\n";
    echo "<li>The APIs validate the token, check order status, and prevent duplicate actions</li>\n";
    echo "</ul>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red'>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
?>