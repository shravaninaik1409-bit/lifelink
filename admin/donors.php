<?php
/**
 * LifeLink - Admin Donor Directory & Verification Management
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
$currentUser = getCurrentUser();
$adminId = $currentUser['user_id'];
$pageTitle = "Donor Directory & Verification — LifeLink";

// Handle Actions via secure POST + CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['id'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        $_SESSION['flash_error'] = "Security validation failed. Please refresh and try again.";
        header("Location: " . BASE_URL . "/admin/donors.php");
        exit();
    }

    $donorId = (int)$_POST['id'];
    $action = $_POST['action'];

    try {
        $userStmt = $db->prepare("SELECT d.user_id, u.full_name, d.blood_group FROM donors d JOIN users u ON d.user_id = u.user_id WHERE d.donor_id = ?");
        $userStmt->execute([$donorId]);
        $donorInfo = $userStmt->fetch();

        if ($donorInfo) {
            $uId = $donorInfo['user_id'];

            if ($action === 'verify') {
                $db->prepare("UPDATE users SET verification_status = 'verified' WHERE user_id = ?")->execute([$uId]);
                logAdminAction($db, $adminId, "Donor Profile Verified", "Verified donor profile for {$donorInfo['full_name']} (Donor #{$donorId})");
                createNotification($db, $uId, "Donor Profile Verified!", "Congratulations! Your LifeLink donor profile and credentials have been verified by the administration. A 'Verified Profile' badge is now displayed on your account.", "system");
                $_SESSION['flash_msg'] = "Donor profile for {$donorInfo['full_name']} marked as Verified Profile.";
            } elseif ($action === 'reject') {
                $db->prepare("UPDATE users SET verification_status = 'rejected' WHERE user_id = ?")->execute([$uId]);
                logAdminAction($db, $adminId, "Donor Verification Rejected", "Rejected verification for Donor #{$donorId}");
                createNotification($db, $uId, "Verification Review Update", "Your donor profile verification could not be approved at this time. Please review your details and contact administration if you need assistance.", "system");
                $_SESSION['flash_msg'] = "Donor profile marked as Verification Rejected.";
            } elseif ($action === 'mark_pending') {
                $db->prepare("UPDATE users SET verification_status = 'pending' WHERE user_id = ?")->execute([$uId]);
                logAdminAction($db, $adminId, "Donor Verification Pending", "Reset Donor #{$donorId} to pending verification");
                $_SESSION['flash_msg'] = "Donor profile status reset to Pending Verification.";
            } elseif ($action === 'toggle_avail') {
                $stmt = $db->prepare("SELECT availability FROM donors WHERE donor_id = ?");
                $stmt->execute([$donorId]);
                $current = $stmt->fetchColumn();
                $new = ($current === 'Available') ? 'Temporarily Unavailable' : 'Available';

                $db->prepare("UPDATE donors SET availability = ? WHERE donor_id = ?")->execute([$new, $donorId]);
                logAdminAction($db, $adminId, "Availability Updated", "Changed Donor #{$donorId} to {$new}");
                $_SESSION['flash_msg'] = "Availability updated to {$new}.";
            }
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Action error: " . $e->getMessage();
    }
    header("Location: " . BASE_URL . "/admin/donors.php");
    exit();
}

$bloodGroup = trim($_GET['blood_group'] ?? '');
$city = trim($_GET['city'] ?? '');
$availability = trim($_GET['availability'] ?? '');
$verificationFilter = trim($_GET['verification'] ?? '');

$sql = "SELECT d.*, u.full_name, u.email, u.phone, u.verification_status, u.account_status,
        (SELECT COUNT(*) FROM donations dn WHERE dn.donor_id = d.donor_id AND dn.verification_status IN ('Verified', 'Approved')) as total_donations
        FROM donors d
        JOIN users u ON d.user_id = u.user_id
        WHERE 1=1";
$params = [];

if (!empty($bloodGroup)) {
    $sql .= " AND d.blood_group = ?";
    $params[] = $bloodGroup;
}
if (!empty($city)) {
    $sql .= " AND d.city LIKE ?";
    $params[] = "%{$city}%";
}
if (!empty($availability)) {
    $sql .= " AND d.availability = ?";
    $params[] = $availability;
}
if (!empty($verificationFilter)) {
    $sql .= " AND u.verification_status = ?";
    $params[] = $verificationFilter;
}

$sql .= " ORDER BY (u.verification_status = 'pending') DESC, (d.availability = 'Available') DESC, d.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$donors = $stmt->fetchAll();

// Count pending verifications
$pendingCount = 0;
try {
    $pendingCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'donor' AND verification_status = 'pending'")->fetchColumn();
} catch (Exception $e) {}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout">
    <?php require_once __DIR__ . '/../includes/dashboard_sidebar.php'; ?>

    <div class="dashboard-main">
        <?php require_once __DIR__ . '/../includes/dashboard_topbar.php'; ?>

        <div class="dashboard-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark">Donor Directory & Verification</h3>
                    <p class="text-muted small mb-0">
                        Review submitted identity and contact information to grant verified profile status.
                    </p>
                </div>
                <div>
                    <?php if ($pendingCount > 0): ?>
                        <a href="?verification=pending" class="btn btn-warning btn-sm text-dark fw-bold">
                            <i class="fa-solid fa-clock me-1"></i> <?= $pendingCount ?> Pending Review
                        </a>
                    <?php else: ?>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2">
                            <i class="fa-solid fa-circle-check me-1"></i> All Profiles Reviewed
                        </span>
                    <?php endif; ?>
                </div>
            </div>

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

            <!-- Filter Controls -->
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-4">
                <form method="GET" action="" class="row g-2 align-items-center">
                    <div class="col-md-3">
                        <select name="verification" class="form-select form-select-sm">
                            <option value="">All Verification Levels</option>
                            <option value="pending" <?= $verificationFilter === 'pending' ? 'selected' : '' ?>>Pending Verification</option>
                            <option value="verified" <?= $verificationFilter === 'verified' ? 'selected' : '' ?>>Verified Profile</option>
                            <option value="unverified" <?= $verificationFilter === 'unverified' ? 'selected' : '' ?>>Unverified</option>
                            <option value="rejected" <?= $verificationFilter === 'rejected' ? 'selected' : '' ?>>Verification Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="blood_group" class="form-select form-select-sm">
                            <option value="">All Blood Groups</option>
                            <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                                <option value="<?= $bg ?>" <?= $bloodGroup === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="text" name="city" class="form-control form-control-sm" placeholder="Filter City..." value="<?= htmlspecialchars($city) ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="availability" class="form-select form-select-sm">
                            <option value="">All Availability</option>
                            <option value="Available" <?= $availability === 'Available' ? 'selected' : '' ?>>Available</option>
                            <option value="Temporarily Unavailable" <?= $availability === 'Temporarily Unavailable' ? 'selected' : '' ?>>Temp Unavailable</option>
                            <option value="Not Accepting Requests" <?= $availability === 'Not Accepting Requests' ? 'selected' : '' ?>>Not Accepting</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-lifelink-red btn-sm flex-grow-1">Filter</button>
                        <a href="<?= BASE_URL ?>/admin/donors.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Table Card -->
            <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Donor Profile</th>
                                <th>Blood Group</th>
                                <th>Location</th>
                                <th>Verification Level</th>
                                <th>Availability</th>
                                <th>Screening</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($donors)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">No donors found matching criteria.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($donors as $d): ?>
                                    <tr class="<?= $d['verification_status'] === 'pending' ? 'table-warning table-opacity-10' : '' ?>">
                                        <td>
                                            <strong class="d-block text-dark"><?= htmlspecialchars($d['full_name']) ?></strong>
                                            <small class="text-muted"><?= htmlspecialchars($d['email']) ?> &bull; <?= htmlspecialchars($d['phone']) ?></small>
                                        </td>
                                        <td>
                                            <span class="blood-badge" style="width: 38px; height: 38px; font-size: 0.95rem;"><?= htmlspecialchars($d['blood_group']) ?></span>
                                        </td>
                                        <td>
                                            <small class="d-block text-dark fw-semibold"><?= htmlspecialchars($d['city']) ?>, <?= htmlspecialchars($d['state']) ?></small>
                                            <small class="text-muted"><?= htmlspecialchars($d['area'] ?? '') ?> (PIN: <?= htmlspecialchars($d['pin_code']) ?>)</small>
                                        </td>
                                        <td>
                                            <?= getVerificationBadge($d['verification_status']) ?>
                                        </td>
                                        <td>
                                            <?php if ($d['availability'] === 'Available'): ?>
                                                <span class="badge bg-success">Available</span>
                                            <?php elseif ($d['availability'] === 'Temporarily Unavailable'): ?>
                                                <span class="badge bg-warning text-dark">Temp Unavailable</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Not Accepting</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($d['screening_completed'])): ?>
                                                <button type="button" class="btn btn-light btn-sm border text-success py-0 px-2" data-bs-toggle="modal" data-bs-target="#screeningModal<?= $d['donor_id'] ?>" style="font-size: 0.75rem;">
                                                    <i class="fa-solid fa-clipboard-check me-1"></i>Completed
                                                </button>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border" style="font-size: 0.75rem;">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-light btn-sm border dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    Action
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                    <?php if ($d['verification_status'] !== 'verified'): ?>
                                                        <li>
                                                            <form method="POST" action="" class="m-0 p-0">
                                                                <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                                                <input type="hidden" name="action" value="verify">
                                                                <input type="hidden" name="id" value="<?= $d['donor_id'] ?>">
                                                                <button type="submit" class="dropdown-item small text-success fw-semibold">
                                                                    <i class="fa-solid fa-circle-check me-2"></i>Verify Profile
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if ($d['verification_status'] !== 'rejected'): ?>
                                                        <li>
                                                            <form method="POST" action="" class="m-0 p-0">
                                                                <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                                                <input type="hidden" name="action" value="reject">
                                                                <input type="hidden" name="id" value="<?= $d['donor_id'] ?>">
                                                                <button type="submit" class="dropdown-item small text-danger" onclick="return confirmAction('Reject verification for this donor profile?');">
                                                                    <i class="fa-solid fa-circle-xmark me-2"></i>Reject Verification
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if ($d['verification_status'] !== 'pending'): ?>
                                                        <li>
                                                            <form method="POST" action="" class="m-0 p-0">
                                                                <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                                                <input type="hidden" name="action" value="mark_pending">
                                                                <input type="hidden" name="id" value="<?= $d['donor_id'] ?>">
                                                                <button type="submit" class="dropdown-item small text-warning">
                                                                    <i class="fa-solid fa-clock me-2"></i>Mark as Pending
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form method="POST" action="" class="m-0 p-0">
                                                            <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                                            <input type="hidden" name="action" value="toggle_avail">
                                                            <input type="hidden" name="id" value="<?= $d['donor_id'] ?>">
                                                            <button type="submit" class="dropdown-item small text-secondary">
                                                                <i class="fa-solid fa-arrows-rotate me-2"></i>Toggle Availability
                                                            </button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Modal: Screening Details -->
                                    <?php if (!empty($d['screening_completed'])): ?>
                                        <?php 
                                            $answers = !empty($d['screening_answers']) ? json_decode($d['screening_answers'], true) : [];
                                        ?>
                                        <div class="modal fade" id="screeningModal<?= $d['donor_id'] ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content rounded-4 border-0 shadow">
                                                    <div class="modal-header border-0 pb-0">
                                                        <h5 class="modal-title fw-bold">Preliminary Eligibility Questionnaire</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4">
                                                        <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                                                            <div class="blood-badge" style="width: 44px; height: 44px; font-size: 1.1rem;"><?= htmlspecialchars($d['blood_group']) ?></div>
                                                            <div>
                                                                <strong class="d-block text-dark"><?= htmlspecialchars($d['full_name']) ?></strong>
                                                                <small class="text-muted"><?= htmlspecialchars($d['city']) ?>, <?= htmlspecialchars($d['state']) ?></small>
                                                            </div>
                                                        </div>

                                                        <div class="p-3 bg-light rounded-3 mb-3">
                                                            <ul class="list-unstyled small mb-0">
                                                                <li class="mb-2">
                                                                    <span class="text-muted d-block">1. Donated blood recently (within 3 months)?</span>
                                                                    <strong><?= htmlspecialchars($answers['donated_recently'] ?? 'No') ?></strong>
                                                                </li>
                                                                <li class="mb-2">
                                                                    <span class="text-muted d-block">2. Feeling healthy and well currently?</span>
                                                                    <strong class="text-success"><?= htmlspecialchars($answers['feeling_well'] ?? 'Yes') ?></strong>
                                                                </li>
                                                                <li class="mb-2">
                                                                    <span class="text-muted d-block">3. Advised by healthcare professional not to donate?</span>
                                                                    <strong><?= htmlspecialchars($answers['medical_advice'] ?? 'No') ?></strong>
                                                                </li>
                                                                <li class="mb-2">
                                                                    <span class="text-muted d-block">4. Willing to donate voluntarily if contacted?</span>
                                                                    <strong class="text-primary"><?= htmlspecialchars($answers['willing_to_donate'] ?? 'Yes') ?></strong>
                                                                </li>
                                                                <li>
                                                                    <span class="text-muted d-block">5. Reviewed basic eligibility guidelines?</span>
                                                                    <strong><?= htmlspecialchars($answers['reviewed_guidelines'] ?? 'Yes') ?></strong>
                                                                </li>
                                                            </ul>
                                                        </div>

                                                        <div class="alert alert-secondary small mb-0">
                                                            <i class="fa-solid fa-stethoscope text-primary me-1"></i>
                                                            <strong>Policy Reminder:</strong> Preliminary screening answers are collected for informational matching. Final medical clearance is performed on-site by qualified healthcare staff at the time of donation.
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-0 pt-0">
                                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                                        <?php if ($d['verification_status'] !== 'verified'): ?>
                                                            <form method="POST" action="" class="d-inline m-0 p-0">
                                                                <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                                                <input type="hidden" name="action" value="verify">
                                                                <input type="hidden" name="id" value="<?= $d['donor_id'] ?>">
                                                                <button type="submit" class="btn btn-success btn-sm">
                                                                    <i class="fa-solid fa-check me-1"></i> Verify Profile Now
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
