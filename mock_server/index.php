<?php
// CeyPay Mock Server in PHP

// Configuration
$webhook_secret = "mock_secret_key";
$orders_file = __DIR__ . '/orders.json';
$logs_dir = __DIR__ . '/logs';
$base_url = getenv('BASE_URL') ?: 'http://localhost:5000';

// time zone to asia/colombo
date_default_timezone_set('Asia/Colombo');


// Helper to get current log file path (organized by date/hour)
function get_current_log_file() {
    global $logs_dir;

    $now = time();
    $date_folder = date('Y-m-d', $now);
    $hour_file = 'request-' . date('H', $now) . '.log';

    $date_path = $logs_dir . '/' . $date_folder;
    $log_file_path = $date_path . '/' . $hour_file;

    // Create logs directory if it doesn't exist
    if (!is_dir($logs_dir)) {
        mkdir($logs_dir, 0755, true);
    }

    // Create date directory if it doesn't exist
    if (!is_dir($date_path)) {
        mkdir($date_path, 0755, true);
    }

    return $log_file_path;
}

// Helper to get current request log file path (organized by date/hour)
function get_current_request_log_file() {
    global $logs_dir;

    $now = time();
    $date_folder = date('Y-m-d', $now);
    $hour_file = 'request-' . date('H', $now) . '.log';

    $date_path = $logs_dir . '/' . $date_folder;
    $log_file_path = $date_path . '/' . $hour_file;

    // Create logs directory if it doesn't exist
    if (!is_dir($logs_dir)) {
        mkdir($logs_dir, 0755, true);
    }

    // Create date directory if it doesn't exist
    if (!is_dir($date_path)) {
        mkdir($date_path, 0755, true);
    }

    return $log_file_path;
}

// Helper to get current app log file path (organized by date/hour)
function get_current_app_log_file() {
    global $logs_dir;

    $now = time();
    $date_folder = date('Y-m-d', $now);
    $hour_file = 'app-' . date('H', $now) . '.log';

    $date_path = $logs_dir . '/' . $date_folder;
    $log_file_path = $date_path . '/' . $hour_file;

    // Create logs directory if it doesn't exist
    if (!is_dir($logs_dir)) {
        mkdir($logs_dir, 0755, true);
    }

    // Create date directory if it doesn't exist
    if (!is_dir($date_path)) {
        mkdir($date_path, 0755, true);
    }

    return $log_file_path;
}

// Helper to get current error reports file path (organized by date/hour)
function get_current_error_reports_file() {
    global $logs_dir;

    $now = time();
    $date_folder = date('Y-m-d', $now);
    $hour_file = 'errors-' . date('H', $now) . '.json';

    $date_path = $logs_dir . '/' . $date_folder;
    $error_file_path = $date_path . '/' . $hour_file;

    // Create logs directory if it doesn't exist
    if (!is_dir($logs_dir)) {
        mkdir($logs_dir, 0755, true);
    }

    // Create date directory if it doesn't exist
    if (!is_dir($date_path)) {
        mkdir($date_path, 0755, true);
    }

    return $error_file_path;
}

