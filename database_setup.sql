-- Crypto Trading Management App Database Setup
-- Execute this SQL to create the required tables

-- Create database (if not exists)
CREATE DATABASE IF NOT EXISTS crypto_trading;
USE crypto_trading;

-- Signals table - stores trading signals with entry points
CREATE TABLE IF NOT EXISTS signals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    symbol VARCHAR(20) NOT NULL,
    signal_type ENUM('LONG', 'SHORT') NOT NULL,
    entry_market_price DECIMAL(15,8),
    entry_2 DECIMAL(15,8),
    entry_3 DECIMAL(15,8),
    leverage INT DEFAULT 1,
    status VARCHAR(15) DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_symbol (symbol),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Orders table - stores individual orders placed on exchange
CREATE TABLE IF NOT EXISTS orders (
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
    status ENUM('NEW', 'FILLED', 'CANCELLED', 'PENDING', 'FAILED') DEFAULT 'NEW',
    fill_price DECIMAL(15,8),
    fill_time TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (signal_id) REFERENCES signals(id) ON DELETE SET NULL,
    INDEX idx_symbol (symbol),
    INDEX idx_status (status),
    INDEX idx_bingx_order_id (bingx_order_id),
    INDEX idx_created_at (created_at)
);

-- Positions table - tracks currently open positions
CREATE TABLE IF NOT EXISTS positions (
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
    
    FOREIGN KEY (signal_id) REFERENCES signals(id) ON DELETE SET NULL,
    INDEX idx_symbol (symbol),
    INDEX idx_status (status),
    INDEX idx_side (side),
    INDEX idx_opened_at (opened_at)
);

-- Watchlist table - stores price alerts for entry points
CREATE TABLE IF NOT EXISTS watchlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(10) NOT NULL,
    entry_price DECIMAL(18, 8) NOT NULL,
    entry_type ENUM('entry_2', 'entry_3') NOT NULL,
    direction ENUM('long', 'short') NOT NULL,
    margin_amount DECIMAL(18, 8) NOT NULL,
    percentage DECIMAL(8, 4) NULL COMMENT 'Percentage used for calculation',
    initial_price DECIMAL(18, 8) NULL COMMENT 'Price when watchlist item was created',
    status ENUM('active', 'triggered', 'cancelled') DEFAULT 'active',
    triggered_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_symbol (symbol),
    INDEX idx_status (status),
    INDEX idx_entry_price (entry_price),
    INDEX idx_created_at (created_at)
);

-- Account balance table - caches account balance info
CREATE TABLE IF NOT EXISTS account_balance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    total_balance DECIMAL(15,4) NOT NULL,
    available_balance DECIMAL(15,4) NOT NULL,
    margin_used DECIMAL(15,4) NOT NULL,
    unrealized_pnl DECIMAL(15,4) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_updated_at (updated_at)
);

-- Trade history table - records all trade executions
CREATE TABLE IF NOT EXISTS trade_history (
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
    
    FOREIGN KEY (signal_id) REFERENCES signals(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    INDEX idx_symbol (symbol),
    INDEX idx_executed_at (executed_at),
    INDEX idx_bingx_trade_id (bingx_trade_id)
);

-- Insert initial account balance row (will be updated by API)
INSERT IGNORE INTO account_balance (id, total_balance, available_balance, margin_used) 
VALUES (1, 0.00, 0.00, 0.00);

-- Sample data for testing (optional)
-- INSERT INTO signals (symbol, signal_type, entry_market_price, leverage) 
-- VALUES ('BTC', 'LONG', 45000.00, 5);

COMMIT;