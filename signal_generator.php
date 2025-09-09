<?php

/**
 * Generate and import a trading signal via JSON API
 * 
 * @param string $symbol - Trading pair (e.g., "BTCUSDT")
 * @param string $side - Position side ("LONG" or "SHORT")
 * @param int $leverage - Leverage amount (1-100)
 * @param array $entries - Entry prices [entry1, entry2, entry3] (1-3 entries)
 * @param array $targets - Take profit targets (1-5 targets, can be absolute prices or percentages like "15%")
 * @param mixed $stopLoss - Stop loss (absolute price or percentage like "6%")
 * @param float $confidenceScore - Signal confidence (0.0-10.0) [optional]
 * @param string $notes - Signal description [optional]
 * @param string $externalSignalId - External reference ID [optional]
 * @param float $riskRewardRatio - Risk/reward ratio [optional]
 * @return array - API response with success status and signal details
 */
function generateAndImportSignal($symbol, $side, $leverage, $entries, $targets, $stopLoss, $confidenceScore = null, $notes = null, $externalSignalId = null, $riskRewardRatio = null) {
    
    // Validate required parameters
    if (empty($symbol) || empty($side) || empty($leverage) || empty($entries) || empty($targets) || is_null($stopLoss)) {
        return [
            'success' => false,
            'error' => 'Missing required parameters: symbol, side, leverage, entries, targets, stopLoss'
        ];
    }
    
    // Build JSON signal data
    $signalData = [
        'symbol' => strtoupper(trim($symbol)),
        'side' => strtoupper(trim($side)),
        'leverage' => (int)$leverage,
        'entries' => array_values($entries), // Ensure numeric array
        'targets' => array_values($targets), // Ensure numeric array
        'stop_loss' => $stopLoss
    ];
    
    // Add optional fields if provided
    if ($confidenceScore !== null) {
        $signalData['confidence_score'] = (float)$confidenceScore;
    }
    
    if ($notes !== null) {
        $signalData['notes'] = trim($notes);
    }
    
    if ($externalSignalId !== null) {
        $signalData['external_signal_id'] = trim($externalSignalId);
    }
    
    if ($riskRewardRatio !== null) {
        $signalData['risk_reward_ratio'] = (float)$riskRewardRatio;
    }
    
    // Convert to JSON
    $jsonPayload = json_encode($signalData);
    
    if ($jsonPayload === false) {
        return [
            'success' => false,
            'error' => 'Failed to encode signal data to JSON: ' . json_last_error_msg()
        ];
    }
    
    // Call the import API
    $apiUrl = 'http://localhost/trade/api/import_signal.php'; // Adjust URL as needed
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonPayload)
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Handle cURL errors
    if ($curlError) {
        return [
            'success' => false,
            'error' => 'API call failed: ' . $curlError
        ];
    }
    
    // Handle HTTP errors
    if ($httpCode !== 200) {
        return [
            'success' => false,
            'error' => "API returned HTTP {$httpCode}",
            'response' => $response
        ];
    }
    
    // Parse API response
    $apiResponse = json_decode($response, true);
    
    if ($apiResponse === null) {
        return [
            'success' => false,
            'error' => 'Invalid JSON response from API: ' . json_last_error_msg(),
            'raw_response' => $response
        ];
    }
    
    // Return the API response with additional metadata
    $apiResponse['generated_json'] = $signalData;
    $apiResponse['api_call_info'] = [
        'url' => $apiUrl,
        'http_code' => $httpCode,
        'payload_size' => strlen($jsonPayload)
    ];
    
    return $apiResponse;
}

// Example usage functions for different scenarios

/**
 * Generate LONG signal with percentage targets
 */
function generateLongSignalWithPercentages($symbol, $leverage, $entries, $targetPercentages, $stopLossPercentage, $notes = null) {
    return generateAndImportSignal(
        $symbol,
        'LONG', 
        $leverage,
        $entries,
        $targetPercentages, // e.g., ["8%", "15%", "25%"]
        $stopLossPercentage, // e.g., "6%"
        null, // confidence_score
        $notes,
        null, // external_signal_id
        null  // risk_reward_ratio
    );
}

/**
 * Generate SHORT signal with absolute prices
 */
function generateShortSignalWithPrices($symbol, $leverage, $entries, $targetPrices, $stopLossPrice, $notes = null) {
    return generateAndImportSignal(
        $symbol,
        'SHORT',
        $leverage, 
        $entries,
        $targetPrices, // e.g., [2600.00, 2400.00, 2200.00]
        $stopLossPrice, // e.g., 3000.00
        null, // confidence_score
        $notes,
        null, // external_signal_id 
        null  // risk_reward_ratio
    );
}

/**
 * Generate signal with full parameters
 */
function generateFullSignal($symbol, $side, $leverage, $entries, $targets, $stopLoss, $confidenceScore, $notes, $externalId, $riskReward) {
    return generateAndImportSignal(
        $symbol,
        $side,
        $leverage,
        $entries,
        $targets,
        $stopLoss,
        $confidenceScore,
        $notes,
        $externalId,
        $riskReward
    );
}

// Usage Examples (uncomment to test):

/*
// Example 1: LONG with percentages
$result1 = generateLongSignalWithPercentages(
    'BTCUSDT',
    10,
    [42500.00, 42000.00, 41500.00],
    ['8%', '15%', '25%', '35%', '50%'],
    '6%',
    'BTC breakout above 42K resistance'
);
var_dump($result1);

// Example 2: SHORT with absolute prices
$result2 = generateShortSignalWithPrices(
    'ETHUSDT',
    8,
    [2800.00, 2850.00],
    [2600.00, 2400.00, 2200.00],
    3000.00,
    'ETH rejection at key resistance'
);
var_dump($result2);

// Example 3: Full signal with all parameters
$result3 = generateFullSignal(
    'ADAUSDT',
    'LONG',
    5,
    [0.85, 0.82],
    ['12%', '25%', '40%'],
    '8%',
    8.5,
    'ADA bullish flag pattern completion',
    'TG_ADA_001_20250909',
    3.2
);
var_dump($result3);

// Example 4: Simple single entry/target signal
$result4 = generateAndImportSignal(
    'DOGEUSDT',
    'LONG',
    4,
    [0.08],
    ['25%'],
    '15%',
    6.0,
    'DOGE breakout potential'
);
var_dump($result4);
*/

?>