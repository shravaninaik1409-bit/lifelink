<?php
/**
 * LifeLink - Mail Configuration
 * Supports environment variables, mail.env.php private overrides, and fallback defaults.
 */

// 1. Load private environment override file if present (git-ignored)
if (file_exists(__DIR__ . '/mail.env.php')) {
    require_once __DIR__ . '/mail.env.php';
}

// Helper to get configuration with precedence: Defined constant > getenv() > default
if (!function_exists('getMailConfig')) {
    function getMailConfig($key, $default) {
        if (defined($key)) {
            return constant($key);
        }
        $env = getenv($key);
        if ($env !== false && $env !== '') {
            return $env;
        }
        return $default;
    }
}

if (!defined('MAIL_ENABLED')) {
    define('MAIL_ENABLED', (bool)getMailConfig('MAIL_ENABLED', true));
}

// Mail driver: 'smtp', 'mail', or 'log'
// 'smtp': Dispatches via live SMTP server (e.g. Gmail, Outlook, Mailtrap) using PHPMailer
// 'mail': Uses standard PHP mail() function
// 'log' : Records outgoing messages to logs/mail.log (ideal for offline local evaluation)
if (!defined('MAIL_DRIVER')) {
    define('MAIL_DRIVER', getMailConfig('MAIL_DRIVER', 'smtp'));
}

// Sender Details
if (!defined('MAIL_FROM_ADDRESS')) {
    define('MAIL_FROM_ADDRESS', getMailConfig('MAIL_FROM_ADDRESS', 'noreply@lifelink.org'));
}
if (!defined('MAIL_FROM_NAME')) {
    define('MAIL_FROM_NAME', getMailConfig('MAIL_FROM_NAME', 'LifeLink Blood Network'));
}

// SMTP Settings (Used when MAIL_DRIVER = 'smtp')
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', getMailConfig('SMTP_HOST', 'smtp.gmail.com'));
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', (int)getMailConfig('SMTP_PORT', 587));
}
if (!defined('SMTP_USER')) {
    define('SMTP_USER', getMailConfig('SMTP_USER', ''));
}
if (!defined('SMTP_PASS')) {
    define('SMTP_PASS', getMailConfig('SMTP_PASS', ''));
}
if (!defined('SMTP_SECURE')) {
    define('SMTP_SECURE', getMailConfig('SMTP_SECURE', 'tls')); // 'tls' or 'ssl'
}
if (!defined('SMTP_TIMEOUT')) {
    define('SMTP_TIMEOUT', (int)getMailConfig('SMTP_TIMEOUT', 10));
}
