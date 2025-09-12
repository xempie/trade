-- Signal Source Tracking & Win Rate Migration
-- Run this SQL to add signal source tracking and win rate calculation capabilities

USE crypto_trading;

-- 1. Create signal_sources table
CREATE TABLE IF NOT EXISTS signal_sources (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    type ENUM('TELEGRAM', 'DISCORD', 'MANUAL', 'API', 'OTHER') NOT NULL,
    channel_url VARCHAR(255) NULL,
    description TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    total_signals INT DEFAULT 0,
    win_count INT DEFAULT 0,
    loss_count INT DEFAULT 0,
    win_rate DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Cached win rate percentage',
    total_pnl DECIMAL(15,4) DEFAULT 0.0000 COMMENT 'Total PnL from all signals',
    avg_pnl DECIMAL(15,4) DEFAULT 0.0000 COMMENT 'Average PnL per signal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_name (name),
    INDEX idx_type (type),
    INDEX idx_is_active (is_active),
    INDEX idx_win_rate (win_rate)
);

-- 2. Create signal_performance table for detailed analytics
CREATE TABLE IF NOT EXISTS signal_performance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    signal_id INT NOT NULL,
    source_id INT NOT NULL,
    symbol VARCHAR(20) NOT NULL,
    signal_type ENUM('LONG', 'SHORT') NOT NULL,
    entry_count INT DEFAULT 0 COMMENT 'Number of entries that were filled',
    total_entry_size DECIMAL(15,8) DEFAULT 0,
    avg_entry_price DECIMAL(15,8) DEFAULT 0,
    exit_price DECIMAL(15,8) NULL,
    max_drawdown DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Max % loss during signal lifetime',
    max_profit DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Max % profit during signal lifetime',
    duration_minutes INT DEFAULT 0 COMMENT 'How long signal was active',
    pnl_usd DECIMAL(15,4) DEFAULT 0.0000,
    pnl_percentage DECIMAL(5,2) DEFAULT 0.00,
    risk_reward_achieved DECIMAL(5,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (signal_id) REFERENCES signals(id) ON DELETE CASCADE,
    FOREIGN KEY (source_id) REFERENCES signal_sources(id) ON DELETE CASCADE,
    
    INDEX idx_signal_id (signal_id),
    INDEX idx_source_id (source_id),
    INDEX idx_symbol (symbol),
    INDEX idx_pnl_usd (pnl_usd),
    INDEX idx_created_at (created_at)
);

-- 3. Add columns to signals table for enhanced tracking
ALTER TABLE signals 
ADD COLUMN IF NOT EXISTS source_id INT NULL AFTER status,
ADD COLUMN IF NOT EXISTS source_name VARCHAR(100) NULL AFTER source_id,
ADD COLUMN IF NOT EXISTS take_profit_1 DECIMAL(15,8) NULL AFTER leverage,
ADD COLUMN IF NOT EXISTS take_profit_2 DECIMAL(15,8) NULL AFTER take_profit_1,
ADD COLUMN IF NOT EXISTS take_profit_3 DECIMAL(15,8) NULL AFTER take_profit_2,
ADD COLUMN IF NOT EXISTS stop_loss DECIMAL(15,8) NULL AFTER take_profit_3,
ADD COLUMN IF NOT EXISTS risk_reward_ratio DECIMAL(5,2) DEFAULT 0.00 AFTER stop_loss,
ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER risk_reward_ratio,
ADD COLUMN IF NOT EXISTS signal_status ENUM('ACTIVE', 'CLOSED', 'CANCELLED') DEFAULT 'ACTIVE' AFTER notes,
ADD COLUMN IF NOT EXISTS closed_at TIMESTAMP NULL AFTER signal_status,
ADD COLUMN IF NOT EXISTS closure_reason ENUM('TAKE_PROFIT', 'STOP_LOSS', 'MANUAL', 'TIME_EXPIRED', 'PARTIAL_PROFIT') NULL AFTER closed_at,
ADD COLUMN IF NOT EXISTS final_pnl DECIMAL(15,4) DEFAULT 0.0000 AFTER closure_reason,
ADD COLUMN IF NOT EXISTS win_status ENUM('WIN', 'LOSS', 'BREAKEVEN') NULL AFTER final_pnl;

-- Add foreign key constraint for source_id
ALTER TABLE signals 
ADD CONSTRAINT fk_signals_source_id 
FOREIGN KEY (source_id) REFERENCES signal_sources(id) ON DELETE SET NULL;

-- Add indexes for new columns
ALTER TABLE signals
ADD INDEX IF NOT EXISTS idx_source_id (source_id),
ADD INDEX IF NOT EXISTS idx_signal_status (signal_status),
ADD INDEX IF NOT EXISTS idx_win_status (win_status),
ADD INDEX IF NOT EXISTS idx_final_pnl (final_pnl),
ADD INDEX IF NOT EXISTS idx_closed_at (closed_at);

-- 4. Add columns to positions table for exit tracking
ALTER TABLE positions
ADD COLUMN IF NOT EXISTS exit_price DECIMAL(15,8) NULL AFTER unrealized_pnl,
ADD COLUMN IF NOT EXISTS exit_reason ENUM('TAKE_PROFIT', 'STOP_LOSS', 'MANUAL', 'LIQUIDATION', 'PARTIAL') NULL AFTER exit_price,
ADD COLUMN IF NOT EXISTS realized_pnl DECIMAL(15,4) DEFAULT 0.0000 AFTER exit_reason;

-- Add indexes for new position columns
ALTER TABLE positions
ADD INDEX IF NOT EXISTS idx_exit_reason (exit_reason),
ADD INDEX IF NOT EXISTS idx_realized_pnl (realized_pnl);

