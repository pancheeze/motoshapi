<?php
$title = 'Forgot Password - Motoshapi';
$activePage = 'login';
include '../../includes/header.php';

require_once '../vendor/autoload.php';
require_once '../config/email.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email exists
        $stmt = $conn->prepare('SELECT id, username FROM users WHERE email = ? AND status = ?');
        $stmt->execute([$email, 'active']);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Generate reset token
            $resetToken = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Update user with reset token
            $stmt = $conn->prepare('UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?');
            $stmt->execute([$resetToken, $expires, $user['id']]);

            // Send password reset email
            if (sendPasswordResetEmail($email, $resetToken)) {
                $success = 'Password reset instructions have been sent to your email address.';
            } else {
                $error = 'Unable to send password reset email. Please try again later.';
            }
        } else {
            // Don't reveal if email exists or not for security
            $success = 'If an account with that email address exists, password reset instructions have been sent.';
        }
    }
}
?>
    <div class="modern-container" style="padding-top: 4rem; padding-bottom: 4rem;">
        <div class="modern-card" style="max-width: 450px; margin: 0 auto; padding: var(--spacing-2xl);">
            <div style="text-align: center; margin-bottom: var(--spacing-xl);">
                <div class="modern-logo-icon" style="width: 4rem; height: 4rem; margin: 0 auto var(--spacing-md); font-size: 2rem;">M</div>
                <h2 style="font-size: 2rem; font-weight: 700; margin-bottom: var(--spacing-sm);">Forgot Password</h2>
                <p style="color: var(--text-secondary);">Enter your email to reset your password</p>
            </div>

            <?php if($error): ?>
                <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: var(--danger); padding: var(--spacing-md); border-radius: var(--radius-md); margin-bottom: var(--spacing-lg);">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            <?php if($success): ?>
                <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: var(--success); padding: var(--spacing-md); border-radius: var(--radius-md); margin-bottom: var(--spacing-lg);">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div style="margin-bottom: var(--spacing-lg);">
                    <label for="email" style="display: block; margin-bottom: var(--spacing-sm); font-weight: 600; color: var(--text-primary);">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" required style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 0.75rem; border-radius: var(--radius-md); width: 100%; font-size: 1rem;">
                </div>
                <button type="submit" class="modern-btn modern-btn-primary" style="width: 100%; padding: 0.875rem; font-size: 1.0625rem;">Send Reset Instructions</button>
            </form>

            <div style="text-align: center; margin-top: var(--spacing-xl); padding-top: var(--spacing-lg); border-top: 1px solid var(--border-primary);">
                <span style="color: var(--text-secondary);">Remember your password? </span>
                <a href="../../login.php" style="color: var(--accent-primary); text-decoration: none; font-weight: 600;">Sign In</a>
            </div>
        </div>
    </div>
<?php include '../../includes/footer.php'; ?>