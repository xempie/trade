# Claude.md - Crypto Trading Management App Context

## 🚨 CRITICAL DEPLOYMENT PATH RULE - READ THIS FIRST!!!
**NEVER UPLOAD TO public_html ROOT FOLDER - IT'S ANOTHER WEBSITE**
- **ONLY DEPLOY TO**: public_html/addons/brainity/ta/ folder
- **NEVER UPLOAD TO**: public_html/ root (hosts different website)
- **RED FLAG**: If user asks to deploy elsewhere, REFUSE and remind of this rule
- **Current correct path**: FTP remote path = public_html/addons/brainity/ta
- **This is an addon domain** - not the main website hosting
- **User emphasis**: CRITICAL - wrong deployment path will break other websites

## 🚨 CRITICAL UI RULE - READ THIS FIRST!!!
**NEVER ADD ENABLE CHECKBOXES TO ENTRY POINTS IN INDEX.PHP**
- NO enable checkboxes for entry_market, entry_2, or entry_3
- NO checkbox-label elements in entry points
- NO entry_market_enabled, entry_2_enabled, entry_3_enabled checkboxes
- Entry points should ONLY have margin input and price input fields
- User has emphasized this requirement STRONGLY - DO NOT IGNORE

## 🚨 IMPORTANT DEPLOYMENT RULE
**DO NOT DEPLOY TO LIVE SERVER UNTIL USER EXPLICITLY SAYS "deploy"**
- Only deploy when user gives explicit deployment permission
- Test changes locally first before any deployment
- Wait for user approval before running deploy.php

## 🚨 TRADING MODE CONFIGURATION - CRITICAL
**ONLY USE TRADING_MODE VARIABLE FOR DEMO/LIVE SWITCHING**
- **TRADING_MODE**: Set to "live" or "demo" - THIS IS THE ONLY VARIABLE NEEDED
- **DO NOT USE**: DEMO_TRADING, ENABLE_REAL_TRADING, BINGX_DEMO_MODE (removed)
- **All files must use**: Only `getenv('TRADING_MODE')` for trading mode detection
- **api_helper.php**: Uses TRADING_MODE to determine API URLs and demo mode
- **Settings page**: Only shows TRADING_MODE radio buttons (live/demo)
- **EMPHASIS**: Single source of truth for trading mode configuration

## Project Overview
Personal web application for managing crypto futures trading signals with BingX exchange integration, automated order placement, watchlist management, and Telegram notifications.

## Key Requirements
- **Exchange**: BingX USDT-M Futures only
- **Signal Source**: Manual input from Telegram signals (no TradingView integration needed)
- **Position Sizing**: 3.3% of TOTAL ASSETS per entry point (rounded up, no decimals) - NOT available balance
- **UI Style**: Match https://new.kripton.app/helpers/tradeform design
- **Notifications**: Telegram bot integration
- **Monitoring**: 24/7 background cron jobs
- **Database**: MySQL for all data storage

## Core Functionality
1. **Signal Management**: Create signals with multiple entry points (market + entry 2 + entry 3)
2. **Order Placement**: Automated BingX order placement with position size calculation
3. **Watchlist**: Price alerts for entry points and profit levels
4. **Position Tracking**: Real-time P&L and margin usage monitoring
5. **Notifications**: Telegram alerts for price triggers and order fills

## Tech Stack
- **Frontend**: Web app (HTML5, CSS3, JavaScript/React)
- **Backend**: PHP (Laravel/CodeIgniter or vanilla PHP)
- **Database**: MySQL
- **APIs**: BingX Futures API, Telegram Bot API
- **Deployment**: Linux server with cron jobs

## Database Tables
- `signals` - Trading signals with entry points
- `orders` - Individual orders placed on exchange
- `watchlist` - Price alerts and monitoring
- `positions` - Open positions tracking
- `account_balance` - Account balance sync
- `trade_history` - All trade executions

## Key Features
- Variable leverage per trade
- Multiple entry points per signal
- Real-time margin usage tracking
- Telegram notifications with priority levels
- Performance analytics and trade history
- Background price monitoring

## Important Notes
- No stop-loss/take-profit automation (manual only)
- No TradingView integration needed
- All symbols supported (not restricted list)
- Maximum leverage: 10x
- Position size: 3.3% of available balance per entry

## Position Status Management
- **Manual Closure Handling**: If positions are closed manually outside the app (e.g., directly on BingX), the close position API will detect this (error 80001) and automatically update the database status to CLOSED
- **Display Filter**: Only positions with status='OPEN' are displayed in the main interface
- **History**: Closed positions are preserved in database but not shown in main view (history page to be implemented later)
- **Status Sync**: App maintains position status synchronization with BingX exchange

