<?php
/**
 * LifeLink - Outbound Mail Service
 * Powered by PHPMailer with SMTP (TLS/SSL), standard mail(), and robust local logging fallback.
 */

require_once __DIR__ . '/../config/mail.php';

// Load PHPMailer classes if available
if (file_exists(__DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php')) {
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Log message to internal mail log (used for development and fallback auditing)
 * Automatically writes to both runtime environment and workspace logs directory if present.
 */
function logMailDelivery($toEmail, $subject, $bodyText, $status = 'LOGGED', $error = '') {
    $targetDirs = [__DIR__ . '/../logs'];

    // Also write to sibling Desktop project workspace if present so developers/users can inspect either location
    $desktopDir = 'C:/Users/Shravani Naik/Desktop/project1/lifeLink/lifeLink/logs';
    if (is_dir($desktopDir) && realpath($desktopDir) !== realpath(__DIR__ . '/../logs')) {
        $targetDirs[] = $desktopDir;
    }

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

    foreach ($targetDirs as $logDir) {
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
            @file_put_contents($logDir . '/.htaccess', "Order Deny,Allow\nDeny from all\nRequire all denied\n");
        }
        $logFile = $logDir . '/mail.log';
        @file_put_contents($logFile, $entry, FILE_APPEND);
    }
}

/**
 * Dispatch outbound message via PHPMailer (primary SMTP driver)
 */
function sendViaPHPMailer($toEmail, $toName, $subject, $htmlContent, $textContent) {
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return ['success' => false, 'error' => 'PHPMailer library classes not loaded'];
    }

    if (empty(SMTP_USER) || empty(SMTP_PASS)) {
        return ['success' => false, 'error' => 'SMTP credentials not configured in config/mail.env.php'];
    }

    $mail = new PHPMailer(true);

    try {
        // Server configuration
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->Port       = (int)SMTP_PORT;
        $mail->Timeout    = defined('SMTP_TIMEOUT') ? (int)SMTP_TIMEOUT : 10;
        $mail->CharSet    = 'UTF-8';

        // Encryption protocol
        if (defined('SMTP_SECURE') && strtolower(SMTP_SECURE) === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif (defined('SMTP_SECURE') && strtolower(SMTP_SECURE) === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = false;
            $mail->SMTPAutoTLS = false;
        }

        // Recipients
        $fromEmail = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'noreply@lifelink.org';
        $fromName  = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'LifeLink Blood Network';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail, $toName ?: '');

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlContent;
        $mail->AltBody = $textContent;

        $mail->send();
        return ['success' => true];
    } catch (PHPMailerException $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo ?: $e->getMessage()];
    } catch (\Throwable $t) {
        return ['success' => false, 'error' => $t->getMessage()];
    }
}

/**
 * Socket-level SMTP Fallback Dispatcher
 */
