# Crypto Trading Management App - Complete Technical Documentation

## 1. Project Overview

### Purpose
Personal web application for managing crypto futures trading signals received via Telegram, with automated order placement on BingX exchange, watchlist management, and performance tracking.

### Key Features
- Signal-based order management with multiple entry points
- Automated position sizing (3.3% of available funds per entry)
- Real-time watchlist with price alerts
- Telegram bot notifications
- Performance tracking and historical data
- 24/7 background monitoring via cron jobs

---

## 2. Technical Architecture

### Tech Stack
- **Frontend**: Web-based (HTML5, CSS3, JavaScript/React/Vue.js)
- **Backend**: PHP (Laravel/CodeIgniter or vanilla PHP)
- **Database**: MySQL
- **Exchange Integration**: BingX USDT-M Futures API
- **Notifications**: Telegram Bot API
- **Deployment**: Linux server with cron jobs
- **UI Framework**: Custom CSS matching Kripton.app style

### System Architecture
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Web Frontend  │────│   Backend API   │────│   BingX API     │
│   (Kripton UI)  │    │   (Orders/Data) │    │   (Futures)     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                                │
                       ┌─────────────────┐    ┌─────────────────┐
                       │   MySQL DB      │    │  Telegram Bot   │
                       │   (History)     │    │  (Notifications)│
                       └─────────────────┘    └─────────────────┘
                                │
                       ┌─────────────────┐
                       │   Cron Jobs     │
                       │ (Monitoring)    │
                       └─────────────────┘
```

---

## 3. Database Schema

### Tables Structure

#### `signals` Table
```sql
CREATE TABLE signals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    symbol VARCHAR(20) NOT NULL,
    signal_type ENUM('LONG', 'SHORT') NOT NULL,
    entry_market_price DECIMAL(15,8),
    entry_2 DECIMAL(15,8),
    entry_3 DECIMAL(15,8),
    leverage INT DEFAULT 1,
    status VARCHAR(15) DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### `orders` Table
```sql
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    signal_id INT,
    bingx_order_id VARCHAR(50),
    symbol VARCHAR(20) NOT NULL,
    side ENUM('BUY', 'SELL') NOT NULL,
    type ENUM('MARKET', 'LIMIT') NOT NULL,
    entry_level ENUM('MARKET', 'ENTRY_2', 'ENTRY_3') NOT NULL,
    quantity DECIMAL(15,8) NOT NULL,
    price DECIMAL(15,8),
    leverage INT,
    status ENUM('NEW', 'FILLED', 'CANCELLED', 'PENDING') DEFAULT 'NEW',
    fill_price DECIMAL(15,8),
    fill_time TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (signal_id) REFERENCES signals(id)
);
```

#### `watchlist` Table
```sql
CREATE TABLE watchlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(10) NOT NULL,
    entry_price DECIMAL(18, 8) NOT NULL,
    entry_type ENUM('entry_2', 'entry_3') NOT NULL,
    direction ENUM('long', 'short') NOT NULL,
    margin_amount DECIMAL(18, 8) NOT NULL,
    percentage DECIMAL(8, 4) NULL COMMENT 'Percentage used for calculation',
    status ENUM('active', 'triggered', 'cancelled') DEFAULT 'active',
    triggered_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_symbol (symbol),
    INDEX idx_status (status),
    INDEX idx_entry_price (entry_price),
    INDEX idx_created_at (created_at)
);
```

**Purpose**: Store price alerts for entry points 2 and 3 with trading direction and margin info
**Key Features**:
- Each entry point (entry_2/entry_3) creates a separate watchlist record  
- Stores symbol, entry price, direction (long/short), and margin amount
- Tracks percentage used for price calculation
- Status management (active/triggered/cancelled)
- Support for notifications when price is touched
- Multiple records per symbol supported (one for entry_2, one for entry_3)

