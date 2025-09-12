-- Automatic Signal Sources & API Integration SQL
-- Updated schema for dynamic signal sources with API automation

USE crypto_trading;

-- 1. Create signal_sources table with VARCHAR for dynamic sources
CREATE TABLE IF NOT EXISTS signal_sources (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL UNIQUE COMMENT 'Dynamic source name from API',
    type VARCHAR(50) NOT NULL COMMENT 'API, TELEGRAM, DISCORD, MANUAL, etc',
    api_endpoint VARCHAR(500) NULL COMMENT 'API URL for fetching signals',
    api_key VARCHAR(255) NULL COMMENT 'API authentication key',
    api_headers JSON NULL COMMENT 'Additional headers for API calls',
    channel_url VARCHAR(500) NULL COMMENT 'Telegram/Discord channel URL',
    description TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    auto_create_signals BOOLEAN DEFAULT FALSE COMMENT 'Auto-create signals from this source',
    last_fetch_at TIMESTAMP NULL COMMENT 'Last time signals were fetched from API',
    fetch_interval_minutes INT DEFAULT 5 COMMENT 'How often to check for new signals',
    total_signals INT DEFAULT 0,
    win_count INT DEFAULT 0,
    loss_count INT DEFAULT 0,
    win_rate DECIMAL(5,2) DEFAULT 0.00,
    total_pnl DECIMAL(15,4) DEFAULT 0.0000,
    avg_pnl DECIMAL(15,4) DEFAULT 0.0000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_name (name),
    INDEX idx_type (type),
    INDEX idx_is_active (is_active),
    INDEX idx_auto_create (auto_create_signals),
    INDEX idx_last_fetch (last_fetch_at),
    INDEX idx_win_rate (win_rate)
);

-- 2. Create api_signal_queue table for incoming signals before processing
CREATE TABLE IF NOT EXISTS api_signal_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    source_id INT NOT NULL,
    source_name VARCHAR(200) NOT NULL,
    external_signal_id VARCHAR(100) NULL COMMENT 'ID from external API',
    raw_signal_data JSON NOT NULL COMMENT 'Original signal data from API',
    parsed_signal_data JSON NULL COMMENT 'Processed signal data ready for creation',
    symbol VARCHAR(20) NOT NULL,
    signal_type ENUM('LONG', 'SHORT') NOT NULL,
    entry_market_price DECIMAL(15,8) NULL,
    entry_2 DECIMAL(15,8) NULL,
    entry_3 DECIMAL(15,8) NULL,
    take_profit_1 DECIMAL(15,8) NULL,
    take_profit_2 DECIMAL(15,8) NULL,
    take_profit_3 DECIMAL(15,8) NULL,
    stop_loss DECIMAL(15,8) NULL,
    leverage INT DEFAULT 1,
    confidence_score DECIMAL(3,1) DEFAULT 0.0 COMMENT 'Signal quality score 0-10',
    queue_status ENUM('PENDING', 'PROCESSING', 'CREATED', 'FAILED', 'REJECTED') DEFAULT 'PENDING',
    rejection_reason TEXT NULL,
    processed_at TIMESTAMP NULL,
    signal_id INT NULL COMMENT 'Created signal ID if successful',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (source_id) REFERENCES signal_sources(id) ON DELETE CASCADE,
    FOREIGN KEY (signal_id) REFERENCES signals(id) ON DELETE SET NULL,
    
    INDEX idx_source_id (source_id),
    INDEX idx_queue_status (queue_status),
    INDEX idx_symbol (symbol),
    INDEX idx_created_at (created_at),
    INDEX idx_external_id (external_signal_id),
    INDEX idx_confidence (confidence_score)
);

-- 3. Create signal_automation_settings table
CREATE TABLE IF NOT EXISTS signal_automation_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    data_type ENUM('STRING', 'INTEGER', 'DECIMAL', 'BOOLEAN', 'JSON') DEFAULT 'STRING',
    description TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_setting_key (setting_key)
);

