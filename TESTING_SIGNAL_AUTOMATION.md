# Signal Automation Testing Guide

## 🚨 IMPORTANT: Demo Mode Safety

**ALWAYS test in demo mode first!** This guide ensures you're testing safely without risking real funds.

## Pre-Testing Checklist

### 1. Verify Demo Mode Configuration
```bash
# Check your .env file
cat .env | grep TRADING_MODE
# Should show: TRADING_MODE=demo

# Test trading mode detection
php -r "
require_once 'api/api_helper.php';
echo 'Trading Mode: ' . (isDemoMode() ? 'DEMO' : 'LIVE') . PHP_EOL;
echo 'API URL: ' . getBingXApiUrl() . PHP_EOL;
"
```

### 2. Verify Database Connection
```bash
# Test database connectivity
php api/test_db.php

# Check if signal_automation_settings table exists
mysql -u your_user -p your_database -e "DESCRIBE signal_automation_settings;"
```

### 3. Verify BingX Demo API Access
```bash
# Test demo API connection
php -r "
require_once 'api/api_helper.php';
loadEnv('.env');
echo 'Demo Mode: ' . (isDemoMode() ? 'YES' : 'NO') . PHP_EOL;
echo 'API URL: ' . getBingXApiUrl() . PHP_EOL;
"

# Test price fetching
php api/get_price.php?symbol=BTC-USDT

# Test balance retrieval (should show VST for demo)
php api/get_balance.php
```

## Step-by-Step Testing Process

### Phase 1: Basic Functionality Tests

#### Test 1: Price Fetching and Logic
```bash
# Run the test automation logic
php test_signal_automation.php

# Expected output should show:
# - Trading Mode: DEMO (if configured correctly)
# - Successful price fetching for BTC-USDT and ETH-USDT
# - Signal trigger logic examples
```

#### Test 2: Position Sizing Logic
```bash
# Test position sizing scenarios
php test_position_sizing.php

# This shows how the MIN(AUTO_MARGIN_PER_ENTRY, 5% of assets) works
```

#### Test 3: Database Settings
```sql
-- Check current automation settings
SELECT * FROM signal_automation_settings;

-- Verify AUTO_MARGIN_PER_ENTRY exists (should be 50.00 by default)
SELECT setting_key, setting_value, data_type 
FROM signal_automation_settings 
WHERE setting_key = 'AUTO_MARGIN_PER_ENTRY';
```

### Phase 2: Create Test Signals

#### Method 1: Create Test Signals Manually
```sql
-- Insert a test PENDING signal for BTC (current price is ~$114,000)
INSERT INTO signals (
    symbol, signal_type, entry_market_price, entry_2, entry_3,
    leverage, take_profit_1, stop_loss, status, signal_status, created_at
) VALUES (
    'BTC-USDT', 'LONG', 110000.00, 108000.00, 106000.00,
    2, 120000.00, 105000.00, 'PENDING', 'ACTIVE', NOW()
);

-- Insert a test SHORT signal for ETH (current price is ~$4,364)
INSERT INTO signals (
    symbol, signal_type, entry_market_price, entry_2, entry_3,
    leverage, take_profit_1, stop_loss, status, signal_status, created_at
) VALUES (
    'ETH-USDT', 'SHORT', 4500.00, 4600.00, 4700.00,
    3, 4000.00, 4800.00, 'PENDING', 'ACTIVE', NOW()
);

-- Check signals were created
SELECT id, symbol, signal_type, entry_market_price, status, created_at 
FROM signals 
WHERE signal_status = 'ACTIVE' 
ORDER BY created_at DESC 
LIMIT 5;
```

#### Method 2: Use Signal Creation Script
I'll create a script for this:

### Phase 3: Test Automation Script

#### Test 3A: Dry Run (No Orders)
```bash
# Run automation with database connection but check logs only
# (Won't place orders if no triggers)
php signal_automation.php

# Check the output for:
# - Database connection success
# - Signal processing messages
# - Position sizing calculations
# - No order placement (if no triggers)
```

#### Test 3B: Test with Triggerable Signals
```sql
-- Create signals that WILL trigger based on current prices
-- For LONG signal: Set entry_market_price BELOW current price
INSERT INTO signals (
    symbol, signal_type, entry_market_price, entry_2, leverage, 
    take_profit_1, stop_loss, status, signal_status, created_at
) VALUES (
    'BTC-USDT', 'LONG', 50000.00, 45000.00, 2,
    120000.00, 45000.00, 'PENDING', 'ACTIVE', NOW()
);

-- For SHORT signal: Set entry_market_price ABOVE current price  
INSERT INTO signals (
    symbol, signal_type, entry_market_price, entry_2, leverage,
    take_profit_1, stop_loss, status, signal_status, created_at
) VALUES (
    'ETH-USDT', 'SHORT', 5000.00, 5200.00, 3,
    3500.00, 5500.00, 'PENDING', 'ACTIVE', NOW()
);
```

