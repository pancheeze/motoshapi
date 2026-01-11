# Email Integration Setup Guide

This guide will help you configure email functionality for the Motoshapi e-commerce system.

## Features Added

- **Welcome Emails**: Sent to new users upon registration
- **Order Confirmations**: Sent when customers place orders
- **Order Status Updates**: Sent when order status changes
- **Password Reset**: Forgot password functionality with email links

## Configuration

### 1. Email Settings

Recommended: DO NOT commit SMTP passwords to git.

You have two safe options:

### Option A (easiest): Create a local config file (recommended)

Create `email/config/email.local.php` (this file should be ignored by git) with:

```php
<?php
define('SMTP_USERNAME', 'buycyclebikeparts@gmail.com');
define('SMTP_PASSWORD', 'your-gmail-app-password-no-spaces');
// Optional
// define('FROM_EMAIL', 'buycyclebikeparts@gmail.com');
// define('FROM_NAME', 'Motoshapi');
```

### Option B: Environment variables

Set these environment variables for Apache/PHP:

- `MOTOSHAPI_SMTP_USERNAME`
- `MOTOSHAPI_SMTP_PASSWORD`
- (optional) `MOTOSHAPI_FROM_EMAIL`
- (optional) `MOTOSHAPI_FROM_NAME`

### If you still want to hardcode (not recommended)

Edit the `email/config/email.php` file and update the following constants:

```php
define('SMTP_HOST', 'smtp.gmail.com'); // Your SMTP server
define('SMTP_PORT', 587); // Port (587 for TLS, 465 for SSL, 25 for non-secure)
define('SMTP_USERNAME', 'your-email@gmail.com'); // Your email address
define('SMTP_PASSWORD', 'your-app-password'); // Your email password or app password
define('SMTP_ENCRYPTION', 'tls'); // 'tls', 'ssl', or '' for non-secure
define('FROM_EMAIL', 'your-email@gmail.com'); // Sender email address
define('FROM_NAME', 'Motoshapi'); // Sender name
```

### 2. Gmail Setup (Recommended)

For Gmail accounts:

1. **Enable 2-Factor Authentication** on your Google account
2. **Generate an App Password**:
   - Go to Google Account settings
   - Security → 2-Step Verification → App passwords
   - Generate a password for "Mail"
   - Use this app password in the `SMTP_PASSWORD` setting

### 3. Other Email Providers

#### Outlook/Hotmail:
```php
define('SMTP_HOST', 'smtp-mail.outlook.com');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls');
```

#### Yahoo:
```php
define('SMTP_HOST', 'smtp.mail.yahoo.com');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls');
```

#### Custom SMTP:
Replace the settings above with your email provider's SMTP details.

## Database Migration

Run the password reset migration to add required fields:

1. Open your browser and navigate to: `http://localhost/motoshapi/database/migrate_database.php`
2. Or manually run the SQL:
```sql
ALTER TABLE users
ADD COLUMN reset_token VARCHAR(255) NULL,
ADD COLUMN reset_token_expires DATETIME NULL;
```

## Testing Email Functionality

### 1. Test Registration
- Create a new user account
- Check if welcome email is received

### 2. Test Order Confirmation
- Place a test order
- Verify order confirmation email is sent

### 3. Test Password Reset
- Use "Forgot Password" link on login page
- Check if reset email is received
- Test the reset link

### 4. Test Order Status Updates
- Login to admin panel
- Change order status
- Verify status update email is sent to customer

## Troubleshooting

### Emails Not Sending

1. **Check SMTP Settings**: Verify all email configuration constants
2. **Firewall/Security**: Ensure SMTP port is not blocked
3. **App Passwords**: For Gmail, ensure you're using an app password, not your regular password
4. **Error Logs**: Check PHP error logs for PHPMailer errors

### Common Issues

- **"Connection failed"**: Check SMTP host and port
- **"Authentication failed"**: Verify username/password
- **"TLS connection failed"**: Try different encryption settings or ports

### Debug Mode

To enable debug output, temporarily add this to your email functions:

```php
$mail->SMTPDebug = 2; // Enable verbose debug output
```

## Security Notes

- Never commit email credentials to version control
- Use app passwords instead of regular passwords when possible
- Consider using environment variables for sensitive data in production
- Regularly rotate email passwords

## Email Templates

Email templates are defined in `config/email.php`. You can customize:

- Welcome email content and styling
- Order confirmation layout
- Password reset email design
- Order status update notifications

All emails are sent as HTML with plain text alternatives for better compatibility.