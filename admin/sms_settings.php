<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if(!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$message = null;

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gatewayUrl = $_POST['gateway_url'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $enabled = isset($_POST['enabled']) ? 'true' : 'false';
    
    // Read current config file
    $configFile = '../config/sms_config.php';
    $content = file_get_contents($configFile);
    
    // Update values
    $content = preg_replace(
        "/define\('SMS_GATEWAY_URL',\s*'[^']*'\);/",
        "define('SMS_GATEWAY_URL', '$gatewayUrl');",
        $content
    );
    $content = preg_replace(
        "/define\('SMS_USERNAME',\s*'[^']*'\);/",
        "define('SMS_USERNAME', '$username');",
        $content
    );
    $content = preg_replace(
        "/define\('SMS_PASSWORD',\s*'[^']*'\);/",
        "define('SMS_PASSWORD', '$password');",
        $content
    );
    $content = preg_replace(
        "/define\('SMS_ENABLED',\s*(true|false)\);/",
        "define('SMS_ENABLED', $enabled);",
        $content
    );
    
    // Save updated config
    if (file_put_contents($configFile, $content)) {
        $message = ['type' => 'success', 'text' => 'SMS settings updated successfully!'];
    } else {
        $message = ['type' => 'danger', 'text' => 'Failed to update settings. Check file permissions.'];
    }
}

// Load current settings
require_once '../config/sms_config.php';

$title = 'SMS Settings - Motoshapi Admin';
$activeAdminPage = 'sms';
$mainClass = 'flex-grow-1 py-4 container-xxl';
include 'includes/header.php';
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h2 class="mb-1"><i class="bi bi-gear me-2"></i>SMS Gateway Settings</h2>
        <p class="text-muted mb-0">Configure your SMS gateway connection for defense/different networks</p>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show">
    <i class="bi bi-<?php echo $message['type'] == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>-fill me-2"></i>
    <?php echo $message['text']; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST">
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Quick Setup para sa  demonstration:</strong> Before your presentation, connect your phone and laptop to the venue's Wi-Fi, 
                open SMSGate app to see the new IP address, tas update mo dito perds.
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">Gateway URL</label>
                <input type="text" name="gateway_url" class="form-control" 
                       value="<?php echo SMS_GATEWAY_URL; ?>" 
                       placeholder="http://192.168.1.100:8080" required>
                <small class="form-text text-muted">
                    Local mode: http://&lt;phone-ip&gt;:8080 | Cloud mode: https://api.sms-gate.app/3rdparty/v1
                </small>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Username</label>
                    <input type="text" name="username" class="form-control" 
                           value="<?php echo SMS_USERNAME; ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Password</label>
                    <input type="text" name="password" class="form-control" 
                           value="<?php echo SMS_PASSWORD; ?>" required>
                </div>
            </div>

            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="enabled" id="smsEnabled" 
                           <?php echo SMS_ENABLED ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="smsEnabled">
                        <strong>Enable SMS Gateway</strong>
                        <small class="d-block text-muted">Turn off to disable all SMS sending</small>
                    </label>
                </div>
            </div>

            <hr class="my-4">

            <div class="alert alert-warning">
                <i class="bi bi-wifi me-2"></i>
                <strong>Current Phone IP:</strong> Check SMSGate app on your Android phone to see the current IP address
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Save Settings
                </button>
                <a href="sms_dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-body p-4">
        <h5 class="mb-3"><i class="bi bi-lightbulb me-2"></i>Defense Day Setup Checklist</h5>
        
        <div class="alert alert-warning mb-3">
            <strong>⚠️ IMPORTANT:</strong> Both phone IP and computer IP will change on different Wi-Fi networks!
        </div>
        
        <h6 class="fw-bold mb-2"><i class="bi bi-1-circle me-2"></i>Step 1: Connect to Venue Wi-Fi</h6>
        <ul class="mb-3">
            <li>Connect both your Android phone and laptop to the defense venue's Wi-Fi</li>
            <li>Make sure both devices are on the same network</li>
        </ul>

        <h6 class="fw-bold mb-2"><i class="bi bi-2-circle me-2"></i>Step 2: Update SMSGate (Sending SMS)</h6>
        <ul class="mb-3">
            <li>Open <strong>SMSGate app</strong> on your phone</li>
            <li>Note the IP address shown (e.g., 192.168.50.123:8080)</li>
            <li>Update <strong>Gateway URL</strong> on this page with the new IP</li>
            <li>Click <strong>Save Settings</strong></li>
        </ul>

        <h6 class="fw-bold mb-2"><i class="bi bi-3-circle me-2"></i>Step 3: Update SMS Forwarder (Receiving SMS)</h6>
        <ul class="mb-3">
            <li>On your laptop, open Command Prompt</li>
            <li>Type: <code>ipconfig</code></li>
            <li>Find <strong>IPv4 Address</strong> (e.g., 192.168.50.100)</li>
            <li>Open <strong>SMS Forwarder app</strong> on your phone</li>
            <li>Update webhook URL to: <code>http://[new-laptop-ip]/MOTOSHAPI/webhooks/sms_webhook.php</code></li>
            <li>Example: <code>http://192.168.50.100/MOTOSHAPI/webhooks/sms_webhook.php</code></li>
        </ul>

        <h6 class="fw-bold mb-2"><i class="bi bi-4-circle me-2"></i>Step 4: Test Everything</h6>
        <ol class="mb-0">
            <li class="mb-2">Go to <a href="sms_dashboard.php" class="fw-bold">SMS Dashboard</a></li>
            <li class="mb-2"><strong>Send test SMS</strong> to verify sending works</li>
            <li class="mb-2">Send SMS to your phone from another phone to test <strong>receiving</strong></li>
            <li class="mb-2">Check if message appears in Dashboard → View Logs tab</li>
            <li>✅ You're ready for demonstration!</li>
        </ol>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-body p-4 bg-light">
        <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>Quick Reference</h6>
        <div class="row">
            <div class="col-md-6 mb-3">
                <strong>Phone IP (SMSGate):</strong><br>
                <small class="text-muted">Check SMSGate app → shown on main screen</small>
            </div>
            <div class="col-md-6 mb-3">
                <strong>Computer IP (Webhook):</strong><br>
                <small class="text-muted">Command Prompt → ipconfig → IPv4 Address</small>
            </div>
        </div>
        <div class="alert alert-success mb-0 mt-2">
            <i class="bi bi-check-circle me-2"></i>
            Both devices must be on the <strong>same Wi-Fi network</strong> for SMS integration to work!
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
