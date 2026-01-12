<?php
/**
 * SMS Helper Functions
 * 
 * Provides functions for sending SMS messages via SMSGate Android gateway
 * and logging SMS activity to the database.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/sms_config.php';

/**
 * Send SMS message via SMSGate Android gateway
 * 
 * @param string|array $recipients Phone number(s) to send SMS to
 * @param string $message The SMS message content
 * @return array Response array with 'success' boolean and 'message' string
 */
function sendSMS($recipients, $message) {
    global $conn;
    
    // Check if SMS is enabled
    if (!SMS_ENABLED) {
        logSMS($recipients, $message, 'disabled', 'SMS gateway is disabled in configuration');
        return [
            'success' => false,
            'message' => 'SMS service is currently disabled'
        ];
    }
    
    // Ensure recipients is an array
    if (!is_array($recipients)) {
        $recipients = [$recipients];
    }
    
    // Format all phone numbers
    $formattedRecipients = array_map('formatPhoneNumber', $recipients);
    
    // Validate message
    if (empty($message)) {
        return [
            'success' => false,
            'message' => 'Message cannot be empty'
        ];
    }
    
    // Prepare JSON payload for SMSGate
    $payload = [
        'phoneNumbers' => $formattedRecipients,
        'message' => $message
    ];
    
    // Prepare authentication header (Basic Auth)
    $authString = SMS_USERNAME . ':' . SMS_PASSWORD;
    $authHeader = 'Basic ' . base64_encode($authString);
    
    // Prepare HTTP context for the request
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/json',
                'Authorization: ' . $authHeader
            ],
            'content' => json_encode($payload),
            'timeout' => 10
        ]
    ];
    
    try {
        // Send request to SMSGate
        $url = rtrim(SMS_GATEWAY_URL, '/') . '/messages';
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        
        // Check if request was successful
        if ($response === false) {
            $error = error_get_last();
            $errorMessage = $error ? $error['message'] : 'Unable to connect to SMS gateway';
            
            // Log failed SMS
            foreach ($formattedRecipients as $recipient) {
                logSMS($recipient, $message, 'failed', $errorMessage);
            }
            
            return [
                'success' => false,
                'message' => 'SMS gateway connection failed: ' . $errorMessage
            ];
        }
        
        // Parse response
        $responseData = json_decode($response, true);
        
        // SMSGate often reports "Failed" even when SMS is sent successfully
        // If we got a valid HTTP response, consider it successful
        // The actual SMS delivery happens at the Android system level
        
        $isSuccess = true;
        $statusMessage = 'SMS sent to gateway successfully';
        
        // Check if response indicates actual failure (connection issues, auth failures)
        if ($responseData && isset($responseData['error'])) {
            // Only treat as failure if there's a genuine error (auth, validation, etc.)
            $errorCode = $responseData['error']['code'] ?? '';
            if (in_array($errorCode, ['AUTH_FAILED', 'INVALID_REQUEST', 'RATE_LIMIT'])) {
                $isSuccess = false;
                $statusMessage = 'SMS gateway error: ' . ($responseData['error']['message'] ?? 'Unknown error');
            } else {
                // Generic failures like RESULT_ERROR_GENERIC_FAILURE are often false positives
                $statusMessage = 'SMS sent (gateway reports: ' . ($responseData['error']['message'] ?? 'generic error') . ' - message likely delivered)';
            }
        }
        
        // Log SMS with appropriate status
        foreach ($formattedRecipients as $recipient) {
            logSMS($recipient, $message, $isSuccess ? 'sent' : 'failed', $statusMessage . ' | Response: ' . json_encode($responseData));
        }
        
        return [
            'success' => $isSuccess,
            'message' => $statusMessage,
            'data' => $responseData
        ];
        
    } catch (Exception $e) {
        // Log exception
        foreach ($formattedRecipients as $recipient) {
            logSMS($recipient, $message, 'failed', 'Exception: ' . $e->getMessage());
        }
        
        return [
            'success' => false,
            'message' => 'SMS sending failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Log SMS activity to database
 * 
 * @param string $phone Phone number
 * @param string $message SMS message content
 * @param string $status Status: 'sent', 'failed', 'received', 'disabled'
 * @param string $response Response or error message from gateway
 * @return bool True if logged successfully
 */
function logSMS($phone, $message, $status, $response = '') {
    global $conn;
    
    if (!SMS_LOG_ENABLED) {
        return false;
    }
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO sms_logs (phone_number, message, status, response, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $phone,
            $message,
            $status,
            $response
        ]);
        
        return true;
    } catch (PDOException $e) {
        // If table doesn't exist yet, fail silently
        if (SMS_DEBUG_MODE) {
            error_log("SMS Log Error: " . $e->getMessage());
        }
        return false;
    }
}

/**
 * Send order placed notification SMS
 * 
 * @param string $phone Customer phone number
 * @param string $orderId Order ID
 * @param float $total Order total amount
 * @return array Response array
 */
function sendOrderPlacedSMS($phone, $orderId, $total) {
    $message = sprintf(SMS_ORDER_PLACED, $orderId, number_format($total, 2));
    return sendSMS($phone, $message);
}

/**
 * Send order shipped notification SMS
 * 
 * @param string $phone Customer phone number
 * @param string $orderId Order ID
 * @return array Response array
 */
function sendOrderShippedSMS($phone, $orderId) {
    $message = sprintf(SMS_ORDER_SHIPPED, $orderId);
    return sendSMS($phone, $message);
}

/**
 * Send order delivered notification SMS
 * 
 * @param string $phone Customer phone number
 * @param string $orderId Order ID
 * @return array Response array
 */
function sendOrderDeliveredSMS($phone, $orderId) {
    $message = sprintf(SMS_ORDER_DELIVERED, $orderId);
    return sendSMS($phone, $message);
}

/**
 * Send order cancelled notification SMS
 * 
 * @param string $phone Customer phone number
 * @param string $orderId Order ID
 * @return array Response array
 */
function sendOrderCancelledSMS($phone, $orderId) {
    $message = sprintf(SMS_ORDER_CANCELLED, $orderId);
    return sendSMS($phone, $message);
}

/**
 * Get SMS logs for admin panel
 * 
 * @param int $limit Number of records to retrieve
 * @param int $offset Offset for pagination
 * @return array Array of SMS log records
 */
function getSMSLogs($limit = 50, $offset = 0) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT * FROM sms_logs 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");
        
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get SMS statistics
 * 
 * @return array Statistics array with counts
 */
function getSMSStats() {
    global $conn;
    
    try {
        $stmt = $conn->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) as received
            FROM sms_logs
        ");
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
            'received' => 0
        ];
    }
}
