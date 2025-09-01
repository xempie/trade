<?php
require_once 'auth/config.php';

// Require authentication
requireAuth();

// Redirect to home page
header('Location: home.php');
exit();
?>