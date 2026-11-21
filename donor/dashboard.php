<?php
/**
 * LifeLink - Donor Dashboard (Organized Healthcare Portal)
 * Clean, structured layout with:
 * - Top Navbar containing Main Menu & Shortcuts
 * - Welcome Banner with 1-click availability switcher & profile progress
 * - 3 Independent Status Parameters (Profile Verification, Availability, Medical Eligibility)
 * - Matching Blood Requests (Full Width)
 * - My Badges & Recognition (Directly below matching requests)
 * - Recent Alerts & Notifications (Directly below badges)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('donor');
$currentUser = getCurrentUser();
$userId = $currentUser['user_id'];
$pageTitle = "Donor Dashboard — LifeLink";

// Fetch full donor record
$stmt = $db->prepare("SELECT d.*, u.full_name, u.email, u.phone, u.verification_status, u.created_at as member_since 
                      FROM donors d 
                      JOIN users u ON d.user_id = u.user_id 
                      WHERE d.user_id = ?");
$stmt->execute([$userId]);
$donor = $stmt->fetch();

if (!$donor) {
    die("Donor profile not found.");
}
$donorId = $donor['donor_id'];
$bloodGroup = $donor['blood_group'];
$city = $donor['city'];

// Handle quick availability toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_availability'])) {
    $newStatus = sanitize($_POST['set_availability']);
    if (in_array($newStatus, ['Available', 'Temporarily Unavailable', 'Not Accepting Requests'])) {
        $upd = $db->prepare("UPDATE donors SET availability = ? WHERE donor_id = ?");
        $upd->execute([$newStatus, $donorId]);
        $donor['availability'] = $newStatus;
        $_SESSION['availability'] = $newStatus;
        $_SESSION['flash_msg'] = "Your availability status is now set to: " . htmlspecialchars($newStatus);
    }
}

// Calculate Statistics
$totalRequests = 0;
$pendingRequests = 0;
$acceptedRequests = 0;
$totalDonations = 0;
$emergencyAlerts = [];
$recentRequests = [];
$recentNotifications = [];

try {
    // Total requests matching this donor's blood group
    $stmtReq = $db->prepare("SELECT COUNT(*) FROM blood_requests WHERE blood_group = ? AND status != 'Cancelled'");
    $stmtReq->execute([$bloodGroup]);
    $totalRequests = (int)$stmtReq->fetchColumn();

    // Accepted responses by this donor
    $stmtAccepted = $db->prepare("SELECT COUNT(*) FROM request_responses WHERE donor_id = ? AND response_status = 'Accepted'");
    $stmtAccepted->execute([$donorId]);
    $acceptedRequests = (int)$stmtAccepted->fetchColumn();

    // Verified donations completed
    $stmtDon = $db->prepare("SELECT COUNT(*) FROM donations WHERE donor_id = ? AND verification_status = 'Verified'");
    $stmtDon->execute([$donorId]);
    $totalDonations = (int)$stmtDon->fetchColumn();

    // Critical Emergency requests matching blood group
    $stmtEmerg = $db->prepare("SELECT * FROM blood_requests 
                              WHERE blood_group = ? 
                              AND urgency = 'Emergency' 
                              AND status IN ('Pending', 'Donors Notified', 'Donor Response Received') 
                              ORDER BY created_at DESC LIMIT 2");
    $stmtEmerg->execute([$bloodGroup]);
    $emergencyAlerts = $stmtEmerg->fetchAll();

    // Recent matching requests
    $stmtRecent = $db->prepare("SELECT r.*, 
                               (SELECT response_status FROM request_responses rr WHERE rr.request_id = r.request_id AND rr.donor_id = ?) as my_response
                               FROM blood_requests r
                               WHERE r.blood_group = ? AND r.status != 'Cancelled'
                               ORDER BY (r.urgency = 'Emergency') DESC, r.created_at DESC LIMIT 6");
    $stmtRecent->execute([$donorId, $bloodGroup]);
    $recentRequests = $stmtRecent->fetchAll();

    // Recent notifications
    $stmtNotif = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmtNotif->execute([$userId]);
    $recentNotifications = $stmtNotif->fetchAll();

} catch (Exception $e) {
    // Graceful fallback
}

// Blood compatibility mapping
$compat = getBloodCompatibility($bloodGroup);

// Donation eligibility calculation (84 days interval for whole blood)
$isEligibleNow = true;
$nextEligibleDate = null;
if (!empty($donor['last_donation_date'])) {
    $lastDonationTime = strtotime($donor['last_donation_date']);
    $nextEligibleTime = strtotime('+84 days', $lastDonationTime);
    if ($nextEligibleTime > time()) {
        $isEligibleNow = false;
        $nextEligibleDate = date('d M Y', $nextEligibleTime);
    }
}

// Profile completeness calculation using core helper
$completenessData = calculateProfileCompleteness($donor);
$profilePercent = $completenessData['percentage'];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/donor_navbar.php';
?>

<div class="bg-light min-vh-100 py-4">
    <div class="container">
        <!-- Flash Message Alerts -->
        <?php if (!empty($_SESSION['flash_msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4 shadow-sm border-0 rounded-4 p-3" role="alert">
                <i class="fa-solid fa-circle-check fs-4 me-3 text-success"></i>
                <div class="flex-grow-1">
                    <strong class="d-block text-dark">Notification</strong>
                    <span class="text-secondary"><?= htmlspecialchars($_SESSION['flash_msg']) ?></span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash_msg']); ?>
        <?php endif; ?>

        <!-- Welcome Banner Card -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #0B192C 0%, #1E3A5F 100%);">
            <div class="card-body p-4 p-md-5 text-white">
                <div class="row g-4 align-items-center justify-content-between">
                    <div class="col-lg-7">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="blood-badge shadow" style="width: 68px; height: 68px; font-size: 1.8rem; background: linear-gradient(135deg, #DC2626, #991B1B); border: 3px solid rgba(255,255,255,0.2);">
                                <?= htmlspecialchars($donor['blood_group']) ?>
                            </div>
                            <div>
                                <h2 class="fw-bold mb-1 text-white">Hello, <?= htmlspecialchars($donor['full_name']) ?>!</h2>
                                <p class="text-white-50 mb-0 small">
                                    <i class="fa-solid fa-location-dot text-danger me-1"></i>
                                    <?= htmlspecialchars($donor['city']) ?>, <?= htmlspecialchars($donor['state']) ?>
                                    <?php if (!empty($donor['area'])): ?> &bull; <?= htmlspecialchars($donor['area']) ?><?php endif; ?>
                                    &bull; Donor ID: <strong>#DN-<?= sprintf('%05d', $donor['donor_id']) ?></strong>
                                </p>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <?= getVerificationBadge($donor['verification_status']) ?>
                            <span class="badge bg-white bg-opacity-20 text-white border border-white border-opacity-25 py-2 px-3">
                                <i class="fa-solid <?= $donor['hide_phone'] ? 'fa-phone-slash' : 'fa-phone' ?> me-1"></i>
                                <?= $donor['hide_phone'] ? 'Phone Masked (Private)' : 'Phone Visible' ?>
                            </span>
                            <span class="badge bg-white bg-opacity-10 text-white-50 py-2 px-3">
                                <i class="fa-regular fa-calendar me-1"></i> Joined <?= date('M Y', strtotime($donor['member_since'])) ?>
                            </span>
                        </div>
                    </div>

                    <!-- 1-Click Availability Selector -->
                    <div class="col-lg-5">
                        <div class="p-3 rounded-4 bg-white bg-opacity-10 border border-white border-opacity-15">
                            <label class="small text-white-50 fw-semibold d-block mb-2">
                                <i class="fa-solid fa-tower-broadcast text-danger me-1"></i> Quick Availability Status:
                            </label>
                            <form method="POST" action="" class="d-flex gap-2">
                                <button type="submit" name="set_availability" value="Available" 
                                        class="btn btn-sm flex-fill py-2 fw-semibold <?= $donor['availability'] === 'Available' ? 'btn-success text-white shadow' : 'btn-outline-light text-white' ?>">
                                    🟢 Available
                                </button>
                                <button type="submit" name="set_availability" value="Temporarily Unavailable" 
                                        class="btn btn-sm flex-fill py-2 fw-semibold <?= $donor['availability'] === 'Temporarily Unavailable' ? 'btn-warning text-dark shadow' : 'btn-outline-light text-white' ?>">
                                    🟡 Temp Busy
                                </button>
                                <button type="submit" name="set_availability" value="Not Accepting Requests" 
                                        class="btn btn-sm flex-fill py-2 fw-semibold <?= $donor['availability'] === 'Not Accepting Requests' ? 'btn-secondary text-white shadow' : 'btn-outline-light text-white' ?>">
                                    ⚪ Inactive
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Sub-bar: Donation Readiness & Profile Progress -->
                <div class="row g-3 mt-3 pt-3 border-top border-white border-opacity-15 align-items-center">
                    <div class="col-md-7">
                        <div class="d-flex align-items-center gap-2 small text-white-50">
                            <i class="fa-solid fa-heart-pulse text-danger fs-5"></i>
                            <div>
                                Whole-Blood Status: 
                                <?php if ($isEligibleNow): ?>
                                    <strong class="text-white"><i class="fa-solid fa-circle-check text-success me-1"></i> Eligible to donate today</strong>
                                <?php else: ?>
                                    <strong class="text-warning"><i class="fa-solid fa-clock text-warning me-1"></i> Next eligible on <?= $nextEligibleDate ?></strong>
                                <?php endif; ?>
                                &bull; Last Donation: <span class="text-white"><?= !empty($donor['last_donation_date']) ? date('d M Y', strtotime($donor['last_donation_date'])) : 'None recorded' ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="d-flex justify-content-between align-items-center mb-1 small">
                            <span class="text-white-50">Profile Completeness: <strong class="text-white"><?= $completenessData['label'] ?></strong></span>
                            <span class="fw-bold text-white"><?= $profilePercent ?>%</span>
                        </div>
                        <div class="progress" style="height: 6px; background-color: rgba(255,255,255,0.2);">
                            <div class="progress-bar bg-danger" style="width: <?= $profilePercent ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3 Independent Status Parameters Card (Requirement 4) -->
        <div class="card border-0 shadow-sm rounded-4 bg-white mb-4 overflow-hidden">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-id-card-clip text-danger me-2"></i>Donor Status Overview</h5>
                    <small class="text-muted">Three independent status parameters track your donor standing.</small>
                </div>
                <span class="badge bg-light text-muted border px-3 py-2"><i class="fa-solid fa-shield-halved text-primary me-1"></i>Independent Parameters</span>
            </div>
            <div class="card-body p-4">
                <div class="row g-4 align-items-start">
                    <!-- Parameter 1: Profile Verification -->
                    <div class="col-md-4 border-end-md">
                        <small class="text-muted text-uppercase fw-bold d-block mb-2" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-user-check text-primary me-1"></i> 1. Profile Verification
                        </small>
                        <div class="mb-2">
                            <?= getVerificationBadge($donor['verification_status']) ?>
                        </div>
                        <p class="small text-muted mb-0" style="font-size: 0.82rem;">
                            <?php if ($donor['verification_status'] === 'verified'): ?>
                                Identity and registration details reviewed and approved by administrators.
                            <?php elseif ($donor['verification_status'] === 'pending'): ?>
                                Account details submitted and awaiting administrative review.
                            <?php elseif ($donor['verification_status'] === 'rejected'): ?>
                                Verification review unsuccessful. Please check profile details.
                            <?php else: ?>
                                Newly registered account awaiting profile submission.
                            <?php endif; ?>
                        </p>
                    </div>

                    <!-- Parameter 2: Availability -->
                    <div class="col-md-4 border-end-md">
                        <small class="text-muted text-uppercase fw-bold d-block mb-2" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-signal text-success me-1"></i> 2. Availability Status
                        </small>
                        <div class="mb-2">
                            <?php if ($donor['availability'] === 'Available'): ?>
                                <span class="badge bg-success text-white"><i class="fa-solid fa-circle-check me-1"></i>Available</span>
                            <?php elseif ($donor['availability'] === 'Temporarily Unavailable'): ?>
                                <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Temporarily Unavailable</span>
                            <?php else: ?>
                                <span class="badge bg-secondary text-white"><i class="fa-solid fa-ban me-1"></i>Not Accepting Requests</span>
                            <?php endif; ?>
                        </div>
                        <p class="small text-muted mb-0" style="font-size: 0.82rem;">
                            Controls whether the search engine prioritizes you when requesters search for <?= htmlspecialchars($donor['blood_group']) ?> blood in <?= htmlspecialchars($donor['city']) ?>.
                        </p>
                    </div>

                    <!-- Parameter 3: Medical Eligibility -->
                    <div class="col-md-4">
                        <small class="text-muted text-uppercase fw-bold d-block mb-2" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-stethoscope text-danger me-1"></i> 3. Clinical Eligibility
                        </small>
                        <div class="mb-2">
                            <?= getMedicalEligibilityBadge() ?>
                        </div>
                        <p class="small text-muted mb-0" style="font-size: 0.82rem;">
                            <?php if (!empty($donor['screening_completed'])): ?>
                                <span class="text-success fw-semibold"><i class="fa-solid fa-clipboard-check me-1"></i>Screening Acknowledgment Completed</span>.
                            <?php else: ?>
                                <span class="text-warning fw-semibold"><i class="fa-solid fa-clipboard-question me-1"></i>Screening Pending</span>.
                            <?php endif; ?>
                            Final medical fitness is determined on-site by qualified healthcare staff at the blood bank.
                        </p>
                    </div>
                </div>

                <div class="alert alert-light border border-secondary-subtle mt-4 mb-0 py-2 px-3 small text-muted d-flex align-items-center gap-2 rounded-3">
                    <i class="fa-solid fa-triangle-exclamation text-warning fs-5 flex-shrink-0"></i>
                    <div>
                        <strong>Healthcare Policy Notice:</strong> Profile verification does not guarantee eligibility to donate. Final clinical assessment is performed by qualified medical personnel at the hospital or licensed blood bank.
                    </div>
                </div>
            </div>
        </div>

        <!-- Emergency Alert Banner if any critical requests match blood group -->
        <?php if (!empty($emergencyAlerts)): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-4 p-4 mb-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="fa-solid fa-triangle-exclamation fa-lg text-danger"></i>
                    <h5 class="fw-bold mb-0 text-danger">Immediate Emergency Blood Need for <?= htmlspecialchars($donor['blood_group']) ?></h5>
                </div>
                <p class="small text-danger mb-3">Critical patients in urgent care require immediate transfusions. Please review details below if you are currently able to respond.</p>
                <div class="row g-3">
                    <?php foreach ($emergencyAlerts as $em): ?>
                        <div class="col-md-6">
                            <div class="bg-white p-3 rounded-3 border border-danger-subtle shadow-sm h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <strong class="text-dark fs-6"><?= htmlspecialchars($em['patient_name']) ?></strong>
                                        <span class="badge bg-danger"><?= (int)$em['units_required'] ?> Unit(s)</span>
                                    </div>
                                    <small class="text-muted d-block"><i class="fa-solid fa-hospital me-1 text-danger"></i><?= htmlspecialchars($em['hospital_name']) ?></small>
                                    <small class="text-muted d-block"><i class="fa-solid fa-location-dot me-1 text-primary"></i><?= htmlspecialchars($em['city']) ?>, <?= htmlspecialchars($em['state']) ?></small>
                                </div>
                                <div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center">
                                    <small class="text-danger fw-semibold"><i class="fa-solid fa-bolt me-1"></i>Emergency Priority</small>
                                    <a href="<?= BASE_URL ?>/donor/request_details.php?id=<?= $em['request_id'] ?>" class="btn btn-danger btn-sm">
                                        View & Respond
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Key Summary Metrics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 d-flex flex-row justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Matching Requests</div>
                        <div class="fs-3 fw-bold text-dark"><?= number_format($totalRequests) ?></div>
                        <small class="text-danger" style="font-size: 0.72rem;">Blood Group <?= htmlspecialchars($donor['blood_group']) ?></small>
                    </div>
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3">
                        <i class="fa-solid fa-bullhorn fa-lg"></i>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 d-flex flex-row justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Accepted Pledges</div>
                        <div class="fs-3 fw-bold text-success"><?= number_format($acceptedRequests) ?></div>
                        <small class="text-muted" style="font-size: 0.72rem;">Willingness confirmed</small>
                    </div>
                    <div class="bg-success bg-opacity-10 text-success rounded-circle p-3">
                        <i class="fa-solid fa-handshake fa-lg"></i>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 d-flex flex-row justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Donations Completed</div>
                        <div class="fs-3 fw-bold text-danger"><?= number_format($totalDonations) ?></div>
                        <small class="text-muted" style="font-size: 0.72rem;">Verified medical records</small>
                    </div>
                    <div class="bg-danger bg-opacity-10 text-danger rounded-circle p-3">
                        <i class="fa-solid fa-droplet fa-lg"></i>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 d-flex flex-row justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Earned Badges</div>
                        <div class="fs-3 fw-bold text-warning"><?= !empty($donor['badges']) ? count(explode(',', $donor['badges'])) : 1 ?></div>
                        <small class="text-muted" style="font-size: 0.72rem;">Community honors</small>
                    </div>
                    <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-3">
                        <i class="fa-solid fa-medal fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 1: MATCHING BLOOD REQUESTS (FULL WIDTH) -->
        <div class="card border-0 shadow-sm rounded-4 bg-white mb-4 overflow-hidden">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-hand-holding-droplet text-danger me-2"></i>Matching Blood Requests
                    </h5>
                    <small class="text-muted">Live patient & hospital requests matching blood group <strong><?= htmlspecialchars($donor['blood_group']) ?></strong></small>
                </div>
                <a href="<?= BASE_URL ?>/donor/requests.php" class="btn btn-outline-danger btn-sm">
                    View All (<?= $totalRequests ?>)
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>Patient / Hospital</th>
                            <th>Location</th>
                            <th>Units & Urgency</th>
                            <th>My Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php if (empty($recentRequests)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-circle-check text-success fa-3x mb-3 opacity-50 d-block"></i>
                                    <h6 class="fw-bold text-dark mb-1">No Open Requests for <?= htmlspecialchars($donor['blood_group']) ?> Right Now</h6>
                                    <p class="small text-muted mb-3" style="max-width: 440px; margin: 0 auto;">
                                        There are currently no active blood requests for your group. As soon as a hospital or patient posts an urgent requirement, an alert will appear here.
                                    </p>
                                    <a href="<?= BASE_URL ?>/find_donors.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="fa-solid fa-magnifying-glass me-1"></i> Browse Donor Directory
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentRequests as $req): ?>
                                <tr class="<?= $req['urgency'] === 'Emergency' ? 'table-danger bg-opacity-25' : '' ?>">
                                    <td>
                                        <strong class="d-block text-dark"><?= htmlspecialchars($req['patient_name']) ?></strong>
                                        <small class="text-muted"><i class="fa-solid fa-hospital text-danger me-1"></i><?= htmlspecialchars($req['hospital_name']) ?></small>
                                    </td>
                                    <td>
                                        <span class="text-dark d-block fw-semibold"><?= htmlspecialchars($req['city']) ?></span>
                                        <small class="text-muted"><?= htmlspecialchars($req['state']) ?></small>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark me-1"><?= (int)$req['units_required'] ?> Unit(s)</span>
                                        <?= getUrgencyBadge($req['urgency']) ?>
                                    </td>
                                    <td>
                                        <?php if ($req['my_response'] === 'Accepted'): ?>
                                            <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Accepted</span>
                                        <?php elseif ($req['my_response'] === 'Rejected'): ?>
                                            <span class="badge bg-secondary">Declined</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark border">Pending Action</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>/donor/request_details.php?id=<?= $req['request_id'] ?>" class="btn btn-sm btn-outline-danger py-1 px-3">
                                            <?= $req['my_response'] ? 'Details' : 'View & Respond' ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION 2: MY BADGES & HONORS (DIRECTLY BELOW MATCHING REQUESTS) -->
        <div class="card border-0 shadow-sm rounded-4 bg-white mb-4 overflow-hidden" id="badgesSection">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-award text-warning me-2"></i>My Badges & Honors
                    </h5>
                    <small class="text-muted">Badges unlocked through voluntary contributions, profile verification, and donations.</small>
                </div>
                <a href="<?= BASE_URL ?>/donor/donation_history.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-clock-rotate-left me-1"></i> Donation History
                </a>
            </div>

            <div class="card-body p-4">
                <div class="row g-3">
                    <!-- Badge 1: Verified Donor -->
                    <div class="col-md-6 col-lg-3">
                        <div class="p-3 border rounded-4 text-center h-100 <?= $donor['verification_status'] === 'verified' ? 'bg-light border-success-subtle' : 'opacity-75 bg-light' ?>">
                            <div class="rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px; background: rgba(16, 185, 129, 0.15);">
                                <i class="fa-solid fa-certificate text-success fs-3"></i>
                            </div>
                            <strong class="d-block text-dark small mb-1">Verified Donor</strong>
                            <small class="text-muted d-block" style="font-size: 0.75rem;">
                                <?= $donor['verification_status'] === 'verified' ? 'Verified by LifeLink Admin' : 'Pending profile verification' ?>
                            </small>
                            <span class="badge <?= $donor['verification_status'] === 'verified' ? 'bg-success' : 'bg-warning text-dark' ?> mt-2" style="font-size: 0.68rem;">
                                <?= $donor['verification_status'] === 'verified' ? 'Unlocked' : 'In Progress' ?>
                            </span>
                        </div>
                    </div>

                    <!-- Badge 2: Community Contributor -->
                    <div class="col-md-6 col-lg-3">
                        <div class="p-3 border rounded-4 text-center h-100 bg-light border-warning-subtle">
                            <div class="rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px; background: rgba(245, 158, 11, 0.15);">
                                <i class="fa-solid fa-medal text-warning fs-3"></i>
                            </div>
                            <strong class="d-block text-dark small mb-1">Community Contributor</strong>
                            <small class="text-muted d-block" style="font-size: 0.75rem;">Registered voluntary donor ready to respond</small>
                            <span class="badge bg-warning text-dark mt-2" style="font-size: 0.68rem;">Unlocked</span>
                        </div>
                    </div>

                    <!-- Badge 3: First Donation Hero -->
                    <div class="col-md-6 col-lg-3">
                        <div class="p-3 border rounded-4 text-center h-100 <?= $totalDonations >= 1 ? 'bg-light border-danger-subtle' : 'opacity-50 bg-light' ?>">
                            <div class="rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px; background: rgba(220, 38, 38, 0.15);">
                                <i class="fa-solid fa-droplet text-danger fs-3"></i>
                            </div>
                            <strong class="d-block text-dark small mb-1">First Donation Hero</strong>
                            <small class="text-muted d-block" style="font-size: 0.75rem;">
                                <?= $totalDonations >= 1 ? 'First donation confirmed' : 'Complete 1 verified donation to unlock' ?>
                            </small>
                            <span class="badge <?= $totalDonations >= 1 ? 'bg-danger' : 'bg-secondary' ?> mt-2" style="font-size: 0.68rem;">
                                <?= $totalDonations >= 1 ? 'Unlocked' : 'Locked' ?>
                            </span>
                        </div>
                    </div>

                    <!-- Badge 4: Life Saver Champion -->
                    <div class="col-md-6 col-lg-3">
                        <div class="p-3 border rounded-4 text-center h-100 <?= $totalDonations >= 3 ? 'bg-light border-primary-subtle' : 'opacity-50 bg-light' ?>">
                            <div class="rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px; background: rgba(2, 132, 199, 0.15);">
                                <i class="fa-solid fa-trophy text-primary fs-3"></i>
                            </div>
                            <strong class="d-block text-dark small mb-1">Life Saver Champion</strong>
                            <small class="text-muted d-block" style="font-size: 0.75rem;">
                                <?= $totalDonations >= 3 ? '3+ verified donations completed' : 'Complete 3 donations to unlock' ?>
                            </small>
                            <span class="badge <?= $totalDonations >= 3 ? 'bg-primary' : 'bg-secondary' ?> mt-2" style="font-size: 0.68rem;">
                                <?= $totalDonations >= 3 ? 'Unlocked' : 'Locked' ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Blood Compatibility Sub-Panel -->
                <div class="p-3 bg-light rounded-4 border mt-3">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-2 text-center text-md-start">
                            <span class="badge bg-danger rounded-pill px-3 py-2 fw-bold fs-6"><?= htmlspecialchars($donor['blood_group']) ?></span>
                            <small class="text-muted d-block mt-1">Compatibility</small>
                        </div>
                        <div class="col-md-5">
                            <small class="text-muted fw-bold text-uppercase d-block mb-1" style="font-size: 0.72rem;">You Can Donate Red Cells To:</small>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach ($compat['give'] as $g): ?>
                                    <span class="badge bg-white text-danger border fw-bold px-2 py-1"><?= htmlspecialchars($g) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <small class="text-muted fw-bold text-uppercase d-block mb-1" style="font-size: 0.72rem;">You Can Receive Blood From:</small>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach ($compat['receive'] as $r): ?>
                                    <span class="badge bg-white text-primary border fw-bold px-2 py-1"><?= htmlspecialchars($r) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 3: RECENT ALERTS & NOTIFICATIONS (DIRECTLY BELOW BADGES) -->
        <div class="card border-0 shadow-sm rounded-4 bg-white mb-4 overflow-hidden" id="alertsSection">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-bell text-primary me-2"></i>Recent Alerts & Updates
                    </h5>
                    <small class="text-muted">Important updates on blood requests, verification decisions, and donor notifications.</small>
                </div>
                <a href="<?= BASE_URL ?>/donor/notifications.php" class="btn btn-outline-secondary btn-sm">
                    View All Notifications
                </a>
            </div>

            <div class="card-body p-0">
                <?php if (empty($recentNotifications)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa-regular fa-bell-slash fa-3x mb-3 text-secondary opacity-50 d-block"></i>
                        <h6 class="fw-bold text-dark mb-1">No Notifications Yet</h6>
                        <p class="small text-muted mb-0">You're all caught up! New request matches and system updates will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentNotifications as $notif): ?>
                            <div class="list-group-item p-3 d-flex align-items-start gap-3 <?= !$notif['is_read'] ? 'bg-light' : '' ?>">
                                <div class="rounded-circle p-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px; background: <?= $notif['notification_type'] === 'emergency' ? 'rgba(220, 38, 38, 0.15)' : ($notif['notification_type'] === 'response' ? 'rgba(16, 185, 129, 0.15)' : 'rgba(2, 132, 199, 0.15)') ?>;">
                                    <i class="fa-solid <?= $notif['notification_type'] === 'emergency' ? 'fa-bolt text-danger' : ($notif['notification_type'] === 'response' ? 'fa-check text-success' : 'fa-bell text-primary') ?>"></i>
                                </div>
                                <div class="flex-grow-1 overflow-hidden">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <strong class="text-dark small"><?= htmlspecialchars($notif['title']) ?></strong>
                                        <small class="text-muted ms-2 text-nowrap" style="font-size: 0.72rem;"><?= timeAgo($notif['created_at']) ?></small>
                                    </div>
                                    <p class="small text-secondary mb-1"><?= htmlspecialchars($notif['message']) ?></p>
                                    <?php if (!empty($notif['related_request_id'])): ?>
                                        <a href="<?= BASE_URL ?>/donor/request_details.php?id=<?= $notif['related_request_id'] ?>" class="btn btn-sm btn-outline-danger py-0 px-2 mt-1" style="font-size: 0.72rem;">
                                            <i class="fa-solid fa-arrow-right me-1"></i> Open Request
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