-- 4. Enhanced signals table for API integration
ALTER TABLE signals 
ADD COLUMN IF NOT EXISTS source_id INT NULL AFTER status,
ADD COLUMN IF NOT EXISTS source_name VARCHAR(200) NULL AFTER source_id,
ADD COLUMN IF NOT EXISTS external_signal_id VARCHAR(100) NULL AFTER source_name,
ADD COLUMN IF NOT EXISTS take_profit_1 DECIMAL(15,8) NULL AFTER leverage,
ADD COLUMN IF NOT EXISTS take_profit_2 DECIMAL(15,8) NULL AFTER take_profit_1,
ADD COLUMN IF NOT EXISTS take_profit_3 DECIMAL(15,8) NULL AFTER take_profit_2,
ADD COLUMN IF NOT EXISTS stop_loss DECIMAL(15,8) NULL AFTER take_profit_3,
ADD COLUMN IF NOT EXISTS risk_reward_ratio DECIMAL(5,2) DEFAULT 0.00 AFTER stop_loss,
ADD COLUMN IF NOT EXISTS confidence_score DECIMAL(3,1) DEFAULT 0.0 AFTER risk_reward_ratio,
ADD COLUMN IF NOT EXISTS auto_created BOOLEAN DEFAULT FALSE AFTER confidence_score,
ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER auto_created,
ADD COLUMN IF NOT EXISTS signal_status ENUM('ACTIVE', 'CLOSED', 'CANCELLED') DEFAULT 'ACTIVE' AFTER notes,
ADD COLUMN IF NOT EXISTS closed_at TIMESTAMP NULL AFTER signal_status,
ADD COLUMN IF NOT EXISTS closure_reason VARCHAR(100) NULL AFTER closed_at,
ADD COLUMN IF NOT EXISTS final_pnl DECIMAL(15,4) DEFAULT 0.0000 AFTER closure_reason,
ADD COLUMN IF NOT EXISTS win_status ENUM('WIN', 'LOSS', 'BREAKEVEN') NULL AFTER final_pnl;

-- Add foreign key and indexes
ALTER TABLE signals 
ADD CONSTRAINT IF NOT EXISTS fk_signals_source_id 
FOREIGN KEY (source_id) REFERENCES signal_sources(id) ON DELETE SET NULL;

ALTER TABLE signals
ADD INDEX IF NOT EXISTS idx_source_id (source_id),
ADD INDEX IF NOT EXISTS idx_external_signal_id (external_signal_id),
ADD INDEX IF NOT EXISTS idx_auto_created (auto_created),
ADD INDEX IF NOT EXISTS idx_signal_status (signal_status),
ADD INDEX IF NOT EXISTS idx_win_status (win_status),
ADD INDEX IF NOT EXISTS idx_confidence (confidence_score);

-- 5. Create signal_processing_log table for monitoring API fetching
CREATE TABLE IF NOT EXISTS signal_processing_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    source_id INT NOT NULL,
    source_name VARCHAR(200) NOT NULL,
    process_type ENUM('FETCH', 'PARSE', 'CREATE', 'ERROR') NOT NULL,
    signals_fetched INT DEFAULT 0,
    signals_parsed INT DEFAULT 0,
    signals_created INT DEFAULT 0,
    signals_failed INT DEFAULT 0,
    error_message TEXT NULL,
    processing_duration_ms INT DEFAULT 0,
    api_response_data JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (source_id) REFERENCES signal_sources(id) ON DELETE CASCADE,
    
    INDEX idx_source_id (source_id),
    INDEX idx_process_type (process_type),
    INDEX idx_created_at (created_at)
);