#### Test 3C: Run Automation
```bash
# Run the automation - should trigger the test signals
php signal_automation.php

# Monitor the output for:
# - Signal trigger detection
# - Position sizing calculation
# - Order placement attempts (in demo mode)
# - Status updates to FILLED
```

### Phase 4: Verify Results

#### Check Database Updates
```sql
-- Check if signals were updated to FILLED
SELECT id, symbol, signal_type, entry_market_price, status, updated_at 
FROM signals 
WHERE status = 'FILLED' 
ORDER BY updated_at DESC;

-- Check recent activity
SELECT id, symbol, signal_type, status, created_at, updated_at
FROM signals 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
ORDER BY updated_at DESC;
```

#### Check BingX Demo Account
1. Log into BingX demo account
2. Check "Orders" section for new positions
3. Verify position sizes match calculations
4. Check if leverage was set correctly

#### Check Logs
```bash
# Check PHP error logs for any issues
tail -f /path/to/php/error.log

# Check automation logs
grep "Signal Automation" /path/to/php/error.log | tail -20
```

## Safety Verifications

### 1. Confirm Demo Mode Throughout Testing
```bash
# Run this before ANY testing
php -r "
require_once 'api/api_helper.php';
loadEnv('.env');
if (!isDemoMode()) {
    echo 'ERROR: NOT IN DEMO MODE! STOP TESTING!' . PHP_EOL;
    exit(1);
} else {
    echo 'SUCCESS: Demo mode confirmed' . PHP_EOL;
    echo 'Demo API URL: ' . getBingXApiUrl() . PHP_EOL;
}
"
```

### 2. Monitor Demo Balance Changes
```bash
# Before testing - record initial balance
php -r "
include 'signal_automation.php';
echo 'Initial Demo Balance: ' . getTotalAssets() . ' VST' . PHP_EOL;
"

# After testing - check balance changes
php -r "
include 'signal_automation.php';
echo 'Final Demo Balance: ' . getTotalAssets() . ' VST' . PHP_EOL;
"
```

## Expected Test Results

### Successful Test Indicators:
- ✅ Scripts run without PHP errors
- ✅ Database connections successful
- ✅ Price fetching works for BTC-USDT, ETH-USDT
- ✅ Position sizing calculations show in logs
- ✅ PENDING signals change to FILLED when triggered
- ✅ New positions appear in BingX demo account
- ✅ Position sizes respect the MIN(AUTO_MARGIN_PER_ENTRY, 5% assets) rule
- ✅ All activity uses VST (demo currency), not USDT

### Warning Signs:
- ❌ Any mention of LIVE mode during testing
- ❌ USDT currency instead of VST in API responses
- ❌ PHP errors or database connection failures
- ❌ No position size calculations in logs
- ❌ Signals not updating from PENDING to FILLED

## Cleanup After Testing

```sql
-- Remove test signals (optional)
DELETE FROM signals 
WHERE created_at >= '2025-09-11 00:00:00' 
AND (notes LIKE '%test%' OR symbol IN ('BTC-USDT', 'ETH-USDT'));

-- Reset automation settings if changed
UPDATE signal_automation_settings 
SET setting_value = '50.00' 
WHERE setting_key = 'AUTO_MARGIN_PER_ENTRY';
```

## Troubleshooting Common Issues

### Issue: "Database connection failed"
```bash
# Check .env database credentials
grep "DB_" .env

# Test MySQL connection directly
mysql -u your_user -p -e "SELECT 1;"
```

### Issue: "API credentials not configured"
```bash
# Check BingX API keys in .env
grep "BINGX_" .env

# Ensure they're demo API keys, not live
```

### Issue: "No signals found to process"
```sql
-- Check if signals exist
SELECT COUNT(*) as total_signals FROM signals;
SELECT COUNT(*) as pending_signals FROM signals WHERE status = 'PENDING';
```

### Issue: Position not appearing in BingX
- Verify you're checking the demo account, not live
- Check if order placement actually succeeded in logs
- Verify symbol format (BTC-USDT vs BTCUSDT)

## Next Steps After Successful Testing

1. **Monitor for 1-2 hours** to see entry2 triggers work
2. **Test different signal types** (LONG/SHORT)
3. **Test with different position sizes** by adjusting AUTO_MARGIN_PER_ENTRY
4. **Set up monitoring** for production use
5. **Configure Telegram notifications** (optional)
6. **Set up cron job** when ready for automation

## Production Deployment Checklist

Only after successful demo testing:

- [ ] All tests pass in demo mode
- [ ] Position sizing works correctly
- [ ] Order placement successful in demo
- [ ] Database updates working
- [ ] Logging is comprehensive
- [ ] Switch to TRADING_MODE=live (when ready)
- [ ] Set up cron job for automation
- [ ] Configure monitoring and alerts