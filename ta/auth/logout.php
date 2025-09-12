<?php
require_once 'config.php';

// Log the logout
$user = getCurrentUser();
if ($user) {
    error_log("Crypto Trading App: User logout for {$user['email']}");
}

// Perform logout
logout();