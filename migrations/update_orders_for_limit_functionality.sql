-- Migration: Update orders table for limit order functionality
-- Run this SQL to add missing columns and status values

USE crypto_trading;

-- Add updated_at column to orders table
ALTER TABLE orders ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Update the status enum to include TRIGGERED
ALTER TABLE orders MODIFY COLUMN status ENUM('NEW', 'FILLED', 'CANCELLED', 'PENDING', 'FAILED', 'TRIGGERED') DEFAULT 'NEW';

-- Add index for updated_at for better performance
ALTER TABLE orders ADD INDEX idx_updated_at (updated_at);

-- Update existing records to have updated_at = created_at
UPDATE orders SET updated_at = created_at WHERE updated_at IS NULL;

COMMIT;