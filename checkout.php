<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to checkout.";
    header("Location: login.php");
    exit();
}
require_once 'config/database.php';
require_once 'config/currency.php';
require_once 'includes/sms_helper.php';

// Fetch user data for auto-fill
$user_data = [];
if (isset($_SESSION['user_id'])) {
    $user_stmt = $conn->prepare('SELECT email, phone FROM users WHERE id = ?');
    $user_stmt->execute([$_SESSION['user_id']]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
}

$buy_now = isset($_GET['buy_now']) && $_GET['buy_now'] == 1;

if ($buy_now) {
    // Single product checkout (Buy Now)
    $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
    $variation_id = isset($_GET['variation_id']) ? intval($_GET['variation_id']) : null;
    $quantity = isset($_GET['quantity']) ? max(1, intval($_GET['quantity'])) : 1;

    // Fetch product and variation info
    $stmt = $conn->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    $variation = null;
    if ($variation_id) {
        $vstmt = $conn->prepare('SELECT * FROM variations WHERE id = ? AND product_id = ?');
        $vstmt->execute([$variation_id, $product_id]);
        $variation = $vstmt->fetch(PDO::FETCH_ASSOC);
    }

    $item_price = $variation && $variation['price'] !== null ? $variation['price'] : ($product['price'] ?? 0);
    $item_stock = $variation ? $variation['stock'] : ($product['stock'] ?? 0);
    $item_name = $product['name'] . ($variation ? ' (' . $variation['variation'] . ')' : '');

    if (!$product || $item_stock < $quantity) {
        $_SESSION['error'] = 'Product not available or insufficient stock.';
        header('Location: product.php?id=' . $product_id);
        exit();
    }

    $checkout_items = [[
        'id' => $product['id'],
        'name' => $item_name,
        'price' => $item_price,
        'image_url' => $product['image_url'],
        'quantity' => $quantity,
        'stock' => $item_stock,
        'variation_id' => $variation_id,
        'variation' => $variation ? $variation['variation'] : null
    ]];
    $total = $item_price * $quantity;
} else {
    $checkout_items = $_SESSION['cart'] ?? [];
    $total = 0;
    foreach ($checkout_items as $item) {
        $total += $item['price'] * $item['quantity'];
    }
}

if (empty($checkout_items)) {
    $_SESSION['error'] = "Your cart is empty. Please add items before checking out.";
    header("Location: cart.php");
    exit();
}

// Online checkout only - COD is the only available payment method
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    try {
        $conn->beginTransaction();

        // Only COD payment is supported
        $payment_mode = 'cod';
        
        // Get payment_mode_id from payment_modes table
        $stmt = $conn->prepare('SELECT id FROM payment_modes WHERE mode_code = ?');
        $stmt->execute([$payment_mode]);
        $payment_mode_id = $stmt->fetchColumn();
        if (!$payment_mode_id) {
            throw new Exception('Payment mode not found.');
        }

        // Create order
        $stmt = $conn->prepare('INSERT INTO orders (user_id, first_name, last_name, total_amount, status, transaction_type, payment_mode_id, payment_details) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $_SESSION['user_id'],
            $_POST['first_name'],
            $_POST['last_name'],
            $total,
            'pending',
            'online',
            $payment_mode_id,
            json_encode([])
        ]);
        $order_id = $conn->lastInsertId();

        // Insert order items
        $stmt = $conn->prepare('INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)');
        foreach ($checkout_items as $item) {
            $stmt->execute([$order_id, $item['id'], $item['quantity'], $item['price']]);
        }

        // Insert shipping information
        $stmt = $conn->prepare('INSERT INTO shipping_information (order_id, first_name, last_name, email, phone, house_number, street, barangay, city, province, postal_code, payment_mode_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $order_id,
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['house_number'],
            $_POST['street'],
            $_POST['barangay'],
            $_POST['city'],
            $_POST['province'],
            $_POST['postal_code'],
            $payment_mode_id
        ]);

        $conn->commit();
        
        // Send SMS notification to customer
        if (!empty($_POST['phone'])) {
            $smsResult = sendOrderPlacedSMS($_POST['phone'], $order_id, $total);
            // SMS is sent but doesn't block order completion if it fails
        }
        
        if (!$buy_now) {
            unset($_SESSION['cart']);
        }

        // Show success message
        $title = 'Order Placed - Motoshapi';
        include 'includes/header.php';
        ?>
        <div class="modern-container" style="padding-top: 4rem; padding-bottom: 4rem;">
            <div class="modern-card" style="max-width: 600px; margin: 0 auto; padding: var(--spacing-2xl); text-align: center;">
                <div style="width: 5rem; height: 5rem; background: linear-gradient(135deg, var(--accent-primary), var(--accent-hover)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto var(--spacing-xl);">
                    <i class="bi bi-check-lg" style="font-size: 3rem; color: #fff;"></i>
                </div>
                <h1 style="font-size: 2rem; font-weight: 700; color: var(--text-primary); margin-bottom: var(--spacing-md);">Order Placed Successfully!</h1>
                <p style="font-size: 1.125rem; color: var(--text-secondary); margin-bottom: var(--spacing-sm);">Thank you for your order.</p>
                <p style="font-size: 1rem; color: var(--text-secondary); margin-bottom: var(--spacing-xl);">Order Number: <span style="color: var(--accent-primary); font-weight: 600; font-size: 1.25rem;">#<?php echo $order_id; ?></span></p>
                <div style="background: var(--bg-primary); border: 1px solid var(--border-primary); padding: var(--spacing-lg); border-radius: var(--radius-md); margin-bottom: var(--spacing-xl);">
                    <p style="color: var(--text-secondary); margin: 0; line-height: 1.6;">We will process your order and send you an email confirmation shortly. You can track your order status from your account.</p>
                </div>
                <div style="display: flex; gap: var(--spacing-md); justify-content: center;">
                    <a href="products.php" class="modern-btn modern-btn-primary">Continue Shopping</a>
                    <a href="orders.php" class="modern-btn modern-btn-secondary">View Orders</a>
                </div>
            </div>
        </div>
        <?php
        include 'includes/footer.php';
        exit;
    } catch (Exception $e) {
        $conn->rollBack();
        $error = 'Error: ' . $e->getMessage();
    }
}

