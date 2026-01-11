<?php
/**
 * Database Migration Script for Motoshapi
 * This script helps migrate from the old database structure to the new one
 */

require_once '../config/database.php';

echo "<h2>Motoshapi Database Migration</h2>";

try {
    $conn->beginTransaction();
    
    // Check if we need to migrate
    $migration_needed = false;
    
    // Check if old tables exist
    $stmt = $conn->query("SHOW TABLES LIKE 'edit_payment_details'");
    if ($stmt->rowCount() > 0) {
        $migration_needed = true;
        echo "<p>Found old table 'edit_payment_details' - will be removed</p>";
    }
    
    // Check if new columns exist
    $stmt = $conn->query("SHOW COLUMNS FROM orders LIKE 'payment_details'");
    if ($stmt->rowCount() == 0) {
        $migration_needed = true;
        echo "<p>Orders table needs 'payment_details' column</p>";
    }
    
    $stmt = $conn->query("SHOW COLUMNS FROM orders LIKE 'updated_at'");
    if ($stmt->rowCount() == 0) {
        $migration_needed = true;
        echo "<p>Orders table needs 'updated_at' column</p>";
    }
    
    $stmt = $conn->query("SHOW COLUMNS FROM payment_modes LIKE 'mode_code'");
    if ($stmt->rowCount() == 0) {
        $migration_needed = true;
        echo "<p>Payment_modes table needs 'mode_code' column</p>";
    }
    
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
    if ($stmt->rowCount() == 0) {
        $migration_needed = true;
        echo "<p>Users table needs 'role' column</p>";
    }
    
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
    if ($stmt->rowCount() == 0) {
        $migration_needed = true;
        echo "<p>Users table needs 'status' column</p>";
    }
    
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'updated_at'");
    if ($stmt->rowCount() == 0) {
        $migration_needed = true;
        echo "<p>Users table needs 'updated_at' column</p>";
    }
    
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'reset_token'");
    if ($stmt->rowCount() == 0) {
        $migration_needed = true;
        echo "<p>Users table needs 'reset_token' column</p>";
    }
    
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'reset_token_expires'");
    if ($stmt->rowCount() == 0) {
        $migration_needed = true;
        echo "<p>Users table needs 'reset_token_expires' column</p>";
    }
    
    $stmt = $conn->query("SHOW COLUMNS FROM products LIKE 'updated_at'");
    if ($stmt->rowCount() == 0) {
        $migration_needed = true;
        echo "<p>Products table needs 'updated_at' column</p>";
    }
    
    $stmt = $conn->query("SHOW COLUMNS FROM products LIKE 'is_active'");
    if ($stmt->rowCount() == 0) {
        $migration_needed = true;
        echo "<p>Products table needs 'is_active' column</p>";
    }
    
    $stmt = $conn->query("SHOW COLUMNS FROM order_items LIKE 'created_at'");
    if ($stmt->rowCount() == 0) {
        $migration_needed = true;
        echo "<p>Order_items table needs 'created_at' column</p>";
    }
    
    $stmt = $conn->query("SHOW COLUMNS FROM variations LIKE 'is_active'");
    if ($stmt->rowCount() == 0) {
        $migration_needed = true;
        echo "<p>Variations table needs 'is_active' column</p>";
    }
    
    $stmt = $conn->query("SHOW COLUMNS FROM variations LIKE 'created_at'");
    if ($stmt->rowCount() == 0) {
        $migration_needed = true;
        echo "<p>Variations table needs 'created_at' column</p>";
    }
    
    if (!$migration_needed) {
        echo "<p style='color: green;'>✅ Database is already up to date!</p>";
        $conn->rollBack();
        exit();
    }
    
    echo "<h3>Starting Migration...</h3>";
    
    // Remove old tables
    $conn->exec("DROP TABLE IF EXISTS edit_payment_details");
    echo "<p>✅ Removed old tables</p>";
    
    // Add new columns to orders table
    $conn->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_details JSON NULL");
    $conn->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    $conn->exec("ALTER TABLE orders MODIFY COLUMN status ENUM('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending'");
    echo "<p>✅ Updated orders table</p>";
    
    // Add new columns to payment_modes table
    $conn->exec("ALTER TABLE payment_modes ADD COLUMN IF NOT EXISTS mode_code VARCHAR(20) NOT NULL DEFAULT ''");
    $conn->exec("ALTER TABLE payment_modes ADD UNIQUE KEY IF NOT EXISTS mode_code (mode_code)");
    
    // Update existing payment modes with codes
    $conn->exec("UPDATE payment_modes SET mode_code = 'cod' WHERE mode_name LIKE '%COD%'");
    echo "<p>✅ Updated payment_modes table</p>";
    
    // Add new columns to users table
    $conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS role ENUM('user','admin') DEFAULT 'user'");
    $conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS status ENUM('active','inactive') DEFAULT 'active'");
    $conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    $conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token VARCHAR(255) NULL");
    $conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token_expires DATETIME NULL");
    echo "<p>✅ Updated users table</p>";
    
    // Add new columns to products table
    $conn->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    $conn->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1");
    echo "<p>✅ Updated products table</p>";
    
    // Add new columns to order_items table
    $conn->exec("ALTER TABLE order_items ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    echo "<p>✅ Updated order_items table</p>";
    
    // Add new columns to variations table
    $conn->exec("ALTER TABLE variations ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1");
    $conn->exec("ALTER TABLE variations ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    echo "<p>✅ Updated variations table</p>";
    
    // Update foreign key constraints
    $conn->exec("ALTER TABLE orders DROP FOREIGN KEY IF EXISTS orders_ibfk_1");
    $conn->exec("ALTER TABLE orders ADD CONSTRAINT orders_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
    
    $conn->exec("ALTER TABLE order_items DROP FOREIGN KEY IF EXISTS order_items_ibfk_1");
    $conn->exec("ALTER TABLE order_items ADD CONSTRAINT order_items_ibfk_1 FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE");
    
    $conn->exec("ALTER TABLE order_items DROP FOREIGN KEY IF EXISTS order_items_ibfk_2");
    $conn->exec("ALTER TABLE order_items ADD CONSTRAINT order_items_ibfk_2 FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL");
    
    $conn->exec("ALTER TABLE products DROP FOREIGN KEY IF EXISTS products_ibfk_1");
    $conn->exec("ALTER TABLE products ADD CONSTRAINT products_ibfk_1 FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL");
    
    $conn->exec("ALTER TABLE shipping_information DROP FOREIGN KEY IF EXISTS shipping_information_ibfk_1");
    $conn->exec("ALTER TABLE shipping_information ADD CONSTRAINT shipping_information_ibfk_1 FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE");
    
    echo "<p>✅ Updated foreign key constraints</p>";
    
    $conn->commit();
    
    echo "<h3 style='color: green;'>✅ Migration completed successfully!</h3>";
    echo "<p>The database has been updated to the latest structure.</p>";
    echo "<p><a href='../admin/index.php'>Go to Admin Panel</a></p>";
    
} catch (Exception $e) {
    $conn->rollBack();
    echo "<h3 style='color: red;'>❌ Migration failed!</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database connection and try again.</p>";
}
?> 