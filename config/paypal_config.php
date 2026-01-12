<?php
// PayPal Configuration
// Create sandbox credentials at https://developer.paypal.com/ (Dashboard -> My Apps & Credentials)

// IMPORTANT: keep this file out of version control if you ever commit.
// For local XAMPP usage, this is fine.

define('PAYPAL_ENV', 'sandbox'); // 'sandbox' or 'live'

define('PAYPAL_CLIENT_ID', 'client_id_here');
define('PAYPAL_CLIENT_SECRET', 'client_secret_here');

define('PAYPAL_CURRENCY_CODE', defined('CURRENCY_CODE') ? CURRENCY_CODE : 'PHP');
