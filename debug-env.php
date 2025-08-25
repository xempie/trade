<?php
/**
 * Debug environment loading on production server
 */

// Load environment function (same as auth config)
function loadAuthEnv($filePath) {
    if (!file_exists($filePath)) {
        return false;
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) {
            continue;
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
    return true;
}

// Test environment loading
$envFile = __DIR__ . '/.env';

echo "<h2>Environment Debug</h2>";
echo "<p><strong>Environment file path:</strong> $envFile</p>";
echo "<p><strong>File exists:</strong> " . (file_exists($envFile) ? 'YES' : 'NO') . "</p>";

if (file_exists($envFile)) {
    echo "<p><strong>File size:</strong> " . filesize($envFile) . " bytes</p>";
    echo "<p><strong>File permissions:</strong> " . substr(sprintf('%o', fileperms($envFile)), -4) . "</p>";
    
    // Try to load environment
    $loadResult = loadAuthEnv($envFile);
    echo "<p><strong>Environment loaded:</strong> " . ($loadResult ? 'SUCCESS' : 'FAILED') . "</p>";
    
    // Check specific variables
    echo "<h3>Google OAuth Variables:</h3>";
    echo "<p><strong>GOOGLE_CLIENT_ID:</strong> " . ($_ENV['GOOGLE_CLIENT_ID'] ?? 'NOT SET') . "</p>";
    echo "<p><strong>GOOGLE_CLIENT_SECRET:</strong> " . (isset($_ENV['GOOGLE_CLIENT_SECRET']) ? 'SET (' . strlen($_ENV['GOOGLE_CLIENT_SECRET']) . ' chars)' : 'NOT SET') . "</p>";
    echo "<p><strong>APP_URL:</strong> " . ($_ENV['APP_URL'] ?? 'NOT SET') . "</p>";
    echo "<p><strong>ALLOWED_EMAILS:</strong> " . ($_ENV['ALLOWED_EMAILS'] ?? 'NOT SET') . "</p>";
    
    // Show first few lines of env file
    echo "<h3>Environment File Content (first 10 lines):</h3>";
    echo "<pre>";
    $lines = file($envFile);
    for ($i = 0; $i < min(10, count($lines)); $i++) {
        echo htmlspecialchars($lines[$i]);
    }
    echo "</pre>";
} else {
    echo "<p style='color: red;'><strong>ERROR:</strong> Environment file does not exist!</p>";
}

echo "<h3>PHP Environment Check:</h3>";
echo "<p><strong>Current working directory:</strong> " . getcwd() . "</p>";
echo "<p><strong>Script directory:</strong> " . __DIR__ . "</p>";
echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";

// Check if running through web server
echo "<p><strong>Server software:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</p>";
echo "<p><strong>Request URI:</strong> " . ($_SERVER['REQUEST_URI'] ?? 'Unknown') . "</p>";
?>