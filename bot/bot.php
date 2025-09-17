<?php

header('Content-Type: application/json');

// Include required files
require_once 'env_loader.php';
require_once 'telegram_sender.php';

/**
 * Signal Webhook Handler
 * Processes incoming trading signals and notifications
 */
class SignalWebhookHandler
{
    private $pdo;
    private $telegram;
    private $enableLogging;

    public function __construct()
    {
        $this->telegram = new TelegramSender();
        $this->enableLogging = EnvLoader::getBool('ENABLE_LOGGING');
    }

    /**
     * Main entry point for processing webhook requests
     */
    public function processWebhook()
    {
        try {
            $data = $this->parseIncomingData();
            $this->validateRequiredFields($data);
            $this->logIncomingData($data);

            $this->pdo = $this->getDbConnection();
            
            $result = $this->processSignalByType($data);
            
            echo json_encode($result, JSON_PRETTY_PRINT);
            
        } catch (Exception $e) {
            $this->handleError($e, $data ?? []);
        }
    }

    /**
     * Parse and clean incoming JSON data
     */
    private function parseIncomingData(): array
    {
        $json = file_get_contents('php://input');
        
        // Clean up encoding issues
        $json = str_replace(['ðŸŸ©ðŸ"€', 'ðŸŸ¥ðŸ"€'], ['ðŸŸ¢', 'ðŸ"´'], $json);
        
        $data = json_decode($json, true);
        
        $this->logDebugInfo($json, $data);
        
        if (!$data) {
            throw new Exception('No data received or invalid JSON: ' . json_last_error_msg());
        }
        
        return $data;
    }

    /**
     * Validate that required fields are present
     */
    private function validateRequiredFields(array $data): void
    {
        // Check if this is a trading signal (has entries/targets) or other signal types
        if (isset($data['entries']) && isset($data['targets'])) {
            // Trading signal - only require symbol and side initially
            $requiredFields = ['symbol', 'side'];
        } else {
            // Other signal types require type field
            $requiredFields = ['symbol', 'side', 'type'];
        }
        
        $missingFields = array_filter($requiredFields, function($field) use ($data) { return !isset($data[$field]);});
        
        if (!empty($missingFields)) {
            $availableFields = implode(', ', array_keys($data));
            throw new Exception(
                'Missing required fields: ' . implode(', ', $missingFields) . 
                '. Available fields: ' . $availableFields
            );
        }
    }

    /**
     * Route signal processing based on type
     */
    private function processSignalByType(array $data): array
    {
        $type = $data['type'] ?? 'TRADING_SIGNAL'; // Default to trading signal if no type specified
        $symbol = $data['symbol'];
        $side = strtoupper($data['side']);
        
        $this->logMessage('debug_log.txt', "Processing signal: Symbol=$symbol, Side=$side, Type=$type");
        
        switch ($type) {
            
            case 'LNL_SIGNAL':
            case 'FVGTOUCH':
                return $this->processFVGSignal($data);
            
            case 'FVG':
                return $this->processFVGSignal($data);
            
            case 'TRIGGER_CROSS':
                return $this->processTriggerCross($data);
            
            case 'T3_SSL':
                return $this->processT3SSL($data);
            
            case 'ICHIMOKU_AFTER_CROSS':
            case 'ICHIMOKU_BEFORE_CROSS':
                return $this->processIchimoku($data);
            
            case 'IN_TREND':
            case 'UP_TREND':
                return $this->processTrendSignal($data);
            
            case 'TRADING_SIGNAL':
                return $this->processTradingSignal($data);
            
            default:
                throw new Exception("Unsupported signal type: $type");
        }
    }

    /**
     * Process FVG (Fair Value Gap) signals
     */
    private function processFVGSignal(array $data): array
    {
        $symbol = $data['symbol'];
        $side = $data['side'];
        $type = $data['type'];
        
        $entry = $data['entry'];
        $cross_bars_ago = $data['cross_bars_ago'];
        $t3_distance = floatval($data['t3_distance']);
        $t3_lines = $data['t3_lines'];

        $meta_data = [
            'entry' => $entry,
            'cross_bars_ago' => $cross_bars_ago,
            't3_distance' => $t3_distance,
            't3_lines' => $t3_lines
        ];

        $telegramResult = $this->telegram->sendFVGAlert($symbol, $side, $type, $meta_data);

        return [
             'success' => true,
             'symbol' => $symbol,
             'message' => 'FVG signal successfully processed',
             'status' => 'NEW',
             'telegram_sent' => $telegramResult['success'] ?? false
         ];
    }

