# Signal Automation Setup Guide

## Overview
The `signal_automation.php` script automatically processes trading signals based on market conditions. It handles:

1. **PENDING Signals**: Monitors entry prices and triggers positions when conditions are met
2. **FILLED Signals**: Monitors entry2 prices for additional position entries

## How It Works

### PENDING Signal Processing
- Fetches all signals with status='PENDING' and signal_status='ACTIVE'
- Gets current market price from BingX API
- For LONG signals: Triggers when current price >= entry_market_price
- For SHORT signals: Triggers when current price <= entry_market_price
- Places market order on BingX when triggered
- Updates signal status to 'FILLED'
- Sends Telegram notification (if configured)

### FILLED Signal Processing (Entry2)
- Fetches all signals with status='FILLED' that have entry_2 price set
- Gets current market price from BingX API  
- For LONG signals: Triggers entry2 when current price <= entry_2
- For SHORT signals: Triggers entry2 when current price >= entry_2
- Places additional market order with same size
- Updates signal status to 'ENTRY2'
- Sends Telegram notification (if configured)

## Position Sizing
The automation uses a **hybrid position sizing strategy** that takes the minimum of:

1. **AUTO_MARGIN_PER_ENTRY** setting from signal_automation_settings table (default: 50.00 USDT)
2. **5% of total account assets** (including used margin)

### Position Size Formula:
```
Position Size = MIN(AUTO_MARGIN_PER_ENTRY, Total Assets × 0.05)
```

### Examples:
- If AUTO_MARGIN_PER_ENTRY = 100 USDT and Total Assets = 1000 USDT:
  - 5% of assets = 50 USDT
  - Position Size = MIN(100, 50) = **50 USDT**
  
- If AUTO_MARGIN_PER_ENTRY = 30 USDT and Total Assets = 1000 USDT:
  - 5% of assets = 50 USDT  
  - Position Size = MIN(30, 50) = **30 USDT**

### Configuration:
```sql
-- Set maximum position size per entry
UPDATE signal_automation_settings 
SET setting_value = '100.00' 
WHERE setting_key = 'AUTO_MARGIN_PER_ENTRY';
```

This ensures positions never exceed 5% of total assets, providing automatic risk management.

## Cronjob Setup

### Linux/Mac Setup
```bash
# Edit crontab
crontab -e

# Add this line to run every minute
* * * * * /usr/bin/php /path/to/your/signal_automation.php >> /path/to/logs/signal_automation.log 2>&1

# Example with full paths:
* * * * * /usr/bin/php /var/www/html/trade/signal_automation.php >> /var/log/signal_automation.log 2>&1
```

### Windows Setup (Task Scheduler)
1. Open Task Scheduler
2. Create Basic Task
3. Set trigger: Daily, repeat every 1 minute
4. Action: Start a program
5. Program: `C:\xampp\php\php.exe`
6. Arguments: `C:\xampp\htdocs\trade\signal_automation.php`
7. Start in: `C:\xampp\htdocs\trade`

### Manual Testing
```bash
# Test the script manually
php signal_automation.php

# Test with web browser (add ?run=1)
https://yourdomain.com/signal_automation.php?run=1

# Test logic without database
php test_signal_automation.php
```

## Required Environment Variables
```env
# Database connection
DB_HOST=localhost
DB_USER=your_db_user
DB_PASSWORD=your_db_password
DB_NAME=crypto_trading

# BingX API credentials
BINGX_API_KEY=your_api_key
BINGX_SECRET_KEY=your_secret_key

# Trading mode
TRADING_MODE=demo  # or 'live'

# Optional: Telegram notifications
TELEGRAM_BOT_TOKEN_NOTIF=your_bot_token
TELEGRAM_CHAT_ID_NOTIF=your_chat_id
```

## Database Configuration
Position sizing and other automation settings are stored in the `signal_automation_settings` table:

```sql
-- View current settings
SELECT * FROM signal_automation_settings;

-- Update position size (default 50.00 USDT)
UPDATE signal_automation_settings 
SET setting_value = '100.00' 
WHERE setting_key = 'AUTO_MARGIN_PER_ENTRY';

-- Other important settings
UPDATE signal_automation_settings 
SET setting_value = 'true' 
WHERE setting_key = 'AUTO_SIGNAL_CREATION_ENABLED';
```

## Signal Table Status Flow
```
PENDING → FILLED → ENTRY2
```

- **PENDING**: Signal created, waiting for entry price trigger
- **FILLED**: Entry position opened, monitoring for entry2 trigger  
- **ENTRY2**: Additional entry position opened

## Logging
The script logs all activity to:
- Console output
- PHP error log
- Optional: Custom log file (modify script as needed)

Log entries include:
- Signal processing details
- Price comparisons
- Order placement results
- Error messages
- Telegram notifications

## Safety Features
- Validates API credentials before trading
- Automatic risk management via hybrid position sizing
- Never exceeds 5% of total assets per position
- Handles API errors gracefully
- Skips signals with invalid data
- Respects trading mode (demo/live)
- Comprehensive logging of all calculations

## Monitoring
Monitor the automation by:
1. Checking log files regularly
2. Verifying signal status updates in database
3. Monitoring BingX account for new positions
4. Setting up Telegram notifications for alerts

## Troubleshooting

### Common Issues
1. **Database connection failed**: Check DB credentials in .env
2. **API errors**: Verify BingX API keys and permissions
3. **No price data**: Check symbol format and BingX API status
4. **Orders not placing**: Verify account balance and leverage settings

### Debug Steps
```bash
# Test database connection
php api/test_db.php

# Test BingX API connection
php api/get_price.php?symbol=BTC-USDT

# Test balance retrieval
php api/get_balance.php

# Test automation logic
php test_signal_automation.php
```

## Security Notes
- Never expose .env file publicly
- Use secure file permissions (600) for .env
- Monitor API key usage and limits
- Use demo mode for testing
- Review all signals before automation goes live