function sendViaSocketSMTP($toEmail, $toName, $subject, $htmlContent, $textContent) {
    if (empty(SMTP_USER) || empty(SMTP_PASS)) {
        return ['success' => false, 'error' => 'SMTP credentials not configured in config/mail.env.php'];
    }

    $timeout = defined('SMTP_TIMEOUT') ? (int)SMTP_TIMEOUT : 10;
    $host = SMTP_HOST;
    $port = (int)SMTP_PORT;

    $remote = ($port == 465 || (defined('SMTP_SECURE') && strtolower(SMTP_SECURE) === 'ssl')) ? 'ssl://' . $host : 'tcp://' . $host;
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
        $readResponse();
        $sendCommand("EHLO " . gethostname(), 250);

        if ($port != 465 && defined('SMTP_SECURE') && strtolower(SMTP_SECURE) === 'tls') {
            $sendCommand("STARTTLS", 220);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("TLS encryption negotiation failed.");
            }
            $sendCommand("EHLO " . gethostname(), 250);
        }

        $sendCommand("AUTH LOGIN", 334);
        $sendCommand(base64_encode(SMTP_USER), 334);
        $sendCommand(base64_encode(SMTP_PASS), 235);

        $fromEmail = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'noreply@lifelink.org';
        $sendCommand("MAIL FROM: <$fromEmail>", 250);
        $sendCommand("RCPT TO: <$toEmail>", 250);

        $sendCommand("DATA", 354);

        $boundary = "----=_Part_" . md5(uniqid());
        $headers = [
            "Date: " . date('r'),
            "From: " . (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'LifeLink Blood Network') . " <$fromEmail>",
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

    // 1. Attempt live SMTP dispatch if credentials provided
    if ($driver === 'smtp' && !empty(SMTP_USER) && !empty(SMTP_PASS)) {
        // Try PHPMailer first
        $res = sendViaPHPMailer($toEmail, $toName, $subject, $htmlContent, $textContent);
        if ($res['success']) {
            logMailDelivery($toEmail, $subject, $textContent, 'SENT_PHPMAILER');
            return true;
        }

        // Secondary fallback to socket SMTP
        $fallbackRes = sendViaSocketSMTP($toEmail, $toName, $subject, $htmlContent, $textContent);
        if ($fallbackRes['success']) {
            logMailDelivery($toEmail, $subject, $textContent, 'SENT_SOCKET_SMTP');
            return true;
        } else {
            logMailDelivery($toEmail, $subject, $textContent, 'SMTP_FAILED_FALLBACK', $res['error'] . ' | ' . $fallbackRes['error']);
        }
    }

    // 2. Standard PHP mail() driver
    if ($driver === 'mail') {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'LifeLink') . " <" . (defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'noreply@lifelink.org') . ">\r\n";
        if (@mail($toEmail, $subject, $htmlContent, $headers)) {
            logMailDelivery($toEmail, $subject, $textContent, 'SENT_PHP_MAIL');
            return true;
        }
    }

    // 3. Safe Local Development Fallback:
    // Records message to logs/mail.log so password reset links and notification digests can be tested offline
    logMailDelivery($toEmail, $subject, $textContent, 'LOGGED_LOCAL');
    return true;
}

/**
 * Send Password Reset Email with Healthcare Branded Template
 */
