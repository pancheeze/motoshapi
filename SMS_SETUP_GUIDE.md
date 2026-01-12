# SMS Integration Setup Guide - MOTOSHAPI

## Overview
This system uses **SMSGate** - a free Android-based SMS gateway that turns your Android phone into an SMS server. Following your professor's lecture notes, this implementation allows:
- **Sending SMS**: MOTOSHAPI → SMSGate app → Customer's phone
- **Receiving SMS**: Customer replies → SMS Forwarder → MOTOSHAPI webhook

**Cost: FREE** - No subscription fees, no API costs, just your phone's SMS plan!

---

## Prerequisites
- ✅ XAMPP server running on local network
- ✅ Android phone with active SIM card
- ✅ Wi-Fi network (phone and XAMPP must be on same network)
- ✅ Basic understanding of IP addresses

---

## Part 1: Setup SMSGate (For SENDING SMS)

### Step 1: Install SMSGate on Android
1. Download and install **SMSGate** app from:
   - GitHub: https://github.com/capcom6/android-sms-gateway
   - Or search "SMSGate" in Google Play Store (if available)

### Step 2: Configure SMSGate App
1. Open the **SMSGate** app on your phone
2. Set a **username** (e.g., `sms`)
3. Set a **password** (e.g., `88888888` or create your own secure password)
4. Start the gateway service
5. **Note the IP address and port** shown in the app (e.g., `192.168.1.100:8080`)

### Step 3: Configure MOTOSHAPI
1. Open `config/sms_config.php` in VS Code
2. Update these settings:
   ```php
   define('SMS_GATEWAY_URL', 'http://192.168.1.100:8080'); // Your phone's IP:port
   define('SMS_USERNAME', 'sms'); // Your SMSGate username
   define('SMS_PASSWORD', '88888888'); // Your SMSGate password
   define('SMS_ENABLED', true); // Change to true to activate
   ```

### Step 4: Create SMS Logs Table
1. Open your browser and go to:
   ```
   http://localhost/MOTOSHAPI/database/create_sms_logs_table.php
   ```
2. You should see: "✓ Successfully created 'sms_logs' table!"

### Step 5: Test SMS Sending
1. Make sure your phone's SMSGate app is running
2. Make sure your phone and computer are on the same Wi-Fi network
3. Place a test order on MOTOSHAPI with a valid phone number
4. Check the SMS logs in the database or your phone's sent messages

---

## Part 2: Setup SMS Forwarder (For RECEIVING SMS - Optional)

### Step 1: Install SMS Forwarder on Android
1. Download and install **Incoming SMS to URL Forwarder** from:
   - GitHub: https://github.com/bogkonstantin/android_income_sms_gateway_webhook

### Step 2: Configure SMS Forwarder App
1. Open the SMS Forwarder app
2. Find your computer's IP address:
   - Open Command Prompt on your computer
   - Type: `ipconfig`
   - Look for "IPv4 Address" (e.g., `192.168.1.50`)
3. In the SMS Forwarder app, set webhook URL to:
   ```
   http://192.168.1.50/MOTOSHAPI/webhooks/sms_webhook.php
   ```
   (Replace `192.168.1.50` with your actual computer IP)
4. Enable the forwarding service

### Step 3: Test Incoming SMS
1. Send an SMS to your Android phone from another phone
2. Check the webhook log file: `webhooks/sms_log.txt`
3. Check the database `sms_logs` table for received messages 

> **⚠️ DEFENSE DAY NOTE:** Your computer's IP address will change when you connect to a different Wi-Fi network!
> Before demonstration, run `ipconfig` again and update the webhook URL in SMS Forwarder app.

---

## Defense Day Quick Setup

**Both IP addresses will change on different Wi-Fi networks!**

### Before Your Defense Presentation:

1. **Connect to Venue Wi-Fi**
   - Connect both phone and laptop to the same Wi-Fi network

2. **Update SMSGate (Sending)**
   - Open SMSGate app → note the IP address
   - Go to Admin Panel → SMS Settings
   - Update Gateway URL with new phone IP
   - Save settings

3. **Update SMS Forwarder (Receiving)**
   - On laptop: Open Command Prompt → type `ipconfig` → note IPv4 Address
   - Open SMS Forwarder app on phone
   - Update webhook URL: `http://[NEW-LAPTOP-IP]/MOTOSHAPI/webhooks/sms_webhook.php`
   - Example: `http://192.168.50.100/MOTOSHAPI/webhooks/sms_webhook.php`

4. **Test Everything**
   - Send test SMS from SMS Dashboard
   - Send SMS to phone to test receiving
   - Check logs in View Logs tab

