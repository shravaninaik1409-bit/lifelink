<?php
/**
 * LifeLink - Outbound Mail Service
 * Supports SMTP (with TLS/SSL), standard PHP mail(), and local fallback logging.
 */

require_once __DIR__ . '/../config/mail.php';

/**
 * Log message to internal mail log (used for development and fallback)
 */
function logMailDelivery($toEmail, $subject, $bodyText, $status = 'LOGGED', $error = '') {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
        @file_put_contents($logDir . '/.htaccess', "Order Deny,Allow\nDeny from all\nRequire all denied\n");
    }
    $logFile = $logDir . '/mail.log';
    $entry = sprintf(
        "[%s] [%s] To: %s | Subject: %s\n%s\n%s\n%s\n\n",
        date('Y-m-d H:i:s'),
        $status,
        $toEmail,
        $subject,
        str_repeat('-', 60),
        trim($bodyText) . ($error ? "\nError: " . $error : ''),
        str_repeat('=', 60)
    );
    @file_put_contents($logFile, $entry, FILE_APPEND);
}

/**
 * Internal SMTP Dispatcher
 */
function sendViaSMTP($toEmail, $toName, $subject, $htmlContent, $textContent) {
    if (empty(SMTP_USER) || empty(SMTP_PASS)) {
        return ['success' => false, 'error' => 'SMTP credentials not configured in config/mail.php'];
    }

    $timeout = defined('SMTP_TIMEOUT') ? SMTP_TIMEOUT : 10;
    $host = SMTP_HOST;
    $port = SMTP_PORT;

    $remote = ($port == 465 || (defined('SMTP_SECURE') && SMTP_SECURE === 'ssl')) ? 'ssl://' . $host : 'tcp://' . $host;
    $socket = @stream_socket_client($remote . ':' . $port, $errno, $errstr, $timeout);
    if (!$socket) {
        return ['success' => false, 'error' => "Could not connect to SMTP server: $errstr ($errno)"];
    }

    stream_set_timeout($socket, $timeout);

    $readResponse = function() use ($socket) {
        $data = '';
        while (!feof($socket)) {
            $line = fgets($socket, 515);
            $data .= $line;
            if (empty($line) || (isset($line[3]) && $line[3] == ' ')) break;
        }
        return $data;
    };

    $sendCommand = function($cmd, $expectedCode) use ($socket, $readResponse) {
        fputs($socket, $cmd . "\r\n");
        $res = $readResponse();
        $code = substr($res, 0, 3);
        if ($code != $expectedCode) {
            throw new Exception("SMTP Command '$cmd' failed. Expected $expectedCode, got: $res");
        }
        return $res;
    };

    try {
        $readResponse(); // Read server greeting
        $sendCommand("EHLO " . gethostname(), 250);

        if ($port != 465 && defined('SMTP_SECURE') && SMTP_SECURE === 'tls') {
            $sendCommand("STARTTLS", 220);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("TLS encryption negotiation failed.");
            }
            $sendCommand("EHLO " . gethostname(), 250);
        }

        // Authenticate
        $sendCommand("AUTH LOGIN", 334);
        $sendCommand(base64_encode(SMTP_USER), 334);
        $sendCommand(base64_encode(SMTP_PASS), 235);

        // Sender & Recipient
        $fromEmail = MAIL_FROM_ADDRESS;
        $sendCommand("MAIL FROM: <$fromEmail>", 250);
        $sendCommand("RCPT TO: <$toEmail>", 250);

        // Data
        $sendCommand("DATA", 354);

        $boundary = "----=_Part_" . md5(uniqid());
        $headers = [
            "Date: " . date('r'),
            "From: " . MAIL_FROM_NAME . " <$fromEmail>",
            "To: " . ($toName ? "$toName <$toEmail>" : "<$toEmail>"),
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"$boundary\""
        ];

        $body = implode("\r\n", $headers) . "\r\n\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($textContent)) . "\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($htmlContent)) . "\r\n";
        $body .= "--$boundary--\r\n.";

        $sendCommand($body, 250);
        $sendCommand("QUIT", 221);
        fclose($socket);

        return ['success' => true];
    } catch (Exception $e) {
        if (is_resource($socket)) {
            @fclose($socket);
        }
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Main Outbound Dispatcher
 */
function sendLifeLinkEmail($toEmail, $toName, $subject, $htmlContent, $textContent = '') {
    if (!defined('MAIL_ENABLED') || !MAIL_ENABLED) {
        logMailDelivery($toEmail, $subject, $textContent ?: strip_tags($htmlContent), 'DISABLED');
        return true;
    }

    if (empty($textContent)) {
        $textContent = strip_tags($htmlContent);
    }

    $driver = defined('MAIL_DRIVER') ? strtolower(MAIL_DRIVER) : 'smtp';

    // If live SMTP is selected and credentials are provided, attempt live dispatch
    if ($driver === 'smtp' && !empty(SMTP_USER) && !empty(SMTP_PASS)) {
        $result = sendViaSMTP($toEmail, $toName, $subject, $htmlContent, $textContent);
        if ($result['success']) {
            logMailDelivery($toEmail, $subject, $textContent, 'SENT_SMTP');
            return true;
        } else {
            logMailDelivery($toEmail, $subject, $textContent, 'SMTP_FAILED_FALLBACK', $result['error']);
        }
    }

    // Standard mail() fallback
    if ($driver === 'mail') {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_ADDRESS . ">\r\n";
        if (@mail($toEmail, $subject, $htmlContent, $headers)) {
            logMailDelivery($toEmail, $subject, $textContent, 'SENT_PHP_MAIL');
            return true;
        }
    }

    // Local development fallback: Log to logs/mail.log so password reset links can be tested offline
    logMailDelivery($toEmail, $subject, $textContent, 'LOGGED_LOCAL');
    return true;
}

/**
 * Send Password Reset Email with Healthcare Branded Template
 */
function sendPasswordResetEmail($toEmail, $recipientName, $resetLink) {
    $subject = "Password Reset Request — LifeLink Blood Network";
    $safeName = htmlspecialchars($recipientName ?: 'LifeLink Member', ENT_QUOTES, 'UTF-8');
    $safeLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
  .card { max-width: 580px; margin: 20px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(15,23,42,0.08); border: 1px solid #e2e8f0; }
  .header { background: linear-gradient(135deg, #0B192C 0%, #1E3A5F 100%); padding: 30px; text-align: center; color: #ffffff; }
  .header h2 { margin: 10px 0 0 0; font-size: 22px; font-weight: 700; }
  .body { padding: 30px; line-height: 1.6; }
  .btn { display: inline-block; background-color: #DC2626; color: #ffffff !important; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: bold; margin: 20px 0; font-size: 15px; }
  .note { background-color: #fef2f2; border-left: 4px solid #DC2626; padding: 12px 16px; margin: 20px 0; font-size: 13px; color: #991b1b; }
  .footer { background: #f1f5f9; padding: 20px; text-align: center; font-size: 12px; color: #64748b; }
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <div style="font-size: 32px;">??</div>
    <h2>Life<span style="color: #ef4444;">Link</span></h2>
    <p style="margin: 4px 0 0; font-size: 13px; opacity: 0.85;">Online Blood Donor Finder &amp; Management System</p>
  </div>
  <div class="body">
    <p>Hello <strong>{$safeName}</strong>,</p>
    <p>We received a request to reset the password for your LifeLink account. Click the secure button below to choose a new password:</p>
    <div style="text-align: center;">
      <a href="{$safeLink}" class="btn">Reset My Password</a>
    </div>
    <div class="note">
      <strong>Important:</strong> This password reset link is single-use and will expire in <strong>60 minutes</strong>.
    </div>
    <p style="font-size: 13px; color: #64748b;">If the button above does not work, copy and paste this link into your browser:<br>
    <a href="{$safeLink}" style="color: #0284c7; word-break: break-all;">{$safeLink}</a></p>
    <p style="font-size: 13px; color: #64748b; margin-top: 25px;">If you did not request this password reset, please disregard this email. Your current password will remain completely safe and unchanged.</p>
  </div>
  <div class="footer">
    &copy; LifeLink Blood Network &bull; In acute medical emergencies call 1800-LIFELINK
  </div>
</div>
</body>
</html>
HTML;

    $text = "Hello {$recipientName},\n\nWe received a request to reset the password for your LifeLink account.\n\nTo reset your password, visit the link below:\n{$resetLink}\n\nThis link expires in 60 minutes and can only be used once.\n\nIf you did not request a password reset, you can safely ignore this email.\n\n- The LifeLink Team";

    return sendLifeLinkEmail($toEmail, $recipientName, $subject, $html, $text);
}