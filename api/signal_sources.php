<?php
/**
 * Signal Sources Management API
 * Handles CRUD operations for signal sources and automation settings
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

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

try {
    $pdo = getDbConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    switch ($method) {
        case 'GET':
            handleGet($pdo, $action);
            break;
        case 'POST':
            handlePost($pdo, $action);
            break;
        case 'PUT':
            handlePut($pdo, $action);
            break;
        case 'DELETE':
            handleDelete($pdo, $action);
            break;
        default:
            throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function handleGet($pdo, $action) {
    switch ($action) {
        case 'sources':
            getSources($pdo);
            break;
        case 'source':
            getSource($pdo, $_GET['id'] ?? null);
            break;
        case 'settings':
            getAutomationSettings($pdo);
            break;
        case 'dashboard':
            getAutomationDashboard($pdo);
            break;
        case 'queue':
            getSignalQueue($pdo);
            break;
        case 'logs':
            getProcessingLogs($pdo);
            break;
        default:
            getSources($pdo);
    }
}

function handlePost($pdo, $action) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'source':
            createSource($pdo, $input);
            break;
        case 'setting':
            updateSetting($pdo, $input);
            break;
        case 'test_api':
            testApiConnection($pdo, $input);
            break;
        default:
            throw new Exception('Invalid action');
    }
}

function handlePut($pdo, $action) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'source':
            updateSource($pdo, $_GET['id'] ?? null, $input);
            break;
        case 'toggle_automation':
            toggleAutomation($pdo, $_GET['id'] ?? null, $input);
            break;
        default:
            throw new Exception('Invalid action');
    }
}

function handleDelete($pdo, $action) {
    switch ($action) {
        case 'source':
            deleteSource($pdo, $_GET['id'] ?? null);
            break;
        case 'clear_queue':
            clearSignalQueue($pdo);
            break;
        default:
            throw new Exception('Invalid action');
    }
}

function getSources($pdo) {
    $sql = "SELECT s.*, 
                   COUNT(asq.id) as queued_signals,
                   MAX(asq.created_at) as last_signal_queued
            FROM signal_sources s
            LEFT JOIN api_signal_queue asq ON s.id = asq.source_id 
                AND asq.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY s.id
            ORDER BY s.auto_create_signals DESC, s.name";
    
    $stmt = $pdo->query($sql);
    $sources = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $sources
    ]);
}

function getSource($pdo, $id) {
    if (!$id) {
        throw new Exception('Source ID required');
    }
    
    $stmt = $pdo->prepare("SELECT * FROM signal_sources WHERE id = ?");
    $stmt->execute([$id]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$source) {
        throw new Exception('Source not found');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $source
    ]);
}

function createSource($pdo, $data) {
    $required = ['name', 'type'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }
    
    $sql = "INSERT INTO signal_sources (
        name, type, api_endpoint, api_key, api_headers, channel_url,
        description, is_active, auto_create_signals, fetch_interval_minutes
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $data['name'],
        $data['type'],
        $data['api_endpoint'] ?? null,
        $data['api_key'] ?? null,
        isset($data['api_headers']) ? json_encode($data['api_headers']) : null,
        $data['channel_url'] ?? null,
        $data['description'] ?? null,
        $data['is_active'] ?? true,
        $data['auto_create_signals'] ?? false,
        $data['fetch_interval_minutes'] ?? 5
    ]);
    
    $sourceId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Signal source created successfully',
        'source_id' => $sourceId
    ]);
}

function updateSource($pdo, $id, $data) {
    if (!$id) {
        throw new Exception('Source ID required');
    }
    
    $updates = [];
    $params = [];
    
    $allowedFields = [
        'name', 'type', 'api_endpoint', 'api_key', 'api_headers', 
        'channel_url', 'description', 'is_active', 'auto_create_signals', 
        'fetch_interval_minutes'
    ];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            if ($field === 'api_headers' && is_array($data[$field])) {
                $params[] = json_encode($data[$field]);
            } else {
                $params[] = $data[$field];
            }
        }
    }
    
    if (empty($updates)) {
        throw new Exception('No valid fields to update');
    }
    
    $params[] = $id;
    $sql = "UPDATE signal_sources SET " . implode(', ', $updates) . " WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    echo json_encode([
        'success' => true,
        'message' => 'Signal source updated successfully'
    ]);
}

function deleteSource($pdo, $id) {
    if (!$id) {
        throw new Exception('Source ID required');
    }
    
    // Check if source has signals
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM signals WHERE source_id = ?");
    $stmt->execute([$id]);
    $signalCount = $stmt->fetch()['count'];
    
    if ($signalCount > 0) {
        throw new Exception("Cannot delete source with $signalCount existing signals");
    }
    
    $stmt = $pdo->prepare("DELETE FROM signal_sources WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Signal source deleted successfully'
    ]);
}

function getAutomationSettings($pdo) {
    $stmt = $pdo->query("SELECT * FROM signal_automation_settings ORDER BY setting_key");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formattedSettings = [];
    foreach ($settings as $setting) {
        $value = $setting['setting_value'];
        
        // Convert value based on data type
        switch ($setting['data_type']) {
            case 'BOOLEAN':
                $value = strtolower($value) === 'true';
                break;
            case 'INTEGER':
                $value = (int) $value;
                break;
            case 'DECIMAL':
                $value = (float) $value;
                break;
            case 'JSON':
                $value = json_decode($value, true);
                break;
        }
        
        $formattedSettings[$setting['setting_key']] = [
            'value' => $value,
            'type' => $setting['data_type'],
            'description' => $setting['description']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $formattedSettings
    ]);
}

function updateSetting($pdo, $data) {
    if (!isset($data['key']) || !isset($data['value'])) {
        throw new Exception('Setting key and value are required');
    }
    
    $dataType = $data['type'] ?? 'STRING';
    $value = $data['value'];
    
    // Convert value to string for storage
    if (is_bool($value)) {
        $value = $value ? 'true' : 'false';
        $dataType = 'BOOLEAN';
    } elseif (is_array($value)) {
        $value = json_encode($value);
        $dataType = 'JSON';
    } elseif (is_numeric($value)) {
        $dataType = strpos($value, '.') !== false ? 'DECIMAL' : 'INTEGER';
    }
    
    $sql = "INSERT INTO signal_automation_settings (setting_key, setting_value, data_type, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                data_type = VALUES(data_type),
                updated_at = NOW()";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$data['key'], $value, $dataType]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Setting updated successfully'
    ]);
}

function getAutomationDashboard($pdo) {
    // Use the dashboard view
    $stmt = $pdo->query("SELECT * FROM signal_automation_dashboard");
    $dashboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get overall statistics
    $statsStmt = $pdo->query("
        SELECT 
            COUNT(*) as total_sources,
            SUM(auto_create_signals) as active_automation_sources,
            SUM(total_signals) as total_signals_ever,
            AVG(win_rate) as avg_win_rate,
            SUM(total_pnl) as total_pnl_all_sources
        FROM signal_sources
        WHERE is_active = TRUE
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent activity (last 24 hours)
    $activityStmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%H:00') as hour,
            COUNT(*) as signals_queued
        FROM api_signal_queue 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d %H')
        ORDER BY hour
    ");
    $activity = $activityStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'sources' => $dashboard,
            'statistics' => $stats,
            'activity_24h' => $activity
        ]
    ]);
}

function getSignalQueue($pdo) {
    $limit = $_GET['limit'] ?? 50;
    $status = $_GET['status'] ?? null;
    
    $sql = "SELECT asq.*, ss.name as source_name 
            FROM api_signal_queue asq
            JOIN signal_sources ss ON asq.source_id = ss.id";
    
    $params = [];
    if ($status) {
        $sql .= " WHERE asq.queue_status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY asq.created_at DESC LIMIT ?";
    $params[] = (int) $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $queue = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $queue
    ]);
}

function getProcessingLogs($pdo) {
    $limit = $_GET['limit'] ?? 100;
    $sourceId = $_GET['source_id'] ?? null;
    
    $sql = "SELECT * FROM signal_processing_log";
    $params = [];
    
    if ($sourceId) {
        $sql .= " WHERE source_id = ?";
        $params[] = $sourceId;
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT ?";
    $params[] = (int) $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $logs
    ]);
}

function testApiConnection($pdo, $data) {
    if (!isset($data['api_endpoint'])) {
        throw new Exception('API endpoint required');
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $data['api_endpoint'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    
    if (!empty($data['api_key'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $data['api_key'],
            'Content-Type: application/json'
        ]);
    }
    
    $startTime = microtime(true);
    $response = curl_exec($ch);
    $duration = (microtime(true) - $startTime) * 1000;
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    $success = $response !== false && $httpCode >= 200 && $httpCode < 300;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'connection_successful' => $success,
            'http_code' => $httpCode,
            'response_time_ms' => round($duration),
            'response_size' => strlen($response),
            'can_parse_json' => json_decode($response) !== null
        ]
    ]);
}

function toggleAutomation($pdo, $id, $data) {
    if (!$id) {
        throw new Exception('Source ID required');
    }
    
    $enabled = $data['enabled'] ?? false;
    
    $stmt = $pdo->prepare("UPDATE signal_sources SET auto_create_signals = ? WHERE id = ?");
    $stmt->execute([$enabled, $id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Automation ' . ($enabled ? 'enabled' : 'disabled') . ' for source'
    ]);
}

function clearSignalQueue($pdo) {
    $status = $_GET['status'] ?? 'FAILED';
    
    $stmt = $pdo->prepare("DELETE FROM api_signal_queue WHERE queue_status = ?");
    $stmt->execute([$status]);
    
    $deleted = $stmt->rowCount();
    
    echo json_encode([
        'success' => true,
        'message' => "Cleared $deleted signals from queue",
        'deleted_count' => $deleted
    ]);
}
?>