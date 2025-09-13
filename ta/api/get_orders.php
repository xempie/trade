<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

// Get orders from database
function getOrders($pdo, $filters = []) {
    try {
        $sql = "SELECT o.*, s.signal_type, s.symbol as signal_symbol 
                FROM orders o 
                LEFT JOIN signals s ON o.signal_id = s.id 
                WHERE 1=1";
        $params = [];
        
        // Add filters
        if (!empty($filters['status'])) {
            $sql .= " AND o.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['symbol'])) {
            $sql .= " AND o.symbol LIKE :symbol";
            $params[':symbol'] = '%' . $filters['symbol'] . '%';
        }
        
        if (!empty($filters['signal_id'])) {
            $sql .= " AND o.signal_id = :signal_id";
            $params[':signal_id'] = $filters['signal_id'];
        }
        
        $sql .= " ORDER BY o.created_at DESC";
        
        // Add limit
        $limit = isset($filters['limit']) ? intval($filters['limit']) : 50;
        $sql .= " LIMIT :limit";
        $params[':limit'] = $limit;
        
        $stmt = $pdo->prepare($sql);
        
        // Bind limit parameter separately
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        
        // Bind other parameters
        foreach ($params as $key => $value) {
            if ($key !== ':limit') {
                $stmt->bindValue($key, $value);
            }
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch orders: ' . $e->getMessage());
    }
}

// Get BingX positions from exchange (demo or live)
function getBingXPositions($isDemo = false) {
    $apiKey = getenv('BINGX_API_KEY') ?: '';
    $apiSecret = getenv('BINGX_SECRET_KEY') ?: '';
    
    if (empty($apiKey) || empty($apiSecret)) {
        return [];
    }
    
    try {
        $timestamp = round(microtime(true) * 1000);
        $queryString = "timestamp={$timestamp}";
        $signature = hash_hmac('sha256', $queryString, $apiSecret);
        
        // Use appropriate API URL based on demo/live mode
        $baseUrl = $isDemo ? 
            (getenv('BINGX_DEMO_URL') ?: 'https://open-api-vst.bingx.com') : 
            (getenv('BINGX_LIVE_URL') ?: 'https://open-api.bingx.com');
            
        $url = $baseUrl . "/openApi/swap/v2/user/positions?" . $queryString . "&signature=" . $signature;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-BX-APIKEY: ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("BingX API HTTP error: {$httpCode}");
        }
        
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['code']) || $data['code'] !== 0) {
            throw new Exception('Failed to fetch positions from BingX');
        }
        
        return $data['data'] ?? [];
        
    } catch (Exception $e) {
        error_log("BingX positions fetch error: " . $e->getMessage());
        return [];
    }
}

// Get positions from database with debug info
function getPositionsWithDebug($pdo, $filters = []) {
    $result = getPositions($pdo, $filters);
    return [
        'data' => $result,
        'debug' => $GLOBALS['positions_debug'] ?? []
    ];
}

