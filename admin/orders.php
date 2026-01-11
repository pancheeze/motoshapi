<?php
session_start();
require_once '../config/database.php';
require_once '../config/currency.php';
require_once '../email/vendor/autoload.php';
require_once '../email/config/email.php';

// Check if admin is logged in
if(!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Handle order status update
if(isset($_POST['update_status']) && isset($_POST['order_id']) && isset($_POST['status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['status'];

    // Valid statuses
    $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    if (!in_array($new_status, $valid_statuses)) {
        $_SESSION['error'] = 'Invalid status.';
        header("Location: orders.php");
        exit();
    }

    // Update order status
    $stmt = $conn->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$new_status, $order_id]);

    // Get order details for email
    $stmt = $conn->prepare("SELECT o.*, u.username, u.email, s.first_name, s.last_name FROM orders o LEFT JOIN users u ON o.user_id = u.id LEFT JOIN shipping_information s ON o.id = s.order_id WHERE o.id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order && !empty($order['email'])) {
        $orderData = [
            'order_id' => $order['id'],
            'customer_name' => trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')) ?: $order['username'],
            'status' => $new_status
        ];

        sendOrderStatusUpdateEmail($order['email'], $orderData);
    }

    $_SESSION['success'] = 'Order status updated successfully.';
    header("Location: orders.php");
    exit();
}

// Fetch all orders with user and shipping info
$stmt = $conn->query("SELECT o.*, u.username, u.email, s.first_name AS shipping_first_name, s.last_name AS shipping_last_name, s.city, s.province, s.postal_code, s.phone, s.house_number, s.barangay FROM orders o LEFT JOIN users u ON o.user_id = u.id LEFT JOIN shipping_information s ON o.id = s.order_id ORDER BY o.created_at DESC");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to get order items
function getOrderItems($conn, $order_id) {
    $stmt = $conn->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
    $stmt->execute([$order_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$fixed_shipping_time = '3-5 business days after order date';
$title = 'Order Management - Motoshapi';
include 'includes/header.php';
?>
<div class="container mt-4">
    <h2 class="mb-4">Order Management</h2>

    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <?php if(empty($orders)): ?>
                <div class="alert alert-info">No orders found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Order Date</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th>Transaction Type</th>
                                <th>Shipping</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($orders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['id']; ?></td>
                                <td>
                                    <?php
                                    if (!empty($order['first_name']) || !empty($order['last_name'])) {
                                        echo htmlspecialchars(trim($order['first_name'] . ' ' . $order['last_name']));
                                    } elseif (!empty($order['username'])) {
                                        echo htmlspecialchars($order['username']);
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($order['email']); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></td>
                                <td><?php echo format_price($order['total_amount']); ?></td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <select name="status" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                            <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                            <option value="shipped" <?php echo $order['status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                            <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                            <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                        <input type="hidden" name="update_status" value="1">
                                    </form>
                                </td>
                                <td><span class="badge bg-primary">Online</span></td>
                                <td>
                                    <?php
                                    $addressParts = array_filter([
                                        $order['house_number'] ?? '',
                                        $order['barangay'] ?? '',
                                        $order['city'] ?? '',
                                        $order['province'] ?? ''
                                    ]);
                                    echo htmlspecialchars(implode(', ', $addressParts));
                                    ?>
                                    <br><small><?php echo $fixed_shipping_time; ?></small>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info" type="button" data-bs-toggle="collapse" data-bs-target="#orderItems<?php echo $order['id']; ?>">View Details</button>
                                </td>
                            </tr>
                            <tr class="collapse" id="orderItems<?php echo $order['id']; ?>">
                                <td colspan="9">
                                    <strong>Order Items:</strong>
                                    <ul>
                                        <?php foreach(getOrderItems($conn, $order['id']) as $item): ?>
                                            <li><?php echo htmlspecialchars($item['name']); ?> x <?php echo $item['quantity']; ?> (<?php echo format_price($item['price']); ?> each)</li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <strong>Contact:</strong> <?php echo htmlspecialchars($order['phone']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/dark-mode.js"></script> 