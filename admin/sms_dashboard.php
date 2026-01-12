<?php
session_start();
require_once '../config/database.php';
require_once '../config/currency.php';
require_once '../config/sms_config.php';
require_once '../includes/sms_helper.php';

// Check if admin is logged in
if(!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Handle SMS sending
$sendResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_sms'])) {
    $phone = $_POST['phone_number'];
    $message = $_POST['message'];
    $replyToId = $_POST['reply_to_id'] ?? null;
    
    $sendResult = sendSMS($phone, $message);
    
    // Mark original message as replied if this is a reply
    if ($sendResult['success'] && $replyToId) {
        $conn->prepare("UPDATE sms_logs SET response = 'Replied' WHERE id = ?")->execute([$replyToId]);
    }
}

// Handle SMS deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_sms'])) {
    $logId = $_POST['log_id'];
    $conn->prepare("DELETE FROM sms_logs WHERE id = ?")->execute([$logId]);
    header("Location: sms_dashboard.php?tab=logs");
    exit();
}

// Handle conversation deletion (all messages from a phone number)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_conversation'])) {
    $phone = $_POST['phone_number'];
    $conn->prepare("DELETE FROM sms_logs WHERE phone_number = ?")->execute([$phone]);
    header("Location: sms_dashboard.php?tab=logs");
    exit();
}

// Get statistics
$stats = [
    'total' => $conn->query("SELECT COUNT(*) FROM sms_logs")->fetchColumn(),
    'sent' => $conn->query("SELECT COUNT(*) FROM sms_logs WHERE status='sent'")->fetchColumn(),
    'failed' => $conn->query("SELECT COUNT(*) FROM sms_logs WHERE status='failed'")->fetchColumn(),
    'received' => $conn->query("SELECT COUNT(*) FROM sms_logs WHERE status='received'")->fetchColumn(),
];

