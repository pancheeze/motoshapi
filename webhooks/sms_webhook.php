<?php
/**
 * SMS Webhook Endpoint
 * 
 * Receives incoming SMS messages from the SMS Forwarder app running on Android phone
 * and logs them to the database for future processing.
 * 
 * Setup Instructions:
 * 1. Install "Incoming SMS to URL Forwarder" app on your Android phone:
 *    https://github.com/bogkonstantin/android_income_sms_gateway_webhook
 * 2. Configure the app to forward SMS to this URL:
 *    http://YOUR_XAMPP_IP/MOTOSHAPI/webhooks/sms_webhook.php
 * 3. Ensure your phone and XAMPP server are on the same Wi-Fi network
 * 
 * Expected JSON payload from SMS Forwarder:
 * {
 *   "from": "+639123456789",
 *   "text": "Customer reply message",
 *   "sentStamp": "1234567890",
 *   "receivedStamp": "1234567890"
 * }
 */

require_once '../config/database.php';
require_once '../includes/sms_helper.php';

// Set JSON response headers
header('Content-Type: application/json');

// Get the incoming data
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

// Log the raw incoming data for debugging
$timestamp = date("Y-m-d H:i:s");
$logFile = __DIR__ . '/sms_log.txt';
file_put_contents(
    $logFile, 
    "[{$timestamp}] " . $rawData . PHP_EOL, 
    FILE_APPEND
);

// Validate incoming data
if (!$data) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid JSON payload',
        'received' => false
    ]);
    exit;
}

// Extract SMS details
$from = $data['from'] ?? 'unknown';
$text = $data['text'] ?? '';
$sentStamp = $data['sentStamp'] ?? time();
$receivedStamp = $data['receivedStamp'] ?? time();

// Validate required fields
if (empty($from) || empty($text)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required fields: from, text',
        'received' => false
    ]);
    exit;
}

// Log the received SMS to database
try {
    logSMS($from, $text, 'received', json_encode([
        'sentStamp' => $sentStamp,
        'receivedStamp' => $receivedStamp,
        'timestamp' => $timestamp
    ]));
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'message' => 'SMS received and logged successfully',
        'received' => true
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
        'received' => false
    ]);
}
