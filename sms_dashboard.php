<?php
/**
 * SMS Management Dashboard
 * All-in-one interface for SMS testing, monitoring, and management
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
require_once 'config/sms_config.php';
require_once 'includes/sms_helper.php';

// Handle SMS sending
$sendResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_sms'])) {
    $phone = $_POST['phone_number'];
    $message = $_POST['message'];
    $sendResult = sendSMS($phone, $message);
}

// Get statistics
$stats = [
    'total' => $conn->query("SELECT COUNT(*) FROM sms_logs")->fetchColumn(),
    'sent' => $conn->query("SELECT COUNT(*) FROM sms_logs WHERE status='sent'")->fetchColumn(),
    'failed' => $conn->query("SELECT COUNT(*) FROM sms_logs WHERE status='failed'")->fetchColumn(),
    'received' => $conn->query("SELECT COUNT(*) FROM sms_logs WHERE status='received'")->fetchColumn(),
];

// Get recent logs
$recentLogs = $conn->query("SELECT * FROM sms_logs ORDER BY created_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

// Test connectivity
function testGatewayConnection() {
    $url = SMS_GATEWAY_URL . '/health';
    $context = stream_context_create(['http' => ['timeout' => 3]]);
    $result = @file_get_contents($url, false, $context);
    return $result !== false;
}
$gatewayOnline = SMS_ENABLED ? testGatewayConnection() : false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Management Dashboard - MOTOSHAPI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary: #0d6efd;
            --success: #198754;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #0dcaf0;
        }
        body { background: #f5f7fa; font-family: 'Segoe UI', system-ui, sans-serif; }
        .dashboard-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem 0; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .stat-card { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .stat-value { font-size: 2.5rem; font-weight: 700; margin: 0; }
        .stat-label { color: #6c757d; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .section-card { background: white; border-radius: 12px; padding: 2rem; margin-bottom: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .section-title { font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem; color: #2d3748; border-left: 4px solid var(--primary); padding-left: 1rem; }
        .status-badge { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; }
        .status-online { background: #d4edda; color: #155724; }
        .status-offline { background: #f8d7da; color: #721c24; }
        .log-item { padding: 1rem; border-left: 4px solid #e2e8f0; margin-bottom: 0.75rem; background: #f8f9fa; border-radius: 6px; }
        .log-item.sent { border-left-color: var(--success); }
        .log-item.failed { border-left-color: var(--danger); }
        .log-item.received { border-left-color: var(--info); }
        .log-phone { font-weight: 600; color: var(--primary); }
        .log-message { margin: 0.5rem 0; font-size: 0.95rem; }
        .log-time { font-size: 0.85rem; color: #6c757d; }
        .config-item { display: flex; justify-content: space-between; padding: 0.75rem; background: #f8f9fa; border-radius: 6px; margin-bottom: 0.5rem; }
        .config-label { font-weight: 600; color: #495057; }
        .config-value { font-family: 'Courier New', monospace; color: #212529; }
        .alert-custom { border-radius: 12px; border: none; padding: 1.25rem; }
        .btn-custom { padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; transition: all 0.2s; }
        .btn-custom:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        .tab-content { padding: 1.5rem 0; }
        .nav-tabs .nav-link { border: none; color: #6c757d; font-weight: 600; padding: 1rem 1.5rem; }
        .nav-tabs .nav-link.active { color: var(--primary); border-bottom: 3px solid var(--primary); background: transparent; }
        .empty-state { text-align: center; padding: 3rem 1rem; color: #6c757d; }
        .empty-state i { font-size: 4rem; margin-bottom: 1rem; opacity: 0.5; }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-2"><i class="bi bi-chat-dots"></i> SMS Management Dashboard</h1>
                    <p class="mb-0 opacity-75">Monitor, test, and manage all SMS communications</p>
                </div>
                <a href="admin/orders.php" class="btn btn-light btn-custom">
                    <i class="bi bi-arrow-left"></i> Back to Admin
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-label">Total SMS</div>
                    <div class="stat-value text-primary"><?php echo $stats['total']; ?></div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-label">Sent</div>
                    <div class="stat-value text-success"><?php echo $stats['sent']; ?></div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-label">Received</div>
                    <div class="stat-value text-info"><?php echo $stats['received']; ?></div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-label">Failed</div>
                    <div class="stat-value text-danger"><?php echo $stats['failed']; ?></div>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="section-card">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#overview">
                        <i class="bi bi-speedometer2"></i> Overview
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#send-sms">
                        <i class="bi bi-send"></i> Send SMS
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#logs">
                        <i class="bi bi-list-ul"></i> SMS Logs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#config">
                        <i class="bi bi-gear"></i> Configuration
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Overview Tab -->
                <div id="overview" class="tab-pane fade show active">
                    <h3 class="section-title">System Status</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="config-item">
                                <span class="config-label">SMS Service Status:</span>
                                <?php if (SMS_ENABLED): ?>
                                    <span class="status-badge status-online">
                                        <i class="bi bi-check-circle-fill"></i> ENABLED
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-offline">
                                        <i class="bi bi-x-circle-fill"></i> DISABLED
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="config-item">
                                <span class="config-label">Gateway Connection:</span>
                                <?php if ($gatewayOnline): ?>
                                    <span class="status-badge status-online">
                                        <i class="bi bi-wifi"></i> ONLINE
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-offline">
                                        <i class="bi bi-wifi-off"></i> OFFLINE
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!SMS_ENABLED): ?>
                        <div class="alert alert-warning alert-custom">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <strong>SMS Service Disabled</strong><br>
                            Enable SMS in <code>config/sms_config.php</code> by setting <code>SMS_ENABLED</code> to <code>true</code>
                        </div>
                    <?php elseif (!$gatewayOnline): ?>
                        <div class="alert alert-danger alert-custom">
                            <i class="bi bi-x-circle-fill"></i>
                            <strong>Cannot Connect to SMSGate</strong><br>
                            <ul class="mb-0 mt-2">
                                <li>Ensure SMSGate app is running on your phone</li>
                                <li>Check phone and computer are on same Wi-Fi</li>
                                <li>Verify gateway URL: <code><?php echo SMS_GATEWAY_URL; ?></code></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success alert-custom">
                            <i class="bi bi-check-circle-fill"></i>
                            <strong>All Systems Operational</strong><br>
                            Your SMS gateway is connected and ready to send messages.
                        </div>
                    <?php endif; ?>

                    <h3 class="section-title mt-4">Quick Actions</h3>
                    <div class="d-flex gap-3 flex-wrap">
                        <button class="btn btn-primary btn-custom" onclick="document.querySelector('a[href=\'#send-sms\']').click()">
                            <i class="bi bi-send"></i> Send Test SMS
                        </button>
                        <button class="btn btn-info btn-custom" onclick="document.querySelector('a[href=\'#logs\']').click()">
                            <i class="bi bi-list-ul"></i> View All Logs
                        </button>
                        <button class="btn btn-secondary btn-custom" onclick="location.reload()">
                            <i class="bi bi-arrow-clockwise"></i> Refresh Dashboard
                        </button>
                    </div>
                </div>

                <!-- Send SMS Tab -->
                <div id="send-sms" class="tab-pane fade">
                    <h3 class="section-title">Send Test SMS</h3>
                    
                    <?php if ($sendResult): ?>
                        <?php if ($sendResult['success']): ?>
                            <div class="alert alert-success alert-custom">
                                <i class="bi bi-check-circle-fill"></i>
                                <strong>SMS Sent Successfully!</strong><br>
                                <?php echo htmlspecialchars($sendResult['message']); ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger alert-custom">
                                <i class="bi bi-x-circle-fill"></i>
                                <strong>SMS Failed</strong><br>
                                <?php echo htmlspecialchars($sendResult['message']); ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Phone Number</label>
                            <input type="text" name="phone_number" class="form-control form-control-lg" 
                                   placeholder="09171234567 or +639171234567" required>
                            <small class="form-text text-muted">Enter Philippine mobile number</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Message</label>
                            <textarea name="message" class="form-control" rows="4" required 
                                      placeholder="Enter your test message here..."></textarea>
                            <small class="form-text text-muted">Standard SMS: 160 characters max</small>
                        </div>

                        <div class="d-flex gap-3">
                            <button type="submit" name="send_sms" class="btn btn-primary btn-custom">
                                <i class="bi bi-send"></i> Send SMS
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="populateTestMessage()">
                                <i class="bi bi-lightning"></i> Load Sample Message
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Logs Tab -->
                <div id="logs" class="tab-pane fade">
                    <h3 class="section-title">SMS Activity Log</h3>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-secondary active" onclick="filterLogs('all')">
                                All (<?php echo $stats['total']; ?>)
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="filterLogs('sent')">
                                Sent (<?php echo $stats['sent']; ?>)
                            </button>
                            <button class="btn btn-sm btn-outline-info" onclick="filterLogs('received')">
                                Received (<?php echo $stats['received']; ?>)
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="filterLogs('failed')">
                                Failed (<?php echo $stats['failed']; ?>)
                            </button>
                        </div>
                        <button class="btn btn-sm btn-outline-primary" onclick="location.reload()">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>

                    <?php if (empty($recentLogs)): ?>
                        <div class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <h4>No SMS Activity Yet</h4>
                            <p>Send your first SMS to see it appear here</p>
                        </div>
                    <?php else: ?>
                        <div id="logs-container">
                            <?php foreach ($recentLogs as $log): ?>
                                <div class="log-item <?php echo $log['status']; ?>" data-status="<?php echo $log['status']; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <?php if ($log['status'] == 'sent'): ?>
                                                    <span class="badge bg-success">Sent</span>
                                                <?php elseif ($log['status'] == 'received'): ?>
                                                    <span class="badge bg-info">Received</span>
                                                <?php elseif ($log['status'] == 'failed'): ?>
                                                    <span class="badge bg-danger">Failed</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><?php echo ucfirst($log['status']); ?></span>
                                                <?php endif; ?>
                                                <span class="log-phone"><?php echo htmlspecialchars($log['phone_number']); ?></span>
                                            </div>
                                            <div class="log-message">
                                                <?php echo nl2br(htmlspecialchars($log['message'])); ?>
                                            </div>
                                            <?php if (!empty($log['response'])): ?>
                                                <details class="mt-2">
                                                    <summary class="text-muted" style="cursor: pointer; font-size: 0.85rem;">
                                                        View response details
                                                    </summary>
                                                    <pre class="mt-2 p-2 bg-white border rounded" style="font-size: 0.8rem; max-height: 150px; overflow-y: auto;"><?php echo htmlspecialchars($log['response']); ?></pre>
                                                </details>
                                            <?php endif; ?>
                                        </div>
                                        <div class="log-time">
                                            <i class="bi bi-clock"></i> <?php echo date('M j, g:i A', strtotime($log['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Configuration Tab -->
                <div id="config" class="tab-pane fade">
                    <h3 class="section-title">SMS Gateway Configuration</h3>
                    
                    <div class="config-item">
                        <span class="config-label">Gateway URL:</span>
                        <span class="config-value"><?php echo SMS_GATEWAY_URL; ?></span>
                    </div>
                    <div class="config-item">
                        <span class="config-label">Username:</span>
                        <span class="config-value"><?php echo SMS_USERNAME; ?></span>
                    </div>
                    <div class="config-item">
                        <span class="config-label">SMS Enabled:</span>
                        <span class="config-value"><?php echo SMS_ENABLED ? 'true' : 'false'; ?></span>
                    </div>
                    <div class="config-item">
                        <span class="config-label">Logging Enabled:</span>
                        <span class="config-value"><?php echo SMS_LOG_ENABLED ? 'true' : 'false'; ?></span>
                    </div>

                    <h3 class="section-title mt-4">Incoming SMS Webhook</h3>
                    <div class="alert alert-info alert-custom">
                        <strong>Configure SMS Forwarder app with this URL:</strong>
                        <div class="input-group mt-2">
                            <input type="text" class="form-control" id="webhookUrl" 
                                   value="http://192.168.100.8/MOTOSHAPI/webhooks/sms_webhook.php" readonly>
                            <button class="btn btn-primary" onclick="copyWebhook()">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                    </div>

                    <h3 class="section-title mt-4">Message Templates</h3>
                    <div class="config-item">
                        <span class="config-label">Order Placed:</span>
                        <span class="config-value"><?php echo SMS_ORDER_PLACED; ?></span>
                    </div>
                    <div class="config-item">
                        <span class="config-label">Order Shipped:</span>
                        <span class="config-value"><?php echo SMS_ORDER_SHIPPED; ?></span>
                    </div>
                    <div class="config-item">
                        <span class="config-label">Order Delivered:</span>
                        <span class="config-value"><?php echo SMS_ORDER_DELIVERED; ?></span>
                    </div>
                    <div class="config-item">
                        <span class="config-label">Order Cancelled:</span>
                        <span class="config-value"><?php echo SMS_ORDER_CANCELLED; ?></span>
                    </div>

                    <div class="alert alert-secondary alert-custom mt-4">
                        <i class="bi bi-info-circle"></i>
                        <strong>Edit Configuration:</strong> config/sms_config.php
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function populateTestMessage() {
            document.querySelector('textarea[name="message"]').value = 
                'MOTOSHAPI: Test message from SMS dashboard. If you receive this, your integration is working perfectly!';
        }

        function filterLogs(status) {
            const logs = document.querySelectorAll('.log-item');
            const buttons = document.querySelectorAll('.btn-group button');
            
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            logs.forEach(log => {
                if (status === 'all' || log.dataset.status === status) {
                    log.style.display = 'block';
                } else {
                    log.style.display = 'none';
                }
            });
        }

        function copyWebhook() {
            const input = document.getElementById('webhookUrl');
            input.select();
            document.execCommand('copy');
            
            const btn = event.target.closest('button');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check"></i> Copied!';
            setTimeout(() => {
                btn.innerHTML = originalHtml;
            }, 2000);
        }

        // Auto-refresh every 60 seconds
        setTimeout(() => location.reload(), 60000);
    </script>
</body>
</html>
