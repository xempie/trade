# Claude.md - Crypto Trading Management App Context

## 🚨 IMPORTANT DEPLOYMENT RULE
**DO NOT DEPLOY TO LIVE SERVER UNTIL USER EXPLICITLY SAYS "deploy"**
- Only deploy when user gives explicit deployment permission
- Test changes locally first before any deployment
- Wait for user approval before running deploy.php

## Project Overview
Personal web application for managing crypto futures trading signals with BingX exchange integration, automated order placement, watchlist management, and Telegram notifications.

## Key Requirements
- **Exchange**: BingX USDT-M Futures only
- **Signal Source**: Manual input from Telegram signals (no TradingView integration needed)
- **Position Sizing**: 3.3% of available funds per entry point (rounded up, no decimals)
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

## Environment Variables Needed
```
[REDACTED_API_KEY]
[REDACTED_SECRET_KEY] 
[REDACTED_PASSPHRASE]
[REDACTED_BOT_TOKEN]
[REDACTED_CHAT_ID]
DB_HOST=localhost
DB_USER=
[REDACTED_DB_PASSWORD]
DB_NAME=crypto_trading
```

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

## Git Commands Policy
When user requests git commands (commit, push, pull, etc.), NEVER ask for confirmation or yes/no questions. Always proceed automatically with the requested git operations. Consider all git command requests as pre-approved and execute them immediately without prompting for user confirmation.