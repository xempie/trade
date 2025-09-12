-- Migration: Add demo mode support to tables
-- This adds is_demo field to track whether orders/positions are demo or live

USE crypto_trading;

-- Add is_demo field to orders table
ALTER TABLE orders 
ADD COLUMN is_demo TINYINT(1) DEFAULT 0 COMMENT 'Whether this order is from demo trading';

-- Add is_demo field to positions table  
ALTER TABLE positions 
ADD COLUMN is_demo TINYINT(1) DEFAULT 0 COMMENT 'Whether this position is from demo trading';

-- Add is_demo field to watchlist table
ALTER TABLE watchlist 
ADD COLUMN is_demo TINYINT(1) DEFAULT 0 COMMENT 'Whether this watchlist item is from demo trading';

-- Add is_demo field to trade_history table
ALTER TABLE trade_history 
ADD COLUMN is_demo TINYINT(1) DEFAULT 0 COMMENT 'Whether this trade is from demo trading';

-- Add is_demo field to account_balance table to track separate balances
ALTER TABLE account_balance 
ADD COLUMN is_demo TINYINT(1) DEFAULT 0 COMMENT 'Whether this is demo or live balance';

-- Create initial demo balance entry
INSERT IGNORE INTO account_balance (id, total_balance, available_balance, margin_used, is_demo) 
VALUES (2, 10000.00, 10000.00, 0.00, 1);

-- Add indexes for demo field on frequently queried tables
CREATE INDEX idx_orders_is_demo ON orders(is_demo);
CREATE INDEX idx_positions_is_demo ON positions(is_demo);
CREATE INDEX idx_watchlist_is_demo ON watchlist(is_demo);

COMMIT;