<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Optional local overrides (NOT committed). Create: email/config/email.local.php
// Example:
//   <?php
//   define('SMTP_USERNAME', 'your@gmail.com');
//   define('SMTP_PASSWORD', 'your-app-password-no-spaces');
//
$localConfigFile = __DIR__ . '/email.local.php';
if (file_exists($localConfigFile)) {
    require_once $localConfigFile;
}

// Email configuration
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', 'smtp.gmail.com'); // Change this to your SMTP host
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', 587); // 587 for TLS, 465 for SSL
}
if (!defined('SMTP_USERNAME')) {
    define('SMTP_USERNAME', getenv('MOTOSHAPI_SMTP_USERNAME') ?: 'buycyclebikeparts@gmail.com');
}
if (!defined('SMTP_PASSWORD')) {
    $envPassword = getenv('MOTOSHAPI_SMTP_PASSWORD') ?: '';
    define('SMTP_PASSWORD', preg_replace('/\s+/', '', $envPassword)); // App password (no spaces)
}
if (!defined('SMTP_ENCRYPTION')) {
    define('SMTP_ENCRYPTION', 'tls'); // 'tls' or 'ssl'
}
if (!defined('FROM_EMAIL')) {
    define('FROM_EMAIL', getenv('MOTOSHAPI_FROM_EMAIL') ?: SMTP_USERNAME);
}
if (!defined('FROM_NAME')) {
    define('FROM_NAME', getenv('MOTOSHAPI_FROM_NAME') ?: 'Motoshapi');
}

// Email templates directory
define('EMAIL_TEMPLATES_DIR', __DIR__ . '/../templates/emails/');

/**
 * Send an email using PHPMailer
 *
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param string $altBody Plain text alternative (optional)
 * @return string|bool Error message on failure, true on success
 */
function sendEmail($to, $subject, $body, $altBody = '') {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        // Gmail app passwords are sometimes copied with spaces; strip them to avoid auth failures.
        $mail->Password = preg_replace('/\s+/', '', (string) SMTP_PASSWORD);
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;

        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        if (!empty($altBody)) {
            $mail->AltBody = $altBody;
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return $e->getMessage();
    }
}

/**
 * Send welcome email to new user
 *
 * @param string $email User email
 * @param string $username Username
 * @return bool
 */
function sendWelcomeEmail($email, $username) {
    $subject = 'Welcome to Motoshapi!';

    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Welcome to Motoshapi!</h1>
            </div>
            <div class='content'>
                <h2>Hello {$username}!</h2>
                <p>Thank you for registering with Motoshapi, your trusted source for motorcycle parts and accessories.</p>
                <p>Your account has been successfully created and you can now:</p>
                <ul>
                    <li>Browse our extensive catalog of motorcycle parts</li>
                    <li>Place orders securely online</li>
                    <li>Track your order status</li>
                    <li>Manage your profile and preferences</li>
                </ul>
                <p>If you have any questions, feel free to contact our support team.</p>
                <a href='http://localhost/motoshapi/products.php' class='button'>Start Shopping</a>
            </div>
            <div class='footer'>
                <p>&copy; 2026 Motoshapi. All rights reserved.</p>
                <p>This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    $altBody = "Welcome to Motoshapi, {$username}!\n\nThank you for registering with us. Your account has been created successfully.\n\nVisit us at: http://localhost/motoshapi/products.php";

    $result = sendEmail($email, $subject, $body, $altBody);
    return $result === true;
}

/**
 * Send order confirmation email
 *
 * @param string $email Customer email
 * @param array $orderData Order information
 * @return bool
 */
