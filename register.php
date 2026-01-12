<?php
$title = 'Register - Motoshapi';
$activePage = 'register';
include 'includes/header.php';

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : null;
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Check if username or email exists
        $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = 'Username or email already exists.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('INSERT INTO users (username, password, email, phone) VALUES (?, ?, ?, ?)');
            if ($stmt->execute([$username, $hashed_password, $email, $phone])) {
                $success = 'Registration successful! You can now <a href="login.php">login</a>.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
    <div class="modern-container" style="padding-top: 4rem; padding-bottom: 4rem;">
        <div class="modern-card" style="max-width: 450px; margin: 0 auto; padding: var(--spacing-2xl);">
            <div style="text-align: center; margin-bottom: var(--spacing-xl);">
                <div class="modern-logo-icon" style="width: 4rem; height: 4rem; margin: 0 auto var(--spacing-md); font-size: 2rem;">M</div>
                <h2 style="font-size: 2rem; font-weight: 700; margin-bottom: var(--spacing-sm);">Create Account</h2>
                <p style="color: var(--text-secondary);">Join Motoshapi today</p>
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
                    <label for="username" style="display: block; margin-bottom: var(--spacing-sm); font-weight: 600; color: var(--text-primary);">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 0.75rem; border-radius: var(--radius-md); width: 100%; font-size: 1rem;">
                </div>
                <div style="margin-bottom: var(--spacing-lg);">
                    <label for="email" style="display: block; margin-bottom: var(--spacing-sm); font-weight: 600; color: var(--text-primary);">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 0.75rem; border-radius: var(--radius-md); width: 100%; font-size: 1rem;">
                </div>
                <div style="margin-bottom: var(--spacing-lg);">
                    <label for="phone" style="display: block; margin-bottom: var(--spacing-sm); font-weight: 600; color: var(--text-primary);">Phone Number <span style="color: var(--text-muted); font-size: 0.875rem;">(Optional)</span></label>
                    <input type="tel" class="form-control" id="phone" name="phone" placeholder="+63 912 345 6789" style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 0.75rem; border-radius: var(--radius-md); width: 100%; font-size: 1rem;">
                </div>
                <div style="margin-bottom: var(--spacing-lg);">
                    <label for="password" style="display: block; margin-bottom: var(--spacing-sm); font-weight: 600; color: var(--text-primary);">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 0.75rem; border-radius: var(--radius-md); width: 100%; font-size: 1rem;">
                </div>
                <div style="margin-bottom: var(--spacing-xl);">
                    <label for="confirm_password" style="display: block; margin-bottom: var(--spacing-sm); font-weight: 600; color: var(--text-primary);">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 0.75rem; border-radius: var(--radius-md); width: 100%; font-size: 1rem;">
                </div>
                <button type="submit" class="modern-btn modern-btn-primary" style="width: 100%; padding: 0.875rem; font-size: 1.0625rem;">Create Account</button>
            </form>
            
            <div style="text-align: center; margin-top: var(--spacing-xl); padding-top: var(--spacing-lg); border-top: 1px solid var(--border-primary);">
                <span style="color: var(--text-secondary);">Already have an account? </span>
                <a href="login.php" style="color: var(--accent-primary); text-decoration: none; font-weight: 600;">Sign In</a>
            </div>
        </div>
    </div>
<?php include 'includes/footer.php'; ?> 