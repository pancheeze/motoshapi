<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../config/currency.php';

$bodyClass = isset($bodyClass) && $bodyClass !== '' ? trim($bodyClass) : 'bg-light';
$mainClassAttr = ' class="flex-grow-1 py-4 container-fluid"';
if (isset($mainClass) && $mainClass !== '') {
    $mainClassAttr = ' class="' . htmlspecialchars($mainClass, ENT_QUOTES) . '"';
}

$navItems = [
    'dashboard' => ['label' => 'Dashboard', 'href' => 'index.php', 'icon' => 'bi-speedometer2'],
    'products' => ['label' => 'Products', 'href' => 'products.php', 'icon' => 'bi-box-seam'],
    'featured' => ['label' => 'Featured Products', 'href' => 'featured_products.php', 'icon' => 'bi-star'],
    'categories' => ['label' => 'Categories', 'href' => 'categories.php', 'icon' => 'bi-tags'],
    'orders' => ['label' => 'Orders', 'href' => 'orders.php', 'icon' => 'bi-receipt'],
    'users' => ['label' => 'Users', 'href' => 'users.php', 'icon' => 'bi-people'],
    'admins' => ['label' => 'Admins', 'href' => 'manage_admins.php', 'icon' => 'bi-shield-lock'],
    'sms' => ['label' => 'SMS Dashboard', 'href' => 'sms_dashboard.php', 'icon' => 'bi-chat-dots'],
    'about' => ['label' => 'About Us', 'href' => 'about_us.php', 'icon' => 'bi-info-circle'],
    'payments' => ['label' => 'Payment Settings', 'href' => 'payment_settings.php', 'icon' => 'bi-credit-card'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'Motoshapi Admin'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
</head>
<body class="<?php echo htmlspecialchars($bodyClass, ENT_QUOTES); ?>">
    <div class="d-flex flex-column min-vh-100">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
            <div class="container-fluid">
                <a class="navbar-brand fw-semibold" href="index.php">
                    <i class="bi bi-gear me-2"></i>Motoshapi Admin
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="adminNavbar">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0 gap-lg-1">
                        <?php foreach ($navItems as $key => $item): ?>
                            <li class="nav-item">
                                <a class="nav-link<?php echo (($activeAdminPage ?? '') === $key) ? ' active' : ''; ?>" href="<?php echo $item['href']; ?>">
                                    <i class="bi <?php echo $item['icon']; ?> me-1"></i><?php echo $item['label']; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-3">
                        <li class="nav-item">
                            <span class="navbar-text small text-white-50">Logged in as Admin</span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                        <li class="nav-item">
                            <button class="btn btn-link nav-link p-0 dark-mode-toggle" id="theme-toggle" type="button" aria-label="Toggle dark mode">
                                <i class="bi bi-moon"></i>
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        <main<?php echo $mainClassAttr; ?>>