// Helper to log API requests
function log_http_request($endpoint, $method, $data = null) {
    $log_file = get_current_request_log_file();

    $timestamp = date('Y-m-d H:i:s');
    $client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $log_entry = "\n" . str_repeat('=', 80) . "\n";
    $log_entry .= "[{$timestamp}] {$method} {$endpoint}\n";
    $log_entry .= "IP: {$client_ip} | User-Agent: {$user_agent}\n";
    $log_entry .= str_repeat('-', 80) . "\n";

    // Request data
    if ($data !== null) {
        $log_entry .= "REQUEST BODY:\n";
        if (is_array($data)) {
            $log_entry .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        } else {
            $log_entry .= $data . "\n";
        }
    }

    $log_entry .= str_repeat('=', 80) . "\n";

    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Helper to log full HTTP requests with headers and response
function log_full_http_request($endpoint, $method, $request_data = null, $response_data = null, $response_code = 200) {
    $log_file = get_current_request_log_file();

    $timestamp = date('Y-m-d H:i:s');
    $client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $log_entry = "\n" . str_repeat('=', 100) . "\n";
    $log_entry .= "[{$timestamp}] {$method} {$endpoint} -> {$response_code}\n";
    $log_entry .= "IP: {$client_ip} | User-Agent: {$user_agent}\n";
    $log_entry .= str_repeat('-', 100) . "\n";

    // Request headers
    $log_entry .= "REQUEST HEADERS:\n";
    $headers = getallheaders();
    foreach ($headers as $key => $value) {
        $log_entry .= "{$key}: {$value}\n";
    }
    $log_entry .= "\n";

    // Request body
    if ($request_data !== null) {
        $log_entry .= "REQUEST BODY:\n";
        if (is_array($request_data)) {
            $log_entry .= json_encode($request_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        } else {
            $log_entry .= $request_data . "\n";
        }
        $log_entry .= "\n";
    }

    // Response
    if ($response_data !== null) {
        $log_entry .= "RESPONSE ({$response_code}):\n";
        if (is_array($response_data)) {
            $log_entry .= json_encode($response_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        } else {
            $log_entry .= $response_data . "\n";
        }
    }

    $log_entry .= str_repeat('=', 100) . "\n";

    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

function log_app_event($event_type, $message, $data = null, $severity = 'info') {
    $log_file = get_current_app_log_file();

    $timestamp = date('Y-m-d H:i:s');
    $client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $log_entry = "\n" . str_repeat('=', 80) . "\n";
    $log_entry .= "[{$timestamp}] APP EVENT: {$event_type} ({$severity})\n";
    $log_entry .= "IP: {$client_ip} | User-Agent: {$user_agent}\n";
    $log_entry .= str_repeat('-', 80) . "\n";
    $log_entry .= "MESSAGE: {$message}\n";

    // Event data
    if ($data !== null) {
        $log_entry .= "EVENT DATA:\n";
        if (is_array($data)) {
            $log_entry .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        } else {
            $log_entry .= $data . "\n";
        }
    }

    $log_entry .= str_repeat('=', 80) . "\n";

    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Helper to read orders
function get_orders() {
    global $orders_file;
    if (!file_exists($orders_file)) {
        return [];
    }
    $content = file_get_contents($orders_file);
    return json_decode($content, true) ?: [];
}

// Helper to save orders
function save_orders($orders) {
    global $orders_file;
    file_put_contents($orders_file, json_encode($orders, JSON_PRETTY_PRINT));
}

// Helper to send webhook
function send_webhook($url, $payload, $secret) {
    $payload_json = json_encode($payload);
    $signature = hash_hmac('sha256', $payload_json, $secret);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-CEYPAY-SIGNATURE: ' . $signature
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return ['code' => $http_code, 'response' => $response, 'error' => $error];
}

// Router
$request_uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Determine Base Path (for subdirectory deployment)
// For PHP built-in server, SCRIPT_NAME equals REQUEST_URI, so we need special handling
$script_name = $_SERVER['SCRIPT_NAME'];
$request_uri = $_SERVER['REQUEST_URI'];

if (strpos($script_name, $request_uri) === 0 && $script_name !== $request_uri) {
    // Normal web server behavior
    $base_path = dirname($script_name);
} else {
    // PHP built-in server behavior - script is at root
    $base_path = '';
}

// Normalize slashes
$base_path = str_replace('\\', '/', $base_path);
// Remove trailing slash if not root
if ($base_path !== '/') {
    $base_path = rtrim($base_path, '/');
} else {
    $base_path = '';
}

// Calculate Route Path (relative to base path)
$route_path = $path;
if ($base_path !== '' && strpos($path, $base_path) === 0) {
    $route_path = substr($path, strlen($base_path));
}
if ($route_path === '') {
    $route_path = '/';
}

// Handle /ceypay/debugger/ prefix for debugger routes
if (strpos($route_path, '/ceypay/debugger') === 0) {
    $base_path = '/ceypay/debugger';
    $route_path = substr($route_path, strlen('/ceypay/debugger'));
    if ($route_path === '') {
        $route_path = '/';
    }
}

// 1. Admin Dashboard (GET /)
if ($route_path === '/' && $method === 'GET') {
    $orders = get_orders();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>CeyPay Mock Server (PHP)</title>
        <style>
            body { font-family: sans-serif; padding: 20px; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .btn { padding: 5px 10px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; }
            .btn:hover { background: #218838; }
            .status-PENDING { color: orange; font-weight: bold; }
            .status-SUCCESS { color: green; font-weight: bold; }
        </style>
    </head>
    <body>
        <h1>CeyPay Mock Server - Transactions</h1>
        <p><strong>Webhook Secret:</strong> <?php echo htmlspecialchars($webhook_secret); ?></p>
        <p><a href="<?php echo $base_path; ?>/logs" class="btn" style="display: inline-block; margin-bottom: 10px;">📋 View API Logs</a>
        <a href="<?php echo $base_path; ?>/errors" class="btn" style="display: inline-block; margin-bottom: 10px; margin-left: 10px;">🚨 View Error Reports</a></p>
        <table>
            <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>Amount</th>
                    <th>Provider</th>
                    <th>Status</th>
                    <th>Webhook URL</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $txn_id => $order): ?>
                <tr>
                    <td><?php echo htmlspecialchars($txn_id); ?></td>
                    <td><?php echo htmlspecialchars($order['amount'] . ' ' . $order['currency']); ?></td>
                    <td><?php echo htmlspecialchars($order['provider']); ?></td>
                    <td class="status-<?php echo htmlspecialchars($order['status']); ?>"><?php echo htmlspecialchars($order['status']); ?></td>
                    <td><?php echo htmlspecialchars($order['webhook_url']); ?></td>
                    <td>
                        <?php if ($order['status'] === 'PENDING'): ?>
                        <form action="<?php echo $base_path; ?>/admin/confirm/<?php echo htmlspecialchars($txn_id); ?>" method="post">
                            <button type="submit" class="btn">Confirm Payment</button>
                        </form>
                        <?php else: ?>
                        Confirmed
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit;
}

// 1.5. Live Logs Viewer (GET /logs)
if ($route_path === '/logs' && $method === 'GET') {
    global $logs_dir;

    $log_content = '';
    $last_modified = 0;

    // Scan logs directory for all date folders and hour files
    if (is_dir($logs_dir)) {
        $date_folders = scandir($logs_dir);
        $all_log_files = [];

        foreach ($date_folders as $date_folder) {
            if ($date_folder === '.' || $date_folder === '..') continue;

            $date_path = $logs_dir . '/' . $date_folder;
            if (!is_dir($date_path)) continue;

            $hour_files = scandir($date_path);
            foreach ($hour_files as $hour_file) {
                if ($hour_file === '.' || $hour_file === '..') continue;

                $file_path = $date_path . '/' . $hour_file;
                if (is_file($file_path) && pathinfo($file_path, PATHINFO_EXTENSION) === 'log') {
                    $file_mtime = filemtime($file_path);
                    $all_log_files[] = [
                        'path' => $file_path,
                        'mtime' => $file_mtime,
                        'date' => $date_folder,
                        'hour' => intval(pathinfo($hour_file, PATHINFO_FILENAME))
                    ];
                    if ($file_mtime > $last_modified) {
                        $last_modified = $file_mtime;
                    }
                }
            }
        }

        // Sort files by date and hour (newest first)
        usort($all_log_files, function($a, $b) {
            if ($a['date'] !== $b['date']) {
                return strcmp($b['date'], $a['date']);
            }
            return $b['hour'] - $a['hour'];
        });

        // Combine content from all log files
        foreach ($all_log_files as $log_file_info) {
            $file_content = file_get_contents($log_file_info['path']);
            if (!empty($file_content)) {
                $log_content .= "\n" . str_repeat('=', 100) . "\n";
                $log_content .= "📁 {$log_file_info['date']} - Hour {$log_file_info['hour']}:00\n";
                $log_content .= str_repeat('=', 100) . "\n";
                $log_content .= $file_content;
            }
        }
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>CeyPay Mock Server - API Logs</title>
        <style>
            body {
                font-family: 'Courier New', monospace;
                padding: 20px;
                background: #1e1e1e;
                color: #d4d4d4;
                margin: 0;
            }
            .header {
                background: #252526;
                padding: 15px 20px;
                margin: -20px -20px 20px -20px;
                border-bottom: 2px solid #007acc;
                position: sticky;
                top: 0;
                z-index: 100;
            }
            h1 {
                margin: 0 0 10px 0;
                color: #fff;
                font-size: 24px;
            }
            .controls {
                display: flex;
                gap: 10px;
                align-items: center;
            }
            .btn {
                padding: 8px 15px;
                background: #007acc;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                border: none;
                cursor: pointer;
                font-size: 14px;
                display: inline-block;
            }
            .btn:hover { background: #005a9e; }
            .btn-danger { background: #d13438; }
            .btn-danger:hover { background: #a02c2f; }
            .status {
                color: #4ec9b0;
                font-size: 12px;
            }
            .auto-refresh {
                display: flex;
                align-items: center;
                gap: 5px;
            }
            .auto-refresh input[type="checkbox"] {
                width: 18px;
                height: 18px;
                cursor: pointer;
            }
            .auto-refresh label {
                cursor: pointer;
                user-select: none;
            }
            pre {
                background: #1e1e1e;
                padding: 20px;
                border-radius: 5px;
                overflow-x: auto;
                white-space: pre-wrap;
                word-wrap: break-word;
                line-height: 1.5;
                border: 1px solid #3c3c3c;
            }
            .empty-state {
                text-align: center;
                padding: 60px 20px;
                color: #858585;
                font-size: 16px;
            }
            .log-section {
                border-left: 3px solid #007acc;
                margin: 10px 0;
            }
            .timestamp {
                color: #4ec9b0;
                font-weight: bold;
            }
            .method-POST { color: #ce9178; }
            .method-GET { color: #4fc1ff; }
            .status-200, .status-201 { color: #4ec9b0; }
            .status-400, .status-401, .status-404, .status-500 { color: #f48771; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>📋 API Logs - Live Viewer</h1>
            <div class="controls">
                <a href="<?php echo $base_path; ?>/" class="btn">← Back to Dashboard</a>
                <button onclick="location.reload()" class="btn">🔄 Refresh</button>
                <form method="post" action="<?php echo $base_path; ?>/logs/clear" style="margin: 0;">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Clear all logs?')">🗑️ Clear Logs</button>
                </form>
                <div class="auto-refresh">
                    <input type="checkbox" id="autoRefresh" checked onchange="toggleAutoRefresh()">
                    <label for="autoRefresh">Auto-refresh (5s)</label>
                </div>
                <span class="status" id="status">Last modified: <?php echo date('Y-m-d H:i:s', $last_modified); ?></span>
            </div>
        </div>

        <?php if (empty($log_content)): ?>
        <div class="empty-state">
            <p>📝 No logs yet. Make some API requests to see them here.</p>
        </div>
        <?php else: ?>
        <pre id="logContent"><?php echo htmlspecialchars($log_content); ?></pre>
        <?php endif; ?>

        <script>
            let autoRefreshEnabled = true;
            let lastModified = <?php echo $last_modified; ?>;
            let refreshInterval;

            function toggleAutoRefresh() {
                autoRefreshEnabled = document.getElementById('autoRefresh').checked;
                if (autoRefreshEnabled) {
                    startAutoRefresh();
                } else {
                    stopAutoRefresh();
                }
            }

            function startAutoRefresh() {
                refreshInterval = setInterval(checkForUpdates, 5000);
            }

            function stopAutoRefresh() {
                if (refreshInterval) {
                    clearInterval(refreshInterval);
                }
            }

            function checkForUpdates() {
                fetch('<?php echo $base_path; ?>/logs/check?t=' + Date.now())
                    .then(response => response.json())
                    .then(data => {
                        if (data.modified > lastModified) {
                            lastModified = data.modified;
                            location.reload();
                        }
                        document.getElementById('status').textContent = 'Last checked: ' + new Date().toLocaleTimeString();
                    })
                    .catch(err => {
                        console.error('Check failed:', err);
                    });
            }

            // Scroll to bottom on load
            window.scrollTo(0, document.body.scrollHeight);

            // Start auto-refresh
            if (autoRefreshEnabled) {
                startAutoRefresh();
            }
        </script>
    </body>
    </html>
    <?php
    exit;
}

// 1.6. Check Logs Modified Time (GET /logs/check)
if ($route_path === '/logs/check' && $method === 'GET') {
    global $logs_dir;
    $last_modified = 0;

    // Scan all log files to find the most recent modification time
    if (is_dir($logs_dir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($logs_dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'log') {
                $file_mtime = $file->getMTime();
                if ($file_mtime > $last_modified) {
                    $last_modified = $file_mtime;
                }
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['modified' => $last_modified]);
    exit;
}

// 1.8. Error Reports Viewer (GET /errors)
if ($route_path === '/errors' && $method === 'GET') {
    $error_log_file = __DIR__ . '/error_reports.json';
    $errors = [];

    if (file_exists($error_log_file)) {
        $errors = json_decode(file_get_contents($error_log_file), true) ?: [];
    }

    // Get last modified time for auto-refresh
    $last_modified = file_exists($error_log_file) ? filemtime($error_log_file) : 0;
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>CeyPay Mock Server - Error Reports</title>
        <style>
            body {
                font-family: 'Courier New', monospace;
                padding: 20px;
                background: #1e1e1e;
                color: #d4d4d4;
                margin: 0;
            }
            .header {
                background: #252526;
                padding: 15px 20px;
                margin: -20px -20px 20px -20px;
                border-bottom: 2px solid #f48771;
                position: sticky;
                top: 0;
                z-index: 100;
            }
            h1 {
                margin: 0 0 10px 0;
                color: #fff;
                font-size: 24px;
            }
            .controls {
                display: flex;
                gap: 10px;
                align-items: center;
                flex-wrap: wrap;
            }
            .btn {
                padding: 8px 15px;
                background: #007acc;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                border: none;
                cursor: pointer;
                font-size: 14px;
                display: inline-block;
            }
            .btn:hover { background: #005a9e; }
            .btn-danger { background: #d13438; }
            .btn-danger:hover { background: #a02c2f; }
            .filters {
                display: flex;
                gap: 10px;
                align-items: center;
                margin-top: 10px;
            }
            .filter-group {
                display: flex;
                align-items: center;
                gap: 5px;
            }
            select, input[type="number"] {
                padding: 5px;
                background: #3c3c3c;
                color: #d4d4d4;
                border: 1px solid #555;
                border-radius: 3px;
            }
            .status {
                color: #4ec9b0;
                font-size: 12px;
            }
            .error-card {
                background: #252526;
                border: 1px solid #3c3c3c;
                border-radius: 5px;
                padding: 15px;
                margin: 10px 0;
                border-left: 4px solid #f48771;
            }
            .error-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
            }
            .error-id {
                color: #4ec9b0;
                font-weight: bold;
                font-size: 14px;
            }
            .error-meta {
                color: #858585;
                font-size: 12px;
            }
            .error-message {
                color: #f48771;
                font-weight: bold;
                margin: 10px 0;
                word-wrap: break-word;
            }
            .error-context {
                background: #1e1e1e;
                padding: 10px;
                border-radius: 3px;
                margin: 10px 0;
                border: 1px solid #3c3c3c;
                font-size: 13px;
                overflow-x: auto;
            }
            .error-details {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 10px;
                font-size: 12px;
                color: #858585;
            }
            .severity-error { border-left-color: #f48771; }
            .severity-warning { border-left-color: #ffcc00; }
            .severity-info { border-left-color: #4ec9b0; }
            .empty-state {
                text-align: center;
                padding: 60px 20px;
                color: #858585;
                font-size: 16px;
            }
            .summary {
                background: #252526;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 20px;
                border: 1px solid #3c3c3c;
            }
            .summary-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
                margin-top: 10px;
            }
            .summary-item {
                text-align: center;
            }
            .summary-value {
                font-size: 24px;
                font-weight: bold;
                color: #4ec9b0;
            }
            .summary-label {
                color: #858585;
                font-size: 12px;
                text-transform: uppercase;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>🚨 Error Reports - Live Viewer</h1>
            <div class="controls">
                <a href="<?php echo $base_path; ?>/" class="btn">← Back to Dashboard</a>
                <button onclick="location.reload()" class="btn">🔄 Refresh</button>
                <form method="post" action="<?php echo $base_path; ?>/errors/clear" style="margin: 0;">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Clear all error reports?')">🗑️ Clear Reports</button>
                </form>
                <span class="status" id="status">Last modified: <?php echo date('Y-m-d H:i:s', $last_modified); ?></span>
            </div>
            <div class="filters">
                <div class="filter-group">
                    <label>Severity:</label>
                    <select id="severityFilter" onchange="applyFilters()">
                        <option value="">All</option>
                        <option value="error">Error</option>
                        <option value="warning">Warning</option>
                        <option value="info">Info</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Source:</label>
                    <select id="sourceFilter" onchange="applyFilters()">
                        <option value="">All</option>
                        <option value="frontend">Frontend</option>
                        <option value="backend">Backend</option>
                        <option value="plugin">Plugin</option>
                        <option value="unknown">Unknown</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Limit:</label>
                    <input type="number" id="limitFilter" value="50" min="1" max="500" onchange="applyFilters()">
                </div>
            </div>
        </div>

        <?php
        // Calculate summary stats
        $total_errors = count($errors);
        $severity_counts = ['error' => 0, 'warning' => 0, 'info' => 0];
        $source_counts = [];

        foreach ($errors as $error) {
            $severity = $error['severity'] ?? 'unknown';
            $source = $error['source'] ?? 'unknown';

            if (isset($severity_counts[$severity])) {
                $severity_counts[$severity]++;
            }
            if (!isset($source_counts[$source])) {
                $source_counts[$source] = 0;
            }
            $source_counts[$source]++;
        }
        ?>

        <div class="summary">
            <h3>Error Summary</h3>
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-value"><?php echo $total_errors; ?></div>
                    <div class="summary-label">Total Reports</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value"><?php echo $severity_counts['error']; ?></div>
                    <div class="summary-label">Errors</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value"><?php echo $severity_counts['warning']; ?></div>
                    <div class="summary-label">Warnings</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value"><?php echo $severity_counts['info']; ?></div>
                    <div class="summary-label">Info</div>
                </div>
            </div>
        </div>

        <?php if (empty($errors)): ?>
        <div class="empty-state">
            <p>✅ No error reports yet. Errors will appear here when reported.</p>
        </div>
        <?php else: ?>
        <?php
        // Sort errors by timestamp (newest first)
        usort($errors, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        foreach ($errors as $error):
        ?>
        <div class="error-card severity-<?php echo htmlspecialchars($error['severity'] ?? 'unknown'); ?>">
            <div class="error-header">
                <span class="error-id"><?php echo htmlspecialchars($error['id'] ?? 'unknown'); ?></span>
                <span class="error-meta">
                    <?php echo htmlspecialchars($error['timestamp'] ?? 'unknown'); ?> |
                    <?php echo htmlspecialchars($error['source'] ?? 'unknown'); ?> |
                    <?php echo htmlspecialchars($error['user_ip'] ?? 'unknown'); ?>
                </span>
            </div>
            <div class="error-message"><?php echo htmlspecialchars($error['error'] ?? 'No error message'); ?></div>
            <div class="error-context">
                <strong>Context:</strong><br>
                <?php echo htmlspecialchars(json_encode($error['context'] ?? [], JSON_PRETTY_PRINT)); ?>
            </div>
            <div class="error-details">
                <div><strong>Severity:</strong> <?php echo htmlspecialchars($error['severity'] ?? 'unknown'); ?></div>
                <div><strong>User Agent:</strong> <?php echo htmlspecialchars(substr($error['user_agent'] ?? 'unknown', 0, 50)); ?></div>
                <?php if (isset($error['additional_data']) && $error['additional_data']): ?>
                <div colspan="2"><strong>Additional Data:</strong> <?php echo htmlspecialchars(json_encode($error['additional_data'])); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <script>
            function applyFilters() {
                const severity = document.getElementById('severityFilter').value;
                const source = document.getElementById('sourceFilter').value;
                const limit = document.getElementById('limitFilter').value;

                let url = '<?php echo $base_path; ?>/errors';
                const params = [];
                if (severity) params.push('severity=' + encodeURIComponent(severity));
                if (source) params.push('source=' + encodeURIComponent(source));
                if (limit) params.push('limit=' + encodeURIComponent(limit));

                if (params.length > 0) {
                    url += '?' + params.join('&');
                }

                window.location.href = url;
            }
        </script>
    </body>
    </html>
    <?php
    exit;
}

// 1.9. Clear Error Reports (POST /errors/clear)
if ($route_path === '/errors/clear' && $method === 'POST') {
    $error_log_file = __DIR__ . '/error_reports.json';
    if (file_exists($error_log_file)) {
        file_put_contents($error_log_file, '[]');
    }
    header('Location: ' . $base_path . '/errors');
    exit;
}

// 1.10. Clear Logs (POST /logs/clear)
if ($route_path === '/logs/clear' && $method === 'POST') {
    global $logs_dir;

    // Recursively delete all log files and folders
    if (is_dir($logs_dir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($logs_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        // Remove the main logs directory itself
        rmdir($logs_dir);
    }

    header('Location: ' . $base_path . '/logs');
    exit;
}

// 2. Create Payment (POST /payment/create)
if ($route_path === '/payment/create' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Log the request
    log_http_request('/payment/create', 'POST', $input);

    $provider = $input['provider'] ?? 'BINANCE';
    $amount = $input['amount'] ?? '0.00';
    $currency = $input['currency'] ?? 'USD';
    $webhook_url = $input['webhookUrl'] ?? ''; // Updated to webhookUrl

    $transaction_id = uniqid('txn_', true);

    $orders = get_orders();
    $orders[$transaction_id] = [
        'status' => 'PENDING',
        'amount' => $amount,
        'currency' => $currency,
        'provider' => $provider,
        'webhook_url' => $webhook_url,
        'created_at' => date('c')
    ];
    save_orders($orders);

    // Use configured base_url for external links if set, otherwise construct from request
    $external_base_url = $base_url;
    if ($base_url === 'http://localhost:5000') {
         // Try to guess if not explicitly set
         $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
         $host = $_SERVER['HTTP_HOST'];
         $external_base_url = "$protocol://$host$base_path";
    }

    $payment_url = $external_base_url . '/pay-ui/' . $transaction_id;

    // Calculate fees based on amount
    $grossAmountUSDT = $amount / 306.5; // Approximate LKR to USDT conversion
    $exchangeFeePercentage = 0.5;
    $ceypayFeePercentage = 1;
    $exchangeFeeAmountUSDT = ($grossAmountUSDT * $exchangeFeePercentage) / 100;
    $ceypayFeeAmountUSDT = ($grossAmountUSDT * $ceypayFeePercentage) / 100;
    $totalFeesUSDT = $exchangeFeeAmountUSDT + $ceypayFeeAmountUSDT;
    $netAmountUSDT = $grossAmountUSDT - $totalFeesUSDT;

    $exchangeFeeAmountLKR = $amount * ($exchangeFeePercentage / 100);
    $ceypayFeeAmountLKR = $amount * ($ceypayFeePercentage / 100);
    $totalFeesLKR = $exchangeFeeAmountLKR + $ceypayFeeAmountLKR;
    $netAmountLKR = $amount - $totalFeesLKR;

    $response_data = [
        "id" => $transaction_id,
        "merchantId" => $input['merchantId'] ?? '',
        "payId" => "MOCK_PAY_ID_" . substr($transaction_id, 0, 8),
        "merchantTradeNo" => "MOCK_TRADE_" . substr($transaction_id, 0, 8),
        "amount" => $amount,
        "currency" => $currency,
        "status" => "INITIATED",
        "qrContent" => "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($payment_url),
        "checkoutLink" => $payment_url,
        "goods" => $input['goods'] ?? [],
        "customerBilling" => $input['customerBilling'] ?? [],
        "paymentProvider" => $provider . "_PAY",
        "expireTime" => null,
        "createdAt" => date('c'),
        "feeBreakdown" => [
            "grossAmountUSDT" => round($grossAmountUSDT, 8),
            "exchangeFeePercentage" => $exchangeFeePercentage,
            "exchangeFeeAmountUSDT" => round($exchangeFeeAmountUSDT, 8),
            "ceypayFeePercentage" => $ceypayFeePercentage,
            "ceypayFeeAmountUSDT" => round($ceypayFeeAmountUSDT, 8),
            "totalFeesUSDT" => round($totalFeesUSDT, 8),
            "netAmountUSDT" => round($netAmountUSDT, 8),
            "grossAmountLKR" => round($amount, 2),
            "exchangeFeeAmountLKR" => round($exchangeFeeAmountLKR, 2),
            "ceypayFeeAmountLKR" => round($ceypayFeeAmountLKR, 2),
            "totalFeesLKR" => round($totalFeesLKR, 2),
            "netAmountLKR" => round($netAmountLKR, 2)
        ],
        "usdtAmount" => round($netAmountUSDT, 8),
        "exchangeRateSnapshot" => 306.5
    ];

    // Log the request
    log_full_http_request('/payment/create', 'POST', $input, $response_data, 201);

    header('Content-Type: application/json');
    http_response_code(201);
    echo json_encode($response_data);
    exit;
}

// 3. Check Status (GET /payment/<transaction_id>)
if (preg_match('#^/payment/([^/]+)$#', $route_path, $matches) && $method === 'GET') {
    $transaction_id = $matches[1];
    $orders = get_orders();

    // Log the request
    log_http_request('/payment/' . $transaction_id, 'GET');

    if (!isset($orders[$transaction_id])) {
        $error_response = ["status" => "NOT_FOUND"];
        http_response_code(404);
        echo json_encode($error_response);
        exit;
    }

    $response_data = [
        "status" => $orders[$transaction_id]['status'],
        "transactionId" => $transaction_id
    ];

    header('Content-Type: application/json');
    echo json_encode($response_data);
    exit;
}

// 3.1 Get Webhook Public Key (GET /merchant/webhook-public-key)
if ($route_path === '/merchant/webhook-public-key' && $method === 'GET') {
    // Return a mock public key for webhook signature verification
    // In production, this would return the actual ED25519 public key
    // For the mock server, we return a dummy key since we're using HMAC SHA256
    $mock_public_key = "-----BEGIN PUBLIC KEY-----
MCowBQYDK2VwAyEAJrQLj5P/89iXES9+vFgrIy29clF9CC/oPPsw3c5D0bs=
-----END PUBLIC KEY-----";

    $response_data = ["publicKey" => $mock_public_key];

    header('Content-Type: application/json');
    echo json_encode($response_data);
    exit;
}

// 3.2 Regenerate Webhook Secret (POST /merchant/webhook-secret/regenerate)
if ($route_path === '/merchant/webhook-secret/regenerate' && $method === 'POST') {
    // In a real app, verify Authorization header
    $webhook_secret = "new_secret_" . uniqid();
    // Note: In this simple file-based mock, we can't easily persist the secret globally
    // without a config file, but we'll return it to simulate the API.
    // The admin dashboard will still show the hardcoded one unless we save it.
    // For simplicity, we just return a new random string.

    header('Content-Type: application/json');
    echo json_encode(["secret" => $webhook_secret]);
    exit;
}

// 4. Admin Confirm (POST /admin/confirm/<transaction_id>)
if (preg_match('#^/admin/confirm/([^/]+)$#', $route_path, $matches) && $method === 'POST') {
    $transaction_id = $matches[1];
    $orders = get_orders();

    if (!isset($orders[$transaction_id])) {
        http_response_code(404);
        echo "Order not found";
        exit;
    }

    $orders[$transaction_id]['status'] = 'SUCCESS';
    save_orders($orders);

    $order = $orders[$transaction_id];

    // Send Webhook
    if (!empty($order['webhook_url'])) {
        $payload = [
            "paymentId" => $transaction_id,
            "payId" => "MOCK_PAY_ID_" . substr($transaction_id, 0, 8),
            "merchantTradeNo" => "MOCK_TRADE_" . substr($transaction_id, 0, 8),
            "status" => "SUCCESS",
            "amount" => $order["amount"],
            "currency" => $order["currency"],
            "timestamp" => gmdate('Y-m-d\TH:i:s.v\Z')
        ];

        // Use the global secret (or the one generated if we were persisting it)
        // For now, use the hardcoded one at the top of the file
        global $webhook_secret;

        // Log webhook being sent
        log_app_event('WEBHOOK_SEND', 'Sending webhook to ' . $order['webhook_url'], $payload, 'info');

        $webhook_result = send_webhook($order['webhook_url'], $payload, $webhook_secret);
    }

    // Redirect back to dashboard
    header('Location: ' . $base_path . '/');
    exit;
}

// 5. Simulate Payment (POST /pay/<transaction_id>)
if (preg_match('#^/pay/([^/]+)$#', $route_path, $matches) && $method === 'POST') {
    $transaction_id = $matches[1];
    $orders = get_orders();

    if (!isset($orders[$transaction_id])) {
        http_response_code(404);
        echo json_encode(["status" => "NOT_FOUND"]);
        exit;
    }

    $orders[$transaction_id]['status'] = 'SUCCESS';
    save_orders($orders);

    header('Content-Type: application/json');
    echo json_encode(["status" => "SUCCESS", "message" => "Payment simulated"]);
    exit;
}

// 6. Payment UI (GET /pay-ui/<transaction_id>)
if (preg_match('#^/pay-ui/([^/]+)$#', $route_path, $matches) && $method === 'GET') {
    $transaction_id = $matches[1];
    $orders = get_orders();

    if (!isset($orders[$transaction_id])) {
        http_response_code(404);
        echo "Transaction not found";
        exit;
    }

    $order = $orders[$transaction_id];
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Confirm Payment - CeyPay Mock</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
            body { font-family: sans-serif; padding: 20px; text-align: center; max-width: 600px; margin: 0 auto; }
            .card { border: 1px solid #ddd; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .amount { font-size: 24px; font-weight: bold; color: #333; margin: 20px 0; }
            .provider { color: #666; margin-bottom: 20px; }
            .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; font-size: 16px; width: 100%; }
            .btn:hover { background: #0056b3; }
            .success { color: green; font-weight: bold; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="card">
            <h2>Confirm Payment</h2>
            <div class="provider">Paying via <?php echo htmlspecialchars($order['provider']); ?></div>
            <div class="amount"><?php echo htmlspecialchars($order['amount'] . ' ' . $order['currency']); ?></div>

            <?php if ($order['status'] === 'PENDING'): ?>
            <form action="<?php echo $base_path; ?>/pay-ui/<?php echo htmlspecialchars($transaction_id); ?>" method="post">
                <button type="submit" class="btn">Confirm Payment</button>
            </form>
            <?php else: ?>
            <div class="success">Payment Successful!</div>
            <p>You can close this window.</p>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 7. Process Payment UI (POST /pay-ui/<transaction_id>)
if (preg_match('#^/pay-ui/([^/]+)$#', $route_path, $matches) && $method === 'POST') {
    $transaction_id = $matches[1];
    $orders = get_orders();

    if (!isset($orders[$transaction_id])) {
        http_response_code(404);
        echo "Transaction not found";
        exit;
    }

    $orders[$transaction_id]['status'] = 'SUCCESS';
    save_orders($orders);

    $order = $orders[$transaction_id];

    // Send Webhook
    if (!empty($order['webhook_url'])) {
        $payload = [
            "paymentId" => $transaction_id,
            "payId" => "MOCK_PAY_ID_" . substr($transaction_id, 0, 8),
            "merchantTradeNo" => "MOCK_TRADE_" . substr($transaction_id, 0, 8),
            "status" => "SUCCESS",
            "amount" => $order["amount"],
            "currency" => $order["currency"],
            "timestamp" => gmdate('Y-m-d\TH:i:s.v\Z')
        ];

        // Log webhook being sent
        log_app_event('WEBHOOK_SEND', 'Sending webhook to ' . $order['webhook_url'], $payload, 'info');

        $webhook_result = send_webhook($order['webhook_url'], $payload, $webhook_secret);
    }

    // Redirect back to the UI page to show success message
    header('Location: ' . $base_path . '/pay-ui/' . $transaction_id);
    exit;
}

// 8. Log Reporting (POST /logs/report)
if ($route_path === '/logs/report' && $method === 'POST') {
    // Add CORS headers for cross-origin requests
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

    $input = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    $required_fields = ['error', 'context'];
    $missing_fields = [];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            $missing_fields[] = $field;
        }
    }

    if (!empty($missing_fields)) {
        $error_response = [
            "status" => "error",
            "message" => "Missing required fields: " . implode(', ', $missing_fields),
            "required_fields" => $required_fields
        ];
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode($error_response);
        exit;
    }

    // Extract error details
    $error_message = $input['error'];
    $error_context = $input['context'];
    $error_severity = $input['severity'] ?? 'error'; // error, warning, info
    $error_source = $input['source'] ?? 'unknown'; // frontend, backend, plugin, etc.
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $user_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $timestamp = date('c');
    $request_id = uniqid('err_', true);

    // Prepare error data for logging
    $error_data = [
        'id' => $request_id,
        'timestamp' => $timestamp,
        'severity' => $error_severity,
        'source' => $error_source,
        'error' => $error_message,
        'context' => $error_context,
        'user_agent' => $user_agent,
        'user_ip' => $user_ip,
        'request_headers' => getallheaders(),
        'additional_data' => $input['additional_data'] ?? null
    ];

    // Also save to a dedicated error log file
    $error_log_file = get_current_error_reports_file();
    $existing_errors = [];
    if (file_exists($error_log_file)) {
        $existing_errors = json_decode(file_get_contents($error_log_file), true) ?: [];
    }
    $existing_errors[] = $error_data;

    // Keep only last 1000 errors to prevent file from growing too large
    if (count($existing_errors) > 1000) {
        $existing_errors = array_slice($existing_errors, -1000);
    }

    file_put_contents($error_log_file, json_encode($existing_errors, JSON_PRETTY_PRINT));

    // Log this error as an app event
    log_app_event('error_report', "Error reported: {$error_message}", [
        'error_id' => $request_id,
        'severity' => $error_severity,
        'source' => $error_source,
        'context' => $error_context,
        'user_ip' => $user_ip,
        'user_agent' => $user_agent
    ], $error_severity);

    $response_data = [
        "status" => "success",
        "message" => "Error report logged successfully",
        "error_id" => $request_id,
        "timestamp" => $timestamp
    ];

    header('Content-Type: application/json');
    echo json_encode($response_data);
    exit;
}

// Handle OPTIONS request for log reporting CORS preflight
if ($route_path === '/logs/report' && $method === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

// 10. Get Log Reports (GET /logs)
if ($route_path === '/logs' && $method === 'GET') {
    global $logs_dir;
    $errors = [];

    // Scan all date directories and error files
    if (is_dir($logs_dir)) {
        $date_dirs = scandir($logs_dir);
        foreach ($date_dirs as $date_dir) {
            if ($date_dir === '.' || $date_dir === '..') continue;

            $date_path = $logs_dir . '/' . $date_dir;
            if (!is_dir($date_path)) continue;

            $error_files = glob($date_path . '/errors-*.json');
            foreach ($error_files as $error_file) {
                if (file_exists($error_file)) {
                    $file_errors = json_decode(file_get_contents($error_file), true) ?: [];
                    $errors = array_merge($errors, $file_errors);
                }
            }
        }
    }

    // Fallback to old error_reports.json if it exists (for backward compatibility)
    $old_error_file = __DIR__ . '/error_reports.json';
    if (file_exists($old_error_file)) {
        $old_errors = json_decode(file_get_contents($old_error_file), true) ?: [];
        $errors = array_merge($errors, $old_errors);
    }

    // Optional filtering
    $severity = $_GET['severity'] ?? null;
    $source = $_GET['source'] ?? null;
    $limit = intval($_GET['limit'] ?? 50);

    if ($severity) {
        $errors = array_filter($errors, function($error) use ($severity) {
            return $error['severity'] === $severity;
        });
    }

    if ($source) {
        $errors = array_filter($errors, function($error) use ($source) {
            return $error['source'] === $source;
        });
    }

    // Get most recent errors first
    $errors = array_reverse($errors);
    $errors = array_slice($errors, 0, $limit);

    $response_data = [
        "status" => "success",
        "count" => count($errors),
        "errors" => $errors
    ];

    header('Content-Type: application/json');
    echo json_encode($response_data);
    exit;
}

// 404 Not Found
http_response_code(404);
header('Content-Type: application/json');
echo json_encode([
    "status" => "NOT_FOUND",
    "message" => "Route not found",
    "debug" => [
        "request_uri" => $request_uri,
        "method" => $method,
        "path" => $path,
        "base_path" => $base_path,
        "route_path" => $route_path,
        "script_name" => $script_name
    ]
]);
exit;
