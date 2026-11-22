<?php
/**
 * LifeLink - Submit Incident / User Report Handler
 * Handles report submissions for donors and blood requests with strict security and duplicate prevention.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
          || (isset($_POST['ajax']) && $_POST['ajax'] === '1');

function respondReport($success, $message, $returnUrl = null) {
    global $isAjax;
    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'redirect' => $returnUrl
        ]);
        exit();
    }

    if ($success) {
        $_SESSION['flash_msg'] = $message;
    } else {
        $_SESSION['flash_error'] = $message;
    }

    $safeUrl = BASE_URL . '/index.php';
    if (!empty($returnUrl)) {
        // Validate internal redirect URL to prevent open redirect
        $parsed = parse_url($returnUrl);
        if (empty($parsed['host']) || $parsed['host'] === ($_SERVER['HTTP_HOST'] ?? 'localhost')) {
            $safeUrl = $returnUrl;
        }
    }

    header("Location: " . $safeUrl);
    exit();
}

// 1. Method check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondReport(false, "Invalid request method.");
}

// 2. Authentication check
if (!isLoggedIn()) {
    respondReport(false, "You must be logged in to submit a report.", BASE_URL . "/login.php");
}

$currentUser = getCurrentUser();
$reporterId = (int)$currentUser['user_id'];

// 3. CSRF Validation
$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCSRFToken($csrfToken)) {
    respondReport(false, "Security validation failed. Please refresh the page and try again.");
}

// 4. Target input sanitization & validation
$targetType = trim($_POST['target_type'] ?? '');
$targetId = (int)($_POST['target_id'] ?? 0);
$reportReason = sanitize(trim($_POST['report_reason'] ?? ''));
$description = sanitize(trim($_POST['description'] ?? ''));
$returnUrl = trim($_POST['return_url'] ?? '');

if (!in_array($targetType, ['donor', 'request'])) {
    respondReport(false, "Invalid report target type specified.", $returnUrl);
}

if ($targetId <= 0) {
    respondReport(false, "Invalid target ID specified.", $returnUrl);
}

// Allowed reasons whitelist
$allowedDonorReasons = [
    'Fake or Incorrect Information',
    'Unreachable / Invalid Phone Number',
    'Demanded Money / Commercial Solicitation',
    'Inappropriate or Abusive Behavior',
    'Not Available / Refused Donation',
    'Other Inappropriate Activity'
];

$allowedRequestReasons = [
    'Fake or Fraudulent Patient Requirement',
    'Duplicate Blood Request',
    'Commercial Resale / Blood Black Market',
    'Incorrect Hospital or Location Details',
    'Already Fulfilled / Outdated Request',
    'Other Suspicious Activity'
];

$validReasons = ($targetType === 'donor') ? $allowedDonorReasons : $allowedRequestReasons;
if (empty($reportReason) || !in_array($reportReason, $validReasons)) {
    respondReport(false, "Please select a valid report category from the options.", $returnUrl);
}

// Validate description
if (empty($description)) {
    respondReport(false, "Please provide a description explaining the issue or suspicious details.", $returnUrl);
}

if (strlen($description) < 5) {
    respondReport(false, "Description must be at least 5 characters long.", $returnUrl);
}

if (strlen($description) > 1000) {
    respondReport(false, "Description cannot exceed 1000 characters.", $returnUrl);
}

$reportedUserId = null;
$requestId = null;

try {
    // 5. Verify target existence in database
    if ($targetType === 'donor') {
        $stmt = $db->prepare("SELECT d.donor_id, d.user_id, u.full_name FROM donors d JOIN users u ON d.user_id = u.user_id WHERE d.donor_id = ?");
        $stmt->execute([$targetId]);
        $targetDonor = $stmt->fetch();
        if (!$targetDonor) {
            respondReport(false, "The reported donor profile does not exist.", $returnUrl);
        }

        $reportedUserId = (int)$targetDonor['user_id'];

        // Self-reporting check
        if ($reportedUserId === $reporterId) {
            respondReport(false, "You cannot submit an incident report against your own account.", $returnUrl);
        }

        // 6. Duplicate report prevention (check pending report for this donor by this user)
        $dupStmt = $db->prepare("SELECT report_id FROM reports WHERE reported_by = ? AND reported_user = ? AND status = 'Pending' LIMIT 1");
        $dupStmt->execute([$reporterId, $reportedUserId]);
        if ($dupStmt->fetch()) {
            respondReport(false, "You have already submitted a pending report for this donor. Our moderation team is actively reviewing it.", $returnUrl);
        }
    } else {
        // targetType === 'request'
        $stmt = $db->prepare("SELECT request_id, requester_id, unique_request_number FROM blood_requests WHERE request_id = ?");
        $stmt->execute([$targetId]);
        $targetRequest = $stmt->fetch();
        if (!$targetRequest) {
            respondReport(false, "The reported blood request does not exist.", $returnUrl);
        }

        $requestId = (int)$targetRequest['request_id'];
        $reportedUserId = (int)$targetRequest['requester_id'];

        // Self-reporting check
        if ($reportedUserId === $reporterId) {
            respondReport(false, "You cannot report your own blood request.", $returnUrl);
        }

        // 6. Duplicate report prevention (check pending report for this request by this user)
        $dupStmt = $db->prepare("SELECT report_id FROM reports WHERE reported_by = ? AND request_id = ? AND status = 'Pending' LIMIT 1");
        $dupStmt->execute([$reporterId, $requestId]);
        if ($dupStmt->fetch()) {
            respondReport(false, "You have already submitted a pending report for this blood request. Our moderation team is actively reviewing it.", $returnUrl);
        }
    }

    // 7. Insert report into reports table
    $ins = $db->prepare("INSERT INTO reports (reported_by, reported_user, request_id, report_reason, description, status, created_at) VALUES (?, ?, ?, ?, ?, 'Pending', NOW())");
    $ins->execute([
        $reporterId,
        $reportedUserId,
        $requestId,
        $reportReason,
        $description
    ]);
    $newReportId = (int)$db->lastInsertId();

    // Create confirmation in-app notification for the reporter
    createNotification(
        $db, 
        $reporterId, 
        "Report Submitted (#{$newReportId})", 
        "Thank you for helping keep LifeLink safe. Your report regarding {$reportReason} has been received and will be reviewed by platform administrators.",
        "system"
    );

    respondReport(true, "Your report has been submitted to the moderation team for review. Thank you for keeping our community safe.", $returnUrl);
} catch (Exception $e) {
    error_log("Report submission error: " . $e->getMessage());
    respondReport(false, "An error occurred while processing your report. Please try again later.", $returnUrl);
}
