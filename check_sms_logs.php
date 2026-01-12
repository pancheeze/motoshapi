<?php
require 'config/database.php';

echo "=== Recent SMS Logs ===\n\n";

$logs = $conn->query('SELECT * FROM sms_logs ORDER BY created_at DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);

if (empty($logs)) {
    echo "No SMS logs found in database!\n";
    echo "\nPossible issues:\n";
    echo "1. SMS_ENABLED might be set to false\n";
    echo "2. Phone field was empty during checkout\n";
    echo "3. SMS sending failed silently\n";
} else {
    foreach($logs as $log) {
        echo "Time: " . $log['created_at'] . "\n";
        echo "Phone: " . $log['phone_number'] . "\n";
        echo "Status: " . $log['status'] . "\n";
        echo "Message: " . substr($log['message'], 0, 80) . "\n";
        echo "Response: " . substr($log['response'], 0, 100) . "\n";
        echo "---\n\n";
    }
}

// Check configuration
require 'config/sms_config.php';
echo "\n=== SMS Configuration ===\n";
echo "Gateway URL: " . SMS_GATEWAY_URL . "\n";
echo "Username: " . SMS_USERNAME . "\n";
echo "SMS Enabled: " . (SMS_ENABLED ? 'YES' : 'NO') . "\n";
?>