    private function processFirstFVGSignal(array $data,$type): array
    {
        $symbol = $data['symbol'];
        $side = $data['side'];
        if ($type == "FVG1") {
            $fvgSize = "1⬜F";
        } else {
            $fvgSize = "1⬜Hit";
        }
        
        $telegramResult = $this->telegram->sendFVGAlert($symbol, $side, $fvgSize);
        return [
             'success' => true,
             'symbol' => $symbol,
             'message' => 'FVG signal successfully processed',
             'status' => 'NEW',
             'telegram_sent' => $telegramResult['success'] ?? false
         ];
    }
    

    /**
     * Process trigger cross events
     */
    private function processTriggerCross(array $data): array
    {
        $this->validateFieldsExist($data, ['levels', 'prices'], 'TRIGGER_CROSS');
        
        $symbol = $data['symbol'];
        $side = $data['side'];
        $levels = $data['levels'];
        $prices = $data['prices'];
        
        $this->logMessage('debug_log.txt', "Attempting to send trigger cross alert for $symbol $side");
        $telegramResult = $this->telegram->sendHitCrossAlert($symbol, $side, $levels, $prices);
        $this->logMessage('debug_log.txt', "Trigger cross alert result: " . json_encode($telegramResult));
        return $this->processTradingSignal($data);
        // return [
        //     'success' => true,
        //     'message' => 'FVG Hit notification sent',
        //     'type' => 'TRIGGER_CROSS'
        // ];
    }

    /**
     * Process T3 SSL signals
     */
    private function processT3SSL(array $data): array
    {
        $symbol = $data['symbol'];
        $side = $data['side'];
        $entry = $data['entry'];
        
        $this->telegram->sendBaselineAlert($symbol, $side, $entry);
        
        return [
            'success' => true,
            'message' => 'Baseline Hit notification sent',
            'type' => 'T3_SSL'
        ];
    }

    /**
     * Process Ichimoku signals
     */
    private function processIchimoku(array $data): array
    {
        $symbol = $data['symbol'];
        $side = $data['side'];
        $entry = $data['entry'];
        $type = $data['type'];
        
        $this->telegram->sendIchiAlert($symbol, $side, $entry, $type);
        return $this->processTradingSignal($data);
        // return [
        //     'success' => true,
        //     'message' => 'Ichimoku notification sent',
        //     'type' => $type
        // ];
    }

    /**
     * Process trend signals (IN_TREND, UP_TREND)
     */
    private function processTrendSignal(array $data): array
    {
        $requiredFields = [
            'entry', 'candle_size', 'distance_to_t3', 
            'candle_position', 'distance_to_trend_start'
        ];
        
        $this->validateFieldsExist($data, $requiredFields, $data['type']);
        
        $type = $data['type'];
        $symbol = $data['symbol'];
        $side = $data['side'];
        $entry = $data['entry'];
        
        // Extract trend signal data
        $trendData = $this->extractTrendData($data);
        
        $this->logMessage('debug_log.txt', "Processing $type signal with entry: $entry");
        
        $this->telegram->sendAdaptiveAlert(
            $type, $symbol, $side, $entry,
            $trendData['candle_size'], $trendData['distance_to_t3'],
            $trendData['candle_position'], $trendData['distance_to_trend_start'],
            $trendData['t3_status'], $trendData['t3_distance'],
            $trendData['t3_strength'], $trendData['t3_squeeze'],
            $trendData['conv_bars'], $trendData['div_bars']
        );
        return $this->processTradingSignal($data);
        // return [
        //     'success' => true,
        //     'message' => "$type alert sent successfully",
        //     'type' => $type,
        //     'symbol' => $symbol,
        //     'side' => $side,
        //     'entry' => $entry
        // ];
    }

