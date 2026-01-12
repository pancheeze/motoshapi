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
    $buy_now = !empty($input['buy_now']);

    if ($buy_now) {
        $product_id = isset($input['product_id']) ? (int)$input['product_id'] : 0;
        $variation_id = isset($input['variation_id']) && $input['variation_id'] !== null ? (int)$input['variation_id'] : null;
        $quantity = isset($input['quantity']) ? max(1, (int)$input['quantity']) : 1;

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

    if ($total <= 0) {
        throw new Exception('Invalid total amount.');
    }

    $amountValue = number_format($total, 2, '.', '');
    $currency = defined('PAYPAL_CURRENCY_CODE') ? PAYPAL_CURRENCY_CODE : CURRENCY_CODE;

    $paypalOrder = paypal_create_order($currency, $amountValue, 'Motoshapi Order');
    $paypalOrderId = $paypalOrder['id'] ?? null;
    if (!$paypalOrderId) {
        throw new Exception('PayPal order ID missing.');
    }

    $_SESSION['paypal_pending'] = $_SESSION['paypal_pending'] ?? [];
    $captureRequestId = paypal_new_request_id();
    $_SESSION['paypal_pending'][$paypalOrderId] = [
        'buy_now' => $buy_now,
        'product_id' => $buy_now ? $product_id : null,
        'variation_id' => $buy_now ? $variation_id : null,
        'quantity' => $buy_now ? $quantity : null,
        'expected_total' => $amountValue,
        'currency' => $currency,
        'capture_request_id' => $captureRequestId,
        'created_at' => time()
    ];

    json_response(['success' => true, 'paypal_order_id' => $paypalOrderId]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 400);
}
