-- Add is_demo columns to existing tables for demo/live trading support
-- Run this SQL when XAMPP/MySQL is available

USE crypto_trading;

-- Add is_demo column to positions table if it doesn't exist
SET @sql = (
    SELECT CASE 
        WHEN COUNT(*) = 0 THEN 
            'ALTER TABLE positions ADD COLUMN is_demo BOOLEAN DEFAULT FALSE AFTER status'
        ELSE 
            'SELECT "is_demo column already exists in positions table" AS message'
    END
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'crypto_trading' 
    AND TABLE_NAME = 'positions' 
    AND COLUMN_NAME = 'is_demo'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add is_demo column to orders table if it doesn't exist  
SET @sql = (
    SELECT CASE 
        WHEN COUNT(*) = 0 THEN 
            'ALTER TABLE orders ADD COLUMN is_demo BOOLEAN DEFAULT FALSE AFTER status'
        ELSE 
            'SELECT "is_demo column already exists in orders table" AS message'
    END
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'crypto_trading' 
    AND TABLE_NAME = 'orders' 
    AND COLUMN_NAME = 'is_demo'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Display final table structures
SELECT 'Positions table structure:' as info;
DESCRIBE positions;

SELECT 'Orders table structure:' as info;  
DESCRIBE orders;

SELECT 'Migration complete!' as status;