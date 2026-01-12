<?php
/**
 * SMS Gateway Configuration
 * 
 * This system uses SMSGate - an Android-based SMS gateway application
 * that turns your Android phone into an SMS server.
 * 
 * Setup Instructions:
 * 1. Install SMSGate app on your Android phone from:
 *    https://github.com/capcom6/android-sms-gateway
 * 2. Open the app and start the SMS gateway service
 * 3. Note the IP address and port shown in the app (e.g., 192.168.1.100:8080)
 * 4. Set username and password in the app settings
 * 5. Make sure your phone and XAMPP server are on the same Wi-Fi network
 * 6. Update the settings below with your gateway details
 */

// SMS Gateway Settings
define('SMS_GATEWAY_URL', 'http://192.168.100.9:8080'); // Replace with your Android phone's IP and port
define('SMS_USERNAME', 'paul'); // Replace with your SMSGate username
define('SMS_PASSWORD', 'trialLang'); // Replace with your SMSGate password

// SMS Gateway Status
define('SMS_ENABLED', true); // Set to true when your SMSGate is configured and running

// Message Templates
define('SMS_ORDER_PLACED', 'MOTOSHAPI: Your order #%s has been received! Total: ₱%s. We will process it soon. Thank you!');
define('SMS_ORDER_SHIPPED', 'MOTOSHAPI: Your order #%s has been shipped! Expected delivery: 3-5 business days.');
define('SMS_ORDER_DELIVERED', 'MOTOSHAPI: Your order #%s has been delivered! Thank you for shopping with us!');
define('SMS_ORDER_CANCELLED', 'MOTOSHAPI: Your order #%s has been cancelled. If you have questions, please contact us.');

// Logging
define('SMS_LOG_ENABLED', true); // Log all SMS messages to database
define('SMS_DEBUG_MODE', false); // Set to true to log detailed debug information

/**
 * Format phone number for SMS sending
 * Ensures phone numbers are in the correct format for the carrier
 * 
 * @param string $phone The phone number to format
 * @return string Formatted phone number
 */
function formatPhoneNumber($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // If phone starts with 0, replace with +63
    if (substr($phone, 0, 1) === '0') {
        $phone = '+63' . substr($phone, 1);
    }
    // If phone doesn't start with +, add +63
    elseif (substr($phone, 0, 1) !== '+') {
        $phone = '+63' . $phone;
    }
    
    return $phone;
}
