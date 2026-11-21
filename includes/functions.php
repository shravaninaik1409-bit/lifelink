<?php
/**
 * LifeLink - Core Utility Functions
 */

if (!function_exists('sanitize')) {
    function sanitize($data) {
        if (is_array($data)) {
            return array_map('sanitize', $data);
        }
        return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * CSRF Protection Helpers
 */
function getCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

/**
 * Generates next unique Blood Request ID: LL-YYYY-XXXXXX
 */
function generateUniqueRequestNumber($pdo) {
    $year = date('Y');
    try {
        $stmt = $pdo->query("SELECT request_id FROM blood_requests ORDER BY request_id DESC LIMIT 1");
        $last = $stmt->fetch();
        $nextNum = $last ? ((int)$last['request_id'] + 1) : 1;
    } catch (Exception $e) {
        $nextNum = rand(10, 999);
    }
    return sprintf("LL-%s-%06d", $year, $nextNum);
}

/**
 * Create a new user notification
 */
function createNotification($pdo, $userId, $title, $message, $type = 'system', $requestId = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, notification_type, related_request_id) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$userId, $title, $message, $type, $requestId]);
    } catch (Exception $e) {
        error_log("Notification Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Log Admin Action
 */
function logAdminAction($pdo, $adminId, $action, $details = '') {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$adminId, $action, $details, $ip]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get count of unread notifications
 */
function getUnreadNotificationsCount($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Time ago formatting helper
 */
function timeAgo($datetime) {
    if (empty($datetime)) return 'N/A';
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return round($diff / 60) . ' mins ago';
    if ($diff < 86400) return round($diff / 3600) . ' hrs ago';
    if ($diff < 604800) return round($diff / 86400) . ' days ago';
    return date('d M Y', $time);
}

/**
 * Urgency badge HTML
 */
function getUrgencyBadge($urgency) {
    switch (strtolower($urgency)) {
        case 'emergency':
            return '<span class="badge bg-danger text-white"><i class="fa-solid fa-triangle-exclamation me-1"></i>Emergency</span>';
        case 'urgent':
            return '<span class="badge bg-warning text-dark"><i class="fa-solid fa-bolt me-1"></i>Urgent</span>';
        default:
            return '<span class="badge bg-info text-dark"><i class="fa-solid fa-clock me-1"></i>Normal</span>';
    }
}

/**
 * Request status badge HTML
 */
function getStatusBadge($status) {
    switch ($status) {
        case 'Pending':
            return '<span class="badge bg-secondary"><i class="fa-solid fa-hourglass-start me-1"></i>Pending</span>';
        case 'Donors Notified':
            return '<span class="badge bg-primary"><i class="fa-solid fa-paper-plane me-1"></i>Donors Notified</span>';
        case 'Donor Response Received':
            return '<span class="badge bg-info text-dark"><i class="fa-solid fa-reply me-1"></i>Response Received</span>';
        case 'Accepted':
            return '<span class="badge bg-success"><i class="fa-solid fa-handshake me-1"></i>Accepted</span>';
        case 'In Progress':
            return '<span class="badge bg-warning text-dark"><i class="fa-solid fa-spinner fa-spin me-1"></i>In Progress</span>';
        case 'Completed':
            return '<span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i>Completed</span>';
        case 'Cancelled':
            return '<span class="badge bg-dark"><i class="fa-solid fa-ban me-1"></i>Cancelled</span>';
        default:
            return '<span class="badge bg-light text-dark">' . htmlspecialchars($status) . '</span>';
    }
}

/**
 * Verification status badge HTML
 */
function getVerificationBadge($status) {
    switch (strtolower((string)$status)) {
        case 'verified':
            return '<span class="badge bg-success text-white"><i class="fa-solid fa-circle-check me-1"></i>Verified Profile</span>';
        case 'pending':
            return '<span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Pending Verification</span>';
        case 'rejected':
            return '<span class="badge bg-danger text-white"><i class="fa-solid fa-circle-xmark me-1"></i>Verification Rejected</span>';
        case 'unverified':
        default:
            return '<span class="badge bg-secondary text-white"><i class="fa-solid fa-circle-question me-1"></i>Unverified</span>';
    }
}

/**
 * Medical eligibility confirmation notice badge
 */
function getMedicalEligibilityBadge() {
    return '<span class="badge bg-light text-danger border border-danger-subtle"><i class="fa-solid fa-stethoscope me-1"></i>Requires Medical Confirmation</span>';
}

/**
 * Hospital confirmation status badge HTML
 */
function getHospitalConfirmationBadge($isConfirmed) {
    if (!empty($isConfirmed)) {
        return '<span class="badge bg-success text-white"><i class="fa-solid fa-hospital-user me-1"></i>Hospital Confirmed</span>';
    }
    return '<span class="badge bg-warning text-dark border border-warning-subtle"><i class="fa-solid fa-hourglass-half me-1"></i>Hospital Confirmation Pending</span>';
}

/**
 * Calculate donor profile completeness percentage (0 - 100%)
 */
function calculateProfileCompleteness($donor) {
    $score = 0;
    $details = [];

    // Basic Account Details
    if (!empty($donor['full_name'])) { $score += 15; } else { $details[] = 'Full Name'; }
    if (!empty($donor['email'])) { $score += 10; } else { $details[] = 'Email Address'; }
    if (!empty($donor['phone'])) { $score += 10; } else { $details[] = 'Phone Number'; }

    // Core Donor Medical Details
    if (!empty($donor['blood_group'])) { $score += 15; } else { $details[] = 'Blood Group'; }
    if (!empty($donor['screening_completed'])) { $score += 10; } else { $details[] = 'Screening Questions'; }

    // Geographic Details
    if (!empty($donor['state']) && !empty($donor['city'])) { $score += 15; } else { $details[] = 'City & State'; }
    if (!empty($donor['pin_code'])) { $score += 10; } else { $details[] = 'Postal PIN'; }

    // Bio & Demographics
    if (!empty($donor['date_of_birth'])) { $score += 5; } else { $details[] = 'Date of Birth'; }
    if (!empty($donor['profile_description'])) { $score += 10; } else { $details[] = 'Bio / Notes'; }

    $percentage = min(100, $score);
    return [
        'percentage' => $percentage,
        'missing' => $details,
        'label' => $percentage >= 85 ? 'Comprehensive' : ($percentage >= 60 ? 'Good' : 'Needs Completion')
    ];
}

/**
 * Blood group compatibility dictionary
 */
function getBloodCompatibility($bloodGroup) {
    $matrix = [
        'O-' => ['give' => ['All Groups (O-, O+, A-, A+, B-, B+, AB-, AB+)'], 'receive' => ['O-']],
        'O+' => ['give' => ['O+', 'A+', 'B+', 'AB+'], 'receive' => ['O+', 'O-']],
        'A-' => ['give' => ['A-', 'A+', 'AB-', 'AB+'], 'receive' => ['A-', 'O-']],
        'A+' => ['give' => ['A+', 'AB+'], 'receive' => ['A+', 'A-', 'O+', 'O-']],
        'B-' => ['give' => ['B-', 'B+', 'AB-', 'AB+'], 'receive' => ['B-', 'O-']],
        'B+' => ['give' => ['B+', 'AB+'], 'receive' => ['B+', 'B-', 'O+', 'O-']],
        'AB-' => ['give' => ['AB-', 'AB+'], 'receive' => ['AB-', 'A-', 'B-', 'O-']],
        'AB+' => ['give' => ['AB+'], 'receive' => ['All Groups (Universal Recipient)']]
    ];
    return $matrix[$bloodGroup] ?? ['give' => [], 'receive' => []];
}
