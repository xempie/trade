<?php
/**
 * Cron Jobs Management Interface
 * Similar to cPanel's cron job manager
 */

// Security check
session_start();
$allowed_ips = ['127.0.0.1', '::1', 'localhost'];
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';

if (!in_array($client_ip, $allowed_ips) && !isset($_SESSION['authenticated'])) {
    if ($_POST['password'] ?? '' === 'admin123') {
        $_SESSION['authenticated'] = true;
    } else {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $error_msg = "Invalid password";
        }
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Cron Jobs Manager - Authentication</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 50px; background: #f5f5f5; }
                .login-box { max-width: 400px; margin: 100px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                input[type="password"] { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; }
                input[type="submit"] { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
                .error { color: red; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class="login-box">
                <h2>🔐 System Access Required</h2>
                <?php if (isset($error_msg)): ?>
                    <div class="error"><?= htmlspecialchars($error_msg) ?></div>
                <?php endif; ?>
                <form method="POST">
                    <label>Password:</label>
                    <input type="password" name="password" required>
                    <input type="submit" value="Login">
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'add_job':
            $result = addCronJob($_POST);
            echo json_encode($result);
            break;

        case 'delete_job':
            $result = deleteCronJob($_POST['job_id']);
            echo json_encode($result);
            break;

        case 'toggle_job':
            $result = toggleCronJob($_POST['job_id']);
            echo json_encode($result);
            break;

        case 'get_logs':
            $result = getCronLogs($_POST['job_id'] ?? null);
            echo json_encode($result);
            break;

        case 'delete_all_jobs':
            $result = deleteAllCronJobs();
            echo json_encode($result);
            break;
    }
    exit;
}

// Functions
function getCurrentCronJobs() {
    $jobs = [];

    // Try to read current crontab
    exec('crontab -l 2>/dev/null', $output, $return_code);

    if ($return_code === 0) {
        $job_id = 1;
        foreach ($output as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;

            // Parse cron line: schedule + command
            $parts = preg_split('/\s+/', $line, 6);
            if (count($parts) >= 6) {
                $schedule = implode(' ', array_slice($parts, 0, 5));
                $command = $parts[5];

                $jobs[] = [
                    'id' => $job_id++,
                    'schedule' => $schedule,
                    'command' => $command,
                    'enabled' => true,
                    'last_run' => getLastRunTime($command),
                    'status' => 'active'
                ];
            }
        }
    }

    return $jobs;
}

function getLastRunTime($command) {
    // Try to get last modification time of log file mentioned in command
    if (preg_match('/>> (.+\.log)/', $command, $matches)) {
        $logFile = $matches[1];
        if (file_exists($logFile)) {
            return date('Y-m-d H:i:s', filemtime($logFile));
        }
    }
    return 'Unknown';
}

function addCronJob($data) {
    try {
        $schedule = trim($data['schedule']);
        $command = trim($data['command']);
        $description = trim($data['description'] ?? '');

        // Validate cron schedule
        if (!isValidCronSchedule($schedule)) {
            return ['success' => false, 'message' => 'Invalid cron schedule format'];
        }

        // Get current crontab
        exec('crontab -l 2>/dev/null', $current, $return_code);

        // Check for duplicate commands (regardless of schedule)
        $commandExists = false;
        if ($return_code === 0) {
            foreach ($current as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) continue;

                // Extract command from existing cron line
                $parts = preg_split('/\s+/', $line, 6);
                if (count($parts) >= 6) {
                    $existingCommand = $parts[5];
                    if (trim($existingCommand) === trim($command)) {
                        $commandExists = true;
                        break;
                    }
                }
            }
        }

        if ($commandExists) {
            return ['success' => false, 'message' => 'This command already exists in crontab. Cannot add duplicate commands.'];
        }

        // Add new job
        $newJob = $schedule . ' ' . $command;
        if (!empty($description)) {
            $current[] = '# ' . $description;
        }
        $current[] = $newJob;

        // Write new crontab
        $tempFile = tempnam(sys_get_temp_dir(), 'crontab');
        file_put_contents($tempFile, implode("\n", $current) . "\n");

        exec("crontab $tempFile 2>&1", $output, $result);
        unlink($tempFile);

        if ($result === 0) {
            return ['success' => true, 'message' => 'Cron job added successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to add cron job: ' . implode(', ', $output)];
        }

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function deleteCronJob($job_id) {
    try {
        exec('crontab -l 2>/dev/null', $current, $return_code);

        if ($return_code !== 0) {
            return ['success' => false, 'message' => 'No crontab found'];
        }

        // Filter out the job to delete
        $filtered = [];
        $current_id = 1;

        foreach ($current as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                $filtered[] = $line;
                continue;
            }

            if ($current_id != $job_id) {
                $filtered[] = $line;
            }
            $current_id++;
        }

        // Write new crontab
        $tempFile = tempnam(sys_get_temp_dir(), 'crontab');
        file_put_contents($tempFile, implode("\n", $filtered) . "\n");

        exec("crontab $tempFile 2>&1", $output, $result);
        unlink($tempFile);

        if ($result === 0) {
            return ['success' => true, 'message' => 'Cron job deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete cron job: ' . implode(', ', $output)];
        }

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function toggleCronJob($job_id) {
    // For simplicity, this will comment/uncomment the job
    return ['success' => true, 'message' => 'Job toggled (comment/uncomment functionality can be added)'];
}

function deleteAllCronJobs() {
    try {
        // Clear all cron jobs by creating empty crontab
        $tempFile = tempnam(sys_get_temp_dir(), 'crontab');
        file_put_contents($tempFile, "# All cron jobs cleared\n");

        exec("crontab $tempFile 2>&1", $output, $result);
        unlink($tempFile);

        if ($result === 0) {
            return ['success' => true, 'message' => 'All cron jobs deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete all cron jobs: ' . implode(', ', $output)];
        }

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function getCronLogs($job_id = null) {
    try {
        $logs = [];

        // Common log locations
        $logPaths = [
            '/var/log/cron',
            '/var/log/cron.log',
            '/var/log/syslog',
            __DIR__ . '/../ta/jobs/cron.log',
            __DIR__ . '/../ta/jobs/balance-sync.log',
            __DIR__ . '/../ta/jobs/position-sync.log',
            __DIR__ . '/../ta/jobs/price-monitor.log',
            __DIR__ . '/../ta/jobs/target-stoploss-monitor.log',
            __DIR__ . '/../ta/jobs/limit-order-monitor.log',
            __DIR__ . '/../ta/jobs/order-status.log'
        ];

        foreach ($logPaths as $logPath) {
            if (file_exists($logPath) && is_readable($logPath)) {
                try {
                    $content = tail($logPath, 50);
                    $size = filesize($logPath);

                    $logs[] = [
                        'file' => basename($logPath),
                        'content' => $content,
                        'size' => $size,
                        'path' => $logPath
                    ];
                } catch (Exception $e) {
                    $logs[] = [
                        'file' => basename($logPath),
                        'content' => "Error reading file: " . $e->getMessage(),
                        'size' => 0,
                        'path' => $logPath
                    ];
                }
            }
        }

        // If no logs found, create a default response
        if (empty($logs)) {
            $logs[] = [
                'file' => 'system',
                'content' => "No log files found or accessible.\n\nChecked locations:\n" . implode("\n", $logPaths),
                'size' => 0,
                'path' => 'none'
            ];
        }

        return ['success' => true, 'logs' => $logs];

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Failed to load logs: ' . $e->getMessage(),
            'logs' => []
        ];
    }
}

function tail($filename, $lines = 50) {
    try {
        if (!file_exists($filename)) {
            return "File does not exist: " . $filename;
        }

        if (!is_readable($filename)) {
            return "File is not readable: " . $filename;
        }

        $file = new SplFileObject($filename, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $startLine = max(0, $totalLines - $lines);
        $content = '';

        $file->rewind();
        $file->seek($startLine);

        while (!$file->eof()) {
            $content .= $file->current();
            $file->next();
        }

        return $content ?: "File is empty or contains no readable content.";

    } catch (Exception $e) {
        return "Error reading file: " . $e->getMessage();
    }
}

function isValidCronSchedule($schedule) {
    $parts = explode(' ', trim($schedule));
    return count($parts) === 5;
}

// Get current jobs for display
$cronJobs = getCurrentCronJobs();
$predefinedJobs = [
    [
        'name' => 'Balance Sync',
        'schedule' => '*/2 * * * *',
        'command' => 'php ' . __DIR__ . '/../ta/jobs/balance-sync.php >> ' . __DIR__ . '/../ta/jobs/balance-sync.log 2>&1',
        'description' => 'Sync account balance every 2 minutes'
    ],
    [
        'name' => 'Position Sync',
        'schedule' => '*/3 * * * *',
        'command' => 'php ' . __DIR__ . '/../ta/jobs/position-sync.php >> ' . __DIR__ . '/../ta/jobs/position-sync.log 2>&1',
        'description' => 'Sync positions every 3 minutes'
    ],
    [
        'name' => 'Price Monitor',
        'schedule' => '*/1 * * * *',
        'command' => 'php ' . __DIR__ . '/../ta/jobs/price-monitor.php >> ' . __DIR__ . '/../ta/jobs/price-monitor.log 2>&1',
        'description' => 'Monitor price alerts every minute'
    ],
    [
        'name' => 'Target/SL Monitor',
        'schedule' => '*/5 * * * *',
        'command' => 'php ' . __DIR__ . '/../ta/jobs/target-stoploss-monitor.php >> ' . __DIR__ . '/../ta/jobs/target-stoploss-monitor.log 2>&1',
        'description' => 'Check target and stop-loss orders every 5 minutes'
    ],
    [
        'name' => 'Limit Order Monitor',
        'schedule' => '*/2 * * * *',
        'command' => 'php ' . __DIR__ . '/../ta/jobs/limit-order-monitor.php >> ' . __DIR__ . '/../ta/jobs/limit-order-monitor.log 2>&1',
        'description' => 'Monitor limit orders every 2 minutes'
    ],
    [
        'name' => 'Order Status Update',
        'schedule' => '*/1 * * * *',
        'command' => 'php ' . __DIR__ . '/../ta/jobs/order-status.php >> ' . __DIR__ . '/../ta/jobs/order-status.log 2>&1',
        'description' => 'Update order status every minute'
    ],
    [
        'name' => 'Telegram Test (1 minute)',
        'schedule' => '*/1 * * * *',
        'command' => 'php ' . __DIR__ . '/../ta/jobs/test-telegram-simple.php >> ' . __DIR__ . '/../ta/jobs/test-telegram-simple.log 2>&1',
        'description' => 'Test telegram notifications every minute - REMOVE AFTER TESTING'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cron Jobs Manager</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: #333;
        }

        .header {
            background: #fff;
            border-bottom: 1px solid #e9ecef;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header h1 {
            color: #495057;
            font-size: 1.75rem;
            font-weight: 600;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding: 1.25rem;
            font-weight: 600;
            color: #495057;
        }

        .card-body {
            padding: 1.5rem;
        }

        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }

        .btn:hover { opacity: 0.9; transform: translateY(-1px); }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #495057;
        }

        .form-control {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }

        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }

        .predefined-jobs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .job-card {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            background: #f8f9fa;
        }

        .job-card h5 {
            margin-bottom: 0.5rem;
            color: #495057;
        }

        .job-card p {
            margin-bottom: 0.5rem;
            color: #6c757d;
            font-size: 0.875rem;
        }

        .job-card code {
            display: block;
            background: #e9ecef;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            margin: 0.5rem 0;
            word-break: break-all;
        }

        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-danger { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }

        .logs-container {
            max-height: 400px;
            overflow-y: auto;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.75rem;
            white-space: pre-wrap;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 2rem;
            width: 90%;
            max-width: 600px;
            border-radius: 8px;
            position: relative;
        }

        .close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6c757d;
        }

        .close:hover { color: #000; }

        @media (max-width: 768px) {
            .container { padding: 0 0.5rem; }
            .header { padding: 1rem; }
            .card-body { padding: 1rem; }
            .table { font-size: 0.75rem; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🕒 Cron Jobs Manager</h1>
        <p>Manage scheduled tasks for your trading system</p>
    </div>

    <div class="container">
        <!-- Current Jobs -->
        <div class="card">
            <div class="card-header">
                <span>📋 Active Cron Jobs</span>
                <div style="float: right;">
                    <button class="btn btn-danger" onclick="deleteAllJobs()" style="margin-right: 10px;">🗑️ Delete All</button>
                    <button class="btn btn-primary" onclick="refreshJobs()">🔄 Refresh</button>
                </div>
            </div>
            <div class="card-body">
                <div id="jobs-status"></div>
                <?php if (empty($cronJobs)): ?>
                    <p>No cron jobs found. Add some jobs using the form below or install predefined jobs.</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Schedule</th>
                                <th>Command</th>
                                <th>Last Run</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cronJobs as $job): ?>
                            <tr>
                                <td><?= $job['id'] ?></td>
                                <td><code><?= htmlspecialchars($job['schedule']) ?></code></td>
                                <td title="<?= htmlspecialchars($job['command']) ?>">
                                    <?= htmlspecialchars(substr($job['command'], 0, 50)) ?>...
                                </td>
                                <td><?= htmlspecialchars($job['last_run']) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $job['enabled'] ? 'active' : 'inactive' ?>">
                                        <?= $job['enabled'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-danger btn-sm" onclick="deleteJob(<?= $job['id'] ?>)">🗑️ Delete</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add New Job -->
        <div class="card">
            <div class="card-header">➕ Add New Cron Job</div>
            <div class="card-body">
                <form id="add-job-form">
                    <div class="form-group">
                        <label>Description (optional):</label>
                        <input type="text" class="form-control" name="description" placeholder="e.g., Daily backup">
                    </div>
                    <div class="form-group">
                        <label>Schedule (Cron Format):</label>
                        <input type="text" class="form-control" name="schedule" placeholder="*/5 * * * *" required>
                        <small>Format: minute hour day month weekday. Use <a href="https://crontab.guru" target="_blank">crontab.guru</a> for help.</small>
                    </div>
                    <div class="form-group">
                        <label>Command:</label>
                        <input type="text" class="form-control" name="command" placeholder="php /path/to/script.php" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Cron Job</button>
                </form>
            </div>
        </div>

        <!-- Predefined Jobs -->
        <div class="card">
            <div class="card-header">🚀 Quick Install - Trading System Jobs</div>
            <div class="card-body">
                <p>Click to install predefined cron jobs for the trading system:</p>
                <div class="predefined-jobs">
                    <?php foreach ($predefinedJobs as $index => $job): ?>
                    <div class="job-card">
                        <h5><?= htmlspecialchars($job['name']) ?></h5>
                        <p><?= htmlspecialchars($job['description']) ?></p>
                        <code><?= htmlspecialchars($job['schedule']) ?></code>
                        <button class="btn btn-success btn-sm" onclick="installPredefinedJob(<?= $index ?>)">
                            📥 Install
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <br>
                <button class="btn btn-primary" onclick="installAllJobs()">📦 Install All Trading Jobs</button>
            </div>
        </div>

        <!-- Logs -->
        <div class="card">
            <div class="card-header">
                📝 Cron Logs
                <button class="btn btn-secondary" style="float: right;" onclick="loadLogs()">🔄 Refresh Logs</button>
            </div>
            <div class="card-body">
                <div id="logs-container" class="logs-container">
                    Click "Refresh Logs" to view recent cron job execution logs.
                </div>
            </div>
        </div>
    </div>

    <script>
        const predefinedJobs = <?= json_encode($predefinedJobs) ?>;

        // Add job form handler
        document.getElementById('add-job-form').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'add_job');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showStatus(data.message, data.success ? 'success' : 'danger');
                if (data.success) {
                    this.reset();
                    setTimeout(() => location.reload(), 1000);
                }
            })
            .catch(error => {
                showStatus('Error: ' + error.message, 'danger');
            });
        });

        function deleteJob(jobId) {
            if (!confirm('Are you sure you want to delete this cron job?')) return;

            const formData = new FormData();
            formData.append('action', 'delete_job');
            formData.append('job_id', jobId);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showStatus(data.message, data.success ? 'success' : 'danger');
                if (data.success) {
                    setTimeout(() => location.reload(), 1000);
                }
            })
            .catch(error => {
                showStatus('Error: ' + error.message, 'danger');
            });
        }

        function deleteAllJobs() {
            if (!confirm('⚠️ WARNING: This will delete ALL cron jobs!\n\nAre you absolutely sure you want to continue?')) return;

            if (!confirm('This action cannot be undone. All scheduled tasks will be removed.\n\nConfirm deletion of ALL cron jobs?')) return;

            const formData = new FormData();
            formData.append('action', 'delete_all_jobs');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showStatus(data.message, data.success ? 'success' : 'danger');
                if (data.success) {
                    setTimeout(() => location.reload(), 1500);
                }
            })
            .catch(error => {
                showStatus('Error: ' + error.message, 'danger');
            });
        }

        function installPredefinedJob(index) {
            const job = predefinedJobs[index];

            const formData = new FormData();
            formData.append('action', 'add_job');
            formData.append('description', job.description);
            formData.append('schedule', job.schedule);
            formData.append('command', job.command);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showStatus(data.message, data.success ? 'success' : 'danger');
                if (data.success) {
                    setTimeout(() => location.reload(), 1000);
                }
            })
            .catch(error => {
                showStatus('Error: ' + error.message, 'danger');
            });
        }

        function installAllJobs() {
            if (!confirm('Install all ' + predefinedJobs.length + ' trading system cron jobs?\n\nNote: Duplicate commands will be skipped automatically.')) return;

            let processed = 0;
            let installed = 0;
            let skipped = 0;
            const total = predefinedJobs.length;

            showStatus('Starting installation of all trading jobs...', 'success');

            predefinedJobs.forEach((job, index) => {
                setTimeout(() => {
                    const formData = new FormData();
                    formData.append('action', 'add_job');
                    formData.append('description', job.description);
                    formData.append('schedule', job.schedule);
                    formData.append('command', job.command);

                    fetch('', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        processed++;
                        if (data.success) {
                            installed++;
                        } else {
                            skipped++;
                        }

                        const statusType = data.success ? 'success' : 'warning';
                        showStatus(`[${processed}/${total}] ${job.name}: ${data.message}`, statusType);

                        if (processed === total) {
                            setTimeout(() => {
                                showStatus(`Installation complete! ${installed} jobs installed, ${skipped} skipped (duplicates).`, 'success');
                                setTimeout(() => location.reload(), 2000);
                            }, 500);
                        }
                    })
                    .catch(error => {
                        processed++;
                        skipped++;
                        showStatus(`[${processed}/${total}] Error installing ${job.name}: ${error.message}`, 'danger');

                        if (processed === total) {
                            setTimeout(() => location.reload(), 2000);
                        }
                    });
                }, index * 300); // Stagger requests
            });
        }

        function loadLogs() {
            const container = document.getElementById('logs-container');
            container.textContent = 'Loading logs...';

            const formData = new FormData();
            formData.append('action', 'get_logs');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);

                    if (data.success && data.logs && data.logs.length > 0) {
                        let content = '';
                        data.logs.forEach(log => {
                            const sizeKB = log.size ? (log.size/1024).toFixed(2) : '0.00';
                            content += `=== ${log.file} (${sizeKB} KB) ===\n`;
                            if (log.path && log.path !== 'none') {
                                content += `Path: ${log.path}\n`;
                            }
                            content += log.content + '\n\n';
                        });
                        container.textContent = content;
                    } else if (data.success) {
                        container.textContent = 'No log files found or accessible.\n\nPossible reasons:\n- Log files haven\'t been created yet\n- Insufficient permissions\n- Cron jobs not running';
                    } else {
                        container.textContent = 'Error: ' + (data.error || 'Unknown error occurred');
                    }
                } catch (parseError) {
                    container.textContent = 'Invalid JSON response from server:\n\n' + text.substring(0, 500) + (text.length > 500 ? '...' : '');
                }
            })
            .catch(error => {
                container.textContent = 'Network or server error: ' + error.message + '\n\nPlease check:\n- Server is running\n- PHP errors in server logs\n- File permissions';
            });
        }

        function refreshJobs() {
            location.reload();
        }

        function showStatus(message, type) {
            const container = document.getElementById('jobs-status');
            container.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            setTimeout(() => {
                container.innerHTML = '';
            }, 5000);
        }

        // Auto-refresh every 30 seconds
        setInterval(refreshJobs, 30000);
    </script>
</body>
</html>