-- 6. Insert default automation settings
INSERT IGNORE INTO signal_automation_settings (setting_key, setting_value, data_type, description) VALUES
('AUTO_SIGNAL_CREATION_ENABLED', 'false', 'BOOLEAN', 'Master switch for automatic signal creation from APIs'),
('MIN_CONFIDENCE_SCORE', '6.0', 'DECIMAL', 'Minimum confidence score to auto-create signals (0-10)'),
('MAX_SIGNALS_PER_HOUR', '10', 'INTEGER', 'Maximum signals to create per hour across all sources'),
('AUTO_LEVERAGE_DEFAULT', '2', 'INTEGER', 'Default leverage for auto-created signals'),
('AUTO_MARGIN_PER_ENTRY', '50.00', 'DECIMAL', 'Default margin amount per entry for auto-created signals'),
('REQUIRED_FIELDS', '["symbol","signal_type","stop_loss"]', 'JSON', 'Required fields for signal validation'),
('BLACKLISTED_SYMBOLS', '["LUNA","FTT"]', 'JSON', 'Symbols to reject automatically'),
('API_TIMEOUT_SECONDS', '30', 'INTEGER', 'Timeout for API calls in seconds'),
('RETRY_FAILED_SIGNALS', 'true', 'BOOLEAN', 'Whether to retry failed signal creation'),
('NOTIFICATION_WEBHOOK_URL', '', 'STRING', 'Webhook URL for signal creation notifications');

-- 7. Insert sample API signal sources
INSERT IGNORE INTO signal_sources (name, type, api_endpoint, description, is_active, auto_create_signals, fetch_interval_minutes) VALUES
('TradingView Alerts API', 'API', 'https://api.example.com/signals', 'TradingView webhook signals', TRUE, FALSE, 1),
('Telegram Signal Bot', 'TELEGRAM', 'https://api.telegram.org/bot<TOKEN>/getUpdates', 'Telegram channel signal parser', TRUE, FALSE, 2),
('Discord Trading Bot', 'API', 'https://discord.com/api/channels/<ID>/messages', 'Discord signal channel', TRUE, FALSE, 3),
('Manual Entry', 'MANUAL', NULL, 'Manually entered signals', TRUE, FALSE, 0),
('3Commas API', 'API', 'https://api.3commas.io/public/api/v1/signals', '3Commas signal provider', TRUE, FALSE, 5),
('CryptoHopper API', 'API', 'https://api.cryptohopper.com/v1/signals', 'CryptoHopper signal feed', TRUE, FALSE, 5);

