<?php
/**
 * Signal Automation Cronjob
 * Fetches signals from APIs and processes them automatically
 * Run this every 1-5 minutes via cron
 */

// Set execution time and memory limits
set_time_limit(300); // 5 minutes max
ini_set('memory_limit', '256M');

// Load environment variables
function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load .env file
loadEnv(__DIR__ . '/../.env');

// Database connection
function getDbConnection() {
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: '';
    $password = getenv('DB_PASSWORD') ?: '';
    $database = getenv('DB_NAME') ?: 'crypto_trading';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception('Database connection failed: ' . $e->getMessage());
    }
}

// Get automation setting
function getAutomationSetting($pdo, $key, $default = null) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value, data_type FROM signal_automation_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return $default;
        }
        
        $value = $result['setting_value'];
        $type = $result['data_type'];
        
        switch ($type) {
            case 'BOOLEAN':
                return strtolower($value) === 'true';
            case 'INTEGER':
                return (int) $value;
            case 'DECIMAL':
                return (float) $value;
            case 'JSON':
                return json_decode($value, true);
            default:
                return $value;
        }
    } catch (Exception $e) {
        error_log("Failed to get setting $key: " . $e->getMessage());
        return $default;
    }
}

// Fetch signals from API source
function fetchSignalsFromAPI($source) {
    $startTime = microtime(true);
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $source['api_endpoint'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Trading-Bot/1.0'
    ]);
    
    // Add authentication headers if available
    if (!empty($source['api_key'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $source['api_key'],
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
    }
    
    // Add custom headers from JSON
    if (!empty($source['api_headers'])) {
        $headers = json_decode($source['api_headers'], true);
        if (is_array($headers)) {
            $headerStrings = [];
            foreach ($headers as $key => $value) {
                $headerStrings[] = "$key: $value";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerStrings);
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
    
    curl_close($ch);
    
    if ($response === false) {
        throw new Exception('cURL error: Failed to fetch from API');
    }
    
    if ($httpCode !== 200) {
        throw new Exception("HTTP error: $httpCode");
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON response from API');
    }
    
    return [
        'data' => $data,
        'duration' => (int) $duration,
        'response_size' => strlen($response)
    ];
}

// Parse signal from API response
function parseSignalFromAPI($rawData, $sourceName) {
    // This is a generic parser - customize based on your API format
    $signal = [];
    
    // Common field mappings
    $fieldMappings = [
        'symbol' => ['symbol', 'pair', 'ticker', 'asset'],
        'signal_type' => ['direction', 'side', 'type', 'signal_type'],
        'entry_market_price' => ['entry', 'price', 'current_price', 'market_price'],
        'entry_2' => ['entry_2', 'entry2', 'second_entry'],
        'entry_3' => ['entry_3', 'entry3', 'third_entry'],
        'take_profit_1' => ['tp1', 'target1', 'take_profit_1', 'tp'],
        'take_profit_2' => ['tp2', 'target2', 'take_profit_2'],
        'take_profit_3' => ['tp3', 'target3', 'take_profit_3'],
        'stop_loss' => ['sl', 'stop_loss', 'stoploss', 'stop'],
        'leverage' => ['leverage', 'lev', 'multiplier'],
        'confidence_score' => ['confidence', 'score', 'rating', 'quality']
    ];
    
    foreach ($fieldMappings as $targetField => $possibleKeys) {
        foreach ($possibleKeys as $key) {
            if (isset($rawData[$key])) {
                $signal[$targetField] = $rawData[$key];
                break;
            }
        }
    }
    
    // Normalize signal type
    if (isset($signal['signal_type'])) {
        $type = strtoupper($signal['signal_type']);
        if (in_array($type, ['BUY', 'LONG', 'UP'])) {
            $signal['signal_type'] = 'LONG';
        } elseif (in_array($type, ['SELL', 'SHORT', 'DOWN'])) {
            $signal['signal_type'] = 'SHORT';
        }
    }
    
    // Normalize symbol (remove common suffixes/prefixes)
    if (isset($signal['symbol'])) {
        $symbol = strtoupper($signal['symbol']);
        $symbol = str_replace(['USDT', '-USDT', '/USDT', 'PERP'], '', $symbol);
        $signal['symbol'] = $symbol;
    }
    
    // Set defaults
    $signal['leverage'] = $signal['leverage'] ?? 2;
    $signal['confidence_score'] = $signal['confidence_score'] ?? 5.0;
    
    return $signal;
}

// Validate parsed signal
function validateSignal($signal, $requiredFields) {
    $errors = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($signal[$field]) || empty($signal[$field])) {
            $errors[] = "Missing required field: $field";
        }
    }
    
    // Additional validation
    if (isset($signal['signal_type']) && !in_array($signal['signal_type'], ['LONG', 'SHORT'])) {
        $errors[] = "Invalid signal_type: must be LONG or SHORT";
    }
    
    if (isset($signal['confidence_score'])) {
        $score = (float) $signal['confidence_score'];
        if ($score < 0 || $score > 10) {
            $errors[] = "Invalid confidence_score: must be between 0 and 10";
        }
    }
    
    return $errors;
}

// Add signal to queue
function addSignalToQueue($pdo, $sourceId, $sourceName, $rawData, $parsedSignal) {
    $sql = "INSERT INTO api_signal_queue (
        source_id, source_name, raw_signal_data, parsed_signal_data,
        symbol, signal_type, entry_market_price, entry_2, entry_3,
        take_profit_1, take_profit_2, take_profit_3, stop_loss,
        leverage, confidence_score, queue_status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', NOW())";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $sourceId,
        $sourceName,
        json_encode($rawData),
        json_encode($parsedSignal),
        $parsedSignal['symbol'] ?? '',
        $parsedSignal['signal_type'] ?? '',
        $parsedSignal['entry_market_price'] ?? null,
        $parsedSignal['entry_2'] ?? null,
        $parsedSignal['entry_3'] ?? null,
        $parsedSignal['take_profit_1'] ?? null,
        $parsedSignal['take_profit_2'] ?? null,
        $parsedSignal['take_profit_3'] ?? null,
        $parsedSignal['stop_loss'] ?? null,
        $parsedSignal['leverage'] ?? 2,
        $parsedSignal['confidence_score'] ?? 5.0
    ]);
}

