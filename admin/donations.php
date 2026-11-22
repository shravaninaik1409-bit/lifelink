<?php
/**
 * LifeLink - Admin Donation Records Verification (Phase 2)
 * Allows administrators to review, approve, or reject manual blood donation records,
 * supply verification notes, and award verified donation credits.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
$currentUser = getCurrentUser();
$adminId = $currentUser['user_id'];
$pageTitle = "Donation Records Verification — LifeLink";

$error = '';
$success = '';

// Handle Admin Action (Approve / Reject) via secure POST + CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && isset($_POST['donation_id'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        $_SESSION['flash_error'] = "Security validation failed. Please refresh and try again.";
        header("Location: " . BASE_URL . "/admin/donations.php");
        exit();
    }

    $donationId = (int)$_POST['donation_id'];
    $actionType = $_POST['action_type'];
    $adminNotes = sanitize($_POST['admin_notes'] ?? '');

    try {
        // Fetch donation record and donor details
        $dStmt = $db->prepare("SELECT d.*, u.user_id as donor_user_id, u.full_name as donor_name, dn.blood_group, dn.last_donation_date 
                               FROM donations d 
                               JOIN donors dn ON d.donor_id = dn.donor_id 
                               JOIN users u ON dn.user_id = u.user_id 
                               WHERE d.donation_id = ?");
        $dStmt->execute([$donationId]);
        $record = $dStmt->fetch();

        if (!$record) {
            $_SESSION['flash_error'] = "Donation record not found.";
        } else {
            $donorId = $record['donor_id'];
            $donorUserId = $record['donor_user_id'];
            $hospitalName = $record['hospital_name'];
            $donationDate = $record['donation_date'];

            if ($actionType === 'approve') {
                $db->beginTransaction();

                // 1. Update donation record
                $upd = $db->prepare("UPDATE donations 
                                     SET verification_status = 'Approved', 
                                         verified_by = ?, 
                                         verified_at = NOW(), 
                                         admin_notes = ? 
                                     WHERE donation_id = ?");
                $upd->execute([$adminId, $adminNotes, $donationId]);

                // 2. Update donor last donation date if this date is newer
                if (empty($record['last_donation_date']) || strtotime($donationDate) > strtotime($record['last_donation_date'])) {
                    $db->prepare("UPDATE donors SET last_donation_date = ? WHERE donor_id = ?")->execute([$donationDate, $donorId]);
                }

                // 3. Recalculate milestone badges on approved counts
                updateDonorMilestoneBadges($db, $donorId);

                // 4. Notify donor
                createNotification(
                    $db, 
                    $donorUserId, 
                    "Donation Record Approved!", 
                    "Your blood donation record on {$donationDate} at {$hospitalName} has been verified and approved by administration. " . ($adminNotes ? "Note: {$adminNotes}" : "You have received recognized donation credits."), 
                    "system"
                );

                // 5. Log admin action
                logAdminAction($db, $adminId, "Donation Record Approved", "Approved Donation #{$donationId} for Donor {$record['donor_name']} at {$hospitalName}");

                $db->commit();
                $_SESSION['flash_msg'] = "Donation record #{$donationId} for {$record['donor_name']} approved successfully!";
            } elseif ($actionType === 'reject') {
                $db->beginTransaction();

                $upd = $db->prepare("UPDATE donations 
                                     SET verification_status = 'Rejected', 
                                         verified_by = ?, 
                                         verified_at = NOW(), 
                                         admin_notes = ? 
                                     WHERE donation_id = ?");
                $upd->execute([$adminId, $adminNotes, $donationId]);

                // Notify donor of rejection
                createNotification(
                    $db, 
                    $donorUserId, 
                    "Donation Verification Update", 
                    "Your submitted donation record at {$hospitalName} could not be approved. Reason: " . ($adminNotes ?: "Information could not be confirmed with the medical center."), 
                    "system"
                );

                logAdminAction($db, $adminId, "Donation Record Rejected", "Rejected Donation #{$donationId} for Donor {$record['donor_name']}. Reason: {$adminNotes}");

                $db->commit();
                $_SESSION['flash_msg'] = "Donation record #{$donationId} marked as Rejected.";
            }
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $_SESSION['flash_error'] = "Action failed: " . $e->getMessage();
    }
    header("Location: " . BASE_URL . "/admin/donations.php");
    exit();
}

// Filters & Search
$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$sql = "SELECT d.*, dn.blood_group, dn.city as donor_city, u.full_name as donor_name, u.email as donor_email,
               r.unique_request_number, adm.full_name as verified_by_name
        FROM donations d 
        JOIN donors dn ON d.donor_id = dn.donor_id 
        JOIN users u ON dn.user_id = u.user_id 
        LEFT JOIN blood_requests r ON d.request_id = r.request_id 
        LEFT JOIN users adm ON d.verified_by = adm.user_id 
        WHERE 1=1";
$params = [];

if (!empty($statusFilter)) {
    if ($statusFilter === 'Approved') {
        $sql .= " AND d.verification_status IN ('Approved', 'Verified')";
    } else {
        $sql .= " AND d.verification_status = ?";
        $params[] = $statusFilter;
    }
}

if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE ? OR d.hospital_name LIKE ? OR d.certificate_no LIKE ? OR r.unique_request_number LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY (d.verification_status = 'Pending') DESC, d.donation_date DESC, d.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Counts for top metric cards
$pendingCount = (int)$db->query("SELECT COUNT(*) FROM donations WHERE verification_status = 'Pending'")->fetchColumn();
$approvedCount = (int)$db->query("SELECT COUNT(*) FROM donations WHERE verification_status IN ('Approved', 'Verified')")->fetchColumn();
$rejectedCount = (int)$db->query("SELECT COUNT(*) FROM donations WHERE verification_status = 'Rejected'")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout">
    <?php require_once __DIR__ . '/../includes/dashboard_sidebar.php'; ?>

    <div class="dashboard-main">
        <?php require_once __DIR__ . '/../includes/dashboard_topbar.php'; ?>

        <div class="dashboard-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <div>
                    <h3 class="fw-bold mb-1 text-dark">Donation Records Verification</h3>
                    <p class="text-muted small mb-0">Review voluntary blood donation records submitted by donors and clinical centers.</p>
                </div>
            </div>

            <?php if (isset($_SESSION['flash_msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show small mb-4" role="alert">
                    <i class="fa-solid fa-circle-check me-1"></i><?= htmlspecialchars($_SESSION['flash_msg']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_msg']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show small mb-4" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-1"></i><?= htmlspecialchars($_SESSION['flash_error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>

            <!-- Metrics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-4 col-sm-6">
                    <div class="metric-card">
                        <div>
                            <div class="metric-label">Pending Reviews</div>
                            <div class="metric-value text-warning"><?= $pendingCount ?></div>
                            <small class="text-muted">Awaiting administrative check</small>
                        </div>
                        <div class="metric-icon-box bg-warning bg-opacity-10 text-warning">
                            <i class="fa-solid fa-hourglass-half"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="metric-card">
                        <div>
                            <div class="metric-label">Approved & Verified</div>
                            <div class="metric-value text-success"><?= $approvedCount ?></div>
                            <small class="text-muted">Accredited donation credits</small>
                        </div>
                        <div class="metric-icon-box bg-success bg-opacity-10 text-success">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-12">
                    <div class="metric-card">
                        <div>
                            <div class="metric-label">Rejected Records</div>
                            <div class="metric-value text-danger"><?= $rejectedCount ?></div>
                            <small class="text-muted">Unverified or invalid submissions</small>
                        </div>
                        <div class="metric-icon-box bg-danger bg-opacity-10 text-danger">
                            <i class="fa-solid fa-circle-xmark"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-4">
                <form method="GET" action="" class="row g-2 align-items-center">
                    <div class="col-md-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search donor name, hospital, certificate no..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Verification Statuses</option>
                            <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Pending Review (<?= $pendingCount ?>)</option>
                            <option value="Approved" <?= $statusFilter === 'Approved' ? 'selected' : '' ?>>Approved & Verified (<?= $approvedCount ?>)</option>
                            <option value="Rejected" <?= $statusFilter === 'Rejected' ? 'selected' : '' ?>>Rejected (<?= $rejectedCount ?>)</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-dark btn-sm flex-grow-1">Filter</button>
                        <a href="<?= BASE_URL ?>/admin/donations.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Donations Table -->
            <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Donor</th>
                                <th>Hospital / Blood Center</th>
                                <th>Donation Date</th>
                                <th>Units</th>
                                <th>Context</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($records)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-inbox fa-3x mb-2 text-secondary opacity-50"></i>
                                        <p class="mb-0">No donation records found matching criteria.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($records as $idx => $r): ?>
                                    <tr>
                                        <td><?= $idx + 1 ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-danger text-white fw-bold"><?= htmlspecialchars($r['blood_group']) ?></span>
                                                <div>
                                                    <strong class="text-dark d-block"><?= htmlspecialchars($r['donor_name']) ?></strong>
                                                    <small class="text-muted"><?= htmlspecialchars($r['donor_city']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong class="text-dark"><?= htmlspecialchars($r['hospital_name']) ?></strong>
                                            <?php if (!empty($r['certificate_no'])): ?>
                                                <small class="text-muted d-block" style="font-size: 0.75rem;">
                                                    <i class="fa-solid fa-certificate text-warning me-1"></i>Ref: <?= htmlspecialchars($r['certificate_no']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="text-dark fw-semibold"><?= date('d M Y', strtotime($r['donation_date'])) ?></span>
                                            <small class="text-muted d-block" style="font-size: 0.72rem;">Logged <?= timeAgo($r['created_at']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-danger border fw-bold"><?= (int)$r['units_donated'] ?> Unit(s)</span>
                                        </td>
                                        <td>
                                            <?php if (!empty($r['unique_request_number'])): ?>
                                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($r['unique_request_number']) ?></span>
                                                <small class="text-muted d-block" style="font-size: 0.72rem;">Hospital Request</small>
                                            <?php else: ?>
                                                <span class="badge bg-light text-secondary border">Self-Reported</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= getDonationStatusBadge($r['verification_status']) ?>
                                            <?php if (!empty($r['admin_notes'])): ?>
                                                <small class="text-muted d-block mt-1" style="font-size: 0.72rem; max-width: 140px;">
                                                    <i class="fa-solid fa-message text-secondary me-1"></i><?= htmlspecialchars($r['admin_notes']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2" data-bs-toggle="modal" data-bs-target="#reviewDonationModal<?= $r['donation_id'] ?>">
                                                <i class="fa-solid fa-clipboard-check me-1"></i> Review
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Review Modal -->
                                    <div class="modal fade" id="reviewDonationModal<?= $r['donation_id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content rounded-4 border-0 shadow">
                                                <div class="modal-header border-0 pb-0">
                                                    <h5 class="modal-title fw-bold">Review Donation #<?= $r['donation_id'] ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <div class="bg-light p-3 rounded-3 mb-3">
                                                        <div class="d-flex justify-content-between mb-1">
                                                            <small class="text-muted">Donor</small>
                                                            <strong class="text-dark"><?= htmlspecialchars($r['donor_name']) ?> (<?= htmlspecialchars($r['blood_group']) ?>)</strong>
                                                        </div>
                                                        <div class="d-flex justify-content-between mb-1">
                                                            <small class="text-muted">Hospital</small>
                                                            <strong class="text-dark"><?= htmlspecialchars($r['hospital_name']) ?></strong>
                                                        </div>
                                                        <div class="d-flex justify-content-between mb-1">
                                                            <small class="text-muted">Donation Date</small>
                                                            <strong class="text-dark"><?= date('d M Y', strtotime($r['donation_date'])) ?></strong>
                                                        </div>
                                                        <div class="d-flex justify-content-between mb-1">
                                                            <small class="text-muted">Units Donated</small>
                                                            <strong class="text-danger"><?= (int)$r['units_donated'] ?> Unit(s)</strong>
                                                        </div>
                                                        <?php if (!empty($r['certificate_no'])): ?>
                                                            <div class="d-flex justify-content-between">
                                                                <small class="text-muted">Reference / Cert No.</small>
                                                                <span class="badge bg-white text-dark border"><?= htmlspecialchars($r['certificate_no']) ?></span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>

                                                    <form method="POST" action="">
                                                        <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                                        <input type="hidden" name="donation_id" value="<?= $r['donation_id'] ?>">

                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold small text-secondary">Verification Note / Reason</label>
                                                            <textarea name="admin_notes" class="form-control form-control-sm" rows="3" placeholder="Enter clinical verification note or reason for rejection..."><?= htmlspecialchars($r['admin_notes'] ?? '') ?></textarea>
                                                        </div>

                                                        <div class="d-flex gap-2">
                                                            <button type="submit" name="action_type" value="approve" class="btn btn-success btn-sm flex-grow-1">
                                                                <i class="fa-solid fa-circle-check me-1"></i> Approve Donation
                                                            </button>
                                                            <button type="submit" name="action_type" value="reject" class="btn btn-outline-danger btn-sm flex-grow-1" onclick="return confirmAction('Reject this donation submission?');">
                                                                <i class="fa-solid fa-circle-xmark me-1"></i> Reject Submission
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
    </div>
</div>