<?php
require_once 'config/database.php';

try {
    // Add password reset fields to users table
    $sql = "ALTER TABLE users
            ADD COLUMN reset_token VARCHAR(255) NULL AFTER email,
            ADD COLUMN reset_token_expires DATETIME NULL AFTER reset_token";

    $conn->exec($sql);
    echo "Password reset fields added to users table successfully.\n";

} catch(PDOException $e) {
    if ($e->getCode() == '42S21') {
        echo "Password reset fields already exist in users table.\n";
    } else {
        echo "Error adding password reset fields: " . $e->getMessage() . "\n";
    }
}
?>