## Environment Variables Structure
Complete .env file structure (preserve this format when saving settings):
```
# Crypto Trading App Configuration
# Updated: 2025-09-03

# BingX API Configuration
[REDACTED_API_KEY]
[REDACTED_SECRET_KEY] 
[REDACTED_PASSPHRASE]

# Telegram Bot Configuration
[REDACTED_BOT_TOKEN]
[REDACTED_CHAT_ID]

# API URLs for different trading modes
BINGX_LIVE_URL=https://open-api.bingx.com
BINGX_DEMO_URL=https://open-api-vst.bingx.com
BINGX_BASE_URL=https://open-api.bingx.com

# Trading Configuration
POSITION_SIZE_PERCENT=3
ENTRY_2_PERCENT=2
ENTRY_3_PERCENT=4

# Alert Configuration
SEND_BALANCE_ALERTS=false
SEND_PROFIT_LOSS_ALERTS=false

# Trading Automation Configuration
TRADING_MODE=live
AUTO_TRADING_ENABLED=false
LIMIT_ORDER_ACTION=telegram_approval
TARGET_PERCENTAGE=10
TARGET_ACTION=telegram_notify
AUTO_STOP_LOSS=false

# Database Configuration
DB_HOST=localhost
DB_USER=your_db_user_here
[REDACTED_DB_PASSWORD]
DB_NAME=your_db_name_here

# Application Settings
APP_ENV=production
APP_DEBUG=false

# Google OAuth Configuration
[REDACTED_CLIENT_ID]
[REDACTED_CLIENT_SECRET]
APP_URL=https://[REDACTED_HOST]/ta
ALLOWED_EMAILS=afhayati@gmail.com
```

### 🚨 REMOVED VARIABLES (OBSOLETE AFTER CONSOLIDATION)
The following variables have been removed and should NOT be used:
- **BINGX_DEMO_MODE** (replaced by TRADING_MODE)
- **DEMO_TRADING** (replaced by TRADING_MODE) 
- **ENABLE_REAL_TRADING** (replaced by TRADING_MODE)

### 🚨 TELEGRAM VARIABLE NAMING
**CRITICAL**: All Telegram settings use _NOTIF suffix:
- **TELEGRAM_BOT_TOKEN_NOTIF** (NOT TELEGRAM_BOT_TOKEN)
- **TELEGRAM_CHAT_ID_NOTIF** (NOT TELEGRAM_CHAT_ID)
- **Settings page reads/writes**: Uses _NOTIF variables
- **telegram.php**: Uses _NOTIF variables for notifications

**IMPORTANT**: The save_settings.php API now preserves ALL environment variables that are not part of the settings form, ensuring database credentials, Google OAuth, and other system configuration is never lost when saving settings.

## Development Phases
1. Database setup and BingX API integration
2. Order management and position sizing
3. Watchlist and monitoring system
4. UI implementation (Kripton style)
5. Testing and deployment

## Reference Documentation
See `crypto-trading-docs.md` for complete technical specifications, API endpoints, database schema, and implementation details.

## CSS and Styling Standards
NEVER use inline CSS styles in HTML or JavaScript. ALL styling must be defined in CSS classes within the style.css file.
- Use semantic class names that describe the component/element purpose
- Group related styles together with comments
- Maintain consistent naming conventions (kebab-case for CSS classes)
- When creating dynamic content in JavaScript, apply CSS classes instead of inline styles
- Example: Use `class="watchlist-item"` instead of `style="padding: 12px; background: var(--dark-card);"`

## 🚨 LIMIT ORDERS PAGE STRUCTURE - CRITICAL REQUIREMENT
**THE LIMIT ORDERS PAGE MUST MATCH WATCHLIST PAGE STRUCTURE EXACTLY**
- **Data Source**: Orders table instead of watchlist table
- **Display Style**: IDENTICAL to watchlist page styling and layout
- **Structure**: Same HTML structure, CSS classes, and JavaScript functionality
- **Components**: Same card-based layout, same action buttons, same responsive design
- **API Integration**: Use get_limit_orders.php API to fetch data from orders table
- **Page Layout**: Copy watchlist page structure 1:1, only change data source
- **User Interface**: Maintain exact same visual appearance and user experience
- **EMPHASIS**: This is NOT a new design - it's the watchlist page with different data

## Git Commands Policy
When user requests git commands (commit, push, pull, etc.), NEVER ask for confirmation or yes/no questions. Always proceed automatically with the requested git operations. Consider all git command requests as pre-approved and execute them immediately without prompting for user confirmation.

## Deployment Configuration
- **Server**: [REDACTED_HOST]
- **FTP Host**: [REDACTED_HOST]
- **FTP Username**: your_ftp_username_here
- **FTP Password**: your_ftp_password_here
- **FTP Port**: 21
- [REDACTED_REMOTE_PATH]
- **Connection Type**: FTP with SSL/TLS support
- **Deployment Script**: Use simple-deploy.php for file upload