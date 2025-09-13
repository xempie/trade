<?php
require_once __DIR__ . '/../auth/api_protection.php';
protectAPI();

require_once __DIR__ . '/../config.php';

try {
    $pdo = getDbConnection();
    
    // Get performance data for last 12 months
    $performance_data = [];
    
    for ($i = 0; $i < 12; $i++) {
        $month_start = date('Y-m-01', strtotime("-$i months"));
        $month_end = date('Y-m-t', strtotime("-$i months"));
        $month_name = date('M Y', strtotime("-$i months"));
        
        // Total orders for the month
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_orders 
            FROM orders 
            WHERE created_at BETWEEN ? AND ? 
            AND status IN ('FILLED', 'CLOSED')
        ");
        $stmt->execute([$month_start, $month_end . ' 23:59:59']);
        $total_orders = $stmt->fetchColumn();
        
        // Win/Loss analysis from positions
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN pnl > 0 THEN 1 END) as wins,
                COUNT(CASE WHEN pnl < 0 THEN 1 END) as losses,
                SUM(CASE WHEN pnl > 0 THEN pnl ELSE 0 END) as total_profit,
                SUM(CASE WHEN pnl < 0 THEN ABS(pnl) ELSE 0 END) as total_loss,
                SUM(pnl) as net_pnl,
                AVG(CASE WHEN pnl > 0 THEN pnl END) as avg_win,
                AVG(CASE WHEN pnl < 0 THEN ABS(pnl) END) as avg_loss
            FROM positions 
            WHERE status = 'CLOSED' 
            AND updated_at BETWEEN ? AND ?
        ");
        $stmt->execute([$month_start, $month_end . ' 23:59:59']);
        $pnl_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Trading volume for the month
        $stmt = $pdo->prepare("
            SELECT SUM(margin_used) as total_volume
            FROM orders 
            WHERE created_at BETWEEN ? AND ? 
            AND status IN ('FILLED', 'CLOSED')
        ");
        $stmt->execute([$month_start, $month_end . ' 23:59:59']);
        $total_volume = $stmt->fetchColumn() ?: 0;
        
        // Calculate win rate
        $total_trades = ($pnl_data['wins'] ?: 0) + ($pnl_data['losses'] ?: 0);
        $win_rate = $total_trades > 0 ? round(($pnl_data['wins'] / $total_trades) * 100, 2) : 0;
        
        // Calculate profit factor
        $profit_factor = ($pnl_data['total_loss'] ?: 0) > 0 ? 
            round(($pnl_data['total_profit'] ?: 0) / ($pnl_data['total_loss'] ?: 0), 2) : 0;
        
        $performance_data[] = [
            'month' => $month_name,
            'month_key' => date('Y-m', strtotime("-$i months")),
            'total_orders' => (int)$total_orders,
            'total_trades' => (int)$total_trades,
            'wins' => (int)($pnl_data['wins'] ?: 0),
            'losses' => (int)($pnl_data['losses'] ?: 0),
            'win_rate' => $win_rate,
            'total_profit' => round($pnl_data['total_profit'] ?: 0, 2),
            'total_loss' => round($pnl_data['total_loss'] ?: 0, 2),
            'net_pnl' => round($pnl_data['net_pnl'] ?: 0, 2),
            'avg_win' => round($pnl_data['avg_win'] ?: 0, 2),
            'avg_loss' => round($pnl_data['avg_loss'] ?: 0, 2),
            'profit_factor' => $profit_factor,
            'total_volume' => round($total_volume, 2)
        ];
    }
    
    // Calculate overall summary
    $total_orders_all = array_sum(array_column($performance_data, 'total_orders'));
    $total_trades_all = array_sum(array_column($performance_data, 'total_trades'));
    $total_wins = array_sum(array_column($performance_data, 'wins'));
    $total_losses = array_sum(array_column($performance_data, 'losses'));
    $total_profit_all = array_sum(array_column($performance_data, 'total_profit'));
    $total_loss_all = array_sum(array_column($performance_data, 'total_loss'));
    $net_pnl_all = array_sum(array_column($performance_data, 'net_pnl'));
    $total_volume_all = array_sum(array_column($performance_data, 'total_volume'));
    
    $overall_win_rate = $total_trades_all > 0 ? round(($total_wins / $total_trades_all) * 100, 2) : 0;
    $overall_profit_factor = $total_loss_all > 0 ? round($total_profit_all / $total_loss_all, 2) : 0;
    $avg_monthly_pnl = count($performance_data) > 0 ? round($net_pnl_all / count($performance_data), 2) : 0;
    
    // Get current account balance for ROI calculation
    $stmt = $pdo->prepare("SELECT balance FROM account_balance ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute();
    $current_balance = $stmt->fetchColumn() ?: 0;
    
    // Calculate ROI (assuming starting balance was current balance minus net PnL)
    $starting_balance = $current_balance - $net_pnl_all;
    $roi = $starting_balance > 0 ? round(($net_pnl_all / $starting_balance) * 100, 2) : 0;
    
    // Best and worst months
    $best_month = null;
    $worst_month = null;
    $best_pnl = PHP_FLOAT_MIN;
    $worst_pnl = PHP_FLOAT_MAX;
    
    foreach ($performance_data as $month) {
        if ($month['net_pnl'] > $best_pnl) {
            $best_pnl = $month['net_pnl'];
            $best_month = $month;
        }
        if ($month['net_pnl'] < $worst_pnl) {
            $worst_pnl = $month['net_pnl'];
            $worst_month = $month;
        }
    }
    
    $summary = [
        'overall' => [
            'total_orders' => $total_orders_all,
            'total_trades' => $total_trades_all,
            'total_wins' => $total_wins,
            'total_losses' => $total_losses,
            'win_rate' => $overall_win_rate,
            'total_profit' => round($total_profit_all, 2),
            'total_loss' => round($total_loss_all, 2),
            'net_pnl' => round($net_pnl_all, 2),
            'profit_factor' => $overall_profit_factor,
            'total_volume' => round($total_volume_all, 2),
            'avg_monthly_pnl' => $avg_monthly_pnl,
            'roi' => $roi,
            'current_balance' => round($current_balance, 2)
        ],
        'best_month' => $best_month,
        'worst_month' => $worst_month,
        'monthly_data' => $performance_data
    ];
    
    sendAPIResponse(true, $summary);
    
} catch (Exception $e) {
    error_log("Performance Summary Error: " . $e->getMessage());
    sendAPIResponse(false, null, "Error fetching performance data: " . $e->getMessage());
}
?>