// Get positions from database
function getPositions($pdo, $filters = []) {
    try {
        $sql = "SELECT p.*, s.signal_type, 
                       s.stop_loss, s.take_profit_1, s.take_profit_2, s.take_profit_3,
                       o.stop_loss_order_id, o.take_profit_order_id, 
                       o.stop_loss_price, o.take_profit_price
                FROM positions p 
                LEFT JOIN signals s ON p.signal_id = s.id 
                LEFT JOIN orders o ON p.signal_id = o.signal_id AND o.entry_level = 'MARKET' AND o.status = 'FILLED'
                WHERE 1=1";
        $params = [];
        
        // Add filters
        if (!empty($filters['status'])) {
            $sql .= " AND p.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['symbol'])) {
            $sql .= " AND p.symbol LIKE :symbol";
            $params[':symbol'] = '%' . $filters['symbol'] . '%';
        }
        
        $sql .= " ORDER BY p.opened_at DESC";
        
        // Add limit
        $limit = isset($filters['limit']) ? intval($filters['limit']) : 20;
        $sql .= " LIMIT :limit";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        
        // Bind other parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $dbPositions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Enhance positions with BingX data from both demo and live exchanges
        $enhancedPositions = [];
        $liveBingxPositions = getBingXPositions(false); // Live exchange
        $demoBingxPositions = getBingXPositions(true);  // Demo exchange
        
        // Debug: Log BingX response for troubleshooting
        error_log("Live BingX Positions Count: " . count($liveBingxPositions));
        error_log("Demo BingX Positions Count: " . count($demoBingxPositions));
        
        // Create separate maps for live and demo BingX positions
        $liveBingxMap = [];
        $demoBingxMap = [];
        
        // Process live positions
        foreach ($liveBingxPositions as $bingxPos) {
            if (isset($bingxPos['symbol']) && isset($bingxPos['positionSide']) && floatval($bingxPos['positionAmt']) != 0) {
                $symbol = $bingxPos['symbol'];
                $side = strtoupper($bingxPos['positionSide']);
                
                $key = $symbol . '_' . $side;
                $liveBingxMap[$key] = $bingxPos;
                
                // Also create alternative keys for common format variations
                if (strpos($symbol, '-') !== false) {
                    $altSymbol = str_replace('-', '', $symbol); // ALGO-USDT -> ALGOUSDT
                    $liveBingxMap[$altSymbol . '_' . $side] = $bingxPos;
                }
                if (strpos($symbol, 'USDT') !== false) {
                    $baseSymbol = str_replace(['-USDT', 'USDT'], '', $symbol); // ALGO-USDT -> ALGO
                    $liveBingxMap[$baseSymbol . '_' . $side] = $bingxPos;
                }
                
                error_log("Live BingX Position Key: $key, PnL: " . ($bingxPos['unrealizedProfit'] ?? 'N/A'));
            }
        }
        
        // Process demo positions  
        foreach ($demoBingxPositions as $bingxPos) {
            if (isset($bingxPos['symbol']) && isset($bingxPos['positionSide']) && floatval($bingxPos['positionAmt']) != 0) {
                $symbol = $bingxPos['symbol'];
                $side = strtoupper($bingxPos['positionSide']);
                
                $key = $symbol . '_' . $side;
                $demoBingxMap[$key] = $bingxPos;
                
                // Also create alternative keys for common format variations
                if (strpos($symbol, '-') !== false) {
                    $altSymbol = str_replace('-', '', $symbol); // ALGO-USDT -> ALGOUSDT
                    $demoBingxMap[$altSymbol . '_' . $side] = $bingxPos;
                }
                if (strpos($symbol, 'USDT') !== false) {
                    $baseSymbol = str_replace(['-USDT', 'USDT'], '', $symbol); // ALGO-USDT -> ALGO
                    $demoBingxMap[$baseSymbol . '_' . $side] = $bingxPos;
                }
                
                error_log("Demo BingX Position Key: $key, PnL: " . ($bingxPos['unrealizedProfit'] ?? 'N/A'));
            }
        }
        
        // Merge database positions with appropriate BingX data (demo or live)
        foreach ($dbPositions as $dbPos) {
            // Determine if this is a demo position
            $isDemo = false;
            if (isset($dbPos['is_demo'])) {
                $isDemo = $dbPos['is_demo'] == 1 || $dbPos['is_demo'] === '1' || $dbPos['is_demo'] === true;
            }
            
            // Use the appropriate map based on demo/live status
            $bingxMap = $isDemo ? $demoBingxMap : $liveBingxMap;
            $modeText = $isDemo ? 'DEMO' : 'LIVE';
            
            // Try multiple symbol formats to match BingX data
            $baseSymbol = $dbPos['symbol'];
            $side = strtoupper($dbPos['side']);
            
            // Generate possible keys to try
            $possibleKeys = [
                $baseSymbol . '_' . $side,                    // ALGO_SHORT
                $baseSymbol . '-USDT_' . $side,               // ALGO-USDT_SHORT  
                $baseSymbol . 'USDT_' . $side,                // ALGOUSDT_SHORT
                $baseSymbol . '-USDT_' . $side,               // ALGO-USDT_SHORT (duplicate but safe)
            ];
            
            $matchedKey = null;
            $bingxPos = null;
            
            // Try each possible key until we find a match
            foreach ($possibleKeys as $key) {
                if (isset($bingxMap[$key])) {
                    $matchedKey = $key;
                    $bingxPos = $bingxMap[$key];
                    break;
                }
            }
            
            // Debug: Log matching attempt
            error_log("DB Position ID: {$dbPos['id']} ({$modeText}), Matched Key: " . ($matchedKey ?? 'NONE') . ", Available Keys: " . implode(', ', array_keys($bingxMap)));
            
            // Start with database position data
            $position = $dbPos;
            
            // If we have exchange data from BingX (demo or live), use it for real-time values
            if ($bingxPos !== null) {
                
                // Add BingX exchange data (demo or live based on position mode)
                $position['unrealized_pnl'] = floatval($bingxPos['unrealizedProfit'] ?? 0);
                $position['mark_price'] = floatval($bingxPos['markPrice'] ?? 0);
                $position['position_value'] = floatval($bingxPos['positionValue'] ?? 0);
                $position['margin_used'] = floatval($bingxPos['margin'] ?? $bingxPos['initialMargin'] ?? 0);
                $position['current_quantity'] = floatval($bingxPos['positionAmt'] ?? 0);
                
                // Use BingX pnlRatio directly (most accurate)
                if (isset($bingxPos['pnlRatio'])) {
                    // BingX pnlRatio is a decimal, multiply by 100 to get percentage
                    $position['pnl_percentage'] = floatval($bingxPos['pnlRatio']) * 100;
                } else {
                    // Fallback: Calculate P&L percentage: (current_price - entry_price) / entry_price * 100
                    $entryPrice = floatval($position['entry_price']);
                    $currentPrice = floatval($bingxPos['markPrice'] ?? 0);
                    $side = strtoupper($position['side']);

                    if ($entryPrice > 0 && $currentPrice > 0) {
                        if ($side === 'LONG') {
                            $position['pnl_percentage'] = (($currentPrice - $entryPrice) / $entryPrice) * 100;
                        } else {
                            $position['pnl_percentage'] = (($entryPrice - $currentPrice) / $entryPrice) * 100;
                        }
                    } else {
                        $position['pnl_percentage'] = 0;
                    }
                }
                
                // Add debug info to position
                $position['debug_matched'] = true;
                $position['debug_key'] = $matchedKey;
                $position['debug_mode'] = $modeText;
            } else {
                // No exchange data available, use zero values
                $position['unrealized_pnl'] = 0;
                $position['mark_price'] = 0;
                $position['position_value'] = 0;
                $position['pnl_percentage'] = 0;
                
                // Add debug info to position
                $position['debug_matched'] = false;
                $position['debug_tried_keys'] = $possibleKeys;
                $position['debug_available_keys'] = array_keys($bingxMap);
                $position['debug_mode'] = $modeText;
                $position['debug_test'] = 'DEMO_LIVE_SEPARATION_DEPLOYED';
            }
            
            $enhancedPositions[] = $position;
        }
        
        // Store debug info globally for access in wrapper function
        $GLOBALS['positions_debug'] = [
            'live_positions_count' => count($liveBingxPositions),
            'demo_positions_count' => count($demoBingxPositions), 
            'live_map_keys' => array_keys($liveBingxMap),
            'demo_map_keys' => array_keys($demoBingxMap),
            'db_positions_count' => count($dbPositions),
            'enhancement_version' => 'DEMO_LIVE_SEPARATED'
        ];
        
        return $enhancedPositions;
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch positions: ' . $e->getMessage());
    }
}

