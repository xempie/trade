# Crypto Trading Management - Cron Jobs Documentation

## Overview

This document provides complete information about the automated background monitoring system (cron jobs) for the crypto trading management application. The cron jobs handle 24/7 monitoring, price alerts, position tracking, and Telegram notifications.

---

## 📋 Table of Contents

1. [Cron Job Scripts](#cron-job-scripts)
2. [Notification Types](#notification-types)
3. [Installation & Setup](#installation--setup)
4. [Configuration](#configuration)
5. [Testing & Validation](#testing--validation)
6. [Troubleshooting](#troubleshooting)
7. [Log Management](#log-management)
8. [Maintenance](#maintenance)

---

## 🤖 Cron Job Scripts

### 1. Price Monitor (`price-monitor.php`)
- **Frequency**: Every minute (`* * * * *`)
- **Purpose**: Monitor watchlist items and trigger price alerts
- **Notifications**: High priority Telegram alerts when price targets are reached

**What it does:**
- Fetches current prices from BingX API
- Compares against watchlist target prices
- Triggers alerts for entry_2 and entry_3 levels
- Marks triggered watchlist items as completed
- Sends detailed Telegram notifications

**Notification Format:**
```
🎯 Price Alert Triggered!

📈 BTC (ENTRY_2)
🎯 Target: $45000
💰 Current: $44950
📊 Direction: LONG
💵 Margin: $100.00
```

### 2. Order Status Monitor (`order-status.php`)
- **Frequency**: Every 2 minutes (`*/2 * * * *`)
- **Purpose**: Check pending order status and notify on fills/cancellations
- **Notifications**: High priority for fills, medium for cancellations

**What it does:**
- Queries BingX API for order status updates
- Updates local database with fill prices and times
- Creates position records for filled market orders
- Sends immediate notifications for order events

**Notification Format:**
```
✅ Order Filled!

📈 BTC (MARKET)
💰 Size: $100.00
💵 Fill Price: $45000
⚡ Leverage: 10x
🎯 Side: BUY
```

### 3. Position Sync (`position-sync.php`)
- **Frequency**: Every 5 minutes (`*/5 * * * *`)
- **Purpose**: Sync position P&L and monitor profit/loss milestones
- **Notifications**: Medium priority for milestones

**What it does:**
- Fetches current positions from BingX
- Updates unrealized P&L in local database
- Checks for profit/loss milestones (10%, 25%, 50%)
- Sends milestone achievement notifications

**Notification Format:**
```
💰 PROFIT Milestone Reached!

💰 BTC (LONG)
🎯 Milestone: 25%
📊 Current P&L: 27.5%
💵 P&L Amount: $275.00
```

### 4. Balance Sync (`balance-sync.php`)
- **Frequency**: Every 15 minutes (`*/15 * * * *`)
- **Purpose**: Monitor account balance and detect significant changes
- **Notifications**: Medium priority for balance changes

**What it does:**
- Fetches account balance from BingX
- Updates local balance records
- Detects significant balance changes (>5%)
- Monitors margin usage and available funds

**Notification Format:**
```
💰 Balance Change Alert

📈 Account balance has increased
📊 Change: 8.5%
💰 From: $1000.00
💰 To: $1085.00
📊 Difference: $85.00
```

---

## 🔔 Notification Types

### Priority Levels
- **HIGH** 🚨 - Order fills, critical price alerts, errors
- **MEDIUM** 💰 - P&L milestones, balance changes, order cancellations
- **LOW** ℹ️ - System status, maintenance notifications

### Notification Categories

#### Price Alerts
- Entry point triggers (entry_2, entry_3)
- Direction-based alerts (LONG/SHORT)
- Target vs current price comparison

#### Trading Events
- Order filled confirmations
- Order cancellation notices
- Position opening/closing alerts

#### Performance Tracking
- Profit milestone achievements
- Loss threshold warnings
- Balance change notifications

#### System Monitoring
- API connection status
- Database sync confirmations
- Error and warning alerts

---

## 🚀 Installation & Setup

### Linux/Production Server

1. **Upload Files**
   ```bash
   # Upload all files to your server
   scp -r jobs/ user@server:/var/www/html/trade/
   scp crontab-config.txt user@server:/tmp/
   ```

2. **Set Permissions**
   ```bash
   chmod +x /var/www/html/trade/jobs/*.php
   chown www-data:www-data /var/www/html/trade/jobs/*.php
   ```

3. **Create Log Directory**
   ```bash
   mkdir -p /var/log/crypto-trading
   chown www-data:www-data /var/log/crypto-trading
   chmod 755 /var/log/crypto-trading
   ```

4. **Update Paths**
   ```bash
   # Edit crontab-config.txt to match your paths
   nano /tmp/crontab-config.txt
   
   # Update these paths:
   # - /var/www/html/trade (your project path)
   # - /usr/bin/php (your PHP path)
   ```

5. **Install Crontab**
   ```bash
   crontab /tmp/crontab-config.txt
   crontab -l  # Verify installation
   ```

### Windows/XAMPP Development

1. **Manual Testing**
   ```cmd
   run-cronjobs-windows.bat
   ```

2. **Windows Task Scheduler Setup**
   - Open Task Scheduler
   - Create Basic Task for each script
   - Set appropriate schedules
   - Use command: `C:\xampp\php\php.exe C:\xampp\htdocs\trade\jobs\script-name.php`

3. **Batch File Automation**
   ```cmd
   # Schedule run-cronjobs-windows.bat to run every few minutes
   # Or create individual scheduled tasks
   ```

---

## ⚙️ Configuration

### Environment Variables Required

Create/update `.env` file with:

```env
# BingX API (Required)
BINGX_API_KEY=your_api_key_here
BINGX_SECRET_KEY=your_secret_key_here

# Database (Required)
DB_HOST=localhost
DB_NAME=crypto_trading
DB_USER=your_db_user
DB_PASSWORD=your_db_password

# Telegram (Optional but recommended)
TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_CHAT_ID=your_chat_id
```

### Telegram Bot Setup

1. **Create Bot**
   - Message @BotFather on Telegram
   - Send `/newbot` command
   - Follow instructions to create bot
   - Save the bot token

2. **Get Chat ID**
   - Start conversation with your bot
   - Send a message to the bot
   - Visit: `https://api.telegram.org/bot<BOT_TOKEN>/getUpdates`
   - Find your chat ID in the response

3. **Test Notifications**
   ```bash
   php setup-cronjobs.php test
   ```

### Database Tables

Ensure these tables exist:
- `signals` - Trading signals
- `orders` - Order records
- `watchlist` - Price alerts
- `positions` - Open positions
- `account_balance` - Balance history

---

## 🧪 Testing & Validation

### Validation Script

```bash
php setup-cronjobs.php validate
```

**Checks:**
- ✅ .env file exists and contains required variables
- ✅ Database connection successful
- ✅ Required tables exist
- ✅ Cronjob scripts exist and are executable
- ✅ BingX API credentials valid
- ✅ Telegram bot configuration

### Testing Scripts

```bash
# Test all cronjob scripts
php setup-cronjobs.php test

# Test individual scripts
php jobs/price-monitor.php
php jobs/order-status.php
php jobs/position-sync.php
php jobs/balance-sync.php
```

### Manual Testing Commands

```bash
# Check crontab is installed
crontab -l

# Check if cron service is running
systemctl status cron  # Ubuntu/Debian
systemctl status crond  # CentOS/RHEL

# Test script permissions
ls -la jobs/
```

---

## 🐛 Troubleshooting

### Common Issues

#### 1. Scripts Not Running
```bash
# Check cron service
systemctl status cron

# Check crontab entries
crontab -l

# Check script permissions
chmod +x jobs/*.php
```

#### 2. Permission Errors
```bash
# Fix ownership
chown www-data:www-data jobs/*.php

# Check PHP path
which php
# Update crontab with correct PHP path
```

#### 3. Database Connection Errors
```bash
# Test database connection
php -r "
$pdo = new PDO('mysql:host=localhost;dbname=crypto_trading', 'user', 'pass');
echo 'Connection successful';
"
```

#### 4. API Connection Issues
```bash
# Test BingX API
curl -H "X-BX-APIKEY: your_key" "https://open-api.bingx.com/openApi/swap/v2/user/balance"
```

#### 5. Telegram Notifications Not Working
```bash
# Test bot token
curl "https://api.telegram.org/bot<BOT_TOKEN>/getMe"

# Test sending message
curl -X POST "https://api.telegram.org/bot<BOT_TOKEN>/sendMessage" \
  -d "chat_id=<CHAT_ID>&text=Test message"
```

### Log Analysis

```bash
# View recent logs
tail -f /var/log/crypto-trading/price-monitor.log
tail -f /var/log/crypto-trading/order-status.log
tail -f /var/log/crypto-trading/position-sync.log
tail -f /var/log/crypto-trading/balance-sync.log

# Search for errors
grep -i error /var/log/crypto-trading/*.log
grep -i failed /var/log/crypto-trading/*.log

# Check disk space
df -h /var/log/
```

---

## 📊 Log Management

### Log Locations

**Linux/Production:**
- `/var/log/crypto-trading/price-monitor.log`
- `/var/log/crypto-trading/order-status.log`
- `/var/log/crypto-trading/position-sync.log`
- `/var/log/crypto-trading/balance-sync.log`

**Windows/XAMPP:**
- `C:\xampp\htdocs\trade\logs\price-monitor.log`
- `C:\xampp\htdocs\trade\logs\order-status.log`
- `C:\xampp\htdocs\trade\logs\position-sync.log`
- `C:\xampp\htdocs\trade\logs\balance-sync.log`

### Log Rotation

Automatic cleanup (included in crontab):
```bash
# Weekly log cleanup (removes logs older than 7 days)
0 2 * * 0 find /var/log/crypto-trading/ -name "*.log" -mtime +7 -delete
```

Manual cleanup:
```bash
# Remove old logs
find /var/log/crypto-trading/ -name "*.log" -mtime +30 -delete

# Archive logs
tar -czf logs-backup-$(date +%Y%m%d).tar.gz /var/log/crypto-trading/*.log
```

---

## 🔧 Maintenance

### Regular Tasks

#### Daily
- Monitor notification delivery
- Check for error messages in logs
- Verify API connectivity

#### Weekly
- Review log files for patterns
- Check database growth
- Validate balance synchronization

#### Monthly
- Archive old logs
- Review and optimize notification frequency
- Update API credentials if needed
- Database cleanup of old records

### Performance Monitoring

```bash
# Check script execution times
grep "finished at" /var/log/crypto-trading/*.log | tail -20

# Monitor database connections
grep "Database connection" /var/log/crypto-trading/*.log

# Check API rate limits
grep "HTTP error" /var/log/crypto-trading/*.log
```

### Updating Scripts

1. **Backup current setup**
   ```bash
   cp -r jobs/ jobs-backup-$(date +%Y%m%d)/
   crontab -l > crontab-backup-$(date +%Y%m%d).txt
   ```

2. **Update scripts**
   ```bash
   # Upload new versions
   # Test individually
   php jobs/script-name.php
   ```

3. **Update crontab if needed**
   ```bash
   crontab -e
   ```

---

## 📞 Support & Contact

### Debug Information to Collect

When reporting issues, include:

1. **System Information**
   ```bash
   php --version
   crontab -l
   systemctl status cron
   ```

2. **Log Files**
   ```bash
   tail -50 /var/log/crypto-trading/*.log
   ```

3. **Configuration**
   ```bash
   ls -la jobs/
   cat .env  # Remove sensitive data
   ```

4. **Test Results**
   ```bash
   php setup-cronjobs.php validate
   php setup-cronjobs.php test
   ```

---

## 📄 File Reference

### Created Files Structure
```
trade/
├── jobs/
│   ├── price-monitor.php      # Price monitoring and alerts
│   ├── order-status.php       # Order status checking
│   ├── position-sync.php      # Position P&L synchronization
│   └── balance-sync.php       # Account balance monitoring
├── logs/                      # Log files (created automatically)
├── crontab-config.txt         # Linux crontab configuration
├── run-cronjobs-windows.bat   # Windows testing script
├── setup-cronjobs.php         # Validation and testing utility
└── CRONS.md                   # This documentation file
```

### Key Commands Summary

```bash
# Setup and validation
php setup-cronjobs.php validate
php setup-cronjobs.php test

# Manual script execution
php jobs/price-monitor.php
php jobs/order-status.php
php jobs/position-sync.php  
php jobs/balance-sync.php

# Crontab management
crontab crontab-config.txt    # Install
crontab -l                    # List
crontab -e                    # Edit

# Log monitoring
tail -f /var/log/crypto-trading/price-monitor.log
grep -i error /var/log/crypto-trading/*.log
```

---

*Last Updated: $(date +%Y-%m-%d)*
*Version: 1.0*