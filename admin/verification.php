<?php
/**
 * LifeLink - Dedicated Donor Verification Panel
 * Allows Administrator to review:
 * - Donor name, Blood group, City, Registration date
 * - Verification status, Screening acknowledgment, Profile completeness
 * - Actions: Verify profile, Keep pending, Reject verification, Add verification notes, Notify donor
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
$currentUser = getCurrentUser();
$adminId = $currentUser['user_id'];
$pageTitle = "Donor Verification Panel — LifeLink";

// Handle Actions (Verify, Keep Pending, Reject, Save Notes, Notify)
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
            $_SESSION['flash_msg'] = "Donor profile verified successfully!";
        } elseif ($action === 'keep_pending') {
            $db->prepare("UPDATE users SET verification_status = 'pending' WHERE user_id = ?")->execute([$targetUserId]);
            if ($notes !== '') {
                $db->prepare("UPDATE donors SET verification_notes = ? WHERE donor_id = ?")->execute([$notes, $donorId]);
            }
            logAdminAction($db, $adminId, "Donor Kept Pending", "Kept User #{$targetUserId} in pending verification. Notes: {$notes}");
            createNotification($db, $targetUserId, "Verification Review Update", "Your donor profile review is currently pending further information or review. " . ($notes ? "Notes: {$notes}" : ""), "system");
            $_SESSION['flash_msg'] = "Donor marked as Pending Verification.";
        } elseif ($action === 'reject') {
            $db->prepare("UPDATE users SET verification_status = 'rejected' WHERE user_id = ?")->execute([$targetUserId]);
            if ($notes !== '') {
                $db->prepare("UPDATE donors SET verification_notes = ? WHERE donor_id = ?")->execute([$notes, $donorId]);
            }
            logAdminAction($db, $adminId, "Donor Verification Rejected", "Rejected User #{$targetUserId}. Reason: {$notes}");
            createNotification($db, $targetUserId, "Verification Update", "Your donor profile verification could not be approved at this time. Reason: " . ($notes ?: "Information provided could not be verified."), "system");
            $_SESSION['flash_msg'] = "Donor verification rejected.";
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
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Action failed: " . $e->getMessage();
    }
    header("Location: " . BASE_URL . "/admin/verification.php");
    exit();
}

// Filters & Search
$statusFilter = trim($_GET['status'] ?? '');
$bloodFilter = trim($_GET['blood_group'] ?? '');
$search = trim($_GET['search'] ?? '');

$sql = "SELECT d.*, u.full_name, u.email, u.phone, u.verification_status, u.created_at as registration_date 
        FROM donors d 
        JOIN users u ON d.user_id = u.user_id 
        WHERE 1=1";
$params = [];

if (!empty($statusFilter)) {
    $sql .= " AND u.verification_status = ?";
    $params[] = $statusFilter;
}
if (!empty($bloodFilter)) {
    $sql .= " AND d.blood_group = ?";
    $params[] = $bloodFilter;
}
if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR d.city LIKE ? OR d.pin_code LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY (u.verification_status = 'pending') DESC, (u.verification_status = 'unverified') DESC, u.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$donors = $stmt->fetchAll();

// Counts for summary pills
$pendingCount = (int)$db->query("SELECT COUNT(*) FROM users u JOIN donors d ON u.user_id = d.user_id WHERE u.verification_status = 'pending'")->fetchColumn();
$verifiedCount = (int)$db->query("SELECT COUNT(*) FROM users u JOIN donors d ON u.user_id = d.user_id WHERE u.verification_status = 'verified'")->fetchColumn();
$unverifiedCount = (int)$db->query("SELECT COUNT(*) FROM users u JOIN donors d ON u.user_id = d.user_id WHERE u.verification_status = 'unverified'")->fetchColumn();
$rejectedCount = (int)$db->query("SELECT COUNT(*) FROM users u JOIN donors d ON u.user_id = d.user_id WHERE u.verification_status = 'rejected'")->fetchColumn();

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
                    <h3 class="fw-bold mb-1 text-dark">
                        <i class="fa-solid fa-user-shield text-danger me-2"></i>Dedicated Donor Verification Panel
                    </h3>
                    <p class="text-muted small mb-0">Verify donor credentials, inspect screening acknowledgments, and manage audit notes.</p>
                </div>
                <div class="d-flex gap-2 mt-2 mt-sm-0">
                    <a href="<?= BASE_URL ?>/admin/donors.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-address-book me-1"></i> Donor Directory
                    </a>
                </div>
            </div>

            <!-- Status Pill Counters -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-md-3">
                    <a href="?status=pending" class="text-decoration-none">
                        <div class="p-3 bg-white border rounded-4 shadow-sm d-flex justify-content-between align-items-center <?= $statusFilter === 'pending' ? 'border-warning border-2' : '' ?>">
                            <div>
                                <small class="text-muted d-block fw-semibold">Pending Review</small>
                                <strong class="fs-4 text-warning"><?= $pendingCount ?></strong>
                            </div>
                            <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-3">
                                <i class="fa-solid fa-clock fa-lg"></i>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-sm-6 col-md-3">
                    <a href="?status=verified" class="text-decoration-none">
                        <div class="p-3 bg-white border rounded-4 shadow-sm d-flex justify-content-between align-items-center <?= $statusFilter === 'verified' ? 'border-success border-2' : '' ?>">
                            <div>
                                <small class="text-muted d-block fw-semibold">Verified Profiles</small>
                                <strong class="fs-4 text-success"><?= $verifiedCount ?></strong>
                            </div>
                            <div class="bg-success bg-opacity-10 text-success rounded-circle p-3">
                                <i class="fa-solid fa-certificate fa-lg"></i>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-sm-6 col-md-3">
                    <a href="?status=unverified" class="text-decoration-none">
                        <div class="p-3 bg-white border rounded-4 shadow-sm d-flex justify-content-between align-items-center <?= $statusFilter === 'unverified' ? 'border-secondary border-2' : '' ?>">
                            <div>
                                <small class="text-muted d-block fw-semibold">Unverified</small>
                                <strong class="fs-4 text-secondary"><?= $unverifiedCount ?></strong>
                            </div>
                            <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle p-3">
                                <i class="fa-solid fa-circle-question fa-lg"></i>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-sm-6 col-md-3">
                    <a href="?status=rejected" class="text-decoration-none">
                        <div class="p-3 bg-white border rounded-4 shadow-sm d-flex justify-content-between align-items-center <?= $statusFilter === 'rejected' ? 'border-danger border-2' : '' ?>">
                            <div>
                                <small class="text-muted d-block fw-semibold">Rejected</small>
                                <strong class="fs-4 text-danger"><?= $rejectedCount ?></strong>
                            </div>
                            <div class="bg-danger bg-opacity-10 text-danger rounded-circle p-3">
                                <i class="fa-solid fa-circle-xmark fa-lg"></i>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Filter Bar Card -->
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-4">
                <form method="GET" action="" class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search by name, email, city, or PIN..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Verification Statuses</option>
                            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending Review (<?= $pendingCount ?>)</option>
                            <option value="verified" <?= $statusFilter === 'verified' ? 'selected' : '' ?>>Verified Profiles</option>
                            <option value="unverified" <?= $statusFilter === 'unverified' ? 'selected' : '' ?>>Unverified</option>
                            <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="blood_group" class="form-select form-select-sm">
                            <option value="">All Blood Groups</option>
                            <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                                <option value="<?= $bg ?>" <?= $bloodFilter === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-lifelink-red btn-sm flex-grow-1">Filter</button>
                        <a href="<?= BASE_URL ?>/admin/verification.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Verification Table Card -->
            <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
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
                            <?php if (empty($donors)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-user-check fa-3x text-secondary opacity-50 mb-3 d-block"></i>
                                        No donor registrations found matching your filter criteria.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($donors as $d): ?>
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
                                            <small class="text-muted"><?= htmlspecialchars($d['area'] ?: $d['state']) ?> (<?= htmlspecialchars($d['pin_code']) ?>)</small>
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
                                                    <form method="POST" action="" class="d-inline" onsubmit="return confirmAction('Reject verification for this donor profile?');">
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
        </div>
    </div>
</div>

<!-- MODALS -->
<?php if (!empty($donors)): ?>
    <?php foreach ($donors as $d): ?>
        <?php $answers = !empty($d['screening_answers']) ? json_decode($d['screening_answers'], true) : []; ?>

        <!-- Screening Answers Modal -->
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

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