// Get signals from database
function getSignals($pdo, $filters = []) {
    try {
        $sql = "SELECT s.*, 
                COUNT(o.id) as total_orders,
                COUNT(CASE WHEN o.status = 'FILLED' THEN 1 END) as filled_orders,
                SUM(CASE WHEN o.status = 'FILLED' THEN o.quantity ELSE 0 END) as total_filled_quantity
                FROM signals s 
                LEFT JOIN orders o ON s.id = o.signal_id 
                WHERE 1=1";
        $params = [];
        
        // Add filters
        if (!empty($filters['status'])) {
            $sql .= " AND s.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['symbol'])) {
            $sql .= " AND s.symbol LIKE :symbol";
            $params[':symbol'] = '%' . $filters['symbol'] . '%';
        }
        
        $sql .= " GROUP BY s.id ORDER BY s.created_at DESC";
        
        // Add limit
        $limit = isset($filters['limit']) ? intval($filters['limit']) : 20;
        $sql .= " LIMIT :limit";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        
        // Bind other parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch signals: ' . $e->getMessage());
    }
}

// Main execution
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Only GET method allowed');
    }
    
    $pdo = getDbConnection();
    
    // Get request type
    $type = $_GET['type'] ?? 'orders';
    
    // Build filters from query parameters
    $filters = [
        'status' => $_GET['status'] ?? null,
        'symbol' => $_GET['symbol'] ?? null,
        'signal_id' => $_GET['signal_id'] ?? null,
        'limit' => $_GET['limit'] ?? null
    ];
    
    // Remove null values
    $filters = array_filter($filters, function($value) {
        return $value !== null && $value !== '';
    });
    
    $data = [];
    $debugInfo = [];
    
    switch ($type) {
        case 'orders':
            $data = getOrders($pdo, $filters);
            break;
            
        case 'positions':
            $result = getPositionsWithDebug($pdo, $filters);
            $data = $result['data'];
            $debugInfo = $result['debug'];
            break;
            
        case 'signals':
            $data = getSignals($pdo, $filters);
            break;
            
        default:
            throw new Exception('Invalid type parameter. Use: orders, positions, or signals');
    }
    
    $response = [
        'success' => true,
        'type' => $type,
        'data' => $data,
        'count' => count($data),
        'filters_applied' => $filters
    ];
    
    // Add debug info for positions
    if ($type === 'positions' && isset($_GET['debug'])) {
        $response['debug'] = $debugInfo;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Get Orders API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code($e->getCode() ?: 400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'method' => $_SERVER['REQUEST_METHOD']
        ]
    ]);
}
?>