    /**
     * Process trading signals with entries, targets, and stop loss
     */
    private function processTradingSignal(array $data): array
    {
        // Validate trading signal specific fields
        //$this->validateTradingSignalData($data);
        
        $symbol = strtoupper(trim($data['symbol']));
        $side = strtoupper(trim($data['side']));
        
        if (!is_null($data['leverage'])) {
            $leverage = intval($data['leverage']);
        } else {
            $leverage = 6;
        }
        
        if (!is_null($data['entries'])) {
            $entries = $data['entries'];
        } else {
            try {
                $entry1 = $this->getMarketPrice($symbol);
                
                // Calculate entry2 based on position type
                // Long: 2% less than entry1 (better entry on dip)
                // Short: 2% more than entry1 (better entry on pump)
                if ($side === 'LONG') {
                    $entry2 = $entry1 * 0.98; // 2% less
                } else { // SHORT
                    $entry2 = $entry1 * 1.02; // 2% more
                }
                
                $entries = [$entry1, $entry2];
                
            } catch (Exception $e) {
                $this->logError("Could not fetch market price for $symbol: " . $e->getMessage());
                throw new Exception("Could not fetch market price for $symbol: " . $e->getMessage());
            }
        }
        
        if (!is_null($data['targets'])) {
            $targets = $data['targets'];
        } else {
            $targets = ["%2"];
        }
        
        if (!is_null($data['stop_loss'])) {
            $stopLoss = $data['stop_loss'];
        } else {
            $stopLoss = ["%5"];
        }
        
        
        
        $isLong = ($side === 'LONG');
        $entryPrice = floatval($entries[0]); // Use first entry as base for percentage calculations
        
        // Process entries
        $processedEntries = $this->processEntries($entries);
        
        // Process targets
        $processedTargets = $this->processTargets($targets, $entryPrice, $isLong);
        
        // Process stop loss
        $processedStopLoss = $this->processStopLoss($stopLoss, $entryPrice, $isLong);
        
        // Export signal to database via API
        $exportResult = $this->exportSignal([
            'symbol' => $symbol,
            'side' => $side,
            'leverage' => $leverage,
            'entries' => $entries, // Keep original entries for API
            'targets' => $targets, // Keep original targets for API
            'stop_loss' => $stopLoss, // Keep original stop_loss for API
            'external_signal_id' => $data['external_signal_id'] ?? null,
            'confidence_score' => $data['confidence_score'] ?? null,
            'notes' => $data['notes'] ?? null,
            'risk_reward_ratio' => $data['risk_reward_ratio'] ?? null
        ]);
        
        // Send Telegram notification if available
        $this->logMessage('debug_log.txt', "Checking if sendTradingSignalAlert method exists...");
        if (method_exists($this->telegram, 'sendTradingSignalAlert')) {
            $this->logMessage('debug_log.txt', "sendTradingSignalAlert method exists, attempting to send notification");
            $telegramResult = $this->telegram->sendTradingSignalAlert($symbol, $side, $processedEntries, $processedTargets, $processedStopLoss, $leverage);
            $this->logMessage('debug_log.txt', "Trading signal notification result: " . json_encode($telegramResult));
        } else {
            $this->logMessage('debug_log.txt', "sendTradingSignalAlert method does NOT exist in telegram object");
        }
        
        return [
            'success' => true,
            'signal_id' => $exportResult['signal_id'] ?? null,
            'message' => 'Trading signal processed successfully',
            'api_response' => $exportResult,
            'processed_data' => [
                'symbol' => $symbol,
                'side' => $side,
                'leverage' => $leverage,
                'entries' => [
                    'entry_market' => $processedEntries['entry_market'],
                    'entry_2' => $processedEntries['entry_2'],
                    'entry_3' => $processedEntries['entry_3']
                ],
                'targets' => [
                    'take_profit_1' => $processedTargets['take_profit_1'],
                    'take_profit_2' => $processedTargets['take_profit_2'],
                    'take_profit_3' => $processedTargets['take_profit_3'],
                    'take_profit_4' => $processedTargets['take_profit_4'],
                    'take_profit_5' => $processedTargets['take_profit_5']
                ],
                'stop_loss' => $processedStopLoss,
                'source' => 'Webhook Import'
            ]
        ];
    }

