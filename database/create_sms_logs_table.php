<?php
/**
 * Database Migration: Create SMS Logs Table
 * 
 * This script creates the sms_logs table for tracking all SMS messages
 * sent and received by the system.
 * 
 * Run this file once by accessing: http://localhost/MOTOSHAPI/database/create_sms_logs_table.php
 */

require_once '../config/database.php';

try {
    // Check if table already exists
    $checkStmt = $conn->query("SHOW TABLES LIKE 'sms_logs'");
    
    if ($checkStmt->rowCount() > 0) {
        echo "ℹ SMS logs table already exists.\n";
        exit;
    }
    
    // Create sms_logs table
    $sql = "
    CREATE TABLE sms_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone_number VARCHAR(20) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('sent', 'failed', 'received', 'disabled') NOT NULL DEFAULT 'sent',
        response TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_phone (phone_number),
        INDEX idx_status (status),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $conn->exec($sql);
    
    echo "✓ Successfully created 'sms_logs' table!\n\n";
    echo "Table structure:\n";
    echo "- id: Auto-increment primary key\n";
    echo "- phone_number: Recipient/sender phone number (VARCHAR 20)\n";
    echo "- message: SMS message content (TEXT)\n";
    echo "- status: Message status (sent/failed/received/disabled)\n";
    echo "- response: Gateway response or error message (TEXT)\n";
    echo "- created_at: Timestamp when SMS was logged\n\n";
    echo "You can now send SMS messages via the system!\n";
    
} catch (PDOException $e) {
    echo "❌ Error creating sms_logs table: " . $e->getMessage() . "\n";
    exit;
}