// Process signals in queue
function processSignalQueue($pdo, $batchSize = 5) {
    $autoCreationEnabled = getAutomationSetting($pdo, 'AUTO_SIGNAL_CREATION_ENABLED', false);
    $minConfidence = getAutomationSetting($pdo, 'MIN_CONFIDENCE_SCORE', 6.0);
    $maxPerHour = getAutomationSetting($pdo, 'MAX_SIGNALS_PER_HOUR', 10);
    
    if (!$autoCreationEnabled) {
        return ['message' => 'Auto signal creation disabled', 'processed' => 0];
    }
    
    // Check hourly limit
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM signals 
        WHERE auto_created = TRUE 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $signalsThisHour = $stmt->fetch()['count'];
    
    if ($signalsThisHour >= $maxPerHour) {
        return ['message' => 'Hourly limit reached', 'processed' => 0, 'limit' => $maxPerHour];
    }
    
    // Get signals to process
    $stmt = $pdo->prepare("
        SELECT * FROM api_signal_queue 
        WHERE queue_status = 'PENDING' 
        AND confidence_score >= ? 
        ORDER BY confidence_score DESC, created_at ASC 
        LIMIT ?
    ");
    $stmt->execute([$minConfidence, $batchSize]);
    $queuedSignals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processed = 0;
    $created = 0;
    $failed = 0;
    
    foreach ($queuedSignals as $queueItem) {
        try {
            // Update status to processing
            $pdo->prepare("UPDATE api_signal_queue SET queue_status = 'PROCESSING' WHERE id = ?")
                 ->execute([$queueItem['id']]);
            
            $parsedData = json_decode($queueItem['parsed_signal_data'], true);
            
            // Create the actual signal
            $signalSql = "INSERT INTO signals (
                symbol, signal_type, source_id, source_name, external_signal_id,
                entry_market_price, entry_2, entry_3, 
                take_profit_1, take_profit_2, take_profit_3, stop_loss,
                leverage, confidence_score, auto_created, 
                status, signal_status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE, 'ACTIVE', 'ACTIVE', NOW())";
            
            $signalStmt = $pdo->prepare($signalSql);
            $signalStmt->execute([
                $parsedData['symbol'],
                $parsedData['signal_type'],
                $queueItem['source_id'],
                $queueItem['source_name'],
                $queueItem['external_signal_id'] ?? null,
                $parsedData['entry_market_price'] ?? null,
                $parsedData['entry_2'] ?? null,
                $parsedData['entry_3'] ?? null,
                $parsedData['take_profit_1'] ?? null,
                $parsedData['take_profit_2'] ?? null,
                $parsedData['take_profit_3'] ?? null,
                $parsedData['stop_loss'] ?? null,
                $parsedData['leverage'] ?? 2,
                $parsedData['confidence_score'] ?? 5.0
            ]);
            
            $signalId = $pdo->lastInsertId();
            
            // Update queue status to created
            $pdo->prepare("UPDATE api_signal_queue SET queue_status = 'CREATED', signal_id = ?, processed_at = NOW() WHERE id = ?")
                 ->execute([$signalId, $queueItem['id']]);
            
            $created++;
            $processed++;
            
            error_log("Auto-created signal ID $signalId from source {$queueItem['source_name']}");
            
        } catch (Exception $e) {
            // Mark as failed
            $pdo->prepare("UPDATE api_signal_queue SET queue_status = 'FAILED', rejection_reason = ?, processed_at = NOW() WHERE id = ?")
                 ->execute([$e->getMessage(), $queueItem['id']]);
            
            $failed++;
            $processed++;
            
            error_log("Failed to create signal from queue ID {$queueItem['id']}: " . $e->getMessage());
        }
        
        // Check if we've hit the hourly limit
        if (($signalsThisHour + $created) >= $maxPerHour) {
            break;
        }
    }
    
    return [
        'processed' => $processed,
        'created' => $created,
        'failed' => $failed,
        'remaining_in_queue' => count($queuedSignals) - $processed
    ];
}