    /**
     * Export signal by generating JSON and calling the import API
     */
    private function exportSignal(array $signalData): array
    {
        // TEMPORARY: Skip API call completely for debugging
        $skipApi = EnvLoader::getBool('SKIP_API_EXPORT', false);
        
        if ($skipApi) {
            $this->logMessage('debug_log.txt', "API export skipped via SKIP_API_EXPORT flag");
            return [
                'success' => true,
                'signal_id' => 'temp-bypass-' . time(),
                'message' => 'Signal processed (API export bypassed)',
                'bypassed' => true
            ];
        }
        
        try {
            // Generate JSON payload for the API
            $jsonPayload = $this->generateSignalJSON($signalData);
            
            // Get API endpoint URL from environment or use default
            $apiEndpoint = EnvLoader::get('SIGNAL_IMPORT_API_URL', '/api/import_signal.php');
            
            // Make sure we have a full URL
            if (strpos($apiEndpoint, 'http') !== 0) {
                $baseUrl = EnvLoader::get('BASE_URL', '');
                if (empty($baseUrl)) {
                    // Construct base URL from current request
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $baseUrl = $protocol . '://' . $host;
                }
                $apiEndpoint = rtrim($baseUrl, '/') . '/' . ltrim($apiEndpoint, '/');
            }
            
            // Log the full URL being called
            $this->logMessage('debug_log.txt', "Attempting to call API: $apiEndpoint");
            
            // Check if the file exists locally first
            $localPath = $_SERVER['DOCUMENT_ROOT'] . parse_url($apiEndpoint, PHP_URL_PATH);
            $this->logMessage('debug_log.txt', "Checking local file: $localPath");
            $this->logMessage('debug_log.txt', "File exists: " . (file_exists($localPath) ? 'YES' : 'NO'));
            
            // Call the import API
            $response = $this->callImportAPI($apiEndpoint, $jsonPayload);
            
            $this->logMessage('debug_log.txt', "Signal exported successfully to API: " . $apiEndpoint);
            
            return $response;
            
        } catch (Exception $e) {
            $this->logError("Export signal failed: " . $e->getMessage());
            
            // Return a fallback response instead of throwing exception
            // This prevents the entire webhook from failing if the API is down
            return [
                'success' => false,
                'error' => 'API export failed: ' . $e->getMessage(),
                'signal_id' => 'fallback-' . time(),
                'message' => 'Signal processed but API export failed'
            ];
        }
    }

    /**
     * Generate JSON payload for signal import API
     */
    private function generateSignalJSON(array $signalData): array
    {
        $json = [
            'symbol' => $signalData['symbol'],
            'side' => $signalData['side'],
            'leverage' => $signalData['leverage'],
            'entries' => $signalData['entries'],
            'targets' => $signalData['targets'],
            'stop_loss' => $signalData['stop_loss']
        ];
        
        // Add optional fields if they exist
        $optionalFields = ['external_signal_id', 'confidence_score', 'notes', 'risk_reward_ratio'];
        foreach ($optionalFields as $field) {
            if (isset($signalData[$field]) && $signalData[$field] !== null) {
                $json[$field] = $signalData[$field];
            }
        }
        
        $this->logMessage('debug_log.txt', "Generated JSON payload: " . json_encode($json, JSON_PRETTY_PRINT));
        
        return $json;
    }

    /**
     * Call the import API with the generated JSON
     */
    private function callImportAPI(string $apiEndpoint, array $jsonPayload): array
    {
        // Create a temp file for verbose output
        $verboseHandle = fopen('php://temp', 'rw+');
        
        // Initialize cURL
        $ch = curl_init();
        
        // Set cURL options
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiEndpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($jsonPayload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false, // For development - should be true in production
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_VERBOSE => true, // Enable verbose output
            CURLOPT_STDERR => $verboseHandle // Capture verbose output
        ]);
        
        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        
        // Get verbose output
        $verboseOutput = '';
        if ($verboseHandle) {
            rewind($verboseHandle);
            $verboseOutput = stream_get_contents($verboseHandle);
            fclose($verboseHandle);
        }
        
        curl_close($ch);
        
        // Enhanced logging
        $this->logMessage('debug_log.txt', "=== API CALL DETAILED DEBUG ===");
        $this->logMessage('debug_log.txt', "URL: $apiEndpoint");
        $this->logMessage('debug_log.txt', "Effective URL: $effectiveUrl");
        $this->logMessage('debug_log.txt', "HTTP Code: $httpCode");
        $this->logMessage('debug_log.txt', "Content Type: $contentType");
        $this->logMessage('debug_log.txt', "Total Time: {$totalTime}s");
        $this->logMessage('debug_log.txt', "cURL Error: " . ($curlError ?: 'None'));
        $this->logMessage('debug_log.txt', "Response Length: " . strlen($response));
        $this->logMessage('debug_log.txt', "Raw Response (first 500 chars): " . substr($response, 0, 500));
        $this->logMessage('debug_log.txt', "Raw Response (full): " . $response);
        $this->logMessage('debug_log.txt', "Verbose Output: " . $verboseOutput);
        $this->logMessage('debug_log.txt', "JSON Payload Sent: " . json_encode($jsonPayload, JSON_PRETTY_PRINT));
        $this->logMessage('debug_log.txt', "=== END DEBUG ===");
        