✅ **Ready for demonstration!**

---

## How It Works

### Order Placed (Automatic SMS)
1. Customer places order on MOTOSHAPI
2. System sends SMS via SMSGate: 
   ```
   "MOTOSHAPI: Your order #123 has been received! Total: ₱1,500.00. 
   We will process it soon. Thank you!"
   ```

### Admin Updates Order Status
**Admin Dashboard → Orders → Actions:**
- **Ship Order** button → Sends SMS:
  ```
  "MOTOSHAPI: Your order #123 has been shipped! 
  Expected delivery: 3-5 business days."
  ```
- **Mark Delivered** button → Sends SMS:
  ```
  "MOTOSHAPI: Your order #123 has been delivered! 
  Thank you for shopping with us!"
  ```
- **Cancel** button → Sends SMS:
  ```
  "MOTOSHAPI: Your order #123 has been cancelled. 
  If you have questions, please contact us."
  ```

---

## Troubleshooting

### SMS Not Sending?
1. **Check SMSGate app is running** on your phone
2. **Verify Wi-Fi connection** - phone and computer on same network
3. **Check IP address** - Phone's IP might change when reconnecting to Wi-Fi
4. **Test connectivity**:
   - Open Command Prompt: `ping 192.168.1.100` (your phone's IP)
   - Should see replies, not "Request timed out"
5. **Check SMS_ENABLED** in `config/sms_config.php` is `true`
6. **View SMS logs** in database table `sms_logs` for error messages

### Wrong IP Address?
- Phone IP addresses can change when reconnecting to Wi-Fi
- Always check SMSGate app for current IP
- Update `config/sms_config.php` with new IP

### Authentication Failed?
- Make sure username and password in `config/sms_config.php` match SMSGate app settings
- Password is case-sensitive

### Can't Access Webhook?
- Make sure your computer's firewall allows incoming connections
- Verify computer's IP address hasn't changed
- Update SMS Forwarder app with new computer IP if needed

---

## Phone Number Format

The system automatically formats phone numbers:
- Input: `09123456789` → Output: `+639123456789`
- Input: `9123456789` → Output: `+639123456789`
- Input: `+639123456789` → Output: `+639123456789`

---

## SMS Message Templates

Edit templates in `config/sms_config.php`:
```php
define('SMS_ORDER_PLACED', 'MOTOSHAPI: Your order #%s has been received! Total: ₱%s. We will process it soon. Thank you!');
define('SMS_ORDER_SHIPPED', 'MOTOSHAPI: Your order #%s has been shipped! Expected delivery: 3-5 business days.');
define('SMS_ORDER_DELIVERED', 'MOTOSHAPI: Your order #%s has been delivered! Thank you for shopping with us!');
define('SMS_ORDER_CANCELLED', 'MOTOSHAPI: Your order #%s has been cancelled. If you have questions, please contact us.');
```

---

## Database Tables

### sms_logs
Tracks all SMS activity:
- `id` - Auto-increment primary key
- `phone_number` - Recipient/sender phone number
- `message` - SMS content
- `status` - 'sent', 'failed', 'received', 'disabled'
- `response` - Gateway response or error message
- `created_at` - Timestamp

---

## Security Notes

1. **Basic Authentication**: SMSGate uses username/password in Base64
2. **Local Network Only**: This setup works only on local Wi-Fi for security
3. **Production Deployment**: For internet-facing deployment, use HTTPS/VPN
4. **Keep Phone Charged**: SMSGate must run continuously to send SMS

---

## Cost Analysis

**Traditional SMS Gateways:**
- Semaphore: ₱0.60 - ₱1.00 per SMS
- Twilio: ~₱0.75 per SMS
- 1000 orders = ₱750 - ₱1,000 monthly cost

**SMSGate (This System):**
- Cost: ₱0 (uses your phone's SMS plan)
- 1000 orders = FREE or included in your unlimited SMS plan

---

## Support & Resources

- **SMSGate GitHub**: https://github.com/capcom6/android-sms-gateway
- **SMS Forwarder GitHub**: https://github.com/bogkonstantin/android_income_sms_gateway_webhook
- **MOTOSHAPI Team**: Contact your team members for help

---

## Next Steps After Setup

1. ✅ Create SMS logs table
2. ✅ Configure SMSGate on Android phone
3. ✅ Update config/sms_config.php with your settings
4. ✅ Enable SMS by setting `SMS_ENABLED = true`
5. ✅ Test with a real order
6. ✅ Monitor SMS logs for delivery confirmation
7. ✅ (Optional) Setup SMS Forwarder for receiving replies

**Your SMS integration is ready! 🎉**