function sendPasswordResetEmail($toEmail, $recipientName, $resetLink) {
    $subject = "Password Reset Request - LifeLink Blood Network";
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
    <div style="font-size: 32px;">&#129656;</div>
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

/**
 * Send Blood Request Broadcast Email to Matching Donors
 *
 * Clearly distinguishes Emergency vs Standard requests in subject and styling.
 * Includes complete clinical location, units needed, and unique request number.
 */
function sendBloodRequestNotificationEmail($toEmail, $recipientName, array $requestData) {
    $urgency = $requestData['urgency'] ?? 'Normal';
    $isEmergency = ($urgency === 'Emergency');
    $isUrgent = ($urgency === 'Urgent');
    $bloodGroup = htmlspecialchars($requestData['blood_group'] ?? '', ENT_QUOTES, 'UTF-8');
    $reqNum = htmlspecialchars($requestData['unique_request_number'] ?? 'REQ-NEW', ENT_QUOTES, 'UTF-8');
    $hospital = htmlspecialchars($requestData['hospital_name'] ?? 'Healthcare Facility', ENT_QUOTES, 'UTF-8');
    $city = htmlspecialchars($requestData['city'] ?? '', ENT_QUOTES, 'UTF-8');
    $state = htmlspecialchars($requestData['state'] ?? '', ENT_QUOTES, 'UTF-8');
    $units = (int)($requestData['units_required'] ?? 1);
    $contactPerson = htmlspecialchars($requestData['contact_person'] ?? '', ENT_QUOTES, 'UTF-8');
    $contactPhone = htmlspecialchars($requestData['contact_phone'] ?? '', ENT_QUOTES, 'UTF-8');
    $requiredDate = !empty($requestData['required_date']) ? date('d M Y', strtotime($requestData['required_date'])) : 'Immediate';

    // Email Subject Line
    if ($isEmergency) {
        $subject = "[EMERGENCY BLOOD REQUEST] Urgent {$bloodGroup} Needed at {$hospital}, {$city} (#{$reqNum})";
    } elseif ($isUrgent) {
        $subject = "[URGENT BLOOD REQUEST] {$bloodGroup} Needed at {$hospital}, {$city} (#{$reqNum})";
    } else {
        $subject = "[BLOOD REQUEST] Matching {$bloodGroup} Needed at {$hospital}, {$city} (#{$reqNum})";
    }

    // Direct Action Link (safely formatted)
    $requestId = (int)($requestData['request_id'] ?? 0);
    $baseUrl = defined('BASE_URL') ? BASE_URL : '/lifeLink';
    if (strpos($baseUrl, 'http://') === 0 || strpos($baseUrl, 'https://') === 0) {
        $loginUrl = rtrim($baseUrl, '/') . '/donor/request_details.php?id=' . $requestId;
    } else {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $loginUrl = $scheme . '://' . $host . '/' . ltrim($baseUrl, '/') . '/donor/request_details.php?id=' . $requestId;
    }

    $headerBg = $isEmergency ? '#DC2626' : '#0B192C';
    $badgeBg = $isEmergency ? '#991B1B' : '#DC2626';
    $urgencyLabel = $isEmergency ? 'CRITICAL EMERGENCY (Immediate)' : ($isUrgent ? 'URGENT (24-48 Hours)' : 'Standard Need');
    $safeRecipient = htmlspecialchars($recipientName ?: 'LifeLink Donor', ENT_QUOTES, 'UTF-8');

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
  .card { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(15,23,42,0.08); border: 1px solid #e2e8f0; }
  .header { background: {$headerBg}; padding: 26px 30px; text-align: center; color: #ffffff; }
  .header h2 { margin: 8px 0 0 0; font-size: 22px; font-weight: 700; }
  .urgency-badge { display: inline-block; background: {$badgeBg}; color: #ffffff; padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
  .body { padding: 30px; line-height: 1.6; }
  .detail-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 20px; margin: 20px 0; }
  .blood-highlight { font-size: 26px; color: #DC2626; font-weight: 800; }
  .btn { display: inline-block; background-color: #DC2626; color: #ffffff !important; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: bold; font-size: 15px; margin: 15px 0; }
  .alert-box { background-color: #fef2f2; border-left: 4px solid #DC2626; padding: 12px 16px; margin: 20px 0; font-size: 13px; color: #991b1b; }
  .footer { background: #f1f5f9; padding: 20px; text-align: center; font-size: 12px; color: #64748b; }
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <span class="urgency-badge">{$urgencyLabel}</span>
    <h2>Life<span style="color: #fca5a5;">Link</span> Blood Alert</h2>
    <p style="margin: 4px 0 0; font-size: 13px; opacity: 0.9;">A patient in your region urgently requires matching blood.</p>
  </div>
  <div class="body">
    <p>Dear <strong>{$safeRecipient}</strong>,</p>
    <p>A new blood donation request matching your blood group (<strong>{$bloodGroup}</strong>) has been broadcast near your area. Please review the requirement below:</p>

    <div class="detail-box">
      <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
        <tr>
          <td style="padding: 7px 0; color: #64748b; font-weight: 500;">Request Number:</td>
          <td style="padding: 7px 0; color: #0f172a; font-weight: 700; text-align: right;">#{$reqNum}</td>
        </tr>
        <tr>
          <td style="padding: 7px 0; color: #64748b; font-weight: 500;">Blood Group Needed:</td>
          <td style="padding: 7px 0; text-align: right;"><span class="blood-highlight">{$bloodGroup}</span></td>
        </tr>
        <tr>
          <td style="padding: 7px 0; color: #64748b; font-weight: 500;">Quantity Required:</td>
          <td style="padding: 7px 0; color: #0f172a; font-weight: 700; text-align: right;">{$units} Unit(s)</td>
        </tr>
        <tr>
          <td style="padding: 7px 0; color: #64748b; font-weight: 500;">Hospital / Center:</td>
          <td style="padding: 7px 0; color: #0f172a; font-weight: 600; text-align: right;">{$hospital}</td>
        </tr>
        <tr>
          <td style="padding: 7px 0; color: #64748b; font-weight: 500;">Location:</td>
          <td style="padding: 7px 0; color: #0f172a; font-weight: 600; text-align: right;">{$city}, {$state}</td>
        </tr>
        <tr>
          <td style="padding: 7px 0; color: #64748b; font-weight: 500;">Required By Date:</td>
          <td style="padding: 7px 0; color: #DC2626; font-weight: 700; text-align: right;">{$requiredDate}</td>
        </tr>
        <tr>
          <td style="padding: 7px 0; color: #64748b; font-weight: 500;">Contact Coordinator:</td>
          <td style="padding: 7px 0; color: #0f172a; font-weight: 600; text-align: right;">{$contactPerson} ({$contactPhone})</td>
        </tr>
      </table>
    </div>

    <div style="text-align: center; margin: 25px 0;">
      <a href="{$loginUrl}" class="btn">View Request &amp; Respond</a>
    </div>

    <div class="alert-box">
      <strong>Medical Eligibility Notice:</strong> Responding in the portal confirms your availability and willingness. On-site clinical evaluation and hemoglobin check by hospital staff is required prior to transfusion.
    </div>

    <p style="font-size: 13px; color: #64748b;">If you are temporarily unavailable or cannot donate at this time, you may update your availability directly in your donor dashboard.</p>
  </div>
  <div class="footer">
    &copy; LifeLink Online Blood Donor Finder System &bull; For medical emergencies call 1800-LIFELINK
  </div>
</div>
</body>
</html>
HTML;

    $text = "LIFELINK BLOOD REQUEST ALERT\n";
    $text .= "Urgency: {$urgencyLabel}\n";
    $text .= "Request #: {$reqNum}\n";
    $text .= "Blood Group: {$bloodGroup} ({$units} units)\n";
    $text .= "Hospital: {$hospital}, {$city}, {$state}\n";
    $text .= "Required By: {$requiredDate}\n";
    $text .= "Contact: {$contactPerson} ({$contactPhone})\n\n";
    $text .= "To review and confirm your willingness to donate, click here:\n{$loginUrl}\n\n";
    $text .= "LifeLink Blood Network";

    return sendLifeLinkEmail($toEmail, $recipientName, $subject, $html, $text);
}

/**
 * Send Blood Request Confirmation Email to the Requester
 * Notifies the requester that their request has been successfully broadcast to donors.
 */
function sendBloodRequestConfirmationEmail($toEmail, $recipientName, array $requestData, $donorCount = 0) {
    $reqNum = htmlspecialchars($requestData['unique_request_number'] ?? 'REQ-NEW', ENT_QUOTES, 'UTF-8');
    $patientName = htmlspecialchars($requestData['patient_name'] ?? 'Patient', ENT_QUOTES, 'UTF-8');
    $bloodGroup = htmlspecialchars($requestData['blood_group'] ?? '', ENT_QUOTES, 'UTF-8');
    $units = (int)($requestData['units_required'] ?? 1);
    $hospital = htmlspecialchars($requestData['hospital_name'] ?? 'Hospital', ENT_QUOTES, 'UTF-8');
    $city = htmlspecialchars($requestData['city'] ?? '', ENT_QUOTES, 'UTF-8');
    $urgency = $requestData['urgency'] ?? 'Normal';
    $requiredDate = !empty($requestData['required_date']) ? date('d M Y', strtotime($requestData['required_date'])) : 'Immediate';

    $subject = "[LifeLink] Blood Request Posted: #{$reqNum} ({$bloodGroup} needed at {$hospital})";

    $requestId = (int)($requestData['request_id'] ?? 0);
    $baseUrl = defined('BASE_URL') ? BASE_URL : '/lifeLink';
    if (strpos($baseUrl, 'http://') === 0 || strpos($baseUrl, 'https://') === 0) {
        $trackUrl = rtrim($baseUrl, '/') . '/requester/request_details.php?id=' . $requestId;
    } else {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $trackUrl = $scheme . '://' . $host . '/' . ltrim($baseUrl, '/') . '/requester/request_details.php?id=' . $requestId;
    }

    $safeRecipient = htmlspecialchars($recipientName ?: 'Requester', ENT_QUOTES, 'UTF-8');

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
  .card { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(15,23,42,0.08); border: 1px solid #e2e8f0; }
  .header { background: #0B192C; padding: 26px 30px; text-align: center; color: #ffffff; }
  .header h2 { margin: 8px 0 0 0; font-size: 22px; font-weight: 700; }
  .body { padding: 30px; line-height: 1.6; }
  .detail-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 20px; margin: 20px 0; }
  .btn { display: inline-block; background-color: #DC2626; color: #ffffff !important; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: bold; font-size: 15px; margin: 15px 0; }
  .note { background-color: #eff6ff; border-left: 4px solid #3b82f6; padding: 12px 16px; margin: 20px 0; font-size: 13px; color: #1e40af; }
  .footer { background: #f1f5f9; padding: 20px; text-align: center; font-size: 12px; color: #64748b; }
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <div style="font-size: 30px;">📋</div>
    <h2>Life<span style="color: #ef4444;">Link</span> Blood Request Posted</h2>
    <p style="margin: 4px 0 0; font-size: 13px; opacity: 0.9;">Your blood requirement has been submitted to the network.</p>
  </div>
  <div class="body">
    <p>Dear <strong>{$safeRecipient}</strong>,</p>
    <p>Your blood request has been successfully created. We have broadcast the notification to <strong>{$donorCount} matching donor(s)</strong> in your area.</p>

    <div class="detail-box">
      <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
        <tr>
          <td style="padding: 6px 0; color: #64748b;">Request Number:</td>
          <td style="padding: 6px 0; color: #0f172a; font-weight: 700; text-align: right;">#{$reqNum}</td>
        </tr>
        <tr>
          <td style="padding: 6px 0; color: #64748b;">Patient:</td>
          <td style="padding: 6px 0; color: #0f172a; font-weight: 600; text-align: right;">{$patientName}</td>
        </tr>
        <tr>
          <td style="padding: 6px 0; color: #64748b;">Blood Group Needed:</td>
          <td style="padding: 6px 0; color: #DC2626; font-weight: 800; text-align: right;">{$bloodGroup} ({$units} units)</td>
        </tr>
        <tr>
          <td style="padding: 6px 0; color: #64748b;">Hospital:</td>
          <td style="padding: 6px 0; color: #0f172a; font-weight: 600; text-align: right;">{$hospital}, {$city}</td>
        </tr>
        <tr>
          <td style="padding: 6px 0; color: #64748b;">Required By:</td>
          <td style="padding: 6px 0; color: #0f172a; font-weight: 600; text-align: right;">{$requiredDate}</td>
        </tr>
        <tr>
          <td style="padding: 6px 0; color: #64748b;">Urgency:</td>
          <td style="padding: 6px 0; color: #0f172a; font-weight: 600; text-align: right;">{$urgency}</td>
        </tr>
      </table>
    </div>

    <div style="text-align: center; margin: 25px 0;">
      <a href="{$trackUrl}" class="btn">Track Responses &amp; Donors</a>
    </div>

    <div class="note">
      <strong>Next Steps:</strong> When a donor responds to your request, you will receive an instant notification in your LifeLink portal.
    </div>
  </div>
  <div class="footer">
    &copy; LifeLink Blood Network &bull; In acute medical emergencies call 1800-LIFELINK
  </div>
</div>
</body>
</html>
HTML;

    $text = "LIFELINK BLOOD REQUEST POSTED\n\n";
    $text .= "Request #: {$reqNum}\n";
    $text .= "Patient: {$patientName}\n";
    $text .= "Blood Group: {$bloodGroup} ({$units} units)\n";
    $text .= "Hospital: {$hospital}, {$city}\n";
    $text .= "Donors Notified: {$donorCount}\n\n";
    $text .= "Track your request online:\n{$trackUrl}\n\n";
    $text .= "LifeLink Blood Network";

    return sendLifeLinkEmail($toEmail, $recipientName, $subject, $html, $text);
}