#### `positions` Table
```sql
CREATE TABLE positions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    symbol VARCHAR(20) NOT NULL,
    side ENUM('LONG', 'SHORT') NOT NULL,
    size DECIMAL(15,8) NOT NULL,
    entry_price DECIMAL(15,8) NOT NULL,
    leverage INT NOT NULL,
    unrealized_pnl DECIMAL(15,4) DEFAULT 0,
    margin_used DECIMAL(15,4) NOT NULL,
    signal_id INT,
    status ENUM('OPEN', 'CLOSED') DEFAULT 'OPEN',
    opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL,
    FOREIGN KEY (signal_id) REFERENCES signals(id)
);
```

#### `account_balance` Table
```sql
CREATE TABLE account_balance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    total_balance DECIMAL(15,4) NOT NULL,
    available_balance DECIMAL(15,4) NOT NULL,
    margin_used DECIMAL(15,4) NOT NULL,
    unrealized_pnl DECIMAL(15,4) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### `trade_history` Table
```sql
CREATE TABLE trade_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    symbol VARCHAR(20) NOT NULL,
    side ENUM('BUY', 'SELL') NOT NULL,
    quantity DECIMAL(15,8) NOT NULL,
    price DECIMAL(15,8) NOT NULL,
    commission DECIMAL(15,8) DEFAULT 0,
    realized_pnl DECIMAL(15,4) DEFAULT 0,
    signal_id INT,
    order_id INT,
    bingx_trade_id VARCHAR(50),
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (signal_id) REFERENCES signals(id),
    FOREIGN KEY (order_id) REFERENCES orders(id)
);
```

---

## 4. API Endpoints Specification

### Authentication
- **API Keys**: BingX API Key, Secret, Passphrase
- **Telegram**: Bot Token, Chat ID
- **Security**: Environment variables, encrypted storage

### Core API Routes

#### Signal Management
```
POST /api/signals
- Create new signal with entry points
- Body: { symbol, signal_type, entry_market_price, entry_2, entry_3, leverage }
- Response: Signal ID and created watchlist items

GET /api/signals
- List all active signals
- Query params: status, symbol, limit, offset

PUT /api/signals/:id
- Update signal status
- Body: { status, notes }

DELETE /api/signals/:id
- Cancel signal and related orders
```

#### Order Management
```
POST /api/orders
- Create new order for entry point
- Body: { signal_id, entry_level, custom_quantity?, leverage }
- Auto-calculates position size (3.3% of available funds)
- Places order on BingX exchange

GET /api/orders
- List orders with filters
- Query params: status, symbol, signal_id

PUT /api/orders/:id/cancel
- Cancel pending order

GET /api/orders/:id/status
- Check order status from BingX
```

#### Watchlist Management
```
POST /api/watchlist
- Add price alert
- Body: { symbol, target_price, condition, alert_type, priority }

GET /api/watchlist
- List watchlist items
- Query params: triggered, priority, alert_type

PUT /api/watchlist/:id
- Update watchlist item
- Body: { target_price, condition, priority }

DELETE /api/watchlist/:id
- Remove watchlist item
```

#### Account & Positions
```
GET /api/account/balance
- Get current account balance from BingX
- Updates local database

GET /api/positions
- List open positions
- Includes unrealized P&L

GET /api/positions/:symbol/pnl
- Get detailed P&L for specific position
```

#### Trading History
```
GET /api/history/trades
- Trade execution history
- Query params: symbol, date_from, date_to, limit