$title = 'Online Checkout - Motoshapi';
include 'includes/header.php';
?>
<div class="modern-container modern-section">
    <h2 class="modern-section-title">Secure <span class="modern-accent-text">Checkout</span></h2>
    <div class="row" style="gap: var(--spacing-xl);">
        <div class="col-md-7">
            <div class="modern-card" style="padding: var(--spacing-xl);">
                <h3 style="font-size: 1.5rem; font-weight: 700; margin-bottom: var(--spacing-lg); color: var(--text-primary);">Shipping Information</h3>
                <?php if(isset($error)): ?>
                    <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: var(--danger); padding: var(--spacing-md); border-radius: var(--radius-md); margin-bottom: var(--spacing-lg);">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label" style="display: block; margin-bottom: var(--spacing-sm); font-weight: 600; color: var(--text-primary);">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 0.75rem; border-radius: var(--radius-md); width: 100%;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label" style="display: block; margin-bottom: var(--spacing-sm); font-weight: 600; color: var(--text-primary);">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 0.75rem; border-radius: var(--radius-md); width: 100%;">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label" style="display: block; margin-bottom: var(--spacing-sm); font-weight: 600; color: var(--text-primary);">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 0.75rem; border-radius: var(--radius-md); width: 100%;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label" style="display: block; margin-bottom: var(--spacing-sm); font-weight: 600; color: var(--text-primary);">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>" required placeholder="+63 912 345 6789" style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 0.75rem; border-radius: var(--radius-md); width: 100%;">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="house_number" class="form-label" style="display: block; margin-bottom: var(--spacing-sm); font-weight: 600; color: var(--text-primary);">House/Building Number</label>
                            <input type="text" class="form-control" id="house_number" name="house_number" required style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 0.75rem; border-radius: var(--radius-md); width: 100%;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="street" class="form-label" style="display: block; margin-bottom: var(--spacing-sm); font-weight: 600; color: var(--text-primary);">Street Name</label>
                            <input type="text" class="form-control" id="street" name="street" required style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 0.75rem; border-radius: var(--radius-md); width: 100%;">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="barangay" class="form-label" style="display: block; margin-bottom: var(--spacing-sm); font-weight: 600; color: var(--text-primary);">Barangay</label>
                            <input type="text" class="form-control" id="barangay" name="barangay" required style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 0.75rem; border-radius: var(--radius-md); width: 100%;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="city" class="form-label" style="display: block; margin-bottom: var(--spacing-sm); font-weight: 600; color: var(--text-primary);">City/Municipality</label>
                            <input type="text" class="form-control" id="city" name="city" required style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 0.75rem; border-radius: var(--radius-md); width: 100%;">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="province" class="form-label" style="display: block; margin-bottom: var(--spacing-sm); font-weight: 600; color: var(--text-primary);">Province</label>
                            <input type="text" class="form-control" id="province" name="province" required style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 0.75rem; border-radius: var(--radius-md); width: 100%;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="postal_code" class="form-label" style="display: block; margin-bottom: var(--spacing-sm); font-weight: 600; color: var(--text-primary);">Postal Code</label>
                            <input type="text" class="form-control" id="postal_code" name="postal_code" required style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 0.75rem; border-radius: var(--radius-md); width: 100%;">
                        </div>
                    </div>

                    <h3 style="font-size: 1.5rem; font-weight: 700; margin-top: var(--spacing-xl); margin-bottom: var(--spacing-lg); color: var(--text-primary);">Payment Method</h3>
                    <div class="mb-4" style="background: var(--bg-primary); border: 1px solid var(--border-primary); padding: var(--spacing-lg); border-radius: var(--radius-md);">
                        <div class="form-check" style="display: flex; align-items: center; gap: var(--spacing-md);">
                            <input class="form-check-input" type="radio" name="payment_mode" id="cod" value="cod" required checked style="width: 20px; height: 20px; accent-color: var(--accent-primary);">
                            <label class="form-check-label" for="cod" style="color: var(--text-primary); font-weight: 600; font-size: 1.0625rem;">
                                Cash on Delivery (COD)
                            </label>
                        </div>
                        <small class="form-text" style="color: var(--text-secondary); display: block; margin-top: var(--spacing-sm); margin-left: 32px;">Pay when your order arrives at your doorstep</small>
                    </div>

                    <div class="d-flex justify-content-between" style="gap: var(--spacing-md); margin-top: var(--spacing-xl);">
                        <a href="cart.php" class="modern-btn modern-btn-secondary">Back to Cart</a>
                        <button type="submit" name="place_order" class="modern-btn modern-btn-primary modern-btn-lg">Place Order</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-md-4">
            <div class="modern-card" style="padding: var(--spacing-xl); position: sticky; top: 100px;">
                <h3 style="font-size: 1.5rem; font-weight: 700; margin-bottom: var(--spacing-lg); color: var(--text-primary);">Order Summary</h3>
                <div style="border-bottom: 1px solid var(--border-primary); margin-bottom: var(--spacing-lg); padding-bottom: var(--spacing-lg);">
                    <?php foreach ($checkout_items as $item): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-md);">
                        <div style="flex: 1;">
                            <p style="color: var(--text-primary); font-weight: 500; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($item['name']); ?></p>
                            <small style="color: var(--text-secondary);">Qty: <?php echo $item['quantity']; ?></small>
                        </div>
                        <span style="color: var(--accent-primary); font-weight: 600;"><?php echo format_price($item['price'] * $item['quantity']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--spacing-md); background: var(--bg-primary); border-radius: var(--radius-md);">
                    <h4 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--text-primary);">Total</h4>
                    <h4 style="font-size: 1.5rem; font-weight: 700; margin: 0; color: var(--accent-primary);"><?php echo format_price($total); ?></h4>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>