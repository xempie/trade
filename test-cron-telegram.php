<?php
/**
 * Manual Test Script for Telegram Cron Job
 * Run this first to verify everything works before adding to cron
 */

echo "=== TESTING TELEGRAM CRON JOB ===\n\n";

// Test 1: Simple version (uses existing infrastructure)
echo "🧪 Testing simple version...\n";
ob_start();
include 'ta/jobs/test-telegram-simple.php';
$output1 = ob_get_clean();
echo $output1;

echo "\n" . str_repeat("=", 50) . "\n\n";

// Test 2: Full version (standalone)
echo "🧪 Testing full version...\n";
ob_start();
include 'ta/jobs/test-telegram-cron.php';
$output2 = ob_get_clean();
echo $output2;

echo "\n=== TEST COMPLETED ===\n";

// Check log files
echo "\n📋 Checking log files:\n";
$logFiles = [
    'ta/jobs/test-telegram-simple.log',
    'ta/jobs/test-telegram-cron.log'
];

foreach ($logFiles as $logFile) {
    if (file_exists($logFile)) {
        echo "✅ {$logFile} (" . filesize($logFile) . " bytes)\n";
        $content = file_get_contents($logFile);
        $lines = explode("\n", $content);
        $lastLine = end($lines);
        if (trim($lastLine)) {
            echo "   Last: " . trim($lastLine) . "\n";
        }
    } else {
        echo "❌ {$logFile} - Not found\n";
    }
}

echo "\n🎯 If you see success messages above, the cron job is ready!\n";
echo "📝 Add this to cron: */1 * * * * php " . __DIR__ . "/ta/jobs/test-telegram-simple.php >> " . __DIR__ . "/ta/jobs/test-telegram-simple.log 2>&1\n";
echo "🌐 Or use the web interface: https://brainity.com.au/sys/jobs.php\n";
?>