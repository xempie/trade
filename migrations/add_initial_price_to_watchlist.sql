-- Add initial_price column to watchlist table
-- This stores the price when the watchlist item was created for progress bar calculations

ALTER TABLE watchlist 
ADD COLUMN initial_price DECIMAL(18, 8) NULL COMMENT 'Price when watchlist item was created' 
AFTER percentage;