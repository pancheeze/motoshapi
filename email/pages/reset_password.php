<?php
$title = 'Reset Password - Motoshapi';
$activePage = 'login';
include '../../includes/header.php';

require_once '../vendor/autoload.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: ../../email/pages/forgot_password.php');
    exit;
}

// Verify token
$stmt = $conn->prepare('SELECT id, username, reset_token_expires FROM users WHERE reset_token = ? AND status = ?');
$stmt->execute([$token, 'active']);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $error = 'Invalid or expired reset token.';
} elseif (strtotime($user['reset_token_expires']) < time()) {
    $error = 'Reset token has expired. Please request a new one.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Update password and clear reset token
        $stmt = $conn->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?');
        if ($stmt->execute([$hashed_password, $user['id']])) {
            $success = 'Password reset successfully! You can now <a href="login.php">login</a> with your new password.';
        } else {
            $error = 'Failed to reset password. Please try again.';
        }
    }
}
?>
    <div class="modern-container" style="padding-top: 4rem; padding-bottom: 4rem;">
        <div class="modern-card" style="max-width: 450px; margin: 0 auto; padding: var(--spacing-2xl);">
            <div style="text-align: center; margin-bottom: var(--spacing-xl);">
                <div class="modern-logo-icon" style="width: 4rem; height: 4rem; margin: 0 auto var(--spacing-md); font-size: 2rem;">M</div>
                <h2 style="font-size: 2rem; font-weight: 700; margin-bottom: var(--spacing-sm);">Reset Password</h2>
                <p style="color: var(--text-secondary);">Enter your new password</p>
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

            <?php if(!$success && $user): ?>
            <form method="POST">
                <div style="margin-bottom: var(--spacing-lg);">
                    <label for="password" style="display: block; margin-bottom: var(--spacing-sm); font-weight: 600; color: var(--text-primary);">New Password</label>
                    <input type="password" class="form-control" id="password" name="password" required style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 0.75rem; border-radius: var(--radius-md); width: 100%; font-size: 1rem;">
                </div>
                <div style="margin-bottom: var(--spacing-xl);">
                    <label for="confirm_password" style="display: block; margin-bottom: var(--spacing-sm); font-weight: 600; color: var(--text-primary);">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 0.75rem; border-radius: var(--radius-md); width: 100%; font-size: 1rem;">
                </div>
                <button type="submit" class="modern-btn modern-btn-primary" style="width: 100%; padding: 0.875rem; font-size: 1.0625rem;">Reset Password</button>
            </form>
            <?php endif; ?>

            <div style="text-align: center; margin-top: var(--spacing-xl); padding-top: var(--spacing-lg); border-top: 1px solid var(--border-primary);">
                <span style="color: var(--text-secondary);">Remember your password? </span>
                <a href="../../login.php" style="color: var(--accent-primary); text-decoration: none; font-weight: 600;">Sign In</a>
            </div>
        </div>
    </div>
<?php include '../../includes/footer.php'; ?>