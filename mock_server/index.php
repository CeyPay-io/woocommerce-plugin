<?php
// CeyPay Mock Server in PHP

// Configuration
$webhook_secret = "mock_secret_key";
$orders_file = __DIR__ . '/orders.json';
$log_file = __DIR__ . '/api_logs.txt';
$base_url = getenv('BASE_URL') ?: 'http://localhost:5000';

// Helper to log API requests
function log_request($endpoint, $method, $data = null, $response = null, $status_code = 200) {
    global $log_file;
    
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "\n" . str_repeat('=', 80) . "\n";
    $log_entry .= "[{$timestamp}] {$method} {$endpoint}\n";
    $log_entry .= str_repeat('-', 80) . "\n";
    
    // Request headers
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0) {
            $header_name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
            $headers[$header_name] = $value;
        }
    }
    
    if (!empty($headers)) {
        $log_entry .= "REQUEST HEADERS:\n";
        $log_entry .= json_encode($headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        $log_entry .= str_repeat('-', 80) . "\n";
    }
    
    // Request data
    if ($data !== null) {
        $log_entry .= "REQUEST BODY:\n";
        if (is_array($data)) {
            $log_entry .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        } else {
            $log_entry .= $data . "\n";
        }
        $log_entry .= str_repeat('-', 80) . "\n";
    }
    
    // Response
    if ($response !== null) {
        $log_entry .= "RESPONSE (Status: {$status_code}):\n";
        if (is_array($response)) {
            $log_entry .= json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        } else {
            $log_entry .= $response . "\n";
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
$script_name = $_SERVER['SCRIPT_NAME'];
$base_path = dirname($script_name);
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
        <p><a href="<?php echo $base_path; ?>/logs" class="btn" style="display: inline-block; margin-bottom: 10px;">📋 View API Logs</a></p>
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
    global $log_file;
    
    $log_content = '';
    if (file_exists($log_file)) {
        $log_content = file_get_contents($log_file);
    }
    
    // Get last modified time for auto-refresh
    $last_modified = file_exists($log_file) ? filemtime($log_file) : 0;
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
    global $log_file;
    $last_modified = file_exists($log_file) ? filemtime($log_file) : 0;
    
    header('Content-Type: application/json');
    echo json_encode(['modified' => $last_modified]);
    exit;
}

// 1.7. Clear Logs (POST /logs/clear)
if ($route_path === '/logs/clear' && $method === 'POST') {
    global $log_file;
    if (file_exists($log_file)) {
        file_put_contents($log_file, '');
    }
    header('Location: ' . $base_path . '/logs');
    exit;
}

// 2. Create Payment (POST /payment/create)
if ($route_path === '/payment/create' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Log the request
    log_request('/payment/create', 'POST', $input);
    
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
    
    // Log the response
    log_request('/payment/create', 'POST', null, $response_data, 201);
    
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
    log_request('/payment/' . $transaction_id, 'GET');
    
    if (!isset($orders[$transaction_id])) {
        $error_response = ["status" => "NOT_FOUND"];
        log_request('/payment/' . $transaction_id, 'GET', null, $error_response, 404);
        http_response_code(404);
        echo json_encode($error_response);
        exit;
    }
    
    $response_data = [
        "status" => $orders[$transaction_id]['status'],
        "transactionId" => $transaction_id
    ];
    
    // Log the response
    log_request('/payment/' . $transaction_id, 'GET', null, $response_data, 200);
    
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
    
    // Log the request
    log_request('/merchant/webhook-public-key', 'GET', null, $response_data, 200);
    
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
        log_request('WEBHOOK_SEND to ' . $order['webhook_url'], 'POST', $payload);
        
        $webhook_result = send_webhook($order['webhook_url'], $payload, $webhook_secret);
        
        // Log webhook response
        log_request('WEBHOOK_RESPONSE from ' . $order['webhook_url'], 'POST', null, [
            'http_code' => $webhook_result['code'],
            'response' => $webhook_result['response'],
            'error' => $webhook_result['error']
        ]);
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
        log_request('WEBHOOK_SEND to ' . $order['webhook_url'], 'POST', $payload);
        
        $webhook_result = send_webhook($order['webhook_url'], $payload, $webhook_secret);
        
        // Log webhook response
        log_request('WEBHOOK_RESPONSE from ' . $order['webhook_url'], 'POST', null, [
            'http_code' => $webhook_result['code'],
            'response' => $webhook_result['response'],
            'error' => $webhook_result['error']
        ]);
    }
    
    // Redirect back to the UI page to show success message
    header('Location: ' . $base_path . '/pay-ui/' . $transaction_id);
    exit;
}

// 8. Debug Telegram (POST /debug/telegram)
if ($route_path === '/debug/telegram' && $method === 'POST') {
    // Add CORS headers
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $message = $input['message'] ?? '';
    
    // Log the request
    log_request('/debug/telegram', 'POST', $input);
    
    $bot_token = '8581816945:AAHaCnbMV2IzXIg-Fl2LEK_uUErbnbs6Odk';
    $chat_id = '1076120105';
    
    $url = "https://api.telegram.org/bot$bot_token/sendMessage";
    
    $data = [
        'chat_id' => $chat_id,
        'text'    => $message,
        'parse_mode' => 'HTML'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $response_data = ["status" => "sent", "response" => json_decode($response)];
    
    // Log the response
    log_request('/debug/telegram', 'POST', null, $response_data, 200);
    
    header('Content-Type: application/json');
    echo json_encode($response_data);
    exit;
}

// Handle OPTIONS request for CORS preflight
if ($route_path === '/debug/telegram' && $method === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Content-Type: application/json');
    echo json_encode([]);
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
