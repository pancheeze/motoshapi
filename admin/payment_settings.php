<?php
session_start();
require_once '../config/database.php';
include 'includes/header.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Payment methods are managed via payment_modes; this setup supports COD and PayPal.
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Settings - Motoshapi Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/dark-mode.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Payment Settings</h2>
        
        <div class="alert alert-info">
            <h5>Cash on Delivery (COD) + PayPal</h5>
            <p>The system supports Cash on Delivery and PayPal. COD is paid on delivery, while PayPal payments are captured online during checkout.</p>
            <p class="mb-0"><strong>Note:</strong> Configure PayPal credentials in <code>config/paypal_config.php</code> for the PayPal button to work.</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dark-mode.js"></script>
</body>
</html> 