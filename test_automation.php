<?php
/**
 * Complete Signal Automation Test Suite
 * One-click testing for the entire automation system
 */

echo "=======================================================\n";
echo "        SIGNAL AUTOMATION TEST SUITE                  \n";
echo "=======================================================\n\n";

// Step 1: Verify demo mode
echo "Step 1: Verifying demo mode configuration...\n";
echo "---------------------------------------------\n";
include 'verify_demo_mode.php';

// Check if verification passed
require_once __DIR__ . '/api/api_helper.php';
loadEnv(__DIR__ . '/.env');

if (!isDemoMode()) {
    echo "\n❌ TESTING ABORTED: Not in demo mode!\n";
    echo "Please set TRADING_MODE=demo in your .env file before testing.\n";
    exit(1);
}

echo "\n✅ Demo mode verification passed!\n\n";

// Step 2: Show current market prices
echo "Step 2: Current market prices...\n";
echo "--------------------------------\n";
include 'test_signal_automation.php';

echo "\n\nStep 3: Checking existing signals in database...\n";
echo "------------------------------------------------\n";

// Step 3: Check existing signals
echo "You should have test signals already in your database.\n";
echo "Use this SQL to check: SELECT id, symbol, signal_type, entry_market_price, status FROM signals WHERE signal_status = 'ACTIVE';\n\n";

echo "\n\nStep 4: Running signal automation...\n";
echo "------------------------------------\n";

// Step 4: Run the automation
include 'signal_automation.php';

echo "\n\nStep 5: Check results in database...\n";
echo "------------------------------------\n";

echo "After automation runs, check your database to see which signals changed from PENDING to FILLED.\n";
echo "Also check your BingX demo account for new positions.\n";

echo "\n=======================================================\n";
echo "                TEST COMPLETED                          \n";
echo "=======================================================\n\n";

echo "What to check next:\n";
echo "1. Review the automation logs above\n";
echo "2. Log into your BingX DEMO account\n";
echo "3. Check the 'Positions' section for new positions\n";
echo "4. Verify position sizes match the calculations\n";
echo "5. Monitor for entry2 triggers if applicable\n\n";

echo "Expected results:\n";
echo "- Signals with entry prices below current price (LONG) should be FILLED\n";
echo "- Signals with entry prices above current price (SHORT) should be FILLED\n";
echo "- Other signals should remain PENDING\n";
echo "- New positions should appear in BingX demo account\n";
echo "- All currencies should be VST (demo), not USDT (live)\n\n";

echo "If you see any issues:\n";
echo "1. Check the logs above for error messages\n";
echo "2. Verify your .env configuration\n";
echo "3. Ensure database connection is working\n";
echo "4. Confirm BingX demo API access\n\n";

echo "Next steps for production:\n";
echo "1. Test with real signal data\n";
echo "2. Monitor for several hours\n";
echo "3. Test entry2 and entry3 triggers\n";
echo "4. Set up cron job when ready\n";
echo "5. Switch to TRADING_MODE=live only when fully tested\n\n";

if (!function_exists('loadEnv')) {
    function loadEnv($path) {
        if (!file_exists($path)) {
            return false;
        }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            if (strpos($line, '=') === false) {
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
    }
}

?>