-- 5. Insert default signal sources
INSERT IGNORE INTO signal_sources (name, type, description, is_active) VALUES
('Manual Entry', 'MANUAL', 'Manually entered signals', TRUE),
('Telegram Channel 1', 'TELEGRAM', 'Primary Telegram signal channel', TRUE),
('Telegram Channel 2', 'TELEGRAM', 'Secondary Telegram signal channel', TRUE),
('Discord Server', 'DISCORD', 'Discord trading signals', TRUE),
('API Signals', 'API', 'Signals from external API sources', TRUE);

-- 6. Set default source for existing signals (Manual Entry)
UPDATE signals 
SET source_id = (SELECT id FROM signal_sources WHERE name = 'Manual Entry' LIMIT 1),
    source_name = 'Manual Entry'
WHERE source_id IS NULL;

-- 7. Create view for easy win rate analysis
CREATE OR REPLACE VIEW signal_source_performance AS
SELECT 
    ss.id,
    ss.name,
    ss.type,
    ss.is_active,
    COUNT(s.id) as total_signals,
    SUM(CASE WHEN s.signal_status = 'CLOSED' THEN 1 ELSE 0 END) as closed_signals,
    SUM(CASE WHEN s.win_status = 'WIN' THEN 1 ELSE 0 END) as wins,
    SUM(CASE WHEN s.win_status = 'LOSS' THEN 1 ELSE 0 END) as losses,
    SUM(CASE WHEN s.win_status = 'BREAKEVEN' THEN 1 ELSE 0 END) as breakevens,
    CASE 
        WHEN SUM(CASE WHEN s.signal_status = 'CLOSED' THEN 1 ELSE 0 END) > 0 
        THEN ROUND((SUM(CASE WHEN s.win_status = 'WIN' THEN 1 ELSE 0 END) / SUM(CASE WHEN s.signal_status = 'CLOSED' THEN 1 ELSE 0 END)) * 100, 2)
        ELSE 0.00 
    END as win_rate_percent,
    COALESCE(SUM(s.final_pnl), 0.00) as total_pnl,
    CASE 
        WHEN COUNT(s.id) > 0 
        THEN ROUND(COALESCE(SUM(s.final_pnl), 0.00) / COUNT(s.id), 2)
        ELSE 0.00 
    END as avg_pnl_per_signal,
    ss.created_at,
    ss.updated_at
FROM signal_sources ss
LEFT JOIN signals s ON ss.id = s.source_id
GROUP BY ss.id, ss.name, ss.type, ss.is_active, ss.created_at, ss.updated_at
ORDER BY win_rate_percent DESC;

-- 8. Create stored procedure to update source statistics
DELIMITER //
CREATE OR REPLACE PROCEDURE UpdateSignalSourceStats(IN source_id_param INT)
BEGIN
    DECLARE total_sigs INT DEFAULT 0;
    DECLARE win_cnt INT DEFAULT 0;
    DECLARE loss_cnt INT DEFAULT 0;
    DECLARE total_pnl_val DECIMAL(15,4) DEFAULT 0.0000;
    DECLARE avg_pnl_val DECIMAL(15,4) DEFAULT 0.0000;
    DECLARE win_rate_val DECIMAL(5,2) DEFAULT 0.00;
    
    -- Get statistics for the source
    SELECT 
        COUNT(*),
        SUM(CASE WHEN win_status = 'WIN' THEN 1 ELSE 0 END),
        SUM(CASE WHEN win_status = 'LOSS' THEN 1 ELSE 0 END),
        COALESCE(SUM(final_pnl), 0.0000)
    INTO total_sigs, win_cnt, loss_cnt, total_pnl_val
    FROM signals 
    WHERE source_id = source_id_param AND signal_status = 'CLOSED';
    
    -- Calculate averages
    IF total_sigs > 0 THEN
        SET win_rate_val = ROUND((win_cnt / total_sigs) * 100, 2);
        SET avg_pnl_val = ROUND(total_pnl_val / total_sigs, 4);
    END IF;
    
    -- Update the source record
    UPDATE signal_sources 
    SET 
        total_signals = total_sigs,
        win_count = win_cnt,
        loss_count = loss_cnt,
        win_rate = win_rate_val,
        total_pnl = total_pnl_val,
        avg_pnl = avg_pnl_val,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = source_id_param;
    
END//
DELIMITER ;

-- 9. Create trigger to auto-update source stats when signals are updated
DELIMITER //
CREATE OR REPLACE TRIGGER signal_status_update_trigger
AFTER UPDATE ON signals
FOR EACH ROW
BEGIN
    -- Only update if signal status or win_status changed
    IF (OLD.signal_status != NEW.signal_status) OR (OLD.win_status != NEW.win_status) OR (OLD.final_pnl != NEW.final_pnl) THEN
        -- Update old source stats if source changed
        IF OLD.source_id IS NOT NULL AND OLD.source_id != NEW.source_id THEN
            CALL UpdateSignalSourceStats(OLD.source_id);
        END IF;
        
        -- Update new source stats
        IF NEW.source_id IS NOT NULL THEN
            CALL UpdateSignalSourceStats(NEW.source_id);
        END IF;
    END IF;
END//
DELIMITER ;

SELECT 'Signal source tracking migration completed successfully!' as status;

-- Display current signal sources
SELECT 'Current Signal Sources:' as info;
SELECT * FROM signal_sources ORDER BY name;

-- Display enhanced signals table structure  
SELECT 'Enhanced signals table structure:' as info;
DESCRIBE signals;