        // Handle cURL errors
        if ($response === false || !empty($curlError)) {
            throw new Exception("cURL request failed: " . $curlError);
        }
        
        // Handle HTTP errors
        if ($httpCode >= 400) {
            // Special handling for 404 - API endpoint doesn't exist
            if ($httpCode == 404) {
                throw new Exception("API endpoint not found: $apiEndpoint. Please check if the import_signal.php file exists.");
            }
            
            $this->logError("API request failed with HTTP code: $httpCode, Response: $response");
            throw new Exception("API request failed with HTTP code: $httpCode. Response: " . substr($response, 0, 200));
        }
        
        // Check if response is empty
        $trimmedResponse = trim($response);
        if (empty($trimmedResponse)) {
            throw new Exception("Empty response from API. This usually means the API file has a fatal error or doesn't exist.");
        }
        
        // Check if response looks like JSON
        if (!in_array($trimmedResponse[0], ['{', '['])) {
            $this->logError("Non-JSON response received: $response");
            throw new Exception("API returned non-JSON response. Content: " . substr($response, 0, 200));
        }
        
        // Decode response
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logError("JSON decode failed. Error: " . json_last_error_msg() . ", Response: $response");
            throw new Exception("Invalid JSON response from API: " . json_last_error_msg() . ". Response: " . substr($response, 0, 200));
        }
        
        // Check if API returned success
        if (!isset($decodedResponse['success']) || !$decodedResponse['success']) {
            $errorMsg = $decodedResponse['error'] ?? 'Unknown API error';
            throw new Exception("API returned error: " . $errorMsg);
        }
        
        $this->logMessage('debug_log.txt', "API Response: " . $response);
        
        return $decodedResponse;
    }

    /**
     * Validate trading signal specific data
     */
    private function validateTradingSignalData(array $data): void
    {
        $required = ['symbol', 'side', 'leverage', 'entries', 'targets', 'stop_loss'];
        $this->validateFieldsExist($data, $required, 'TRADING_SIGNAL');
        
        // Validate side
        if (!in_array(strtoupper($data['side']), ['LONG', 'SHORT'])) {
            throw new Exception("Field 'side' must be 'LONG' or 'SHORT'");
        }
        
        // Validate arrays
        if (!is_array($data['entries']) || empty($data['entries'])) {
            throw new Exception("Field 'entries' must be a non-empty array");
        }
        
        if (!is_array($data['targets']) || empty($data['targets'])) {
            throw new Exception("Field 'targets' must be a non-empty array");
        }
        
        // Validate array sizes
        if (count($data['entries']) > 3) {
            throw new Exception("Maximum 3 entries allowed");
        }
        
        if (count($data['targets']) > 5) {
            throw new Exception("Maximum 5 targets allowed");
        }
        
        // Validate leverage
        $leverage = intval($data['leverage']);
        if ($leverage < 1 || $leverage > 100) {
            throw new Exception("Leverage must be between 1 and 100");
        }
    }

    /**
     * Process entry prices
     */
    private function processEntries(array $entries): array
    {
        return [
            'entry_market' => isset($entries[0]) ? floatval($entries[0]) : null,
            'entry_2' => isset($entries[1]) ? floatval($entries[1]) : null,
            'entry_3' => isset($entries[2]) ? floatval($entries[2]) : null
        ];
    }

    /**
     * Process target prices with percentage support
     */
    private function processTargets(array $targets, float $entryPrice, bool $isLong): array
    {
        $processedTargets = [
            'take_profit_1' => null,
            'take_profit_2' => null,
            'take_profit_3' => null,
            'take_profit_4' => null,
            'take_profit_5' => null
        ];
        
        $targetKeys = array_keys($processedTargets);
        
        for ($i = 0; $i < count($targets) && $i < 5; $i++) {
            $target = $targets[$i];
            $percentage = $this->parsePercentage($target);
            
            if ($percentage !== null) {
                // Calculate from percentage
                $processedTargets[$targetKeys[$i]] = $this->calculatePriceFromPercentage($entryPrice, $percentage, $isLong, true);
            } else {
                // Use absolute price
                $processedTargets[$targetKeys[$i]] = floatval($target);
            }
        }
        
        return $processedTargets;
    }

    /**
     * Process stop loss with percentage support
     */
    private function processStopLoss($stopLoss, float $entryPrice, bool $isLong): float
    {
        // Handle array input (take first element)
        if (is_array($stopLoss)) {
            $stopLossValue = $stopLoss[0] ?? null;
        } else {
            $stopLossValue = $stopLoss;
        }

        if ($stopLossValue === null) {
            // Default to 5% if no stop loss provided
            $stopLossValue = '5%';
        }

        $percentage = $this->parsePercentage($stopLossValue);

        if ($percentage !== null) {
            // Calculate from percentage
            return $this->calculatePriceFromPercentage($entryPrice, $percentage, $isLong, false);
        } else {
            // Use absolute price
            return floatval($stopLossValue);
        }
    }

    /**
     * Parse percentage value from string
     */
    private function parsePercentage($value): ?float
    {
        if (is_string($value) && strpos($value, '%') !== false) {
            return floatval(str_replace('%', '', $value)) / 100;
        }
        return null;
    }

    /**
     * Calculate price from percentage
     */
    private function calculatePriceFromPercentage(float $entryPrice, float $percentage, bool $isLong, bool $isTarget = true): float
    {
        if ($isTarget) {
            if ($isLong) {
                return $entryPrice * (1 + $percentage); // LONG targets: price goes up
            } else {
                return $entryPrice * (1 - $percentage); // SHORT targets: price goes down
            }
        } else {
            // Stop loss calculation
            if ($isLong) {
                return $entryPrice * (1 - $percentage); // LONG stop: price goes down
            } else {
                return $entryPrice * (1 + $percentage); // SHORT stop: price goes up
            }
        }
    }

    /**
     * Extract trend signal data with defaults for optional fields
     */
    private function extractTrendData(array $data): array
    {
        return [
            'candle_size' => $data['candle_size'],
            'distance_to_t3' => $data['distance_to_t3'],
            'candle_position' => $data['candle_position'],
            'distance_to_trend_start' => $data['distance_to_trend_start'],
            't3_status' => $data['t3_status'] ?? null,
            't3_distance' => $data['t3_distance'] ?? null,
            't3_strength' => $data['t3_strength'] ?? null,
            't3_squeeze' => $data['t3_squeeze'] ?? null,
            'conv_bars' => $data['conv_bars'] ?? null,
            'div_bars' => $data['div_bars'] ?? null
        ];
    }

    /**
     * Validate that specific fields exist in data
     */
    private function validateFieldsExist(array $data, array $fields, string $context): void
    {
        $missingFields = array_filter($fields, function($field) use ($data) { return !isset($data[$field]); });
        
        if (!empty($missingFields)) {
            throw new Exception(
                "Missing required fields for $context: " . implode(', ', $missingFields)
            );
        }
    }

    /**
     * Get database connection
     */
    private function getDbConnection(): PDO
    {
        $host = EnvLoader::get('DB_HOST');
        $name = EnvLoader::get('DB_NAME');
        $user = EnvLoader::get('DB_USER');
        $pass = EnvLoader::get('DB_PASS');
        
        try {
            $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Log debug information about the incoming request
     */
    private function logDebugInfo(string $json, ?array $data): void
    {
        $debugLog = [
            'timestamp' => date('Y-m-d H:i:s'),
            'raw_input' => $json,
            'parsed_data' => $data,
            'json_decode_error' => json_last_error_msg(),
            'content_length' => strlen($json),
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unknown'
        ];
        
        $this->logMessage(
            'debug_log.txt', 
            "=== WEBHOOK DEBUG START ===\n" . 
            json_encode($debugLog, JSON_PRETTY_PRINT) . 
            "\n=== END ===\n"
        );
    }

    /**
     * Log incoming data
     */
    private function logIncomingData(array $data): void
    {
        $this->logMessage(
            'signals_log.json', 
            "INCOMING:\n" . json_encode($data, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Generic logging method
     */
    private function logMessage(string $filename, string $message): void
    {
        if ($this->enableLogging) {
            file_put_contents(
                $filename, 
                date('Y-m-d H:i:s') . " - " . $message . "\n\n", 
                FILE_APPEND
            );
        }
    }

    /**
     * Log errors to error log file
     */
    private function logError(string $message): void
    {
        $logFile = EnvLoader::get('LOG_FILE', __DIR__ . '/errors.log');
        $timestamp = date('Y-m-d H:i:s');
        $fullMessage = "[$timestamp] $message\n";
        
        file_put_contents($logFile, $fullMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * Handle errors with comprehensive logging and response
     */
    private function handleError(Exception $e, array $data): void
    {
        $errorDetails = [
            'timestamp' => date('Y-m-d H:i:s'),
            'error_message' => $e->getMessage(),
            'error_line' => $e->getLine(),
            'error_file' => $e->getFile(),
            'stack_trace' => $e->getTraceAsString(),
            'input_data' => $data
        ];

        $this->logMessage(
            'debug_log.txt',
            "=== ERROR DETAILS ===\n" .
            json_encode($errorDetails, JSON_PRETTY_PRINT) .
            "\n=== END ERROR ==="
        );

        $this->logError("Signal processing error: " . $e->getMessage());

        // Send error notification if Telegram is available
        if ($this->telegram) {
            $this->logMessage('debug_log.txt', "Attempting to send error notification via Telegram");
            $telegramResult = $this->telegram->sendErrorNotification($e->getMessage(), $e->getLine());
            $this->logMessage('debug_log.txt', "Telegram error notification result: " . json_encode($telegramResult));
        } else {
            $this->logMessage('debug_log.txt', "Telegram object not available for error notification");
        }

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'debug_info' => $errorDetails
        ], JSON_PRETTY_PRINT);
    }
    
    /**
     * Get current market price from BingX API
     * @param string $symbol Trading symbol (e.g., 'BTCUSDT')
     * @return float Current market price
     * @throws Exception If API request fails or symbol not found
     */
    private function getMarketPrice(string $symbol): float
    {
        try {
            // BingX API endpoint for 24hr ticker price statistics
            $url = "https://open-api.bingx.com/openApi/swap/v2/quote/ticker";
            
            // Format symbol for BingX (add hyphen before USDT)
            $formattedSymbol = str_replace("USDT", "-USDT", $symbol);
            $formattedSymbol = str_replace("--", "-", $formattedSymbol);
            
            // Prepare parameters
            $params = [
                'symbol' => $formattedSymbol
            ];
            
            // Build query string
            $queryString = http_build_query($params);
            $fullUrl = $url . '?' . $queryString;
            
            // Initialize cURL
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $fullUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'User-Agent: PHP-Trading-Bot/1.0'
                ],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_FOLLOWLOCATION => true
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            // Check for cURL errors
            if ($error) {
                throw new Exception("cURL error: " . $error);
            }
            
            // Check HTTP status code
            if ($httpCode !== 200) {
                throw new Exception("HTTP error: " . $httpCode . " - Response: " . $response);
            }
            
            // Decode JSON response
            $data = json_decode($response, true);
            
            // Check if JSON decoding was successful
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON decode error: " . json_last_error_msg());
            }
            
            // Check API response structure
            if (!isset($data['code']) || $data['code'] !== 0) {
                $errorMsg = $data['msg'] ?? 'Unknown API error';
                throw new Exception("BingX API error: " . $errorMsg);
            }
            
            // Extract price data
            if (!isset($data['data']['lastPrice'])) {
                throw new Exception("Price data not found in API response for symbol: " . $formattedSymbol);
            }
            
            $price = floatval($data['data']['lastPrice']);
            
            // Validate price
            if ($price <= 0) {
                throw new Exception("Invalid price received: " . $price . " for symbol: " . $formattedSymbol);
            }
            
            // Log successful price fetch
            $this->logMessage('debug_log.txt', "Successfully fetched market price for {$formattedSymbol}: {$price}");
            
            return $price;
            
        } catch (Exception $e) {
            // Log the error
            $this->logError("Error fetching market price for {$symbol}: " . $e->getMessage());
            
            // Re-throw the exception to be handled by calling code
            throw new Exception("Failed to get market price for {$symbol}: " . $e->getMessage());
        }
    }
}

// Initialize and run the webhook handler
try {
    $handler = new SignalWebhookHandler();
    $handler->processWebhook();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to initialize webhook handler: ' . $e->getMessage()
    ]);
}

?>