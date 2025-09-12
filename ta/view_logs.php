<?php
// Simple log viewer to debug demo order issues
header('Content-Type: text/html; charset=utf-8');

// Set error log file path
$errorLog = ini_get('error_log') ?: '/tmp/php_errors.log';
$customLog = __DIR__ . '/debug.log';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Debug Log Viewer</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #fff; margin: 20px; }
        .log-section { margin: 20px 0; }
        .log-content { background: #2d2d2d; padding: 15px; border-radius: 5px; white-space: pre-wrap; max-height: 500px; overflow-y: auto; }
        h2 { color: #4CAF50; }
        .error { color: #ff4444; }
        .warning { color: #ffaa00; }
        .info { color: #44aaff; }
    </style>
</head>
<body>";

echo "<h1>🔍 Demo Order Debug Logs</h1>";

// Show custom debug log if exists
if (file_exists($customLog)) {
    echo "<div class='log-section'>
        <h2>Custom Debug Log (debug.log)</h2>
        <div class='log-content'>";
    
    $lines = file($customLog);
    $recentLines = array_slice($lines, -100); // Show last 100 lines
    
    foreach ($recentLines as $line) {
        $line = htmlspecialchars($line);
        if (strpos($line, 'ERROR') !== false) {
            echo "<span class='error'>$line</span>";
        } elseif (strpos($line, 'WARNING') !== false) {
            echo "<span class='warning'>$line</span>";
        } else {
            echo "<span class='info'>$line</span>";
        }
    }
    
    echo "</div></div>";
} else {
    echo "<div class='log-section'>
        <h2>Custom Debug Log</h2>
        <div class='log-content'>No custom debug.log found</div>
    </div>";
}

// Show PHP error log
if (file_exists($errorLog)) {
    echo "<div class='log-section'>
        <h2>PHP Error Log</h2>
        <div class='log-content'>";
    
    $lines = file($errorLog);
    $recentLines = array_slice($lines, -50); // Show last 50 lines
    
    foreach ($recentLines as $line) {
        $line = htmlspecialchars($line);
        if (strpos($line, 'trade') !== false || strpos($line, 'place_order') !== false) {
            echo "<span class='error'>$line</span>";
        } else {
            echo $line;
        }
    }
    
    echo "</div></div>";
} else {
    echo "<div class='log-section'>
        <h2>PHP Error Log</h2>
        <div class='log-content'>Error log not found at: $errorLog</div>
    </div>";
}

// Show recent access attempts
echo "<div class='log-section'>
    <h2>Recent place_order.php Requests</h2>
    <div class='log-content'>";

// Check for recent requests in server logs (if accessible)
$accessLog = '/var/log/apache2/access.log';
if (file_exists($accessLog)) {
    $command = "tail -100 $accessLog | grep 'place_order.php'";
    $output = shell_exec($command);
    echo htmlspecialchars($output ?: 'No recent place_order.php requests found');
} else {
    echo "Server access log not accessible";
}

echo "</div></div>";

echo "<div class='log-section'>
    <h2>📝 Manual Test</h2>
    <p><a href='test_place_order.php' target='_blank'>Click here to test place_order.php directly</a></p>
</div>";

echo "</body></html>";
?>