<?php
$title = 'My Orders - Motoshapi';
include 'includes/header.php';
require_once 'includes/sms_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle order received action
if (isset($_POST['order_received']) && isset($_POST['order_id'])) {
    $order_id = intval($_POST['order_id']);
    
    // Get order details and phone number
    $stmt = $conn->prepare('
        SELECT o.*, s.phone 
        FROM orders o 
        LEFT JOIN shipping_information s ON o.id = s.order_id 
        WHERE o.id = ? AND o.user_id = ?
    ');
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        // Update order status to delivered
        $stmt = $conn->prepare('UPDATE orders SET status = "delivered" WHERE id = ? AND user_id = ?');
        $stmt->execute([$order_id, $user_id]);
        
        // Send SMS confirmation to customer that their order receipt was confirmed
        if (!empty($order['phone'])) {
            $message = "MOTOSHAPI: Thank you for confirming receipt of order #{$order_id}! We hope you're satisfied with your purchase. Please rate your experience!";
            sendSMS($order['phone'], $message);
        }
    }
}

// Fetch user orders
$stmt = $conn->prepare('SELECT o.*, s.first_name, s.last_name, s.city, s.province, s.postal_code, s.phone FROM orders o LEFT JOIN shipping_information s ON o.id = s.order_id WHERE o.user_id = ? ORDER BY o.created_at DESC');
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getOrderItems($conn, $order_id) {
    $stmt = $conn->prepare('SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?');
    $stmt->execute([$order_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$fixed_shipping_time = '3-5 business days after order date';
?>
    <div class="modern-container modern-section">
        <div class="modern-card" style="padding: var(--spacing-2xl);">
            <h2 class="modern-section-title" style="text-align: left; margin-bottom: var(--spacing-xl);">My <span class="modern-accent-text">Orders</span></h2>
            
            <?php if (empty($orders)): ?>
                <div style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-secondary); padding: var(--spacing-xl); border-radius: var(--radius-md); text-align: center;">
                    <i class="bi bi-inbox" style="font-size: 3rem; display: block; margin-bottom: var(--spacing-md); opacity: 0.5;"></i>
                    <p class="mb-0">You have no orders yet.</p>
                </div>
            <?php else: ?>
                    <div class="table-responsive" style="overflow-x: auto;">
                        <table class="table" style="width: 100%; border-collapse: collapse; background: transparent;">
                            <thead style="background: var(--bg-primary); border-bottom: 2px solid var(--border-primary);">
                                <tr>
                                    <th style="padding: var(--spacing-md); color: var(--text-primary); font-weight: 600; border: 1px solid var(--border-primary);">Order ID</th>
                                    <th style="padding: var(--spacing-md); color: var(--text-primary); font-weight: 600; border: 1px solid var(--border-primary);">Order Date</th>
                                    <th style="padding: var(--spacing-md); color: var(--text-primary); font-weight: 600; border: 1px solid var(--border-primary);">Total Amount</th>
                                    <th style="padding: var(--spacing-md); color: var(--text-primary); font-weight: 600; border: 1px solid var(--border-primary);">Status</th>
                                    <th style="padding: var(--spacing-md); color: var(--text-primary); font-weight: 600; border: 1px solid var(--border-primary);">Shipping</th>
                                    <th style="padding: var(--spacing-md); color: var(--text-primary); font-weight: 600; border: 1px solid var(--border-primary);">Details</th>
                                    <th style="padding: var(--spacing-md); color: var(--text-primary); font-weight: 600; border: 1px solid var(--border-primary);">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                <tr style="border-bottom: 1px solid var(--border-primary);">
                                    <td style="padding: var(--spacing-md); color: var(--text-primary); border: 1px solid var(--border-primary); font-weight: 600;">#<?php echo $order['id']; ?></td>
                                    <td style="padding: var(--spacing-md); color: var(--text-secondary); border: 1px solid var(--border-primary);"><?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></td>
                                    <td style="padding: var(--spacing-md); color: var(--accent-primary); font-weight: 600; border: 1px solid var(--border-primary);"><?php echo format_price($order['total_amount']); ?></td>
                                    <td style="padding: var(--spacing-md); border: 1px solid var(--border-primary);"><span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: var(--radius-md); font-size: 0.875rem; font-weight: 600; background: <?php echo $order['status'] == 'pending' ? 'rgba(217, 119, 6, 0.1)' : ($order['status'] == 'delivered' ? 'rgba(45, 122, 79, 0.1)' : 'rgba(30, 64, 175, 0.1)'); ?>; color: <?php echo $order['status'] == 'pending' ? 'var(--warning)' : ($order['status'] == 'delivered' ? 'var(--success)' : 'var(--info)'); ?>;"><?php echo ucfirst($order['status']); ?></span></td>
                                    <td style="padding: var(--spacing-md); color: var(--text-secondary); border: 1px solid var(--border-primary); font-size: 0.875rem;">
                                        <?php echo htmlspecialchars($order['city'] . ', ' . $order['province'] . ' ' . $order['postal_code']); ?><br>
                                        <small style="color: var(--text-muted);"><?php echo $fixed_shipping_time; ?></small>
                                    </td>
                                    <td style="padding: var(--spacing-md); border: 1px solid var(--border-primary);">
                                        <button class="modern-btn modern-btn-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#orderItems<?php echo $order['id']; ?>" style="padding: 0.5rem 1rem; font-size: 0.875rem;">View</button>
                                    </td>
                                    <td style="padding: var(--spacing-md); border: 1px solid var(--border-primary);">
                                        <?php if ($order['status'] != 'delivered'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <button type="submit" name="order_received" class="modern-btn" style="background: var(--success); color: #fff; padding: 0.5rem 1rem; font-size: 0.875rem; border: none; cursor: pointer; border-radius: var(--radius-md);">
                                                    Order Received
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: var(--success); font-weight: 600;">✓ Received</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr class="collapse" id="orderItems<?php echo $order['id']; ?>" style="background: var(--bg-primary);">
                                    <td colspan="7" style="padding: var(--spacing-lg); border: 1px solid var(--border-primary);">
                                        <strong style="color: var(--text-primary); display: block; margin-bottom: var(--spacing-md);">Order Items:</strong>
                                        <ul style="color: var(--text-secondary); margin-bottom: var(--spacing-md);">
                                            <?php foreach(getOrderItems($conn, $order['id']) as $item): ?>
                                                <li style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($item['name']); ?> x <?php echo $item['quantity']; ?> <span style="color: var(--accent-primary);">(<?php echo format_price($item['price']); ?> each)</span></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <strong style="color: var(--text-primary);">Contact:</strong> <span style="color: var(--text-secondary);"><?php echo htmlspecialchars($order['phone']); ?></span>
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
<?php include 'includes/footer.php'; ?> 