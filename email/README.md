# Email Integration Module

This directory contains all email-related functionality for the Motoshapi e-commerce system.

## 📁 Directory Structure

```
email/
├── config/
│   └── email.php              # Email configuration and functions
├── database/
│   └── add_password_reset_fields.php  # Database migration for password reset
├── pages/
│   ├── forgot_password.php    # Forgot password page
│   ├── reset_password.php     # Password reset page
│   └── test_email.php         # Email testing utility
├── vendor/                    # PHPMailer library and autoloader
│   └── phpmailer/
└── README.md                  # This documentation
```

## 🚀 Features

- **Welcome Emails**: Sent to new user registrations
- **Order Confirmations**: Detailed receipts with order details
- **Order Status Updates**: Notifications when order status changes
- **Password Reset**: Secure forgot password functionality
- **Email Testing**: Built-in test utility for configuration

## ⚙️ Setup

1. **Configure Email Settings**:
	- Recommended: create `config/email.local.php` (not committed) with your SMTP details
	- Or set env vars `MOTOSHAPI_SMTP_USERNAME` / `MOTOSHAPI_SMTP_PASSWORD`
2. **Run Migration**: Execute `database/add_password_reset_fields.php`
3. **Test Configuration**: Use `pages/test_email.php` to verify setup

## 📧 Email Templates

All email templates are defined in `config/email.php` and include:
- Responsive HTML design
- Plain text alternatives
- Professional branding
- Dynamic content insertion

## 🔧 Integration Points

The email module integrates with:
- `../../register.php` - User registration
- `../../checkout.php` - Order processing
- `../../admin/orders.php` - Order status management
- `../../login.php` - Password reset links

## 📖 Usage

```php
require_once 'email/vendor/autoload.php';
require_once 'email/config/email.php';

// Send welcome email
sendWelcomeEmail('user@example.com', 'John Doe');

// Send order confirmation
sendOrderConfirmationEmail('user@example.com', $orderData);

// Send password reset
sendPasswordResetEmail('user@example.com', $resetToken);
```

## 🔒 Security

- Secure token-based password resets
- SMTP authentication
- Input validation and sanitization
- CSRF protection on forms

## 🐛 Troubleshooting

- Check `pages/test_email.php` for configuration issues
- Verify SMTP settings in `config/email.php`
- Ensure database migration has been run
- Check PHP error logs for detailed error messages