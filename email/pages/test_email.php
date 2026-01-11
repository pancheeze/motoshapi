<?php
/**
 * Email Test Script
 * This script helps test email configuration
 */

require_once '../vendor/autoload.php';
require_once '../../config/database.php';
require_once '../config/email.php';

$title = 'Email Configuration Test';
include '../../includes/header.php';
?>

<div class="modern-container" style="padding-top: 4rem; padding-bottom: 4rem;">
    <div class="modern-card" style="max-width: 600px; margin: 0 auto; padding: var(--spacing-2xl);">
        <div style="text-align: center; margin-bottom: var(--spacing-xl);">
            <div class="modern-logo-icon" style="width: 4rem; height: 4rem; margin: 0 auto var(--spacing-md); font-size: 2rem;">M</div>
            <h2 style="font-size: 2rem; font-weight: 700; margin-bottom: var(--spacing-sm);">Email Configuration Test</h2>
            <p style="color: var(--text-secondary);">Test your email settings</p>
        </div>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $test_email = trim($_POST['test_email']);

            if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
                echo '<div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: var(--danger); padding: var(--spacing-md); border-radius: var(--radius-md); margin-bottom: var(--spacing-lg);">
                    Please enter a valid email address.
                </div>';
            } else {
                // Send test email
                $subject = 'Motoshapi Email Test';
                $body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>Email Test Successful!</h1>
                        </div>
                        <div class='content'>
                            <h2>Hello!</h2>
                            <p>This is a test email from your Motoshapi system.</p>
                            <p>If you received this email, your email configuration is working correctly.</p>
                            <p><strong>Test Details:</strong></p>
                            <ul>
                                <li>SMTP Host: " . SMTP_HOST . "</li>
                                <li>SMTP Port: " . SMTP_PORT . "</li>
                                <li>Encryption: " . SMTP_ENCRYPTION . "</li>
                                <li>From: " . FROM_EMAIL . "</li>
                                <li>Test sent at: " . date('Y-m-d H:i:s') . "</li>
                            </ul>
                        </div>
                    </div>
                </body>
                </html>
                ";

                $altBody = "Email Test Successful!\n\nThis is a test email from your Motoshapi system.\n\nIf you received this email, your email configuration is working correctly.\n\nTest sent at: " . date('Y-m-d H:i:s');

                if (sendEmail($test_email, $subject, $body, $altBody) === true) {
                    echo '<div style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: var(--success); padding: var(--spacing-md); border-radius: var(--radius-md); margin-bottom: var(--spacing-lg);">
                        ✅ Test email sent successfully! Check your inbox (and spam folder) for the test message.
                    </div>';
                } else {
                    $error_msg = sendEmail($test_email, $subject, $body, $altBody);
                    echo '<div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: var(--danger); padding: var(--spacing-md); border-radius: var(--radius-md); margin-bottom: var(--spacing-lg);">
                        ❌ Failed to send test email: ' . htmlspecialchars($error_msg) . '<br>Please check your email configuration in config/email.php
                    </div>';
                }
            }
        }
        ?>

        <div style="background: var(--bg-secondary); border: 1px solid var(--border-primary); padding: var(--spacing-lg); border-radius: var(--radius-md); margin-bottom: var(--spacing-xl);">
            <h3 style="margin-bottom: var(--spacing-md); color: var(--text-primary);">Current Configuration</h3>
            <div style="font-family: monospace; font-size: 0.875rem; color: var(--text-secondary);">
                <div>SMTP Host: <?php echo htmlspecialchars(SMTP_HOST); ?></div>
                <div>SMTP Port: <?php echo htmlspecialchars(SMTP_PORT); ?></div>
                <div>Encryption: <?php echo htmlspecialchars(SMTP_ENCRYPTION); ?></div>
                <div>From Email: <?php echo htmlspecialchars(FROM_EMAIL); ?></div>
                <div>From Name: <?php echo htmlspecialchars(FROM_NAME); ?></div>
            </div>
        </div>

        <form method="POST">
            <div style="margin-bottom: var(--spacing-lg);">
                <label for="test_email" style="display: block; margin-bottom: var(--spacing-sm); font-weight: 600; color: var(--text-primary);">Test Email Address</label>
                <input type="email" class="form-control" id="test_email" name="test_email" required style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 0.75rem; border-radius: var(--radius-md); width: 100%; font-size: 1rem;" placeholder="Enter your email address">
                <small style="color: var(--text-secondary); margin-top: var(--spacing-sm); display: block;">We'll send a test email to verify your configuration</small>
            </div>
            <button type="submit" class="modern-btn modern-btn-primary" style="width: 100%; padding: 0.875rem; font-size: 1.0625rem;">Send Test Email</button>
        </form>

        <div style="text-align: center; margin-top: var(--spacing-xl); padding-top: var(--spacing-lg); border-top: 1px solid var(--border-primary);">
            <a href="../../EMAIL_SETUP.md" target="_blank" style="color: var(--accent-primary); text-decoration: none; font-weight: 500;">📖 View Setup Guide</a>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>