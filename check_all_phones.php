<?php
require 'config/database.php';

echo "=== Recent Orders Phone Numbers ===\n\n";

$orders = $conn->query("
    SELECT o.id, s.phone, o.created_at
    FROM orders o
    LEFT JOIN shipping_information s ON o.id = s.order_id
    ORDER BY o.created_at DESC
    LIMIT 15
")->fetchAll(PDO::FETCH_ASSOC);

foreach($orders as $order) {
    $phone = $order['phone'] ?? 'NULL';
    $length = strlen($phone);
    echo "Order #{$order['id']}: [{$phone}] ({$length} chars)\n";
}
?>