function sendOrderConfirmationEmail($email, $orderData) {
    $subject = "Order Confirmation - Order #{$orderData['order_id']}";

    $orderItems = '';
    $total = 0;

    foreach ($orderData['items'] as $item) {
        $subtotal = $item['price'] * $item['quantity'];
        $total += $subtotal;
        $orderItems .= "
        <tr>
            <td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$item['name']}</td>
            <td style='padding: 10px; border-bottom: 1px solid #ddd; text-align: center;'>{$item['quantity']}</td>
            <td style='padding: 10px; border-bottom: 1px solid #ddd; text-align: right;'>₱" . number_format($item['price'], 2) . "</td>
            <td style='padding: 10px; border-bottom: 1px solid #ddd; text-align: right;'>₱" . number_format($subtotal, 2) . "</td>
        </tr>
        ";
    }

    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .order-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .order-table th { background: #667eea; color: white; padding: 12px; text-align: left; }
            .order-table td { padding: 10px; border-bottom: 1px solid #ddd; }
            .total-row { background: #f0f0f0; font-weight: bold; }
            .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Order Confirmation</h1>
                <p>Order #{$orderData['order_id']}</p>
            </div>
            <div class='content'>
                <h2>Thank you for your order!</h2>
                <p>Dear {$orderData['customer_name']},</p>
                <p>We have received your order and are processing it. Here are the details:</p>

                <h3>Order Details</h3>
                <table class='order-table'>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$orderItems}
                        <tr class='total-row'>
                            <td colspan='3' style='text-align: right; padding: 15px;'>Total:</td>
                            <td style='text-align: right; padding: 15px;'>₱" . number_format($total, 2) . "</td>
                        </tr>
                    </tbody>
                </table>

                <h3>Shipping Information</h3>
                <p>
                    <strong>Name:</strong> {$orderData['first_name']} {$orderData['last_name']}<br>
                    <strong>Email:</strong> {$orderData['email']}<br>
                    <strong>Phone:</strong> {$orderData['phone']}<br>
                    <strong>Address:</strong> {$orderData['house_number']} {$orderData['street']}, {$orderData['barangay']}, {$orderData['city']}, {$orderData['province']} {$orderData['postal_code']}
                </p>

                <p><strong>Payment Method:</strong> Cash on Delivery (COD)</p>
                <p><strong>Order Status:</strong> Pending</p>

                <p>You will receive another email when your order ships. You can track your order status by logging into your account.</p>

                <a href='http://localhost/motoshapi/orders.php' class='button'>Track Your Order</a>
            </div>
            <div class='footer'>
                <p>&copy; 2026 Motoshapi. All rights reserved.</p>
                <p>This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    $altBody = "Order Confirmation - Order #{$orderData['order_id']}\n\nThank you for your order, {$orderData['customer_name']}!\n\nOrder Total: ₱" . number_format($total, 2) . "\n\nYou can track your order at: http://localhost/motoshapi/orders.php";

    $result = sendEmail($email, $subject, $body, $altBody);
    return $result === true;
}

/**
 * Send password reset email
 *
 * @param string $email User email
 * @param string $resetToken Reset token
 * @return bool
 */
function sendPasswordResetEmail($email, $resetToken) {
    $subject = 'Password Reset Request - Motoshapi';

    $resetLink = "http://localhost/motoshapi/email/pages/reset_password.php?token=" . urlencode($resetToken);

    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Password Reset</h1>
            </div>
            <div class='content'>
                <h2>Reset Your Password</h2>
                <p>You have requested to reset your password for your Motoshapi account.</p>
                <p>Click the button below to reset your password:</p>

                <div style='text-align: center;'>
                    <a href='{$resetLink}' class='button'>Reset Password</a>
                </div>

                <div class='warning'>
                    <strong>Important:</strong> This link will expire in 1 hour for security reasons. If you didn't request this password reset, please ignore this email.
                </div>

                <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
                <p><a href='{$resetLink}'>{$resetLink}</a></p>

                <p>For security reasons, this link can only be used once.</p>
            </div>
            <div class='footer'>
                <p>&copy; 2026 Motoshapi. All rights reserved.</p>
                <p>This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    $altBody = "Password Reset Request\n\nYou have requested to reset your password.\n\nReset your password here: {$resetLink}\n\nThis link will expire in 1 hour.";

    $result = sendEmail($email, $subject, $body, $altBody);
    return $result === true;
}

/**
 * Send order status update email
 *
 * @param string $email Customer email
 * @param array $orderData Order information with status
 * @return bool
 */
function sendOrderStatusUpdateEmail($email, $orderData) {
    $subject = "Order Status Update - Order #{$orderData['order_id']}";

    $statusMessages = [
        'pending' => 'Your order is being processed.',
        'processing' => 'Your order is being prepared for shipment.',
        'shipped' => 'Your order has been shipped and is on its way!',
        'delivered' => 'Your order has been delivered successfully.',
        'cancelled' => 'Your order has been cancelled.'
    ];

    $statusMessage = isset($statusMessages[$orderData['status']]) ? $statusMessages[$orderData['status']] : 'Your order status has been updated.';

    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .status-box { background: white; border-left: 4px solid #667eea; padding: 20px; margin: 20px 0; border-radius: 5px; }
            .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Order Status Update</h1>
                <p>Order #{$orderData['order_id']}</p>
            </div>
            <div class='content'>
                <h2>Hello {$orderData['customer_name']},</h2>

                <div class='status-box'>
                    <h3>Status: " . ucfirst($orderData['status']) . "</h3>
                    <p>{$statusMessage}</p>
                </div>

                <p>You can track your order and view detailed information by logging into your account.</p>

                <a href='http://localhost/motoshapi/orders.php' class='button'>View Order Details</a>
            </div>
            <div class='footer'>
                <p>&copy; 2026 Motoshapi. All rights reserved.</p>
                <p>This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    $altBody = "Order Status Update - Order #{$orderData['order_id']}\n\nStatus: " . ucfirst($orderData['status']) . "\n\n{$statusMessage}\n\nView details at: http://localhost/motoshapi/orders.php";

    $result = sendEmail($email, $subject, $body, $altBody);
    return $result === true;
}
?>