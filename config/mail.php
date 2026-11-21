<?php
/**
 * LifeLink - Mail Configuration
 * Configure your SMTP settings here for outbound emails (e.g., Password Reset, Notifications).
 */

if (!defined('MAIL_ENABLED')) {
    define('MAIL_ENABLED', true);
}

// Mail driver: 'smtp', 'mail', or 'log'
// 'smtp': Dispatches via live SMTP server (e.g., Gmail, Outlook, SendGrid)
// 'mail': Uses standard PHP mail() function
// 'log' : Records outgoing messages to logs/mail.log (ideal for local offline XAMPP evaluation)
if (!defined('MAIL_DRIVER')) {
    define('MAIL_DRIVER', 'smtp');
}

// Sender Details
if (!defined('MAIL_FROM_ADDRESS')) {
    define('MAIL_FROM_ADDRESS', 'noreply@lifelink.org');
}
if (!defined('MAIL_FROM_NAME')) {
    define('MAIL_FROM_NAME', 'LifeLink Blood Network');
}

// SMTP Settings (Used when MAIL_DRIVER = 'smtp')
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', 'smtp.gmail.com');
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', 587);
}
if (!defined('SMTP_USER')) {
    define('SMTP_USER', ''); // Enter your SMTP email address
}
if (!defined('SMTP_PASS')) {
    define('SMTP_PASS', ''); // Enter your SMTP password or App Password
}
if (!defined('SMTP_SECURE')) {
    define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'
}
if (!defined('SMTP_TIMEOUT')) {
    define('SMTP_TIMEOUT', 10);
}