GET /api/history/performance
- Performance analytics
- Returns: total P&L, win rate, best/worst trades
```

---

## 5. Frontend Components Structure

### Main Layout (Kripton.app Style)
- **Header**: Account balance, total margin usage
- **Sidebar**: Navigation (Signals, Orders, Watchlist, History)
- **Main Content**: Dynamic component rendering
- **Theme**: Dark theme matching Kripton.app design

### Components

#### 1. Signal Creation Form
```
Components:
- Symbol input with search/dropdown
- Signal type toggle (LONG/SHORT)
- Entry points inputs (Market, Entry 2, Entry 3)
- Leverage slider/input
- Position size calculator (shows 3.3% calculation)
- Submit button
```

#### 2. Orders Dashboard
```
Components:
- Active orders table (Symbol, Side, Entry Level, Quantity, Price, Status)
- Quick actions (Cancel, Modify)
- Order status indicators (Pending, Filled, Cancelled)
- Real-time updates via WebSocket/polling
```

#### 3. Watchlist Panel
```
Components:
- Watchlist table (Symbol, Target Price, Current Price, Condition, Priority)
- Add watchlist item form
- Alert status indicators
- Price change percentages
- Quick edit functionality
```

#### 4. Positions Monitor
```
Components:
- Open positions table (Symbol, Side, Size, Entry, Current Price, PnL, Margin)
- Real-time P&L updates
- Position actions (manual close if needed)
- Margin usage chart/indicator
```

#### 5. Performance Dashboard
```
Components:
- P&L charts (daily, weekly, monthly)
- Trade statistics (win rate, avg profit/loss)
- Recent trade history table
- Performance metrics cards
```

---

## 6. BingX API Integration

### Required Endpoints
```
Account Information:
- GET /openApi/swap/v2/user/balance
- GET /openApi/swap/v2/user/positions

Order Management:
- POST /openApi/swap/v2/trade/order (Place order)
- GET /openApi/swap/v2/trade/openOrders
- DELETE /openApi/swap/v2/trade/order (Cancel order)
- GET /openApi/swap/v2/trade/order (Order details)

Market Data:
- GET /openApi/swap/v2/quote/ticker (Price data)
- WebSocket for real-time price updates
```

### Position Size Calculation Logic
```php
function calculatePositionSize($availableBalance, $riskPercentage = 3.3) {
    $riskAmount = $availableBalance * ($riskPercentage / 100);
    $positionSize = ceil($riskAmount); // Round up, no decimals
    return $positionSize;
}

// Example:
// Available balance: $1,000
// Risk: 3.3%
// Position size: ceil(1000 * 0.033) = $33
```

---

## 7. Telegram Bot Integration

### Bot Setup
- Create bot via @BotFather
- Store bot token in environment variables
- Get chat ID for notifications

### Notification Types
```javascript
const NotificationTemplates = {
    WATCHLIST_TRIGGERED: `🎯 Price Alert: ${symbol} reached ${price}`,
    ORDER_FILLED: `✅ Order Filled: ${side} ${symbol} at ${price}`,
    PROFIT_ALERT: `💰 Profit Alert: ${symbol} P&L: ${pnl}%`,
    ERROR_ALERT: `⚠️ Error: ${errorMessage}`,
    POSITION_OPENED: `📈 Position Opened: ${side} ${symbol} Size: ${size}`
};
```

### Priority Levels
- **HIGH**: Immediate order fills, errors
- **MEDIUM**: Watchlist triggers, position updates
- **LOW**: General system notifications

---

## 8. Background Monitoring (Cron Jobs)

### Cron Schedule
```bash
# Price monitoring - every minute
* * * * * /usr/bin/php /var/www/html/jobs/price-monitor.php

# Position updates - every 5 minutes  
*/5 * * * * /usr/bin/php /var/www/html/jobs/position-sync.php

# Account balance sync - every 15 minutes
*/15 * * * * /usr/bin/php /var/www/html/jobs/balance-sync.php

# Order status check - every 2 minutes
*/2 * * * * /usr/bin/php /var/www/html/jobs/order-status.php
```

### Monitoring Scripts

#### Price Monitor (price-monitor.php)
```php
<?php
// Check all watchlist items
// Compare current prices with target prices
// Trigger notifications for matched conditions
// Update database with triggered status
?>
```

#### Position Sync (position-sync.php)
```php
<?php
// Fetch current positions from BingX
// Update unrealized P&L
// Check profit/loss alert thresholds
// Send profit alerts if configured
?>
```

#### Balance Sync (balance-sync.php)
```php
<?php
// Update account balance from BingX
// Calculate available funds for new positions
// Update margin usage statistics
?>
```

---

## 9. Configuration & Environment

### Environment Variables
```
# BingX API
[REDACTED_API_KEY]
[REDACTED_SECRET_KEY] 
[REDACTED_PASSPHRASE]
BINGX_BASE_URL=https://open-api.bingx.com

# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=crypto_trading
DB_USER=your_db_user
[REDACTED_DB_PASSWORD]

# Telegram
[REDACTED_BOT_TOKEN]
[REDACTED_CHAT_ID]

# Application
PORT=3000
NODE_ENV=production
RISK_PERCENTAGE=3.3
```

### Configuration File (config.json)
```json
{
    "trading": {
        "defaultRiskPercentage": 3.3,
        "maxLeverage": 10,
        "supportedSymbols": "ALL",
        "orderTimeout": 300000
    },
    "notifications": {
        "enableTelegram": true,
        "retryAttempts": 3,
        "priorityLevels": ["HIGH", "MEDIUM", "LOW"]
    },
    "monitoring": {
        "priceCheckInterval": 60000,
        "positionSyncInterval": 300000,
        "balanceSyncInterval": 900000
    }
}
```

---

## 10. Security & Error Handling

### Security Measures
- API keys stored in environment variables
- Input validation and sanitization
- Rate limiting for API calls
- SQL injection prevention
- HTTPS only for production

### Error Handling
- Exchange API failures
- Network connectivity issues
- Invalid order parameters
- Insufficient balance scenarios
- Database connection errors

### Logging
- Order execution logs
- Error tracking
- API call monitoring
- Performance metrics
- User activity logs

---

## 11. Development Phases

### Phase 1: Core Infrastructure
1. Database setup and migrations
2. BingX API integration
3. Basic order placement functionality
4. Account balance synchronization

### Phase 2: Order Management
1. Signal creation interface
2. Multiple entry point handling
3. Position size calculation
4. Order status monitoring

### Phase 3: Watchlist & Monitoring
1. Watchlist management
2. Price alert system
3. Background monitoring jobs
4. Telegram notification system

### Phase 4: UI & Performance
1. Kripton.app style implementation
2. Real-time updates
3. Performance dashboard
4. Historical data analysis

### Phase 5: Testing & Deployment
1. Unit and integration testing
2. Error handling improvements
3. Performance optimization
4. Production deployment

---

## 12. Testing Strategy

### Unit Tests
- Database models and queries
- API endpoint functionality
- Position size calculations
- Price alert logic

### Integration Tests
- BingX API integration
- Telegram bot functionality
- Database operations
- Cron job execution

### Manual Testing Scenarios
- Signal creation workflow
- Order placement and monitoring
- Watchlist alert triggers
- Error scenarios and recovery

---

## Important Implementation Notes

### Key Differences Explained

#### Positions vs Trade History
- **`positions` Table**: Tracks currently open positions with real-time P&L
- **`trade_history` Table**: Records every individual buy/sell transaction executed

#### Margin Used
- **`margin_used`**: Amount of account balance locked as collateral for the position
- **Calculation**: Position Size ÷ Leverage
- **Example**: $100 position at 5x leverage = $20 margin used

### Position Sizing Logic
- Always 3.3% of available account balance
- Rounded up to whole numbers (no decimals)
- Calculated per entry point, not per signal
- Updates dynamically as account balance changes

# TRADING SYSTEM WORKFLOW (CORRECTED)

## Trading System Complete Workflow

### Important Distinction
- **Watchlist**: Price monitoring for notifications ONLY (NO TRADING)  
- **Limit Orders**: Actual trading orders in `orders` table that get executed when price is touched

### 1. Order Placement Workflow

```
User Places Trade Order
    ├── Entry 1 (Market): Executed immediately
    │   └── Saved to positions table (status='OPEN')
    │
    ├── Entry 2 (Limit): Saved to orders table  
    │   └── status='PENDING', entry_level='ENTRY_2'
    │
    └── Entry 3 (Limit): Saved to orders table
        └── status='PENDING', entry_level='ENTRY_3'
```

### 2. Limit Order Monitoring

**Cronjob: `jobs/limit-order-monitor.php` (every minute)**
```sql
Query: SELECT * FROM orders WHERE status='PENDING'
For each pending order:
  - Get current price from BingX API
  - Check if price touched (Long: current ≤ order_price, Short: current ≥ order_price)
  - If touched: proceed to auto-trading decision
