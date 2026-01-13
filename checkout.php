<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to checkout.";
    header("Location: login.php");
    exit();
}
require_once 'config/database.php';
require_once 'config/currency.php';
require_once 'config/paypal_config.php';
require_once 'includes/sms_helper.php';
require_once 'email/vendor/autoload.php';
require_once 'email/config/email.php';

// Fetch user data for auto-fill
$user_data = [];
if (isset($_SESSION['user_id'])) {
    $user_stmt = $conn->prepare('SELECT username, email, phone FROM users WHERE id = ?');
    $user_stmt->execute([$_SESSION['user_id']]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Split username into first/last name (or use username for both if no space)
    if (!empty($user_data['username'])) {
        $name_parts = explode(' ', $user_data['username'], 2);
        $user_data['first_name'] = $name_parts[0];
        $user_data['last_name'] = isset($name_parts[1]) ? $name_parts[1] : $name_parts[0];
    }
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

// Online checkout
$selected_payment_mode = $_POST['payment_mode'] ?? 'cod';
$selected_payment_mode = strtolower(trim((string)$selected_payment_mode));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    try {
        $allowed_payment_modes = ['cod', 'paypal'];
        if (!in_array($selected_payment_mode, $allowed_payment_modes, true)) {
            throw new Exception('Invalid payment method selected.');
        }

        if ($selected_payment_mode === 'paypal') {
            throw new Exception('Please complete payment using the PayPal button below.');
        }

        $conn->beginTransaction();

        // Get payment_mode_id from payment_modes table (auto-create if missing)
        $stmt = $conn->prepare('SELECT id, is_active, mode_name FROM payment_modes WHERE mode_code = ?');
        $stmt->execute([$selected_payment_mode]);
        $payment_mode_row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment_mode_row) {
            $mode_name = $selected_payment_mode === 'paypal' ? 'PayPal' : 'Cash on Delivery (COD)';
            $stmt = $conn->prepare('INSERT INTO payment_modes (mode_name, mode_code, is_active) VALUES (?, ?, 1)');
            $stmt->execute([$mode_name, $selected_payment_mode]);
            $payment_mode_id = $conn->lastInsertId();
            $payment_mode_name = $mode_name;
        } else {
            if ((int)$payment_mode_row['is_active'] !== 1) {
                throw new Exception('Selected payment method is currently unavailable.');
            }
            $payment_mode_id = (int)$payment_mode_row['id'];
            $payment_mode_name = $payment_mode_row['mode_name'] ?: strtoupper($selected_payment_mode);
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
            json_encode([
                'payment_mode' => $selected_payment_mode,
                'payment_mode_name' => $payment_mode_name,
                'status' => $selected_payment_mode === 'paypal' ? 'awaiting_payment' : 'unpaid'
            ])
        ]);
        $order_id = $conn->lastInsertId();

        // Insert order items
        $stmt = $conn->prepare('INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)');
        foreach ($checkout_items as $item) {
            $stmt->execute([$order_id, $item['id'], $item['quantity'], $item['price']]);
        }

        // Insert shipping information
        $phone = $_POST['phone'] ?? '';

        $stmt = $conn->prepare('INSERT INTO shipping_information (order_id, first_name, last_name, email, phone, house_number, street, barangay, city, province, postal_code, payment_mode_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $order_id,
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['email'],
            $phone,
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
        
        // Send Email confirmation to customer
        if (!empty($_POST['email'])) {
            $customer_name = trim($_POST['first_name'] . ' ' . $_POST['last_name']);
            $customer_email = $_POST['email'];
            
            error_log("[CHECKOUT EMAIL] Attempting to send order confirmation to: {$customer_email}");
            
            // Build order items HTML
            $items_html = '';
            foreach ($checkout_items as $item) {
                $item_total = $item['price'] * $item['quantity'];
                $items_html .= "<tr>
                    <td style='padding: 10px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($item['name']) . "</td>
                    <td style='padding: 10px; border-bottom: 1px solid #ddd; text-align: center;'>" . $item['quantity'] . "</td>
                    <td style='padding: 10px; border-bottom: 1px solid #ddd; text-align: right;'>" . format_price($item['price']) . "</td>
                    <td style='padding: 10px; border-bottom: 1px solid #ddd; text-align: right;'>" . format_price($item_total) . "</td>
                </tr>";
            }
            
            $email_subject = "Order Confirmation #" . $order_id . " - MOTOSHAPI";
            $email_body = "
            <html>
            <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                    <h1 style='color: white; margin: 0;'>Order Confirmed!</h1>
                </div>
                <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;'>
                    <p style='font-size: 16px; color: #333;'>Hello <strong>" . htmlspecialchars($customer_name) . "</strong>,</p>
                    <p style='font-size: 16px; color: #333;'>Thank you for your order! We've received your order and will process it soon.</p>
                    
                    <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                        <h3 style='color: #667eea; margin-top: 0;'>Order Details</h3>
                        <p><strong>Order Number:</strong> #" . $order_id . "</p>
                        <p><strong>Order Date:</strong> " . date('F j, Y, g:i A') . "</p>
                        <p><strong>Payment Method:</strong> " . htmlspecialchars($payment_mode_name) . "</p>
                    </div>
                    
                    <h3 style='color: #667eea;'>Order Items</h3>
                    <table style='width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden;'>
                        <thead>
                            <tr style='background: #667eea; color: white;'>
                                <th style='padding: 10px; text-align: left;'>Product</th>
                                <th style='padding: 10px; text-align: center;'>Qty</th>
                                <th style='padding: 10px; text-align: right;'>Price</th>
                                <th style='padding: 10px; text-align: right;'>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            " . $items_html . "
                        </tbody>
                        <tfoot>
                            <tr style='background: #f8f9fa; font-weight: bold;'>
                                <td colspan='3' style='padding: 15px; text-align: right;'>Total Amount:</td>
                                <td style='padding: 15px; text-align: right; color: #667eea; font-size: 18px;'>" . format_price($total) . "</td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <div style='background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                        <p style='margin: 0; color: #1976d2;'><strong>📦 What's Next?</strong></p>
                        <p style='margin: 5px 0 0 0;'>We'll notify you via SMS and email when your order is processed and shipped.</p>
                    </div>
                    
                    <p style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #999; font-size: 12px;'>
                        This is an automated email from MOTOSHAPI. Please do not reply to this email.
                    </p>
                </div>
            </body>
            </html>
            ";
            
            try {
                $email_result = sendEmail($customer_email, $email_subject, $email_body);
                if ($email_result) {
                    error_log("[CHECKOUT EMAIL] Successfully sent order confirmation to: {$customer_email}");
                } else {
                    error_log("[CHECKOUT EMAIL] Failed to send order confirmation to: {$customer_email}");
                }
            } catch (Exception $e) {
                error_log("[CHECKOUT EMAIL] Error sending order confirmation: " . $e->getMessage());
            }
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
                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>" required style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 0.75rem; border-radius: var(--radius-md); width: 100%;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label" style="display: block; margin-bottom: var(--spacing-sm); font-weight: 600; color: var(--text-primary);">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>" required style="background: var(--bg-primary); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 0.75rem; border-radius: var(--radius-md); width: 100%;">
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
                            <input class="form-check-input" type="radio" name="payment_mode" id="cod" value="cod" required <?php echo ($selected_payment_mode === 'cod') ? 'checked' : ''; ?> style="width: 20px; height: 20px; accent-color: var(--accent-primary);">
                            <label class="form-check-label" for="cod" style="color: var(--text-primary); font-weight: 600; font-size: 1.0625rem;">
                                Cash on Delivery (COD)
                            </label>
                        </div>
                        <small class="form-text" style="color: var(--text-secondary); display: block; margin-top: var(--spacing-sm); margin-left: 32px;">Pay when your order arrives at your doorstep</small>
                    </div>

                    <div class="mb-4" style="background: var(--bg-primary); border: 1px solid var(--border-primary); padding: var(--spacing-lg); border-radius: var(--radius-md);">
                        <div class="form-check" style="display: flex; align-items: center; gap: var(--spacing-md);">
                            <input class="form-check-input" type="radio" name="payment_mode" id="paypal" value="paypal" <?php echo ($selected_payment_mode === 'paypal') ? 'checked' : ''; ?> style="width: 20px; height: 20px; accent-color: var(--accent-primary);">
                            <label class="form-check-label" for="paypal" style="color: var(--text-primary); font-weight: 600; font-size: 1.0625rem;">
                                PayPal
                            </label>
                        </div>
                        <small class="form-text" style="color: var(--text-secondary); display: block; margin-top: var(--spacing-sm); margin-left: 32px;">Pay securely using PayPal (<?php echo (defined('PAYPAL_ENV') && PAYPAL_ENV === 'live') ? 'Live' : 'Sandbox'; ?>). Click <strong>Place Order</strong> to proceed.</small>
                    </div>

                    <div id="paypal-section" class="mb-4" style="display:none; background: var(--bg-primary); border: 1px solid var(--border-primary); padding: var(--spacing-lg); border-radius: var(--radius-md);">
                        <?php if (defined('PAYPAL_CLIENT_ID') && PAYPAL_CLIENT_ID !== '' && PAYPAL_CLIENT_ID !== 'YOUR_SANDBOX_CLIENT_ID_HERE'): ?>
                            <div id="paypal-errors" style="display:none; background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: var(--danger); padding: var(--spacing-md); border-radius: var(--radius-md); margin-bottom: var(--spacing-md);"></div>
                            <div id="paypal-loading" style="display:none; background: rgba(30, 64, 175, 0.08); border: 1px solid rgba(30, 64, 175, 0.25); color: var(--text-primary); padding: var(--spacing-md); border-radius: var(--radius-md); margin-bottom: var(--spacing-md);">
                                Loading PayPal…
                            </div>
                            <div id="paypal-button-container"></div>
                        <?php else: ?>
                            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: var(--danger); padding: var(--spacing-md); border-radius: var(--radius-md);">
                                PayPal is not configured yet. Set your Sandbox Client ID/Secret in <code>config/paypal_config.php</code>.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-between" style="gap: var(--spacing-md); margin-top: var(--spacing-xl);">
                        <a href="cart.php" class="modern-btn modern-btn-secondary">Back to Cart</a>
                        <button id="place-order-btn" type="submit" name="place_order" class="modern-btn modern-btn-primary modern-btn-lg">Place Order</button>
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

<?php if (defined('PAYPAL_CLIENT_ID') && PAYPAL_CLIENT_ID !== '' && PAYPAL_CLIENT_ID !== 'YOUR_SANDBOX_CLIENT_ID_HERE'): ?>
    <script
        id="paypal-sdk"
        data-namespace="paypalSdk"
        src="https://www.paypal.com/sdk/js?client-id=<?php echo urlencode(PAYPAL_CLIENT_ID); ?>&currency=<?php echo urlencode(defined('PAYPAL_CURRENCY_CODE') ? PAYPAL_CURRENCY_CODE : CURRENCY_CODE); ?>&components=buttons&intent=capture&disable-funding=card,credit"
    ></script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form[method="POST"]');
    const paypalSection = document.getElementById('paypal-section');
    const placeOrderBtn = document.getElementById('place-order-btn');
    const paypalErrors = document.getElementById('paypal-errors');
    const paypalLoading = document.getElementById('paypal-loading');
    let paypalRendered = false;
    let paypalSectionOpened = false;

    function selectedPaymentMode() {
        const el = document.querySelector('input[name="payment_mode"]:checked');
        return el ? el.value : 'cod';
    }

    function togglePaymentUI() {
        const mode = selectedPaymentMode();
        if (mode === 'paypal') {
            if (placeOrderBtn) {
                placeOrderBtn.style.display = '';
                placeOrderBtn.textContent = 'Place Order';
            }
            // Only reveal PayPal buttons after the user clicks Place Order
            if (paypalSection) paypalSection.style.display = paypalSectionOpened ? 'block' : 'none';
            if (paypalErrors && !paypalSectionOpened) paypalErrors.style.display = 'none';
            if (paypalSectionOpened) renderPaypalButtonsSoon();
        } else {
            if (paypalSection) paypalSection.style.display = 'none';
            if (placeOrderBtn) placeOrderBtn.style.display = '';
            if (paypalErrors) paypalErrors.style.display = 'none';
            if (placeOrderBtn) placeOrderBtn.textContent = 'Place Order';
            paypalSectionOpened = false;
            setPaypalLoading(false);
        }
    }

    function showPaypalError(message) {
        if (!paypalErrors) {
            alert(message);
            return;
        }
        paypalErrors.textContent = message;
        paypalErrors.style.display = 'block';
        if (paypalLoading) paypalLoading.style.display = 'none';
    }

    function setPaypalLoading(isLoading, message) {
        if (!paypalLoading) return;
        paypalLoading.style.display = isLoading ? 'block' : 'none';
        if (isLoading) {
            paypalLoading.textContent = message || 'Loading PayPal…';
        }
    }

    function getPaypalSdk() {
        return window.paypalSdk;
    }

    document.querySelectorAll('input[name="payment_mode"]').forEach(function (radio) {
        radio.addEventListener('change', togglePaymentUI);
    });

    if (form) {
        form.addEventListener('submit', function (e) {
            if (selectedPaymentMode() === 'paypal') {
                e.preventDefault();

                // Validate shipping first
                if (!form.checkValidity()) {
                    form.reportValidity();
                    showPaypalError('Please complete the required shipping information before paying.');
                    return;
                }

                paypalSectionOpened = true;
                togglePaymentUI();

                // Ensure PayPal section is visible then render buttons
                if (paypalSection) {
                    paypalSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                renderPaypalButtonsSoon();
            }
        });
    }

    togglePaymentUI();

    const buyNow = <?php echo $buy_now ? 'true' : 'false'; ?>;
    const productId = <?php echo (int)($buy_now ? $product_id : 0); ?>;
    const variationId = <?php echo $buy_now ? (is_null($variation_id) ? 'null' : (int)$variation_id) : 'null'; ?>;
    const quantity = <?php echo (int)($buy_now ? $quantity : 0); ?>;

    function collectShipping() {
        const fields = ['first_name','last_name','email','phone','house_number','street','barangay','city','province','postal_code'];
        const data = {};
        fields.forEach(function (name) {
            const el = document.querySelector('[name="' + name + '"]');
            data[name] = el ? el.value : '';
        });
        return data;
    }

    function renderPaypalButtons() {
        if (paypalRendered) return;

        const container = document.getElementById('paypal-button-container');
        if (!container) return;

        // Must be visible before render
        const isVisible = container.offsetParent !== null;
        if (!isVisible) return;

        const sdk = getPaypalSdk();
        // SDK might still be loading; retry loop will handle it
        if (!sdk || typeof sdk.Buttons !== 'function') return;

        // Avoid duplicate renders
        if (container.childElementCount > 0) {
            paypalRendered = true;
            return;
        }

        sdk.Buttons({
            // Render PayPal button only (no card/credit buttons)
            fundingSource: sdk.FUNDING && sdk.FUNDING.PAYPAL ? sdk.FUNDING.PAYPAL : undefined,
            onClick: function (data, actions) {
                if (selectedPaymentMode() !== 'paypal') {
                    showPaypalError('Please select PayPal first.');
                    return actions.reject();
                }

                if (form && !form.checkValidity()) {
                    form.reportValidity();
                    showPaypalError('Please complete the required shipping information before paying.');
                    return actions.reject();
                }

                return actions.resolve();
            },
            createOrder: async function () {
                try {
                    if (paypalErrors) paypalErrors.style.display = 'none';
                    const payload = {
                        buy_now: buyNow,
                        product_id: buyNow ? productId : null,
                        variation_id: buyNow ? variationId : null,
                        quantity: buyNow ? quantity : null
                    };

                    const resp = await fetch('paypal_create_order.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });

                    const json = await resp.json();
                    if (!json.success) {
                        showPaypalError(json.message || 'Failed to create PayPal order');
                        throw new Error(json.message || 'Failed to create PayPal order');
                    }
                    return json.paypal_order_id;
                } catch (e) {
                    showPaypalError('PayPal could not start. Please try again.');
                    throw e;
                }
            },
            onApprove: async function (data) {
                try {
                    const resp = await fetch('paypal_capture_order.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            paypal_order_id: data.orderID,
                            shipping: collectShipping()
                        })
                    });
                    const json = await resp.json();
                    if (!json.success) {
                        showPaypalError(json.message || 'PayPal capture failed');
                        return;
                    }
                    window.location.href = json.redirect_url;
                } catch (e) {
                    showPaypalError('PayPal payment could not be completed. Please try again.');
                }
            },
            onError: function (err) {
                showPaypalError('PayPal error occurred. Please try again.');
            }
        }).render('#paypal-button-container').then(function () {
            paypalRendered = true;
            setPaypalLoading(false);
        }).catch(function (e) {
            showPaypalError('PayPal could not be displayed. Please refresh and try again.');
        });
    }

    function renderPaypalButtonsSoon() {
        if (paypalRendered) return;
        if (!paypalSectionOpened) return;

        setPaypalLoading(true, 'Loading PayPal…');

        let attempts = 0;
        const maxAttempts = 60; // ~6 seconds

        const tick = function () {
            try {
                attempts++;

                const container = document.getElementById('paypal-button-container');
                const containerVisible = !!(container && container.offsetParent !== null);
                const sdk = getPaypalSdk();
                const buttonsAvailable = !!(sdk && typeof sdk.Buttons === 'function');

                setPaypalLoading(true, 'Loading PayPal…');

                renderPaypalButtons();

                if (paypalRendered) return;

                if (attempts >= maxAttempts) {
                    setPaypalLoading(false);
                    showPaypalError('PayPal could not be initialized. Please refresh the page and try again.');
                    return;
                }

                setTimeout(tick, 100);
            } catch (e) {
                setPaypalLoading(false);
                showPaypalError('PayPal initialization crashed: ' + (e && e.message ? e.message : 'Unknown error'));
            }
        };

        setTimeout(tick, 0);
    }

    // Initialize UI based on current selection
    togglePaymentUI();
});
</script>

<?php include 'includes/footer.php'; ?>