// Log processing results
function logProcessingResult($pdo, $sourceId, $sourceName, $processType, $stats) {
    $sql = "INSERT INTO signal_processing_log (
        source_id, source_name, process_type, 
        signals_fetched, signals_parsed, signals_created, signals_failed,
        processing_duration_ms, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $sourceId,
        $sourceName,
        $processType,
        $stats['fetched'] ?? 0,
        $stats['parsed'] ?? 0,
        $stats['created'] ?? 0,
        $stats['failed'] ?? 0,
        $stats['duration'] ?? 0
    ]);
}

// Main execution
try {
    $startTime = microtime(true);
    $pdo = getDbConnection();
    
    echo "[" . date('Y-m-d H:i:s') . "] Starting signal automation job\n";
    
    // Check if automation is enabled
    $autoEnabled = getAutomationSetting($pdo, 'AUTO_SIGNAL_CREATION_ENABLED', false);
    if (!$autoEnabled) {
        echo "Signal automation disabled - exiting\n";
        exit(0);
    }
    
    // Get active API sources
    $stmt = $pdo->query("
        SELECT * FROM signal_sources 
        WHERE is_active = TRUE 
        AND auto_create_signals = TRUE 
        AND api_endpoint IS NOT NULL
        AND (last_fetch_at IS NULL OR 
             TIMESTAMPDIFF(MINUTE, last_fetch_at, NOW()) >= fetch_interval_minutes)
        ORDER BY fetch_interval_minutes ASC
    ");
    
    $sources = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($sources) . " sources to process\n";
    
    $totalFetched = 0;
    $totalParsed = 0;
    
    // Process each source
    foreach ($sources as $source) {
        try {
            echo "Processing source: {$source['name']}\n";
            
            // Fetch signals from API
            $apiResult = fetchSignalsFromAPI($source);
            $rawSignals = $apiResult['data'];
            
            // Handle different API response formats
            if (isset($rawSignals['signals']) && is_array($rawSignals['signals'])) {
                $signalsToProcess = $rawSignals['signals'];
            } elseif (isset($rawSignals['data']) && is_array($rawSignals['data'])) {
                $signalsToProcess = $rawSignals['data'];
            } elseif (is_array($rawSignals)) {
                $signalsToProcess = $rawSignals;
            } else {
                throw new Exception('Unexpected API response format');
            }
            
            $fetched = count($signalsToProcess);
            $parsed = 0;
            $totalFetched += $fetched;
            
            echo "  Fetched $fetched signals\n";
            
            $requiredFields = getAutomationSetting($pdo, 'REQUIRED_FIELDS', ['symbol', 'signal_type', 'stop_loss']);
            $blacklistedSymbols = getAutomationSetting($pdo, 'BLACKLISTED_SYMBOLS', []);
            
            // Parse and queue each signal
            foreach ($signalsToProcess as $rawSignal) {
                try {
                    $parsedSignal = parseSignalFromAPI($rawSignal, $source['name']);
                    
                    // Check blacklist
                    if (in_array($parsedSignal['symbol'] ?? '', $blacklistedSymbols)) {
                        continue;
                    }
                    
                    // Validate signal
                    $errors = validateSignal($parsedSignal, $requiredFields);
                    if (!empty($errors)) {
                        error_log("Signal validation failed: " . implode(', ', $errors));
                        continue;
                    }
                    
                    // Add to queue
                    if (addSignalToQueue($pdo, $source['id'], $source['name'], $rawSignal, $parsedSignal)) {
                        $parsed++;
                        $totalParsed++;
                    }
                    
                } catch (Exception $e) {
                    error_log("Failed to parse signal: " . $e->getMessage());
                }
            }
            
            echo "  Parsed and queued $parsed signals\n";
            
            // Update last fetch time
            $pdo->prepare("UPDATE signal_sources SET last_fetch_at = NOW() WHERE id = ?")
                 ->execute([$source['id']]);
            
            // Log the fetch result
            logProcessingResult($pdo, $source['id'], $source['name'], 'FETCH', [
                'fetched' => $fetched,
                'parsed' => $parsed,
                'duration' => $apiResult['duration']
            ]);
            
        } catch (Exception $e) {
            error_log("Failed to process source {$source['name']}: " . $e->getMessage());
            
            // Log the error
            logProcessingResult($pdo, $source['id'], $source['name'], 'ERROR', [
                'failed' => 1,
                'duration' => 0
            ]);
        }
    }
    
    // Process the signal queue
    echo "Processing signal queue...\n";
    $queueResult = processSignalQueue($pdo, 10);
    
    echo "Queue processed: {$queueResult['processed']} total, {$queueResult['created']} created, {$queueResult['failed']} failed\n";
    
    $totalDuration = (microtime(true) - $startTime) * 1000;
    echo "Signal automation completed in " . round($totalDuration) . "ms\n";
    echo "Summary: $totalFetched fetched, $totalParsed queued, {$queueResult['created']} signals created\n";
    
} catch (Exception $e) {
    error_log("Signal automation job failed: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>