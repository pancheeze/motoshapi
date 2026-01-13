<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    http_response_code(401);
    exit;
}

require_once 'config/database.php';
require_once 'config/currency.php';
require_once 'config/paypal_config.php';
require_once 'includes/paypal_helper.php';

try {
    if (!paypal_is_configured()) {
        throw new Exception('PayPal sandbox credentials are not configured.');
    }

    $input = read_json_input(true);
    $paypalOrderId = trim((string)($input['paypal_order_id'] ?? ''));
    if ($paypalOrderId === '') {
        throw new Exception('Missing PayPal order id.');
    }

    $pending = $_SESSION['paypal_pending'][$paypalOrderId] ?? null;
    if (!$pending) {
        throw new Exception('No pending PayPal checkout found. Please refresh checkout and try again.');
    }

    // Basic expiry guard (30 minutes)
    if (!empty($pending['created_at']) && (time() - (int)$pending['created_at']) > 1800) {
        unset($_SESSION['paypal_pending'][$paypalOrderId]);
        throw new Exception('PayPal session expired. Please retry checkout.');
    }

    // Validate shipping payload
    $shipping = $input['shipping'] ?? [];
    $requiredFields = ['first_name','last_name','email','phone','house_number','street','barangay','city','province','postal_code'];
    foreach ($requiredFields as $field) {
        if (!isset($shipping[$field]) || trim((string)$shipping[$field]) === '') {
            throw new Exception('Missing required field: ' . $field);
        }
    }

    // Recalculate server-side total
    $buy_now = !empty($pending['buy_now']);
    if ($buy_now) {
        $product_id = (int)($pending['product_id'] ?? 0);
        $variation_id = $pending['variation_id'] !== null ? (int)$pending['variation_id'] : null;
        $quantity = max(1, (int)($pending['quantity'] ?? 1));

        $stmt = $conn->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        $variation = null;
        if ($variation_id) {
            $vstmt = $conn->prepare('SELECT * FROM variations WHERE id = ? AND product_id = ?');
            $vstmt->execute([$variation_id, $product_id]);
            $variation = $vstmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$product) {
            throw new Exception('Product not found.');
        }

        $item_price = $variation && $variation['price'] !== null ? $variation['price'] : ($product['price'] ?? 0);
        $item_stock = $variation ? $variation['stock'] : ($product['stock'] ?? 0);
        if ($item_stock < $quantity) {
            throw new Exception('Insufficient stock for this item.');
        }

        $checkout_items = [[
            'id' => $product['id'],
            'name' => $product['name'] . ($variation ? ' (' . $variation['variation'] . ')' : ''),
            'price' => $item_price,
            'quantity' => $quantity
        ]];
        $total = (float)$item_price * (int)$quantity;
    } else {
        $checkout_items = $_SESSION['cart'] ?? [];
        if (empty($checkout_items)) {
            throw new Exception('Cart is empty.');
        }
        $total = 0;
        foreach ($checkout_items as $item) {
            $total += ((float)$item['price']) * ((int)$item['quantity']);
        }
    }

    $expectedTotal = number_format($total, 2, '.', '');
    if ($expectedTotal !== (string)($pending['expected_total'] ?? '')) {
        throw new Exception('Order total changed. Please refresh checkout.');
    }

    // Capture in PayPal (idempotent via PayPal-Request-Id)
    $captureRequestId = $pending['capture_request_id'] ?? null;
    $capture = paypal_capture_order($paypalOrderId, is_string($captureRequestId) ? $captureRequestId : null);
    $status = $capture['status'] ?? '';
    if ($status !== 'COMPLETED') {
        throw new Exception('PayPal capture not completed. Status: ' . $status);
    }

    $currency = $pending['currency'] ?? (defined('PAYPAL_CURRENCY_CODE') ? PAYPAL_CURRENCY_CODE : CURRENCY_CODE);
    $captures = $capture['purchase_units'][0]['payments']['captures'] ?? [];
    $captureId = $captures[0]['id'] ?? null;
    $capturedAmount = $captures[0]['amount']['value'] ?? null;
    $capturedCurrency = $captures[0]['amount']['currency_code'] ?? null;

    if (!$captureId || !$capturedAmount) {
        throw new Exception('PayPal capture details missing.');
    }
    if ((string)$capturedAmount !== $expectedTotal || (string)$capturedCurrency !== (string)$currency) {
        throw new Exception('PayPal amount mismatch.');
    }

    // Ensure payment mode exists
    $payment_mode = 'paypal';
    $stmt = $conn->prepare('SELECT id, is_active, mode_name FROM payment_modes WHERE mode_code = ?');
    $stmt->execute([$payment_mode]);
    $payment_mode_row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment_mode_row) {
        $stmt = $conn->prepare('INSERT INTO payment_modes (mode_name, mode_code, is_active) VALUES (?, ?, 1)');
        $stmt->execute(['PayPal', 'paypal']);
        $payment_mode_id = (int)$conn->lastInsertId();
        $payment_mode_name = 'PayPal';
    } else {
        if ((int)$payment_mode_row['is_active'] !== 1) {
            throw new Exception('PayPal payment method is currently unavailable.');
        }
        $payment_mode_id = (int)$payment_mode_row['id'];
        $payment_mode_name = $payment_mode_row['mode_name'] ?: 'PayPal';
    }

    // Create local order
    $conn->beginTransaction();

    $stmt = $conn->prepare('INSERT INTO orders (user_id, first_name, last_name, total_amount, status, transaction_type, payment_mode_id, payment_details) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $_SESSION['user_id'],
        $shipping['first_name'],
        $shipping['last_name'],
        $expectedTotal,
        'pending',
        'online',
        $payment_mode_id,
        json_encode([
            'payment_mode' => 'paypal',
            'payment_mode_name' => $payment_mode_name,
            'paypal_order_id' => $paypalOrderId,
            'paypal_capture_id' => $captureId,
            'paypal_status' => $status,
            'payer' => $capture['payer'] ?? null
        ])
    ]);

    $order_id = (int)$conn->lastInsertId();

    $stmt = $conn->prepare('INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)');
    foreach ($checkout_items as $item) {
        $stmt->execute([$order_id, (int)$item['id'], (int)$item['quantity'], (float)$item['price']]);
    }

    $stmt = $conn->prepare('INSERT INTO shipping_information (order_id, first_name, last_name, email, phone, house_number, street, barangay, city, province, postal_code, payment_mode_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $order_id,
        $shipping['first_name'],
        $shipping['last_name'],
        $shipping['email'],
        $shipping['phone'],
        $shipping['house_number'],
        $shipping['street'],
        $shipping['barangay'],
        $shipping['city'],
        $shipping['province'],
        $shipping['postal_code'],
        $payment_mode_id
    ]);

    $conn->commit();

    // Send Email confirmation to customer
    if (!empty($shipping['email'])) {
        require_once 'email/vendor/autoload.php';
        require_once 'email/config/email.php';
        
        $customer_name = trim($shipping['first_name'] . ' ' . $shipping['last_name']);
        $customer_email = $shipping['email'];
        
        error_log("[PAYPAL EMAIL] Attempting to send order confirmation to: {$customer_email}");
        
        // Build order items HTML
        $items_html = '';
        foreach ($checkout_items as $item) {
            $item_total = $item['price'] * $item['quantity'];
            $items_html .= "<tr>
                <td>" . htmlspecialchars($item['name']) . "</td>
                <td style='text-align: center;'>" . $item['quantity'] . "</td>
                <td style='text-align: right;'>" . format_price($item['price']) . "</td>
                <td style='text-align: right;'>" . format_price($item_total) . "</td>
            </tr>";
        }
        
        $email_subject = "Order Confirmation #" . $order_id . " - MOTOSHAPI";
        $email_body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; }
                .header h1 { color: white; margin: 0; }
                .content { padding: 30px; background: #f8f9fa; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
                th { background: #667eea; color: white; }
                .total { font-size: 18px; font-weight: bold; color: #667eea; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>✅ Order Confirmed!</h1>
            </div>
            <div class='content'>
                <p>Hi <strong>{$customer_name}</strong>,</p>
                <p>Thank you for your order! Your payment via <strong>PayPal</strong> has been received.</p>
                
                <h3>Order Details:</h3>
                <p><strong>Order Number:</strong> #{$order_id}<br>
                <strong>Order Date:</strong> " . date('F j, Y, g:i a') . "<br>
                <strong>Payment Method:</strong> PayPal</p>
                
                <h3>Items Ordered:</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th style='text-align: center;'>Quantity</th>
                            <th style='text-align: right;'>Price</th>
                            <th style='text-align: right;'>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$items_html}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan='3' style='text-align: right; padding-top: 20px;'><strong>Total Amount:</strong></td>
                            <td style='text-align: right; padding-top: 20px;' class='total'>" . format_price($expectedTotal) . "</td>
                        </tr>
                    </tfoot>
                </table>
                
                <div style='background: #e8f4fd; border-left: 4px solid #667eea; padding: 15px; margin-top: 20px;'>
                    <h4 style='margin-top: 0;'>What's Next?</h4>
                    <p style='margin-bottom: 0;'>We're preparing your order for shipment. You'll receive another email when your order ships with tracking information.</p>
                </div>
            </div>
            <div class='footer'>
                <p>Thank you for shopping with MOTOSHAPI!<br>
                Questions? Contact us at buycyclebikeparts@gmail.com</p>
            </div>
        </body>
        </html>
        ";
        
        try {
            $email_result = sendEmail($customer_email, $email_subject, $email_body);
            if ($email_result) {
                error_log("[PAYPAL EMAIL] Successfully sent order confirmation to: {$customer_email}");
            } else {
                error_log("[PAYPAL EMAIL] Failed to send order confirmation to: {$customer_email}");
            }
        } catch (Exception $e) {
            error_log("[PAYPAL EMAIL] Error sending order confirmation: " . $e->getMessage());
        }
    }
    
    // Send SMS notification to customer
    if (!empty($shipping['phone'])) {
        require_once 'includes/sms_helper.php';
        $smsResult = sendOrderPlacedSMS($shipping['phone'], $order_id, $expectedTotal);
        // SMS is sent but doesn't block order completion if it fails
    }

    if (!$buy_now) {
        unset($_SESSION['cart']);
    }
    unset($_SESSION['paypal_pending'][$paypalOrderId]);

    json_response([
        'success' => true,
        'order_id' => $order_id,
        'redirect_url' => 'paypal_success.php?order_id=' . $order_id
    ]);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    json_response(['success' => false, 'message' => $e->getMessage()], 400);
}