-- 8. Create view for signal automation dashboard
CREATE OR REPLACE VIEW signal_automation_dashboard AS
SELECT 
    ss.id,
    ss.name as source_name,
    ss.type,
    ss.is_active,
    ss.auto_create_signals,
    ss.fetch_interval_minutes,
    ss.last_fetch_at,
    TIMESTAMPDIFF(MINUTE, ss.last_fetch_at, NOW()) as minutes_since_last_fetch,
    COUNT(asq.id) as queued_signals,
    SUM(CASE WHEN asq.queue_status = 'PENDING' THEN 1 ELSE 0 END) as pending_signals,
    SUM(CASE WHEN asq.queue_status = 'CREATED' THEN 1 ELSE 0 END) as created_signals,
    SUM(CASE WHEN asq.queue_status = 'FAILED' THEN 1 ELSE 0 END) as failed_signals,
    ss.total_signals,
    ss.win_rate,
    ss.total_pnl,
    -- Recent processing stats (last 24 hours)
    (SELECT COUNT(*) FROM signal_processing_log spl 
     WHERE spl.source_id = ss.id AND spl.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as processes_24h,
    (SELECT SUM(signals_created) FROM signal_processing_log spl 
     WHERE spl.source_id = ss.id AND spl.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as signals_created_24h
FROM signal_sources ss
LEFT JOIN api_signal_queue asq ON ss.id = asq.source_id AND asq.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY ss.id, ss.name, ss.type, ss.is_active, ss.auto_create_signals, ss.fetch_interval_minutes, 
         ss.last_fetch_at, ss.total_signals, ss.win_rate, ss.total_pnl
ORDER BY ss.auto_create_signals DESC, ss.is_active DESC, ss.name;

-- 9. Create stored procedures for signal automation

-- Procedure to get automation settings
DELIMITER //
CREATE OR REPLACE PROCEDURE GetAutomationSetting(IN setting_key_param VARCHAR(100))
BEGIN
    SELECT setting_value, data_type 
    FROM signal_automation_settings 
    WHERE setting_key = setting_key_param;
END//

-- Procedure to update automation settings
CREATE OR REPLACE PROCEDURE UpdateAutomationSetting(
    IN setting_key_param VARCHAR(100), 
    IN setting_value_param TEXT,
    IN data_type_param VARCHAR(20)
)
BEGIN
    INSERT INTO signal_automation_settings (setting_key, setting_value, data_type, updated_at)
    VALUES (setting_key_param, setting_value_param, data_type_param, NOW())
    ON DUPLICATE KEY UPDATE 
        setting_value = setting_value_param,
        data_type = data_type_param,
        updated_at = NOW();
END//

-- Procedure to process signal queue
CREATE OR REPLACE PROCEDURE ProcessSignalQueue(IN batch_size INT)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE queue_id INT;
    
    -- Get automation settings
    DECLARE auto_creation_enabled BOOLEAN DEFAULT FALSE;
    DECLARE min_confidence DECIMAL(3,1) DEFAULT 6.0;
    DECLARE max_per_hour INT DEFAULT 10;
    
    -- Get current settings
    SELECT CASE WHEN setting_value = 'true' THEN TRUE ELSE FALSE END
    INTO auto_creation_enabled
    FROM signal_automation_settings 
    WHERE setting_key = 'AUTO_SIGNAL_CREATION_ENABLED';
    
    SELECT CAST(setting_value AS DECIMAL(3,1))
    INTO min_confidence
    FROM signal_automation_settings 
    WHERE setting_key = 'MIN_CONFIDENCE_SCORE';
    
    SELECT CAST(setting_value AS SIGNED)
    INTO max_per_hour
    FROM signal_automation_settings 
    WHERE setting_key = 'MAX_SIGNALS_PER_HOUR';
    
    -- Only process if automation is enabled
    IF auto_creation_enabled THEN
        -- Check hourly limit
        SET @signals_created_this_hour = (
            SELECT COUNT(*) FROM signals 
            WHERE auto_created = TRUE 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        );
        
        IF @signals_created_this_hour < max_per_hour THEN
            -- Update pending signals that meet criteria
            UPDATE api_signal_queue 
            SET queue_status = 'PROCESSING'
            WHERE queue_status = 'PENDING' 
            AND confidence_score >= min_confidence
            ORDER BY created_at ASC
            LIMIT batch_size;
            
            -- Log processing attempt
            INSERT INTO signal_processing_log (source_id, source_name, process_type, processing_duration_ms)
            SELECT DISTINCT source_id, source_name, 'PARSE', 0
            FROM api_signal_queue 
            WHERE queue_status = 'PROCESSING';
        END IF;
    END IF;
END//

DELIMITER ;

-- 10. Create indexes for performance optimization
CREATE INDEX IF NOT EXISTS idx_signals_auto_created_date ON signals(auto_created, created_at);
CREATE INDEX IF NOT EXISTS idx_queue_confidence_status ON api_signal_queue(confidence_score, queue_status);
CREATE INDEX IF NOT EXISTS idx_processing_log_date ON signal_processing_log(created_at);

-- Display automation settings
SELECT 'Current Automation Settings:' as info;
SELECT setting_key, setting_value, data_type, description 
FROM signal_automation_settings 
ORDER BY setting_key;

-- Display signal sources
SELECT 'Signal Sources Configuration:' as info;
SELECT id, name, type, is_active, auto_create_signals, fetch_interval_minutes
FROM signal_sources 
ORDER BY auto_create_signals DESC, name;