// Get recent logs grouped by phone number for conversation view
$recentLogs = $conn->query("
    SELECT * FROM sms_logs 
    ORDER BY phone_number, created_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Group messages by phone number for conversation view with proper threading
$conversations = [];
foreach ($recentLogs as $log) {
    $phone = $log['phone_number'];
    if (!isset($conversations[$phone])) {
        $conversations[$phone] = [
            'messages' => [],
            'last_activity' => $log['created_at']
        ];
    }
    $conversations[$phone]['messages'][] = $log;
    // Update last activity time
    if (strtotime($log['created_at']) > strtotime($conversations[$phone]['last_activity'])) {
        $conversations[$phone]['last_activity'] = $log['created_at'];
    }
}

// Sort conversations by last activity (most recent first)
uasort($conversations, function($a, $b) {
    return strtotime($b['last_activity']) - strtotime($a['last_activity']);
});

// Get users with phone numbers for quick selection
$usersWithPhone = $conn->query("
    SELECT DISTINCT u.id, u.username, u.email, u.phone 
    FROM users u 
    WHERE u.phone IS NOT NULL AND u.phone != '' 
    ORDER BY u.username
")->fetchAll(PDO::FETCH_ASSOC);

// Get unreplied customer messages
$unrepliedMessages = $conn->query("
    SELECT * FROM sms_logs 
    WHERE status='received' AND (response IS NULL OR response = '' OR response NOT LIKE '%Replied%')
    ORDER BY created_at DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Test connectivity
function testGatewayConnection() {
    $url = SMS_GATEWAY_URL . '/health';
    $context = stream_context_create(['http' => ['timeout' => 3]]);
    $result = @file_get_contents($url, false, $context);
    return $result !== false;
}
$gatewayOnline = SMS_ENABLED ? testGatewayConnection() : false;

$title = 'SMS Dashboard - Motoshapi Admin';
$activeAdminPage = 'sms';
$mainClass = 'flex-grow-1 py-4 container-xxl';
include 'includes/header.php';
?>

<style>
.stat-card { transition: transform 0.2s; }
.stat-card:hover { transform: translateY(-4px); }
.log-item { padding: 1rem; border-left: 4px solid #e2e8f0; margin-bottom: 0.75rem; background: #f8f9fa; border-radius: 6px; }
.log-item.sent { border-left-color: var(--bs-success); background: #f0f8f0; }
.log-item.failed { border-left-color: var(--bs-danger); }
.log-item.received { border-left-color: var(--bs-info); background: #f0f7ff; }
.conversation-card { transition: box-shadow 0.2s; }
.conversation-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important; }
.message-container .btn { opacity: 0; transition: opacity 0.2s; }
.message-container:hover .btn { opacity: 1; }
</style>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h2 class="mb-1"><i class="bi bi-chat-dots me-2"></i>SMS Management Dashboard</h2>
        <p class="text-muted mb-0">Monitor, test, and manage all SMS communications</p>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card text-white bg-primary h-100 shadow-sm stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <h5 class="card-title">Total SMS</h5>
                    <i class="bi bi-chat-square-text fs-4"></i>
                </div>
                <p class="display-5 fw-bold mb-0"><?php echo $stats['total']; ?></p>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card text-white bg-success h-100 shadow-sm stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <h5 class="card-title">Sent</h5>
                    <i class="bi bi-check-circle fs-4"></i>
                </div>
                <p class="display-5 fw-bold mb-0"><?php echo $stats['sent']; ?></p>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card text-white bg-info h-100 shadow-sm stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <h5 class="card-title">Received</h5>
                    <i class="bi bi-arrow-down-circle fs-4"></i>
                </div>
                <p class="display-5 fw-bold mb-0"><?php echo $stats['received']; ?></p>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card text-white bg-danger h-100 shadow-sm stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <h5 class="card-title">Failed</h5>
                    <i class="bi bi-x-circle fs-4"></i>
                </div>
                <p class="display-5 fw-bold mb-0"><?php echo $stats['failed']; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-0">
        <ul class="nav nav-tabs nav-fill border-bottom-0" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#overview" type="button">
                    <i class="bi bi-speedometer2 me-1"></i> Overview
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#send-sms" type="button">
                    <i class="bi bi-send me-1"></i> Send SMS
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#logs" type="button">
                    <i class="bi bi-list-ul me-1"></i> SMS Logs
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#config" type="button">
                    <i class="bi bi-gear me-1"></i> Configuration
                </button>
            </li>
        </ul>

        <div class="tab-content p-4">
            <!-- Overview Tab -->
            <div class="tab-pane fade show active" id="overview">
                <h4 class="mb-4">System Status</h4>
                
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                            <span class="fw-bold">SMS Service Status:</span>
                            <?php if (SMS_ENABLED): ?>
                                <span class="badge bg-success px-3 py-2">
                                    <i class="bi bi-check-circle-fill me-1"></i> ENABLED
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger px-3 py-2">
                                    <i class="bi bi-x-circle-fill me-1"></i> DISABLED
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                            <span class="fw-bold">Gateway Connection:</span>
                            <?php if ($gatewayOnline): ?>
                                <span class="badge bg-success px-3 py-2">
                                    <i class="bi bi-wifi me-1"></i> ONLINE
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger px-3 py-2">
                                    <i class="bi bi-wifi-off me-1"></i> OFFLINE
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!SMS_ENABLED): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>SMS Service Disabled</strong><br>
                        Enable SMS in <code>config/sms_config.php</code> by setting <code>SMS_ENABLED</code> to <code>true</code>
                    </div>
                <?php elseif (!$gatewayOnline): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-x-circle-fill me-2"></i>
                        <strong>Cannot Connect to SMSGate</strong>
                        <ul class="mb-0 mt-2">
                            <li>Ensure SMSGate app is running on your phone</li>
                            <li>Check phone and computer are on same Wi-Fi</li>
                            <li>Verify gateway URL: <code><?php echo SMS_GATEWAY_URL; ?></code></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <strong>All Systems Operational</strong><br>
                        Your SMS gateway is connected and ready to send messages.
                    </div>
                <?php endif; ?>

                <h4 class="mb-3 mt-4">Quick Actions</h4>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-primary" onclick="document.querySelector('button[data-bs-target=\'#send-sms\']').click()">
                        <i class="bi bi-send me-1"></i> Send Test SMS
                    </button>
                    <button class="btn btn-info" onclick="document.querySelector('button[data-bs-target=\'#logs\']').click()">
                        <i class="bi bi-list-ul me-1"></i> View All Logs
                    </button>
                    <button class="btn btn-warning" onclick="viewReceivedMessages()">
                        <i class="bi bi-inbox me-1"></i> View Customer Replies (<?php echo $stats['received']; ?>)
                    </button>
                    <button class="btn btn-secondary" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh Dashboard
                    </button>
                </div>

                <?php
                // Show unreplied messages that need attention
                if (!empty($unrepliedMessages)):
                ?>
                <h4 class="mb-3 mt-4">
                    <span class="badge bg-warning text-dark"><?php echo count($unrepliedMessages); ?></span>
                    Unreplied Customer Messages
                </h4>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Action Required:</strong> These customers are waiting for your response.
                </div>
                <?php foreach ($unrepliedMessages as $msg): ?>
                    <div class="card mb-2 border-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <span class="badge bg-warning text-dark mb-2">Needs Reply</span>
                                    <h6 class="mb-1 text-primary">From: <?php echo htmlspecialchars($msg['phone_number']); ?></h6>
                                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                                    <div class="d-flex gap-2 mt-2">
                                        <button class="btn btn-sm btn-success" onclick="replyToCustomer('<?php echo htmlspecialchars($msg['phone_number']); ?>', <?php echo $msg['id']; ?>)">
                                            <i class="bi bi-reply-fill me-1"></i> Reply
                                        </button>
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <i class="bi bi-clock me-1"></i><?php echo date('M j, g:i A', strtotime($msg['created_at'])); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="alert alert-success mt-4">
                    <i class="bi bi-check-circle me-2"></i>
                    <strong>All caught up!</strong> No unreplied messages at the moment.
                </div>
                <?php endif; ?>

                <?php
                // Show recent received messages (already replied)
                $repliedMessages = $conn->query("
                    SELECT r.*, s.message as reply_message, s.created_at as reply_time
                    FROM sms_logs r
                    LEFT JOIN sms_logs s ON s.phone_number = r.phone_number AND s.status = 'sent' AND s.created_at > r.created_at
                    WHERE r.status='received' AND r.response LIKE '%Replied%'
                    ORDER BY r.created_at DESC 
                    LIMIT 10
                ")->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($repliedMessages)):
                ?>
                <h4 class="mb-3 mt-4">
                    <i class="bi bi-check-circle text-success me-2"></i>Reply History
                </h4>
                <p class="text-muted">Track all replies MOTOSHAPI sent to customers</p>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>How it works:</strong> When customers reply to your SMS, their messages are automatically forwarded here by SMS Forwarder app.
                </div>
                <?php foreach ($repliedMessages as $replied): ?>
                    <div class="card mb-2 border-success">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 border-end">
                                    <span class="badge bg-info mb-2">Customer Sent</span>
                                    <h6 class="mb-1 text-primary">From: <?php echo htmlspecialchars($replied['phone_number']); ?></h6>
                                    <p class="mb-1 text-muted small"><?php echo nl2br(htmlspecialchars($replied['message'])); ?></p>
                                    <small class="text-muted">
                                        <i class="bi bi-clock me-1"></i><?php echo date('M j, g:i A', strtotime($replied['created_at'])); ?>
                                    </small>
                                </div>
                                <div class="col-md-6">
                                    <span class="badge bg-success mb-2">MOTOSHAPI Replied</span>
                                    <h6 class="mb-1 text-success">To: <?php echo htmlspecialchars($replied['phone_number']); ?></h6>
                                    <?php if (!empty($replied['reply_message'])): ?>
                                        <p class="mb-1"><strong><?php echo nl2br(htmlspecialchars($replied['reply_message'])); ?></strong></p>
                                        <small class="text-muted">
                                            <i class="bi bi-clock me-1"></i><?php echo date('M j, g:i A', strtotime($replied['reply_time'])); ?>
                                        </small>
                                    <?php else: ?>
                                        <p class="mb-1 text-muted"><em>Reply sent (view in logs for details)</em></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="text-center mt-3">
                    <button class="btn btn-sm btn-outline-success" onclick="document.querySelector('button[data-bs-target=\'#logs\']').click(); setTimeout(() => filterLogs('sent'), 100)">
                        View All Sent Messages <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Send SMS Tab -->
            <div class="tab-pane fade" id="send-sms">
                <h4 class="mb-4">Send Test SMS</h4>
                
                <?php if ($sendResult): ?>
                    <?php if ($sendResult['success']): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <strong>SMS Sent Successfully!</strong><br>
                            <?php echo htmlspecialchars($sendResult['message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="bi bi-x-circle-fill me-2"></i>
                            <strong>SMS Failed</strong><br>
                            <?php echo htmlspecialchars($sendResult['message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="alert alert-info mb-4">
                    <strong><i class="bi bi-lightbulb me-1"></i> Quick Templates:</strong> Click any template below to auto-fill the message
                </div>

                <div class="mb-4">
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadTemplate('test')">
                            <i class="bi bi-lightning me-1"></i> Test Message
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="loadTemplate('order_placed')">
                            <i class="bi bi-receipt me-1"></i> Order Placed
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-info" onclick="loadTemplate('order_shipped')">
                            <i class="bi bi-truck me-1"></i> Order Shipped
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-warning" onclick="loadTemplate('order_delivered')">
                            <i class="bi bi-check-circle me-1"></i> Order Delivered
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="loadTemplate('cancel_order')">
                            <i class="bi bi-x-circle me-1"></i> Cancel Order
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadTemplate('promo')">
                            <i class="bi bi-tag me-1"></i> Promo Message
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-dark" onclick="loadTemplate('thank_you')">
                            <i class="bi bi-heart me-1"></i> Thank You
                        </button>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="reply_to_id" id="replyToId" value="">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Customer or Enter Phone Number</label>
                        <select class="form-select form-select-lg mb-2" id="customerSelect" onchange="fillPhoneFromCustomer()">
                            <option value="">-- Select a customer (or type phone below) --</option>
                            <?php foreach ($usersWithPhone as $user): ?>
                                <option value="<?php echo htmlspecialchars($user['phone']); ?>" 
                                        data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                    <?php echo htmlspecialchars($user['username']); ?> - <?php echo htmlspecialchars($user['phone']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="phone_number" id="phoneInput" class="form-control form-control-lg" 
                               placeholder="Or type: 09171234567 or +639171234567" required>
                        <small class="form-text text-muted">Select customer above or manually enter phone number</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Message</label>
                        <textarea name="message" id="smsMessage" class="form-control" rows="5" required 
                                  placeholder="Enter your message or click a template above..."></textarea>
                        <small class="form-text text-muted">
                            <span id="charCount">0</span>/160 characters
                        </small>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" name="send_sms" class="btn btn-primary">
                            <i class="bi bi-send me-1"></i> Send SMS
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('smsMessage').value = ''">
                            <i class="bi bi-x-circle me-1"></i> Clear
                        </button>
                    </div>
                </form>
            </div>

            <!-- Logs Tab -->
            <div class="tab-pane fade" id="logs">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">SMS Conversation History</h4>
                    <button class="btn btn-sm btn-outline-primary" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                    </button>
                </div>
                
                <div class="btn-group mb-3" role="group">
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

                <?php if (empty($recentLogs)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox display-1 mb-3 d-block opacity-25"></i>
                        <h5>No SMS Activity Yet</h5>
                        <p>Send your first SMS to see it appear here</p>
                    </div>
                <?php else: ?>
                    <div id="logs-container">
                        <?php foreach ($conversations as $phone => $conversation): ?>
                            <div class="card mb-3 border shadow-sm conversation-card">
                                <div class="card-header bg-light">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">
                                            <i class="bi bi-person-circle me-2"></i>
                                            <strong><?php echo htmlspecialchars($phone); ?></strong>
                                        </h6>
                                        <div>
                                            <span class="badge bg-secondary"><?php echo count($conversation['messages']); ?> messages</span>
                                            <small class="text-muted ms-2">
                                                Last: <?php echo date('M j, g:i A', strtotime($conversation['last_activity'])); ?>
                                            </small>
                                            <button class="btn btn-sm btn-outline-danger ms-2" onclick="deleteConversation('<?php echo htmlspecialchars($phone); ?>')" title="Delete entire conversation">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body p-3" style="max-height: 500px; overflow-y: auto;">
                                    <?php foreach ($conversation['messages'] as $log): ?>
                                        <div class="mb-3 <?php echo $log['status'] == 'received' ? 'text-start' : 'text-end'; ?> message-container" data-status="<?php echo $log['status']; ?>">
                                            <div class="d-inline-block position-relative" style="max-width: 70%;">
                                                <div class="<?php echo $log['status'] == 'received' ? 'bg-light border' : 'bg-success text-white'; ?> p-3 rounded-3 shadow-sm">
                                                    <button class="btn btn-sm position-absolute top-0 end-0 m-1 <?php echo $log['status'] == 'received' ? 'btn-outline-danger' : 'btn-light text-danger'; ?>" 
                                                            onclick="deleteSMS(<?php echo $log['id']; ?>)" 
                                                            style="padding: 0.15rem 0.4rem; font-size: 0.75rem;"
                                                            title="Delete this message">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                    <div class="mb-2">
                                                        <?php if ($log['status'] == 'sent'): ?>
                                                            <span class="badge bg-white text-success mb-2">
                                                                <i class="bi bi-check-circle me-1"></i>MOTOSHAPI
                                                            </span>
                                                        <?php elseif ($log['status'] == 'received'): ?>
                                                            <span class="badge bg-info mb-2">
                                                                <i class="bi bi-person me-1"></i>Customer
                                                            </span>
                                                        <?php elseif ($log['status'] == 'failed'): ?>
                                                            <span class="badge bg-danger mb-2">
                                                                <i class="bi bi-x-circle me-1"></i>Failed
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="mb-2">
                                                        <?php echo nl2br(htmlspecialchars($log['message'])); ?>
                                                    </div>
                                                    <small class="<?php echo $log['status'] == 'sent' ? 'text-white-50' : 'text-muted'; ?>">
                                                        <i class="bi bi-clock me-1"></i><?php echo date('M j, g:i A', strtotime($log['created_at'])); ?>
                                                    </small>
                                                    <?php if (!empty($log['response']) && $log['status'] == 'sent'): ?>
                                                        <details class="mt-2">
                                                            <summary class="text-white-50 small" style="cursor: pointer;">▸ View response details</summary>
                                                            <div class="mt-2 p-2 bg-white text-dark rounded small">
                                                                <?php
                                                                $responseData = json_decode($log['response'], true);
                                                                if ($responseData && is_array($responseData) && isset($responseData['state'])):
                                                                ?>
                                                                    <table class="table table-sm table-borderless mb-0">
                                                                        <tr>
                                                                            <th width="40%">Message ID:</th>
                                                                            <td><code class="small"><?php echo htmlspecialchars(substr($responseData['id'] ?? 'N/A', 0, 15)); ?>...</code></td>
                                                                        </tr>
                                                                        <tr>
                                                                            <th>Status:</th>
                                                                            <td>
                                                                                <span class="badge bg-<?php echo ($responseData['state'] ?? '') === 'Pending' ? 'warning' : 'info'; ?>">
                                                                                    <?php echo htmlspecialchars($responseData['state'] ?? 'Unknown'); ?>
                                                                                </span>
                                                                            </td>
                                                                        </tr>
                                                                        <?php if (isset($responseData['recipients'][0])): ?>
                                                                        <tr>
                                                                            <th>Recipient:</th>
                                                                            <td>
                                                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($responseData['recipients'][0]['state'] ?? ''); ?></span>
                                                                            </td>
                                                                        </tr>
                                                                        <?php endif; ?>
                                                                    </table>
                                                                <?php else: ?>
                                                                    <div><?php echo nl2br(htmlspecialchars($log['response'])); ?></div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </details>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="text-center mt-3 pt-3 border-top">
                                        <button class="btn btn-sm btn-success" onclick="replyToCustomer('<?php echo htmlspecialchars($phone); ?>')">
                                            <i class="bi bi-reply-fill me-1"></i> Reply to <?php echo htmlspecialchars($phone); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Configuration Tab -->
            <div class="tab-pane fade" id="config">
                <h4 class="mb-4">SMS Gateway Configuration</h4>
                
                <div class="table-responsive mb-4">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th width="30%">Gateway URL:</th>
                                <td><code><?php echo SMS_GATEWAY_URL; ?></code></td>
                            </tr>
                            <tr>
                                <th>Username:</th>
                                <td><code><?php echo SMS_USERNAME; ?></code></td>
                            </tr>
                            <tr>
                                <th>SMS Enabled:</th>
                                <td><code><?php echo SMS_ENABLED ? 'true' : 'false'; ?></code></td>
                            </tr>
                            <tr>
                                <th>Logging Enabled:</th>
                                <td><code><?php echo SMS_LOG_ENABLED ? 'true' : 'false'; ?></code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h4 class="mb-3">Incoming SMS Webhook</h4>
                <div class="alert alert-info">
                    <strong>Configure SMS Forwarder app with this URL:</strong>
                    <div class="input-group mt-2">
                        <input type="text" class="form-control" id="webhookUrl" 
                               value="http://192.168.100.8/MOTOSHAPI/webhooks/sms_webhook.php" readonly>
                        <button class="btn btn-primary" onclick="copyWebhook()">
                            <i class="bi bi-clipboard me-1"></i> Copy
                        </button>
                    </div>
                </div>

                <h4 class="mb-3 mt-4">Message Templates</h4>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th width="30%">Order Placed:</th>
                                <td><code><?php echo SMS_ORDER_PLACED; ?></code></td>
                            </tr>
                            <tr>
                                <th>Order Shipped:</th>
                                <td><code><?php echo SMS_ORDER_SHIPPED; ?></code></td>
                            </tr>
                            <tr>
                                <th>Order Delivered:</th>
                                <td><code><?php echo SMS_ORDER_DELIVERED; ?></code></td>
                            </tr>
                            <tr>
                                <th>Order Cancelled:</th>
                                <td><code><?php echo SMS_ORDER_CANCELLED; ?></code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-secondary mt-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Edit Configuration:</strong> config/sms_config.php
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Delete single SMS message
function deleteSMS(logId) {
    if (confirm('Are you sure you want to delete this message? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="delete_sms" value="1">
            <input type="hidden" name="log_id" value="${logId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Delete entire conversation
function deleteConversation(phoneNumber) {
    if (confirm(`Delete entire conversation with ${phoneNumber}? This will remove all messages from this number and cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="delete_conversation" value="1">
            <input type="hidden" name="phone_number" value="${phoneNumber}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Fill phone number from customer selection
function fillPhoneFromCustomer() {
    const select = document.getElementById('customerSelect');
    const phoneInput = document.getElementById('phoneInput');
    phoneInput.value = select.value;
}

// View received messages - go to logs tab and filter
function viewReceivedMessages() {
    document.querySelector('button[data-bs-target="#logs"]').click();
    setTimeout(() => {
        const receivedBtn = Array.from(document.querySelectorAll('.btn-group button'))
            .find(btn => btn.textContent.includes('Received'));
        if (receivedBtn) receivedBtn.click();
    }, 100);
}

// Reply to customer - switch to Send SMS tab and auto-fill phone number
function replyToCustomer(phoneNumber, messageId = null) {
    // Switch to Send SMS tab
    document.querySelector('button[data-bs-target="#send-sms"]').click();
    
    // Wait for tab to load, then fill phone number
    setTimeout(() => {
        const phoneInput = document.getElementById('phoneInput');
        const replyToIdInput = document.getElementById('replyToId');
        const customerSelect = document.getElementById('customerSelect');
        
        if (phoneInput) {
            phoneInput.value = phoneNumber;
            if (replyToIdInput && messageId) {
                replyToIdInput.value = messageId;
            }
            phoneInput.focus();
            
            // Scroll to the form
            phoneInput.scrollIntoView({ behavior: 'smooth', block: 'start' });
            
            // Show notification
            const notification = document.createElement('div');
            notification.className = 'alert alert-success alert-dismissible fade show mt-3';
            notification.innerHTML = `
                <i class="bi bi-check-circle me-2"></i>
                <strong>Ready to reply!</strong> Replying to ${phoneNumber}. Type your message below.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            phoneInput.parentElement.insertBefore(notification, phoneInput.nextSibling);
            
            // Auto-remove notification after 5 seconds
            setTimeout(() => notification.remove(), 5000);
        }
    }, 200);
}

// SMS Templates
const smsTemplates = {
    test: 'MOTOSHAPI: Test message from SMS dashboard. If you receive this, your integration is working perfectly!',
    order_placed: 'MOTOSHAPI: Your order #[ORDER_ID] has been received! Total: ₱[AMOUNT]. We will process it soon. Thank you!',
    order_shipped: 'MOTOSHAPI: Your order #[ORDER_ID] has been shipped! Expected delivery: 3-5 business days. Track your order at motoshapi.com',
    order_delivered: 'MOTOSHAPI: Your order #[ORDER_ID] has been delivered! Thank you for shopping with us. Rate your experience!',
    cancel_order: 'MOTOSHAPI: Your cancellation request for order #[ORDER_ID] has been processed. Refund will be issued within 3-5 business days.',
    promo: 'MOTOSHAPI SALE! Get 20% OFF on all motorcycle parts this weekend only! Shop now at motoshapi.com Limited time offer!',
    thank_you: 'MOTOSHAPI: Thank you for your purchase! We appreciate your trust. For support, reply to this message or visit motoshapi.com'
};

function loadTemplate(templateKey) {
    const message = smsTemplates[templateKey];
    const textarea = document.getElementById('smsMessage');
    textarea.value = message;
    updateCharCount();
    
    // Scroll to textarea
    textarea.scrollIntoView({ behavior: 'smooth', block: 'center' });
    textarea.focus();
}

function updateCharCount() {
    const textarea = document.getElementById('smsMessage');
    const charCount = document.getElementById('charCount');
    const length = textarea.value.length;
    charCount.textContent = length;
    
    if (length > 160) {
        charCount.classList.add('text-danger', 'fw-bold');
    } else {
        charCount.classList.remove('text-danger', 'fw-bold');
    }
}

// Update character count on input
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('smsMessage');
    if (textarea) {
        textarea.addEventListener('input', updateCharCount);
    }
});

function filterLogs(status) {
    const messages = document.querySelectorAll('#logs-container .mb-3[data-status]');
    const cards = document.querySelectorAll('.conversation-card');
    const buttons = document.querySelectorAll('.btn-group button');
    
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    if (status === 'all') {
        cards.forEach(card => card.style.display = 'block');
        messages.forEach(msg => msg.style.display = 'block');
    } else {
        cards.forEach(card => {
            const cardMessages = card.querySelectorAll('.mb-3[data-status]');
            let hasMatch = false;
            
            cardMessages.forEach(msg => {
                if (msg.dataset.status === status) {
                    msg.style.display = 'block';
                    hasMatch = true;
                } else {
                    msg.style.display = 'none';
                }
            });
            
            card.style.display = hasMatch ? 'block' : 'none';
        });
    }
}

function copyWebhook() {
    const input = document.getElementById('webhookUrl');
    input.select();
    document.execCommand('copy');
    
    const btn = event.target.closest('button');
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Copied!';
    setTimeout(() => {
        btn.innerHTML = originalHtml;
    }, 2000);
}

// Auto-refresh every 60 seconds
setTimeout(() => location.reload(), 60000);
</script>

<?php include 'includes/footer.php'; ?>