```

### 3. Auto Trading Decision Tree

```
Price Touched?
    ├── YES → Check Settings
    │   ├── auto_trading_enabled=true AND limit_order_action='auto_execute'
    │   │   └── Auto Execute Limit Order
    │   │       ├── Set leverage on BingX
    │   │       ├── Place market order
    │   │       ├── Save new position to positions table
    │   │       ├── Update order status: PENDING → FILLED
    │   │       ├── Send Telegram success notification
    │   │       └── If fails: fall back to manual approval
    │   │
    │   └── auto_trading_enabled=false OR limit_order_action='telegram_approval'
    │       └── Send Telegram approval message with buttons
    │           └── Wait for user to click "Execute" button
    │
    └── NO → Continue monitoring
```

### 4. Multi-Position Management

**Same Symbol Position Tracking:**
```sql
Query: SELECT * FROM positions WHERE symbol='BTCUSDT' AND status='OPEN'

Example Positions:
- Position 1: 0.1 BTC at $50,000 (Entry 1 - Market)
- Position 2: 0.1 BTC at $49,000 (Entry 2 - Limit executed)  
- Position 3: 0.1 BTC at $48,000 (Entry 3 - Limit executed)

Total Tracking:
- Combined Quantity: 0.3 BTC
- Weighted Average Entry: $49,000
- Combined P&L Monitoring
```

### 5. Target/Stop Loss Monitoring

**Cronjob: `jobs/target-stoploss-monitor.php` (every minute)**

#### Target Hit Logic:
```sql
For each symbol with open positions:
  - Calculate combined P&L percentage
  - Check if P&L ≥ Target percentage
  - OR check if current price ≥ Target price (long) / ≤ Target price (short)
  
If target hit:
  - Close ALL positions for that symbol simultaneously
  - Use market orders for immediate execution
  - Update all positions: status='CLOSED'
  - Calculate total profit
  - Send Telegram profit notification
```

#### Stop Loss Hit Logic:
```sql
For each symbol with open positions:
  - Check if current price ≤ Stop loss (long) / ≥ Stop loss (short)
  
If stop loss hit:
  - Emergency close ALL positions for that symbol  
  - Use market orders for immediate execution
  - Update all positions: status='CLOSED'
  - Calculate total loss
  - Send Telegram loss alert
```

### 6. Separate Watchlist System

**Cronjob: `jobs/price-monitor.php` (every minute)**
```sql
Query: SELECT * FROM watchlist WHERE status='active'

Purpose: NOTIFICATIONS ONLY
Actions:
  - Monitor watchlist prices
  - Send Telegram price alerts
  - Mark items as 'triggered'
  - NO trading execution
```

## Database Workflow Integration

### orders Table Flow:
```
NEW → PENDING → FILLED (when executed) or CANCELLED
             ↑
         (limit orders wait here for price touch)
```

### positions Table Flow:  
```
OPEN → CLOSED (when target/stoploss hit or manually closed)
  ↑
(multiple positions per symbol possible)
```

### Key Implementation Requirements

#### Missing Components to Implement:
1. **Limit Order Monitor Cronjob** (`jobs/limit-order-monitor.php`)
2. **Target/StopLoss Monitor Cronjob** (`jobs/target-stoploss-monitor.php`)  
3. **Multi-position P&L calculation logic**
4. **Simultaneous position closing for same symbol**
5. **Telegram notifications for target/stoploss hits**

#### Current vs Required Cronjobs:
- ✅ **Watchlist Monitor** (`jobs/price-monitor.php`) - notifications only
- ❌ **Limit Order Monitor** - execute pending orders when price touched
- ❌ **Target/StopLoss Monitor** - close positions when targets hit

This corrected workflow ensures proper separation between watchlist alerts (notifications only) and actual trading execution (limit orders), with proper multi-position management for the same symbol.

---

This documentation provides a complete foundation for developing your crypto trading management application. Each section can be expanded with specific implementation details as development progresses.