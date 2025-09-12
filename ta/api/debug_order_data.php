<?php
// Debug endpoint to see exactly what data is being sent
require_once __DIR__ . '/../auth/api_protection.php';
protectAPI();

header('Content-Type: application/json');

try {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    $debug = [
        'success' => true,
        'received_data' => $input,
        'raw_input' => $rawInput,
        'required_fields' => ['symbol', 'direction', 'leverage', 'enabled_entries'],
        'validation' => []
    ];
    
    // Check each required field
    $required = ['symbol', 'direction', 'leverage', 'enabled_entries'];
    foreach ($required as $field) {
        $debug['validation'][$field] = [
            'exists' => isset($input[$field]),
            'value' => $input[$field] ?? null,
            'type' => gettype($input[$field] ?? null)
        ];
    }
    
    // Check enabled_entries structure
    if (isset($input['enabled_entries']) && is_array($input['enabled_entries'])) {
        $debug['enabled_entries_analysis'] = [];
        foreach ($input['enabled_entries'] as $i => $entry) {
            $debug['enabled_entries_analysis'][$i] = [
                'type' => $entry['type'] ?? 'missing',
                'price' => $entry['price'] ?? 'missing', 
                'margin' => $entry['margin'] ?? 'missing',
                'valid' => isset($entry['type']) && isset($entry['margin'])
            ];
        }
    }
    
    echo json_encode($debug, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'raw_input' => $rawInput ?? 'No input received'
    ], JSON_PRETTY_PRINT);
}
?>