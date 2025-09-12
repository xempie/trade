<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    echo json_encode([
        'success' => true,
        'debug' => 'This is the debug version - new code deployed',
        'timestamp' => date('Y-m-d H:i:s'),
        'server_time' => time()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>