-- Safe migration for Stop Loss and Take Profit order tracking fields
-- Handles cases where columns may already exist

-- Add stop_loss_order_id column if it doesn't exist
ALTER TABLE orders ADD COLUMN stop_loss_order_id VARCHAR(50) NULL AFTER bingx_order_id;

-- Add take_profit_order_id column if it doesn't exist
ALTER TABLE orders ADD COLUMN take_profit_order_id VARCHAR(50) NULL AFTER stop_loss_order_id;

-- Add stop_loss_price column if it doesn't exist
ALTER TABLE orders ADD COLUMN stop_loss_price DECIMAL(15,8) NULL AFTER take_profit_order_id;

-- Add take_profit_price column if it doesn't exist
ALTER TABLE orders ADD COLUMN take_profit_price DECIMAL(15,8) NULL AFTER stop_loss_price;

-- Add indexes for the new fields
ALTER TABLE orders ADD INDEX idx_stop_loss_order_id (stop_loss_order_id);
ALTER TABLE orders ADD INDEX idx_take_profit_order_id (take_profit_order_id);

-- Update the entry_level enum to include SL and TP types
ALTER TABLE orders MODIFY COLUMN entry_level ENUM('MARKET', 'ENTRY_2', 'ENTRY_3', 'STOP_LOSS', 'TAKE_PROFIT') NOT NULL;

-- Update the type enum to include STOP_MARKET type
ALTER TABLE orders MODIFY COLUMN type ENUM('MARKET', 'LIMIT', 'STOP_MARKET') NOT NULL;