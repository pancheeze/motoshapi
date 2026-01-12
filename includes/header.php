<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';
require_once 'config/currency.php';

$bodyClass = isset($bodyClass) && $bodyClass !== '' ? trim($bodyClass) : 'bg-light';
$mainClassAttr = ' class="flex-grow-1 py-4"';
if (isset($mainClass) && $mainClass !== '') {
    $mainClassAttr = ' class="' . htmlspecialchars($mainClass, ENT_QUOTES) . '"';
}

$cart_count = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += $item['quantity'] ?? 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'Motoshapi - Motorcycle Parts'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/modern-theme.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/chatbot-widget.css">
</head>
<body style="background: #E8E4C9; color: #ffffff;">
    <div class="d-flex flex-column min-vh-100">
        <!-- Modern Navigation -->
        <nav class="modern-navbar">
            <div class="modern-navbar-container">
                <!-- Logo -->
                <a href="index.php" class="modern-logo">
                    <div class="modern-logo-icon">M</div>
                    <span>MOTOSHAPI</span>
                </a>

                <!-- Desktop Navigation Links -->
                <ul class="modern-nav-links d-none d-lg-flex">
                    <li><a href="index.php" class="modern-nav-link<?php echo ($activePage ?? '') === 'home' ? ' active' : ''; ?>">Home</a></li>
                    <li><a href="products.php" class="modern-nav-link<?php echo ($activePage ?? '') === 'products' ? ' active' : ''; ?>">Products</a></li>
                    <li><a href="profile.php" class="modern-nav-link<?php echo ($activePage ?? '') === 'profile' ? ' active' : ''; ?>">Profile</a></li>
                    <li><a href="orders.php" class="modern-nav-link<?php echo ($activePage ?? '') === 'orders' ? ' active' : ''; ?>">My Orders</a></li>
                </ul>

                <!-- Actions -->
                <div class="modern-nav-actions">
                    <!-- Cart -->
                    <a href="cart.php" class="modern-icon-btn">
                        <i class="bi bi-cart" style="font-size: 1.25rem;"></i>
                        <?php if($cart_count > 0): ?>
                            <span class="modern-cart-badge"><?php echo $cart_count; ?></span>
                        <?php endif; ?>
                    </a>

                    <!-- User Menu -->
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="profile.php" class="modern-icon-btn">
                            <i class="bi bi-person" style="font-size: 1.25rem;"></i>
                        </a>
                        <a href="logout.php" class="modern-icon-btn" title="Logout">
                            <i class="bi bi-box-arrow-right" style="font-size: 1.25rem;"></i>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="modern-btn modern-btn-primary" style="padding: 0.5rem 1rem;">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
        <main<?php echo $mainClassAttr; ?>>