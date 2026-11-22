<?php
/**
 * LifeLink - Private Mail Environment Configuration (Template)
 *
 * Instructions:
 * 1. Copy this file to 'config/mail.env.php' (which is excluded from Git via .gitignore).
 * 2. Fill in your SMTP service credentials below (e.g. Gmail App Password, Mailtrap, SendGrid, etc.).
 * 3. Never commit 'mail.env.php' or private credentials to GitHub.
 */

// Enable outbound email dispatch
define('MAIL_ENABLED', true);

// Mail driver: 'smtp' for live delivery, 'mail' for PHP mail(), or 'log' for offline file logging
define('MAIL_DRIVER', 'smtp');

// Sender Address and Name
define('MAIL_FROM_ADDRESS', 'noreply@lifelink.org');
define('MAIL_FROM_NAME', 'LifeLink Blood Network');

// SMTP Server Configuration
// For Gmail: Host = 'smtp.gmail.com', Port = 587, Secure = 'tls' (Generate an App Password in your Google Account)
// For Mailtrap: Host = 'sandbox.smtp.mailtrap.io', Port = 2525, Secure = 'tls'
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-16-character-app-password');
define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'
define('SMTP_TIMEOUT', 10);
