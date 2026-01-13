<?php
session_start();
require_once '../config/database.php';
require_once '../config/currency.php';
require_once '../includes/sms_helper.php';
require_once '../email/vendor/autoload.php';
require_once '../email/config/email.php';

// Check if admin is logged in
if(!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Handle order status update
if(isset($_POST['update_status']) && isset($_POST['order_id']) && isset($_POST['new_status'])) {
    error_log("[ORDER STATUS DEBUG] Status update triggered - Order ID: {$_POST['order_id']}, New Status: {$_POST['new_status']}");
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['new_status'];
    
    // Get order and customer phone number before updating
    $stmt = $conn->prepare("
        SELECT o.*, s.phone, s.email 
        FROM orders o 
        LEFT JOIN shipping_information s ON o.id = s.order_id 
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Update order status
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $order_id]);
    
    // Send EMAIL notification
    if ($order && !empty($order['email'])) {
        $orderData = [
            'order_id' => $order['id'],
            'customer_name' => trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')) ?: $order['username'],
            'status' => $new_status
        ];
        error_log("[ORDER STATUS] Attempting to send status update email to: {$order['email']} for order #{$order['id']} - Status: {$new_status}");
        try {
            $email_result = sendOrderStatusUpdateEmail($order['email'], $orderData);
            if ($email_result) {
                error_log("[ORDER STATUS] Successfully sent status update email to: {$order['email']}");
            } else {
                error_log("[ORDER STATUS] Failed to send status update email to: {$order['email']}");
            }
        } catch (Exception $e) {
            error_log("[ORDER STATUS] Error sending status update email: " . $e->getMessage());
        }
    } else {
        error_log("[ORDER STATUS] Email NOT sent - Order: " . ($order ? "found" : "not found") . ", Email: " . (isset($order['email']) ? "'{$order['email']}'" : "empty/null"));
    }
    
    // Send SMS notification based on status (only if valid phone number)
    $phone = $order['phone'] ?? '';
    $phoneLength = strlen(preg_replace('/[^0-9]/', '', $phone)); // Count only digits
    
    if (!empty($phone) && $phoneLength >= 10) { // Valid phone has at least 10 digits
        switch($new_status) {
            case 'shipped':
                sendOrderShippedSMS($phone, $order_id);
                break;
            case 'delivered':
                sendOrderDeliveredSMS($phone, $order_id);
                break;
            case 'cancelled':
                sendOrderCancelledSMS($phone, $order_id);
                break;
        }
    }
    
    $_SESSION['success'] = "Order #$order_id status updated to $new_status";
    header("Location: orders.php");
    exit();
}

// Handle order confirmation (legacy support)
if(isset($_POST['confirm_order']) && isset($_POST['order_id'])) {
    $order_id = intval($_POST['order_id']);
    
    // Get customer phone
    $stmt = $conn->prepare("
        SELECT s.phone 
        FROM orders o 
        LEFT JOIN shipping_information s ON o.id = s.order_id 
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $conn->prepare("UPDATE orders SET status = 'delivered' WHERE id = ?");
    $stmt->execute([$order_id]);
    
    // Send delivered SMS
    if (!empty($order['phone'])) {
        sendOrderDeliveredSMS($order['phone'], $order_id);
    }
    
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
            <?php 
            echo htmlspecialchars($_SESSION['success']); 
            unset($_SESSION['success']);
            ?>
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
                                <td><span class="badge bg-<?php echo $order['status'] == 'pending' ? 'warning' : 'success'; ?>"><?php echo ucfirst($order['status']); ?></span></td>
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
                                    <div class="d-flex gap-2 align-items-center">
                                        <button class="btn btn-sm btn-info" type="button" data-bs-toggle="collapse" data-bs-target="#orderItems<?php echo $order['id']; ?>">View</button>
                                        
                                        <?php if ($order['status'] == 'pending'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="new_status" value="shipped">
                                                <button type="submit" name="update_status" class="btn btn-sm btn-primary" onclick="return confirm('Mark order as shipped?')">Ship Order</button>
                                            </form>
                                        <?php elseif ($order['status'] == 'shipped'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="new_status" value="delivered">
                                                <button type="submit" name="update_status" class="btn btn-sm btn-success" onclick="return confirm('Mark order as delivered?')">Mark Delivered</button>
                                            </form>
                                        <?php elseif ($order['status'] == 'delivered'): ?>
                                            <span class="badge bg-success">Completed</span>
                                        <?php endif; ?>
                                        
                                        <?php if ($order['status'] != 'delivered' && $order['status'] != 'cancelled'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="new_status" value="cancelled">
                                                <button type="submit" name="update_status" class="btn btn-sm btn-danger" onclick="return confirm('Cancel this order?')">Cancel</button>
                                            </form>
                                        <?php elseif ($order['status'] == 'cancelled'): ?>
                                            <span class="badge bg-danger">Cancelled</span>
                                        <?php endif; ?>
                                    </div>
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