<?php
/**
 * LifeLink - Administrator Dashboard
 * Features:
 * - Real-time Key System Metrics
 * - Dedicated Donor Verification Panel (Section 6)
 * - Dedicated Blood Request Monitoring Panel (Section 6)
 * - Visual Blood Group & Geographic Analytics
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
$currentUser = getCurrentUser();
$adminId = $currentUser['user_id'];
$pageTitle = "Admin Dashboard — LifeLink";

// Handle Actions (Donor Verification, Notes, Notification, Hospital Confirmation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $action = $_POST['action_type'];
    $donorId = (int)($_POST['donor_id'] ?? 0);
    $targetUserId = (int)($_POST['user_id'] ?? 0);
    $notes = sanitize($_POST['verification_notes'] ?? '');

    try {
        if ($action === 'verify') {
            $db->prepare("UPDATE users SET verification_status = 'verified' WHERE user_id = ?")->execute([$targetUserId]);
            if ($notes !== '') {
                $db->prepare("UPDATE donors SET verification_notes = ? WHERE donor_id = ?")->execute([$notes, $donorId]);
            }
            logAdminAction($db, $adminId, "Donor Profile Verified", "Verified User #{$targetUserId} (Donor #{$donorId}). Notes: {$notes}");
            createNotification($db, $targetUserId, "Profile Verified Successfully", "Congratulations! Your LifeLink donor profile and credentials have been verified by the administration. " . ($notes ? "Notes: {$notes}" : "A 'Verified Profile' badge is now visible on your account."), "system");
            $_SESSION['flash_msg'] = "Donor profile marked as Verified Profile!";
        } elseif ($action === 'keep_pending') {
            $db->prepare("UPDATE users SET verification_status = 'pending' WHERE user_id = ?")->execute([$targetUserId]);
            if ($notes !== '') {
                $db->prepare("UPDATE donors SET verification_notes = ? WHERE donor_id = ?")->execute([$notes, $donorId]);
            }
            logAdminAction($db, $adminId, "Donor Kept Pending", "Kept User #{$targetUserId} in pending verification. Notes: {$notes}");
            createNotification($db, $targetUserId, "Verification Pending", "Your donor profile review is currently pending further information or review. " . ($notes ? "Notes: {$notes}" : ""), "system");
            $_SESSION['flash_msg'] = "Donor status kept as Pending Verification.";
        } elseif ($action === 'reject') {
            $db->prepare("UPDATE users SET verification_status = 'rejected' WHERE user_id = ?")->execute([$targetUserId]);
            if ($notes !== '') {
                $db->prepare("UPDATE donors SET verification_notes = ? WHERE donor_id = ?")->execute([$notes, $donorId]);
            }
            logAdminAction($db, $adminId, "Donor Verification Rejected", "Rejected User #{$targetUserId}. Reason: {$notes}");
            createNotification($db, $targetUserId, "Verification Update", "Your donor profile verification could not be approved at this time. Reason: " . ($notes ?: "Information provided could not be verified."), "system");
            $_SESSION['flash_msg'] = "Donor profile verification marked as Rejected.";
        } elseif ($action === 'save_notes') {
            $db->prepare("UPDATE donors SET verification_notes = ? WHERE donor_id = ?")->execute([$notes, $donorId]);
            logAdminAction($db, $adminId, "Verification Notes Updated", "Updated notes for Donor #{$donorId}");
            $_SESSION['flash_msg'] = "Verification notes saved successfully!";
        } elseif ($action === 'notify_donor') {
            $customMsg = sanitize($_POST['notification_message'] ?? '');
            if (!empty($customMsg)) {
                createNotification($db, $targetUserId, "Notice from LifeLink Administration", $customMsg, "system");
                logAdminAction($db, $adminId, "Donor Direct Notification", "Notified User #{$targetUserId}: {$customMsg}");
                $_SESSION['flash_msg'] = "Direct notification successfully delivered to donor!";
            }
        } elseif ($action === 'toggle_hospital') {
            $rId = (int)($_POST['request_id'] ?? 0);
            $hNotes = sanitize($_POST['hospital_notes'] ?? '');
            $curConfirmed = (int)($_POST['current_hospital_confirmed'] ?? 0);
            $newConfirmed = $curConfirmed ? 0 : 1;

            $db->prepare("UPDATE blood_requests SET hospital_confirmed = ?, hospital_confirmed_at = NOW(), hospital_notes = ? WHERE request_id = ?")
               ->execute([$newConfirmed, $hNotes, $rId]);

            $reqUserStmt = $db->prepare("SELECT requester_id, unique_request_number, hospital_name FROM blood_requests WHERE request_id = ?");
            $reqUserStmt->execute([$rId]);
            $reqInfo = $reqUserStmt->fetch();

            $statusText = $newConfirmed 
                ? "Hospital coordination confirmed for Request #{$reqInfo['unique_request_number']} at {$reqInfo['hospital_name']}! Attending blood bank confirmed." 
                : "Hospital coordination status changed to Pending for Request #{$reqInfo['unique_request_number']}.";

            logAdminAction($db, $adminId, "Hospital Confirmation Updated", $statusText);
            if ($reqInfo) {
                createNotification($db, $reqInfo['requester_id'], "Hospital Coordination Update", $statusText, "status", $rId);
            }
            $_SESSION['flash_msg'] = $newConfirmed ? "Hospital coordination confirmed!" : "Hospital coordination reset to Pending.";
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Action failed: " . $e->getMessage();
    }
    header("Location: " . BASE_URL . "/admin/dashboard.php");
    exit();
}

// Core Statistics
$stats = [
    'total_users' => 0,
    'total_donors' => 0,
    'available_donors' => 0,
    'total_requests' => 0,
    'pending_requests' => 0,
    'emergency_requests' => 0,
    'completed_requests' => 0,
    'total_donations' => 0,
    'pending_verifications' => 0,
];

$bgDistribution = [];
$cityDistribution = [];
$recentReqs = [];
$recentUsers = [];
$verificationDonors = [];
$monitoredRequests = [];

try {
    $stats['total_users'] = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['total_donors'] = (int)$db->query("SELECT COUNT(*) FROM donors")->fetchColumn();
    $stats['available_donors'] = (int)$db->query("SELECT COUNT(*) FROM donors WHERE availability = 'Available'")->fetchColumn();
    $stats['total_requests'] = (int)$db->query("SELECT COUNT(*) FROM blood_requests")->fetchColumn();
    $stats['pending_requests'] = (int)$db->query("SELECT COUNT(*) FROM blood_requests WHERE status IN ('Pending', 'Donors Notified')")->fetchColumn();
    $stats['emergency_requests'] = (int)$db->query("SELECT COUNT(*) FROM blood_requests WHERE urgency = 'Emergency' AND status != 'Completed' AND status != 'Cancelled'")->fetchColumn();
    $stats['completed_requests'] = (int)$db->query("SELECT COUNT(*) FROM blood_requests WHERE status = 'Completed'")->fetchColumn();
    $stats['total_donations'] = (int)$db->query("SELECT COUNT(*) FROM donations WHERE verification_status = 'Verified'")->fetchColumn();
    $stats['pending_verifications'] = (int)$db->query("SELECT COUNT(*) FROM users u JOIN donors d ON u.user_id = d.user_id WHERE u.verification_status = 'pending'")->fetchColumn();

    // Blood Group Distribution
    $bgStmt = $db->query("SELECT blood_group, COUNT(*) as count FROM donors GROUP BY blood_group ORDER BY count DESC");
    $bgDistribution = $bgStmt->fetchAll();

    // City Distribution
    $cityStmt = $db->query("SELECT city, COUNT(*) as count FROM donors GROUP BY city ORDER BY count DESC LIMIT 5");
    $cityDistribution = $cityStmt->fetchAll();

    // Recent Requests
    $recentReqs = $db->query("SELECT * FROM blood_requests ORDER BY (urgency = 'Emergency') DESC, created_at DESC LIMIT 5")->fetchAll();

    // Recent Users
    $recentUsers = $db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();

    // Dedicated Donor Verification Panel Query (pending first, with user details)
    $donorVerifStmt = $db->query("
        SELECT d.*, u.full_name, u.email, u.phone, u.verification_status, u.created_at as registration_date 
        FROM donors d 
        JOIN users u ON d.user_id = u.user_id 
        ORDER BY (u.verification_status = 'pending') DESC, (u.verification_status = 'unverified') DESC, u.created_at DESC 
        LIMIT 10
    ");
    $verificationDonors = $donorVerifStmt->fetchAll();

    // Dedicated Blood Request Monitoring Panel Query (with response stats and hospital confirmation)
    $monitoredReqStmt = $db->query("
        SELECT br.*, u.full_name as requester_name,
               (SELECT COUNT(*) FROM request_responses rr WHERE rr.request_id = br.request_id) as total_responses,
               (SELECT COUNT(*) FROM request_responses rr WHERE rr.request_id = br.request_id AND rr.response_status = 'Accepted') as accepted_responses
        FROM blood_requests br
        JOIN users u ON br.requester_id = u.user_id
        ORDER BY (br.status = 'Pending' OR br.status = 'Donors Notified') DESC, (br.urgency = 'Emergency') DESC, (br.hospital_confirmed = 0) DESC, br.created_at DESC
        LIMIT 10
    ");
    $monitoredRequests = $monitoredReqStmt->fetchAll();

} catch (Exception $e) {
    // Graceful error handling
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout">
    <?php require_once __DIR__ . '/../includes/dashboard_sidebar.php'; ?>

    <div class="dashboard-main">
        <?php require_once __DIR__ . '/../includes/dashboard_topbar.php'; ?>

        <div class="dashboard-body">
            <?php if (!empty($_SESSION['flash_msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show small" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($_SESSION['flash_msg']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_msg']); ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($_SESSION['flash_error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>

            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark">System Overview</h3>
                    <p class="text-muted small mb-0">Platform health, live donor metrics, and emergency monitoring.</p>
                </div>
                <div class="d-flex gap-2 mt-2 mt-sm-0">
                    <a href="<?= BASE_URL ?>/admin/reports.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-chart-pie me-1"></i> View Analytics
                    </a>
                    <a href="<?= BASE_URL ?>/install.php" class="btn btn-outline-warning btn-sm">
                        <i class="fa-solid fa-server me-1"></i> Setup / Re-seed
                    </a>
                </div>
            </div>

            <!-- Emergency Warning Banner if active emergency requests exist -->
            <?php if ($stats['emergency_requests'] > 0): ?>
                <div class="alert alert-danger d-flex align-items-center justify-content-between p-3 rounded-4 border-0 shadow-sm mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-danger text-white rounded-circle p-2 px-3 fw-bold">
                            <i class="fa-solid fa-triangle-exclamation fa-lg"></i>
                        </div>
                        <div>
                            <strong class="text-danger d-block">Critical Emergency Requests Alert</strong>
                            <small class="text-danger-emphasis">There are currently <?= $stats['emergency_requests'] ?> active high-priority emergency blood request(s) requiring immediate attention.</small>
                        </div>
                    </div>
                    <a href="<?= BASE_URL ?>/admin/requests.php?urgency=Emergency" class="btn btn-danger btn-sm text-nowrap">
                        Review Emergencies
                    </a>
                </div>
            <?php endif; ?>

            <!-- Pending Donor Verifications Banner -->
            <?php if ($stats['pending_verifications'] > 0): ?>
                <div class="alert alert-warning d-flex align-items-center justify-content-between p-3 rounded-4 border-0 shadow-sm mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-warning text-dark rounded-circle p-2 px-3 fw-bold">
                            <i class="fa-solid fa-user-shield fa-lg"></i>
                        </div>
                        <div>
                            <strong class="text-dark d-block">Donor Profile Verification Queue</strong>
                            <small class="text-secondary">There are <?= $stats['pending_verifications'] ?> registered donor(s) awaiting identity review and screening verification.</small>
                        </div>
                    </div>
                    <a href="#donorVerificationPanel" class="btn btn-warning btn-sm text-dark fw-bold text-nowrap">
                        <i class="fa-solid fa-list-check me-1"></i> Review Panel (<?= $stats['pending_verifications'] ?>)
                    </a>
                </div>
            <?php endif; ?>

            <!-- Primary Metrics Grid -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="metric-card">
                        <div>
                            <div class="metric-label">Total Users</div>
                            <div class="metric-value"><?= number_format($stats['total_users']) ?></div>
                        </div>
                        <div class="metric-icon-box bg-primary bg-opacity-10 text-primary">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="metric-card">
                        <div>
                            <div class="metric-label">Registered Donors</div>
                            <div class="metric-value text-danger"><?= number_format($stats['total_donors']) ?></div>
                            <small class="text-success fw-bold" style="font-size: 0.75rem;">
                                <?= $stats['available_donors'] ?> Available Now
                            </small>
                        </div>
                        <div class="metric-icon-box bg-danger bg-opacity-10 text-danger">
                            <i class="fa-solid fa-heart-pulse"></i>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="metric-card">
                        <div>
                            <div class="metric-label">Blood Requests</div>
                            <div class="metric-value text-dark"><?= number_format($stats['total_requests']) ?></div>
                            <small class="text-danger fw-bold" style="font-size: 0.75rem;">
                                <?= $stats['pending_requests'] ?> In Progress
                            </small>
                        </div>
                        <div class="metric-icon-box bg-warning bg-opacity-10 text-warning">
                            <i class="fa-solid fa-hand-holding-droplet"></i>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="metric-card">
                        <div>
                            <div class="metric-label">Verified Donations</div>
                            <div class="metric-value text-success"><?= number_format($stats['total_donations']) ?></div>
                            <small class="text-muted" style="font-size: 0.75rem;">Recorded Sessions</small>
                        </div>
                        <div class="metric-icon-box bg-success bg-opacity-10 text-success">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DEDICATED DONOR VERIFICATION PANEL -->
            <div class="content-card mb-4" id="donorVerificationPanel">
                <div class="content-card-header d-flex flex-wrap justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-1 text-dark">
                            <i class="fa-solid fa-user-shield text-danger me-2"></i>Dedicated Donor Verification Panel
                        </h5>
                        <p class="text-muted small mb-0">Review identity details, preliminary eligibility screening acknowledgments, and profile completeness.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-2 mt-sm-0">
                        <?php if ($stats['pending_verifications'] > 0): ?>
                            <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i><?= $stats['pending_verifications'] ?> Pending Review</span>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/admin/donors.php" class="btn btn-outline-danger btn-sm">
                            <i class="fa-solid fa-list-check me-1"></i> Full Donor Directory
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Donor Profile</th>
                                <th>Blood Group</th>
                                <th>City / Area</th>
                                <th>Registered</th>
                                <th>Verification Status</th>
                                <th>Screening Acknowledgment</th>
                                <th>Profile Completeness</th>
                                <th class="text-end">Admin Actions</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <?php if (empty($verificationDonors)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">No donor registrations available to verify.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($verificationDonors as $d): ?>
                                    <?php 
                                        $completeness = calculateProfileCompleteness($d);
                                        $barColor = $completeness['percentage'] >= 80 ? 'bg-success' : ($completeness['percentage'] >= 50 ? 'bg-warning' : 'bg-danger');
                                        $answers = !empty($d['screening_answers']) ? json_decode($d['screening_answers'], true) : [];
                                    ?>
                                    <tr class="<?= $d['verification_status'] === 'pending' ? 'table-warning bg-opacity-25' : '' ?>">
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="user-avatar-circle" style="width: 36px; height: 36px; font-size: 0.85rem;">
                                                    <?= strtoupper(substr($d['full_name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <strong class="d-block text-dark"><?= htmlspecialchars($d['full_name']) ?></strong>
                                                    <small class="text-muted"><?= htmlspecialchars($d['email']) ?> &bull; <?= htmlspecialchars($d['phone']) ?></small>
                                                    <?php if (!empty($d['verification_notes'])): ?>
                                                        <div class="text-muted" style="font-size: 0.7rem;">
                                                            <i class="fa-solid fa-note-sticky text-warning me-1"></i>Note: <?= htmlspecialchars($d['verification_notes']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="blood-badge" style="width: 36px; height: 36px; font-size: 0.95rem;">
                                                <?= htmlspecialchars($d['blood_group']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="text-dark d-block"><?= htmlspecialchars($d['city']) ?></strong>
                                            <small class="text-muted"><?= htmlspecialchars($d['area'] ?: $d['state']) ?></small>
                                        </td>
                                        <td class="text-muted text-nowrap">
                                            <?= date('d M Y', strtotime($d['registration_date'])) ?>
                                            <small class="d-block text-muted" style="font-size: 0.72rem;"><?= timeAgo($d['registration_date']) ?></small>
                                        </td>
                                        <td>
                                            <?= getVerificationBadge($d['verification_status']) ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($d['screening_completed'])): ?>
                                                <button type="button" class="btn btn-outline-success btn-sm py-0 px-2" data-bs-toggle="modal" data-bs-target="#screeningModal<?= $d['donor_id'] ?>">
                                                    <i class="fa-solid fa-clipboard-check me-1"></i>5/5 Answered
                                                </button>
                                            <?php else: ?>
                                                <span class="badge bg-light text-secondary border">Incomplete</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="min-width: 140px;">
                                            <div class="d-flex justify-content-between align-items-center mb-1" style="font-size: 0.72rem;">
                                                <span class="fw-semibold text-secondary"><?= $completeness['label'] ?></span>
                                                <strong class="text-dark"><?= $completeness['percentage'] ?>%</strong>
                                            </div>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar <?= $barColor ?>" style="width: <?= $completeness['percentage'] ?>%;"></div>
                                            </div>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <div class="d-inline-flex gap-1">
                                                <!-- Verify Button -->
                                                <?php if ($d['verification_status'] !== 'verified'): ?>
                                                    <form method="POST" action="" class="d-inline" onsubmit="return confirmAction('Approve and mark profile as Verified?');">
                                                        <input type="hidden" name="action_type" value="verify">
                                                        <input type="hidden" name="donor_id" value="<?= $d['donor_id'] ?>">
                                                        <input type="hidden" name="user_id" value="<?= $d['user_id'] ?>">
                                                        <button type="submit" class="btn btn-success btn-sm py-1 px-2" title="Verify Profile">
                                                            <i class="fa-solid fa-check"></i> Verify
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <!-- Keep Pending Button -->
                                                <?php if ($d['verification_status'] !== 'pending'): ?>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="action_type" value="keep_pending">
                                                        <input type="hidden" name="donor_id" value="<?= $d['donor_id'] ?>">
                                                        <input type="hidden" name="user_id" value="<?= $d['user_id'] ?>">
                                                        <button type="submit" class="btn btn-warning btn-sm py-1 px-2 text-dark" title="Keep Pending">
                                                            <i class="fa-solid fa-clock"></i> Pending
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <!-- Reject Button -->
                                                <?php if ($d['verification_status'] !== 'rejected'): ?>
                                                    <form method="POST" action="" class="d-inline" onsubmit="return confirmAction('Reject verification for this donor?');">
                                                        <input type="hidden" name="action_type" value="reject">
                                                        <input type="hidden" name="donor_id" value="<?= $d['donor_id'] ?>">
                                                        <input type="hidden" name="user_id" value="<?= $d['user_id'] ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm py-1 px-2" title="Reject Verification">
                                                            <i class="fa-solid fa-ban"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <!-- Notes Modal Trigger -->
                                                <button type="button" class="btn btn-light btn-sm border py-1 px-2" data-bs-toggle="modal" data-bs-target="#notesModal<?= $d['donor_id'] ?>" title="Verification Notes">
                                                    <i class="fa-solid fa-note-sticky text-warning"></i>
                                                </button>

                                                <!-- Notify Modal Trigger -->
                                                <button type="button" class="btn btn-light btn-sm border py-1 px-2" data-bs-toggle="modal" data-bs-target="#notifyModal<?= $d['donor_id'] ?>" title="Notify Donor">
                                                    <i class="fa-solid fa-bell text-primary"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- DEDICATED BLOOD REQUEST MONITORING PANEL -->
            <div class="content-card mb-4" id="bloodRequestMonitoringPanel">
                <div class="content-card-header d-flex flex-wrap justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-1 text-dark">
                            <i class="fa-solid fa-clipboard-pulse text-danger me-2"></i>Blood Request Monitoring Panel
                        </h5>
                        <p class="text-muted small mb-0">Real-time status monitoring, matched donor responses, and hospital coordination verification.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-2 mt-sm-0">
                        <a href="<?= BASE_URL ?>/admin/requests.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fa-solid fa-list-check me-1"></i> All Blood Requests
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Request ID & Patient</th>
                                <th>Group & Units</th>
                                <th>Urgency</th>
                                <th>Donor Responses</th>
                                <th>Request Status</th>
                                <th>Hospital Confirmation Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <?php if (empty($monitoredRequests)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No blood requests currently in monitoring queue.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($monitoredRequests as $reqItem): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-dark border d-block mb-1" style="width: fit-content;">
                                                #<?= htmlspecialchars($reqItem['unique_request_number']) ?>
                                            </span>
                                            <strong class="text-dark d-block"><?= htmlspecialchars($reqItem['patient_name']) ?></strong>
                                            <small class="text-muted">
                                                <i class="fa-solid fa-hospital text-danger me-1"></i><?= htmlspecialchars($reqItem['hospital_name']) ?> (<?= htmlspecialchars($reqItem['city']) ?>)
                                            </small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="blood-badge" style="width: 36px; height: 36px; font-size: 0.95rem;">
                                                    <?= htmlspecialchars($reqItem['blood_group']) ?>
                                                </span>
                                                <strong class="text-danger"><?= (int)$reqItem['units_required'] ?> Unit(s)</strong>
                                            </div>
                                        </td>
                                        <td>
                                            <?= getUrgencyBadge($reqItem['urgency']) ?>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                <span>
                                                    <i class="fa-solid fa-reply text-secondary me-1"></i>
                                                    <strong><?= (int)$reqItem['total_responses'] ?></strong> Response(s)
                                                </span>
                                                <?php if ((int)$reqItem['accepted_responses'] > 0): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle" style="width: fit-content;">
                                                        <i class="fa-solid fa-check me-1"></i><?= (int)$reqItem['accepted_responses'] ?> Accepted
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?= getStatusBadge($reqItem['status']) ?>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                <div><?= getHospitalConfirmationBadge($reqItem['hospital_confirmed']) ?></div>
                                                <?php if (!empty($reqItem['hospital_confirmed_at'])): ?>
                                                    <small class="text-muted" style="font-size: 0.7rem;">
                                                        Confirmed <?= timeAgo($reqItem['hospital_confirmed_at']) ?>
                                                    </small>
                                                <?php endif; ?>
                                                <?php if (!empty($reqItem['hospital_notes'])): ?>
                                                    <small class="text-secondary" style="font-size: 0.7rem;">
                                                        <i class="fa-solid fa-notes-medical text-primary me-1"></i><?= htmlspecialchars(substr($reqItem['hospital_notes'], 0, 35)) ?>...
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <div class="d-inline-flex gap-1">
                                                <!-- Hospital Coordination Button -->
                                                <button type="button" class="btn btn-sm <?= $reqItem['hospital_confirmed'] ? 'btn-outline-success' : 'btn-outline-warning' ?> py-1 px-2" data-bs-toggle="modal" data-bs-target="#hospitalModal<?= $reqItem['request_id'] ?>">
                                                    <i class="fa-solid fa-hospital-user me-1"></i><?= $reqItem['hospital_confirmed'] ? 'Hospital Confirmed' : 'Confirm Hospital' ?>
                                                </button>

                                                <!-- Full Tracking Link -->
                                                <a href="<?= BASE_URL ?>/admin/request_details.php?id=<?= $reqItem['request_id'] ?>" class="btn btn-light btn-sm border py-1 px-2" title="Track Request">
                                                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Distribution Charts & Visual Analytics -->
            <div class="row g-4 mb-4">
                <!-- Blood Group Breakdown -->
                <div class="col-lg-6">
                    <div class="content-card h-100">
                        <div class="content-card-header">
                            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-droplet text-danger me-2"></i>Blood Group Inventory</h5>
                        </div>
                        <div class="content-card-body">
                            <?php if (empty($bgDistribution)): ?>
                                <p class="text-muted small">No donor data available.</p>
                            <?php else: ?>
                                <?php 
                                    $maxCount = max(array_column($bgDistribution, 'count'));
                                ?>
                                <div class="d-flex flex-column gap-3">
                                    <?php foreach ($bgDistribution as $b): ?>
                                        <?php 
                                            $pct = $maxCount > 0 ? round(($b['count'] / $maxCount) * 100) : 0; 
                                        ?>
                                        <div>
                                            <div class="d-flex justify-content-between small fw-bold mb-1">
                                                <span class="text-danger"><?= htmlspecialchars($b['blood_group']) ?></span>
                                                <span class="text-muted"><?= (int)$b['count'] ?> Donor(s)</span>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-danger" style="width: <?= $pct ?>%;"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Top Cities -->
                <div class="col-lg-6">
                    <div class="content-card h-100">
                        <div class="content-card-header">
                            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-map-location-dot text-primary me-2"></i>Top Donor Cities</h5>
                        </div>
                        <div class="content-card-body">
                            <?php if (empty($cityDistribution)): ?>
                                <p class="text-muted small">No city data available.</p>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($cityDistribution as $c): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="fa-solid fa-location-dot text-primary"></i>
                                                <strong class="text-dark"><?= htmlspecialchars($c['city']) ?></strong>
                                            </div>
                                            <span class="badge bg-light text-dark border"><?= (int)$c['count'] ?> Donors</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Requests & Recent Users -->
            <div class="row g-4">
                <!-- Recent Requests -->
                <div class="col-lg-7">
                    <div class="content-card">
                        <div class="content-card-header">
                            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-file-waveform text-danger me-2"></i>Recent Blood Requests</h5>
                            <a href="<?= BASE_URL ?>/admin/requests.php" class="btn btn-outline-danger btn-sm">View All</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID & Patient</th>
                                        <th>Blood</th>
                                        <th>Hospital & City</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentReqs as $r): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-light text-dark border d-block mb-1" style="width: fit-content;"><?= htmlspecialchars($r['unique_request_number']) ?></span>
                                                <strong class="text-dark"><?= htmlspecialchars($r['patient_name']) ?></strong>
                                                <div><?= getUrgencyBadge($r['urgency']) ?></div>
                                            </td>
                                            <td>
                                                <span class="blood-badge" style="width: 38px; height: 38px; font-size: 0.95rem;"><?= htmlspecialchars($r['blood_group']) ?></span>
                                            </td>
                                            <td>
                                                <small class="d-block text-dark fw-semibold"><?= htmlspecialchars($r['hospital_name']) ?></small>
                                                <small class="text-muted"><?= htmlspecialchars($r['city']) ?></small>
                                            </td>
                                            <td><?= getStatusBadge($r['status']) ?></td>
                                            <td>
                                                <a href="<?= BASE_URL ?>/admin/request_details.php?id=<?= $r['request_id'] ?>" class="btn btn-sm btn-outline-secondary">
                                                    Manage
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Registered Users -->
                <div class="col-lg-5">
                    <div class="content-card">
                        <div class="content-card-header">
                            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-user-plus text-success me-2"></i>Recent Registrations</h5>
                            <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline-secondary btn-sm">All Users</a>
                        </div>
                        <div class="content-card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($recentUsers as $u): ?>
                                    <div class="list-group-item p-3 d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="user-avatar-circle" style="width: 38px; height: 38px; font-size: 0.9rem;">
                                                <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <strong class="d-block text-dark"><?= htmlspecialchars($u['full_name']) ?></strong>
                                                <small class="text-muted"><?= htmlspecialchars($u['email']) ?></small>
                                            </div>
                                        </div>
                                        <span class="badge bg-light text-dark border text-capitalize">
                                            <?= htmlspecialchars($u['role']) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODALS FOR DONOR VERIFICATION & SCREENING -->
<?php if (!empty($verificationDonors)): ?>
    <?php foreach ($verificationDonors as $d): ?>
        <?php $answers = !empty($d['screening_answers']) ? json_decode($d['screening_answers'], true) : []; ?>

        <!-- Screening Answers Inspection Modal -->
        <div class="modal fade" id="screeningModal<?= $d['donor_id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold">
                            <i class="fa-solid fa-clipboard-question text-danger me-2"></i>Screening Acknowledgment
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-3 p-3 bg-light rounded-3">
                            <div class="blood-badge" style="width: 44px; height: 44px; font-size: 1.1rem;"><?= htmlspecialchars($d['blood_group']) ?></div>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($d['full_name']) ?></h6>
                                <small class="text-muted"><?= htmlspecialchars($d['city']) ?>, <?= htmlspecialchars($d['state']) ?></small>
                            </div>
                        </div>

                        <div class="alert alert-info border-0 rounded-3 small mb-3">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            <strong>Policy Notice:</strong> Screening questionnaire responses are self-acknowledged. Final medical eligibility is confirmed on-site by medical staff at the time of donation.
                        </div>

                        <div class="list-group list-group-flush border rounded-3 mb-3">
                            <div class="list-group-item small d-flex justify-content-between align-items-center">
                                <span>1. Donated blood recently (within 3 months):</span>
                                <span class="badge bg-secondary"><?= htmlspecialchars($answers['donated_recently'] ?? 'No') ?></span>
                            </div>
                            <div class="list-group-item small d-flex justify-content-between align-items-center">
                                <span>2. Currently feeling healthy & well:</span>
                                <span class="badge bg-success"><?= htmlspecialchars($answers['feeling_well'] ?? 'Yes') ?></span>
                            </div>
                            <div class="list-group-item small d-flex justify-content-between align-items-center">
                                <span>3. Advised by doctor NOT to donate:</span>
                                <span class="badge bg-secondary"><?= htmlspecialchars($answers['doctor_restriction'] ?? 'No') ?></span>
                            </div>
                            <div class="list-group-item small d-flex justify-content-between align-items-center">
                                <span>4. Willing to donate when contacted:</span>
                                <span class="badge bg-success"><?= htmlspecialchars($answers['willing_to_donate'] ?? 'Yes') ?></span>
                            </div>
                            <div class="list-group-item small d-flex justify-content-between align-items-center">
                                <span>5. Reviewed basic donor eligibility rules:</span>
                                <span class="badge bg-success"><?= htmlspecialchars($answers['reviewed_guidelines'] ?? 'Yes') ?></span>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center small text-muted">
                            <span>Acknowledgment Timestamp:</span>
                            <strong><?= !empty($d['screening_date']) ? date('d M Y, h:i A', strtotime($d['screening_date'])) : 'During Registration' ?></strong>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Verification Notes Modal -->
        <div class="modal fade" id="notesModal<?= $d['donor_id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <form method="POST" action="">
                        <input type="hidden" name="action_type" value="save_notes">
                        <input type="hidden" name="donor_id" value="<?= $d['donor_id'] ?>">
                        <input type="hidden" name="user_id" value="<?= $d['user_id'] ?>">

                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title fw-bold">
                                <i class="fa-solid fa-note-sticky text-warning me-2"></i>Verification Notes
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <p class="small text-muted mb-3">Add internal notes or audit reasoning regarding this donor's profile verification status (e.g., identity card checked, contact confirmed, or missing documentation).</p>
                            
                            <label class="form-label small fw-semibold text-secondary">Verification Notes / Audit Log:</label>
                            <textarea name="verification_notes" rows="4" class="form-control" placeholder="e.g. Identity checked against government ID; phone number confirmed via telephone call..."><?= htmlspecialchars($d['verification_notes'] ?? '') ?></textarea>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Save Notes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Notify Donor Modal -->
        <div class="modal fade" id="notifyModal<?= $d['donor_id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <form method="POST" action="">
                        <input type="hidden" name="action_type" value="notify_donor">
                        <input type="hidden" name="donor_id" value="<?= $d['donor_id'] ?>">
                        <input type="hidden" name="user_id" value="<?= $d['user_id'] ?>">

                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title fw-bold">
                                <i class="fa-solid fa-paper-plane text-primary me-2"></i>Notify Donor Directly
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <p class="small text-muted mb-3">
                                Send an in-app notification directly to <strong><?= htmlspecialchars($d['full_name']) ?></strong> regarding their profile, verification requirements, or urgent blood drive coordination.
                            </p>

                            <label class="form-label small fw-semibold text-secondary">Message Content:</label>
                            <textarea name="notification_message" rows="4" class="form-control" placeholder="Write your message to the donor..." required>Please review your profile details or contact the administration team regarding your verification status.</textarea>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa-solid fa-paper-plane me-1"></i> Send Notification
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- MODALS FOR HOSPITAL CONFIRMATION -->
<?php if (!empty($monitoredRequests)): ?>
    <?php foreach ($monitoredRequests as $reqItem): ?>
        <div class="modal fade" id="hospitalModal<?= $reqItem['request_id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <form method="POST" action="">
                        <input type="hidden" name="action_type" value="toggle_hospital">
                        <input type="hidden" name="request_id" value="<?= $reqItem['request_id'] ?>">
                        <input type="hidden" name="current_hospital_confirmed" value="<?= (int)$reqItem['hospital_confirmed'] ?>">

                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title fw-bold">
                                <i class="fa-solid fa-hospital-user text-primary me-2"></i>Hospital Coordination Confirmation
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="p-3 bg-light rounded-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <strong class="text-dark">#<?= htmlspecialchars($reqItem['unique_request_number']) ?></strong>
                                    <span class="badge bg-danger"><?= htmlspecialchars($reqItem['blood_group']) ?> &bull; <?= (int)$reqItem['units_required'] ?> Unit(s)</span>
                                </div>
                                <div class="small text-muted"><i class="fa-solid fa-hospital text-danger me-1"></i><?= htmlspecialchars($reqItem['hospital_name']) ?> (<?= htmlspecialchars($reqItem['city']) ?>)</div>
                                <div class="small text-muted"><i class="fa-solid fa-user me-1"></i>Patient: <?= htmlspecialchars($reqItem['patient_name']) ?></div>
                            </div>

                            <div class="alert <?= $reqItem['hospital_confirmed'] ? 'alert-success' : 'alert-warning' ?> small mb-3">
                                <strong>Current Status:</strong>
                                <?= $reqItem['hospital_confirmed'] ? 'Hospital Confirmed (Coordination established)' : 'Hospital Confirmation Pending' ?>
                            </div>

                            <label class="form-label small fw-semibold text-secondary">Hospital Blood Bank / Staff Coordination Notes:</label>
                            <textarea name="hospital_notes" rows="3" class="form-control" placeholder="e.g. Attending physician Dr. Verma notified. Blood bank unit reservation confirmed; donor screening counter prepared."><?= htmlspecialchars($reqItem['hospital_notes'] ?? '') ?></textarea>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn <?= $reqItem['hospital_confirmed'] ? 'btn-warning text-dark' : 'btn-success' ?> btn-sm">
                                <i class="fa-solid fa-circle-check me-1"></i>
                                <?= $reqItem['hospital_confirmed'] ? 'Mark Confirmation as Pending' : 'Confirm Hospital Coordination' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
