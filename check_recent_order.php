<?php
require 'config/database.php';

echo "=== Most Recent Order ===\n\n";

$order = $conn->query("
    SELECT o.id, o.created_at, s.phone, s.first_name, s.last_name
    FROM orders o
    LEFT JOIN shipping_information s ON o.id = s.order_id
    ORDER BY o.created_at DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if ($order) {
    echo "Order ID: " . $order['id'] . "\n";
    echo "Customer: " . $order['first_name'] . " " . $order['last_name'] . "\n";
    echo "Phone stored in DB: '" . $order['phone'] . "'\n";
    echo "Phone length: " . strlen($order['phone']) . " characters\n";
    echo "Created: " . $order['created_at'] . "\n";
} else {
    echo "No orders found\n";
}
?>
