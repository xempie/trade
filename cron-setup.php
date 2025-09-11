<?php
/**
 * Automated Cron Job Setup Script
 * 
 * This script automatically sets up all required cron jobs for the trading system
 */

// Get the current working directory (project root)
$projectRoot = __DIR__;

// Detect the correct PHP binary path
function findPhpBinary() {
    $possiblePaths = [
        '/usr/bin/php',
        '/usr/local/bin/php', 
        '/opt/lampp/bin/php',
        '/Applications/XAMPP/bin/php', // macOS XAMPP
        'C:\\xampp\\php\\php.exe', // Windows XAMPP
        'php' // System PATH
    ];
    
    foreach ($possiblePaths as $path) {
        if (is_executable($path)) {
            return $path;
        }
    }
    
    // Test if 'php' is available in PATH
    $output = [];
    $returnCode = 0;
    exec('which php 2>/dev/null', $output, $returnCode);
    if ($returnCode === 0 && !empty($output[0])) {
        return trim($output[0]);
    }
    
    return 'php'; // Default fallback
}

$phpBinary = findPhpBinary();
echo "Detected PHP binary: {$phpBinary}\n";

// Define all cron jobs
$cronJobs = [
    [
        'name' => 'Balance Sync',
        'schedule' => '*/2 * * * *',
        'script' => 'jobs/balance-sync.php',
        'description' => 'Sync account balance every 2 minutes'
    ],
    [
        'name' => 'Position Sync', 
        'schedule' => '*/3 * * * *',
        'script' => 'jobs/position-sync.php',
        'description' => 'Sync positions every 3 minutes'
    ],
    [
        'name' => 'SL/TP Monitor',
        'schedule' => '*/5 * * * *', 
        'script' => 'jobs/sl-tp-monitor.php',
        'description' => 'Check and create missing SL/TP orders every 5 minutes'
    ]
];

// Generate cron entries
$cronEntries = [];
$cronEntries[] = "# Crypto Trading System - Auto-generated cron jobs";
$cronEntries[] = "# Generated on: " . date('Y-m-d H:i:s');
$cronEntries[] = "";

foreach ($cronJobs as $job) {
    $cronEntries[] = "# {$job['name']} - {$job['description']}";
    $cronEntry = "{$job['schedule']} {$phpBinary} {$projectRoot}/{$job['script']} >> {$projectRoot}/jobs/cron.log 2>&1";
    $cronEntries[] = $cronEntry;
    $cronEntries[] = "";
}

$cronContent = implode("\n", $cronEntries);

// Save cron configuration to file
$cronFile = $projectRoot . '/cron-jobs.txt';
file_put_contents($cronFile, $cronContent);

echo "\n=== Cron Job Setup ===\n";
echo "Generated cron configuration saved to: {$cronFile}\n\n";
echo "Cron entries to add:\n";
echo str_repeat("=", 80) . "\n";
echo $cronContent;
echo str_repeat("=", 80) . "\n\n";

// Check if we can automatically install (Unix/Linux only)
if (PHP_OS_FAMILY !== 'Windows') {
    echo "To install these cron jobs automatically, run:\n";
    echo "  crontab {$cronFile}\n\n";
    
    echo "To add to existing cron jobs without overwriting:\n";
    echo "  crontab -l > current_cron.txt 2>/dev/null || true\n";
    echo "  cat {$cronFile} >> current_cron.txt\n";
    echo "  crontab current_cron.txt\n\n";
    
    // Offer automatic installation
    echo "Would you like to install these cron jobs now? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim(strtolower($line)) === 'y') {
        echo "Installing cron jobs...\n";
        
        // Backup existing crontab
        exec('crontab -l > /tmp/crontab_backup.txt 2>/dev/null || true');
        
        // Install new cron jobs  
        $result = 0;
        exec("crontab {$cronFile} 2>&1", $output, $result);
        
        if ($result === 0) {
            echo "✅ Cron jobs installed successfully!\n";
            echo "Backup of previous crontab saved to: /tmp/crontab_backup.txt\n";
        } else {
            echo "❌ Failed to install cron jobs:\n";
            echo implode("\n", $output) . "\n";
            echo "Please install manually using the commands above.\n";
        }
    } else {
        echo "Cron jobs not installed. Please install manually using the commands above.\n";
    }
} else {
    echo "Windows detected. Please set up scheduled tasks manually.\n";
    echo "Use Windows Task Scheduler to run these scripts at the specified intervals.\n";
}

echo "\n=== Next Steps ===\n";
echo "1. Verify cron jobs are running: crontab -l\n";  
echo "2. Check logs: tail -f {$projectRoot}/jobs/cron.log\n";
echo "3. Test individual scripts manually:\n";
foreach ($cronJobs as $job) {
    echo "   php {$projectRoot}/{$job['script']}\n";
}
echo "\n=== Log Files ===\n";
echo "- Main log: {$projectRoot}/jobs/cron.log\n";
echo "- SL/TP Monitor: {$projectRoot}/jobs/sl-tp-monitor.log\n";
echo "- Balance Sync: {$projectRoot}/jobs/balance-sync.log\n"; 
echo "- Position Sync: {$projectRoot}/jobs/position-sync.log\n";

// Create jobs directory if it doesn't exist
$jobsDir = $projectRoot . '/jobs';
if (!is_dir($jobsDir)) {
    mkdir($jobsDir, 0755, true);
    echo "\nCreated jobs directory: {$jobsDir}\n";
}

// Check if log files exist and create empty ones if needed
$logFiles = [
    'cron.log',
    'sl-tp-monitor.log', 
    'balance-sync.log',
    'position-sync.log'
];

foreach ($logFiles as $logFile) {
    $logPath = $jobsDir . '/' . $logFile;
    if (!file_exists($logPath)) {
        touch($logPath);
        chmod($logPath, 0644);
        echo "Created log file: {$logPath}\n";
    }
}
?>