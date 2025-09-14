<?php
require_once __DIR__ . '/../auth/api_protection.php';
protectAPI();

require_once __DIR__ . '/../auth/config.php';

try {
    // Database connection
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: '';
    $password = getenv('DB_PASSWORD') ?: '';
    $database = getenv('DB_NAME') ?: 'crypto_trading';
    
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get parameters for pagination and filtering
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 200) : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $symbol_filter = isset($_GET['symbol']) ? trim($_GET['symbol']) : '';
    
    // Build query for closed positions (trades)
    $where_conditions = ["status = 'CLOSED'"];
    $params = [];
    
    if (!empty($symbol_filter)) {
        $where_conditions[] = "symbol LIKE ?";
        $params[] = "%{$symbol_filter}%";
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    // Get trades data from positions table
    $sql = "
        SELECT 
            id,
            symbol,
            side,
            size as quantity,
            entry_price,
            close_price,
            leverage,
            margin_used,
            final_pnl,
            opened_at,
            closed_at,
            signal_id,
            is_demo
        FROM positions 
        {$where_clause}
        ORDER BY closed_at DESC 
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $trades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) FROM positions {$where_clause}";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute(array_slice($params, 0, -2)); // Remove limit and offset
    $total_count = $count_stmt->fetchColumn();
    
    // Process trades data
    $processed_trades = [];
    
    foreach ($trades as $trade) {
        // Calculate P&L percentage if we have entry and close prices
        $pnl_percentage = 0;
        if ($trade['entry_price'] && $trade['close_price'] && $trade['entry_price'] > 0) {
            if (strtoupper($trade['side']) === 'LONG') {
                $pnl_percentage = (($trade['close_price'] - $trade['entry_price']) / $trade['entry_price']) * 100;
            } else {
                $pnl_percentage = (($trade['entry_price'] - $trade['close_price']) / $trade['entry_price']) * 100;
            }
            
            // Apply leverage if available
            if ($trade['leverage'] && $trade['leverage'] > 0) {
                $pnl_percentage *= $trade['leverage'];
            }
        }
        
        // Format dates
        $opened_at = $trade['opened_at'] ? date('M j, Y H:i', strtotime($trade['opened_at'])) : 'N/A';
        $closed_at = $trade['closed_at'] ? date('M j, Y H:i', strtotime($trade['closed_at'])) : 'N/A';
        
        // Calculate duration
        $duration = 'N/A';
        if ($trade['opened_at'] && $trade['closed_at']) {
            $start = new DateTime($trade['opened_at']);
            $end = new DateTime($trade['closed_at']);
            $interval = $start->diff($end);
            
            if ($interval->days > 0) {
                $duration = $interval->days . 'd ' . $interval->h . 'h';
            } elseif ($interval->h > 0) {
                $duration = $interval->h . 'h ' . $interval->i . 'm';
            } else {
                $duration = $interval->i . 'm';
            }
        }
        
        $processed_trades[] = [
            'id' => $trade['id'],
            'symbol' => $trade['symbol'],
            'side' => strtoupper($trade['side']),
            'quantity' => number_format($trade['quantity'], 6),
            'entry_price' => $trade['entry_price'] ? number_format($trade['entry_price'], 6) : 'N/A',
            'close_price' => $trade['close_price'] ? number_format($trade['close_price'], 6) : 'N/A',
            'leverage' => $trade['leverage'] ? $trade['leverage'] . 'x' : 'N/A',
            'margin_used' => $trade['margin_used'] ? '$' . number_format($trade['margin_used'], 2) : 'N/A',
            'final_pnl' => $trade['final_pnl'] ? number_format($trade['final_pnl'], 2) : 0,
            'pnl_percentage' => round($pnl_percentage, 2),
            'pnl_display' => ($trade['final_pnl'] ? '$' . number_format($trade['final_pnl'], 2) : '$0.00') . 
                           ' (' . number_format($pnl_percentage, 1) . '%)',
            'opened_at' => $opened_at,
            'closed_at' => $closed_at,
            'duration' => $duration,
            'is_demo' => $trade['is_demo'] ? true : false,
            'signal_id' => $trade['signal_id'],
            'is_profitable' => $trade['final_pnl'] > 0
        ];
    }
    
    // Get summary statistics
    $summary_sql = "
        SELECT 
            COUNT(*) as total_trades,
            COUNT(CASE WHEN final_pnl > 0 THEN 1 END) as winning_trades,
            COUNT(CASE WHEN final_pnl < 0 THEN 1 END) as losing_trades,
            SUM(final_pnl) as total_pnl,
            AVG(final_pnl) as avg_pnl,
            MAX(final_pnl) as best_trade,
            MIN(final_pnl) as worst_trade
        FROM positions 
        {$where_clause}
    ";
    
    $summary_stmt = $pdo->prepare($summary_sql);
    $summary_stmt->execute(array_slice($params, 0, -2)); // Remove limit and offset
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);
    
    $win_rate = $summary['total_trades'] > 0 ? 
        round(($summary['winning_trades'] / $summary['total_trades']) * 100, 2) : 0;
    
    $response = [
        'success' => true,
        'trades' => $processed_trades,
        'pagination' => [
            'total' => (int)$total_count,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total_count
        ],
        'summary' => [
            'total_trades' => (int)$summary['total_trades'],
            'winning_trades' => (int)$summary['winning_trades'],
            'losing_trades' => (int)$summary['losing_trades'],
            'win_rate' => $win_rate,
            'total_pnl' => round($summary['total_pnl'], 2),
            'avg_pnl' => round($summary['avg_pnl'], 2),
            'best_trade' => round($summary['best_trade'], 2),
            'worst_trade' => round($summary['worst_trade'], 2)
        ]
    ];
    
    sendAPIResponse(true, $response);
    
} catch (Exception $e) {
    error_log("Trades History Error: " . $e->getMessage());
    sendAPIResponse(false, null, "Error fetching trades data: " . $e->getMessage());
}
?>