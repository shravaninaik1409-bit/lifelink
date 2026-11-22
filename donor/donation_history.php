<?php
/**
 * LifeLink - Donor Donation History & Badges (Phase 2)
 * Features manual donation submission with 'Pending' verification,
 * admin review protection, and milestone calculations strictly on approved donations.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('donor');
$currentUser = getCurrentUser();
$userId = $currentUser['user_id'];
$pageTitle = "Donation History — LifeLink";

$stmt = $db->prepare("SELECT donor_id, badges, last_donation_date FROM donors WHERE user_id = ?");
$stmt->execute([$userId]);
$donor = $stmt->fetch();
$donorId = $donor['donor_id'];

$error = '';
$success = '';

// Handle manual donation log submission (saves as Pending Admin Review)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_donation'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        $error = "Security validation failed. Please refresh the page and try again.";
    } else {
        $hospital = sanitize($_POST['hospital_name'] ?? '');
        $donationDate = sanitize($_POST['donation_date'] ?? '');
        $units = max(1, min(5, (int)($_POST['units_donated'] ?? 1)));
        $certificateNo = sanitize($_POST['certificate_no'] ?? '');

        if (empty($hospital) || empty($donationDate)) {
            $error = "Please fill in all mandatory fields (Hospital name and Donation date).";
        } elseif (strtotime($donationDate) > time()) {
            $error = "Donation date cannot be in the future.";
        } else {
            try {
                // Save record with 'Pending' status and 'manual' submission type
                $ins = $db->prepare("INSERT INTO donations 
                    (donor_id, donation_date, hospital_name, units_donated, certificate_no, verification_status, submission_type) 
                    VALUES (?, ?, ?, ?, ?, 'Pending', 'manual')");
                $ins->execute([$donorId, $donationDate, $hospital, $units, !empty($certificateNo) ? $certificateNo : null]);

                $success = "Donation record submitted for administrative review! It will be verified by our clinical team before milestone credits are granted.";
            } catch (Exception $e) {
                $error = "Failed to log donation: " . $e->getMessage();
            }
        }
    }
}

// Fetch all donation records for this donor
$dStmt = $db->prepare("SELECT d.*, r.unique_request_number 
                       FROM donations d 
                       LEFT JOIN blood_requests r ON d.request_id = r.request_id 
                       WHERE d.donor_id = ? 
                       ORDER BY d.donation_date DESC, d.created_at DESC");
$dStmt->execute([$donorId]);
$donations = $dStmt->fetchAll();

// Calculate verified vs pending statistics
$approvedDonations = array_filter($donations, fn($d) => in_array($d['verification_status'], ['Verified', 'Approved']));
$pendingDonations = array_filter($donations, fn($d) => $d['verification_status'] === 'Pending');
$rejectedDonations = array_filter($donations, fn($d) => $d['verification_status'] === 'Rejected');

$totalApprovedSessions = count($approvedDonations);
$totalApprovedUnits = array_sum(array_column($approvedDonations, 'units_donated'));
$totalPendingSessions = count($pendingDonations);

// Milestone badges updated strictly on approved donations
$currentBadges = updateDonorMilestoneBadges($db, $donorId);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/donor_navbar.php';
?>

<div class="bg-light min-vh-100 py-4">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h3 class="fw-bold mb-1 text-dark">Donation History & Badges</h3>
                <p class="text-muted small mb-0">Track verified blood donation milestones and monitor pending submissions.</p>
            </div>
            <button type="button" class="btn btn-lifelink-red" data-bs-toggle="modal" data-bs-target="#logDonationModal">
                <i class="fa-solid fa-plus me-1"></i> Log Past Donation
            </button>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                <i class="fa-solid fa-circle-exclamation me-1"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show small" role="alert">
                <i class="fa-solid fa-circle-check me-1"></i><?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Recognition Badges Showcase -->
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
            <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-medal text-warning me-2"></i>Recognition Badges & Milestones</h5>
            <div class="row g-3">
                <div class="col-md-3 col-sm-6">
                    <div class="p-3 rounded-3 border <?= $totalApprovedSessions >= 1 ? 'bg-white shadow-sm border-danger' : 'bg-light opacity-75' ?> d-flex align-items-center gap-3">
                        <div class="user-avatar-circle <?= $totalApprovedSessions >= 1 ? 'bg-danger text-white' : 'bg-secondary text-white' ?>"><i class="fa-solid fa-droplet"></i></div>
                        <div>
                            <strong class="text-dark d-block">First Donation</strong>
                            <small class="text-muted"><?= $totalApprovedSessions >= 1 ? 'Earned' : '1 donation required' ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="p-3 rounded-3 border <?= $totalApprovedSessions >= 3 ? 'bg-white shadow-sm border-warning' : 'bg-light opacity-75' ?> d-flex align-items-center gap-3">
                        <div class="user-avatar-circle <?= $totalApprovedSessions >= 3 ? 'bg-warning text-dark' : 'bg-secondary text-white' ?>"><i class="fa-solid fa-award"></i></div>
                        <div>
                            <strong class="text-dark d-block">Bronze LifeSaver</strong>
                            <small class="text-muted"><?= $totalApprovedSessions >= 3 ? 'Earned' : (3 - $totalApprovedSessions) . ' more required' ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="p-3 rounded-3 border <?= $totalApprovedSessions >= 5 ? 'bg-white shadow-sm border-primary' : 'bg-light opacity-75' ?> d-flex align-items-center gap-3">
                        <div class="user-avatar-circle <?= $totalApprovedSessions >= 5 ? 'bg-primary text-white' : 'bg-secondary text-white' ?>"><i class="fa-solid fa-shield-heart"></i></div>
                        <div>
                            <strong class="text-dark d-block">Silver Guardian</strong>
                            <small class="text-muted"><?= $totalApprovedSessions >= 5 ? 'Earned' : (5 - $totalApprovedSessions) . ' more required' ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="p-3 rounded-3 border <?= $totalApprovedSessions >= 10 ? 'bg-white shadow-sm border-success' : 'bg-light opacity-75' ?> d-flex align-items-center gap-3">
                        <div class="user-avatar-circle <?= $totalApprovedSessions >= 10 ? 'bg-success text-white' : 'bg-secondary text-white' ?>"><i class="fa-solid fa-crown"></i></div>
                        <div>
                            <strong class="text-dark d-block">Gold Hero</strong>
                            <small class="text-muted"><?= $totalApprovedSessions >= 10 ? 'Earned' : (10 - $totalApprovedSessions) . ' more required' ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Ribbon -->
        <div class="row g-3 mb-4">
            <div class="col-md-4 col-sm-6">
                <div class="metric-card">
                    <div>
                        <div class="metric-label">Approved Donations</div>
                        <div class="metric-value text-success"><?= $totalApprovedSessions ?></div>
                        <small class="text-muted"><?= $totalApprovedUnits ?> total unit(s) credited</small>
                    </div>
                    <div class="metric-icon-box bg-success bg-opacity-10 text-success">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="metric-card">
                    <div>
                        <div class="metric-label">Pending Verification</div>
                        <div class="metric-value text-warning"><?= $totalPendingSessions ?></div>
                        <small class="text-muted">Awaiting administrative check</small>
                    </div>
                    <div class="metric-icon-box bg-warning bg-opacity-10 text-warning">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-12">
                <div class="metric-card">
                    <div>
                        <div class="metric-label">Estimated Lives Impacted</div>
                        <div class="metric-value text-primary"><?= max($totalApprovedSessions * 3, $totalApprovedSessions) ?></div>
                        <small class="text-muted">Calculated on approved units</small>
                    </div>
                    <div class="metric-icon-box bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-heart-pulse"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- History Table -->
        <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Donation Date</th>
                            <th>Blood Bank / Hospital</th>
                            <th>Fulfillment Context</th>
                            <th>Units</th>
                            <th>Status</th>
                            <th>Verification & Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($donations)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-clock-rotate-left fa-3x mb-3 text-secondary opacity-50"></i>
                                    <p class="mb-0">No past donation records logged yet. Click "Log Past Donation" above to record one.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($donations as $idx => $d): ?>
                                <tr>
                                    <td><?= $idx + 1 ?></td>
                                    <td>
                                        <strong class="text-dark"><?= date('d M Y', strtotime($d['donation_date'])) ?></strong>
                                    </td>
                                    <td>
                                        <i class="fa-solid fa-hospital text-danger me-1"></i>
                                        <?= htmlspecialchars($d['hospital_name']) ?>
                                        <?php if (!empty($d['certificate_no'])): ?>
                                            <small class="text-muted d-block" style="font-size: 0.75rem;">
                                                <i class="fa-solid fa-certificate me-1"></i>Ref: <?= htmlspecialchars($d['certificate_no']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($d['unique_request_number'])): ?>
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($d['unique_request_number']) ?></span>
                                            <small class="text-muted d-block">Hospital Request</small>
                                        <?php else: ?>
                                            <span class="badge bg-light text-secondary border">Self-Reported Drive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-danger"><?= (int)$d['units_donated'] ?> Unit(s)</span>
                                    </td>
                                    <td>
                                        <?= getDonationStatusBadge($d['verification_status']) ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($d['admin_notes'])): ?>
                                            <small class="text-muted d-block">
                                                <i class="fa-solid fa-comment-medical text-primary me-1"></i>
                                                <?= htmlspecialchars($d['admin_notes']) ?>
                                            </small>
                                        <?php elseif (in_array($d['verification_status'], ['Verified', 'Approved'])): ?>
                                            <small class="text-success" style="font-size: 0.75rem;">
                                                <i class="fa-solid fa-check-double me-1"></i>Verified on <?= !empty($d['verified_at']) ? date('d M Y', strtotime($d['verified_at'])) : 'Hospital Record' ?>
                                            </small>
                                        <?php else: ?>
                                            <small class="text-muted" style="font-size: 0.75rem;">Awaiting staff review</small>
                                        <?php endif; ?>
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

<!-- Modal: Log Donation -->
<div class="modal fade" id="logDonationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Log Past Blood Donation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                <input type="hidden" name="log_donation" value="1">
                <div class="modal-body p-4">
                    <div class="alert alert-info small mb-3 border-0">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Self-reported donation records are submitted with <strong>Pending</strong> status and validated by LifeLink administrators before official credits and badges are awarded.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Hospital / Blood Center Name <span class="text-danger">*</span></label>
                        <input type="text" name="hospital_name" class="form-control" placeholder="e.g. Red Cross Blood Center, Mumbai" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Donation Date <span class="text-danger">*</span></label>
                        <input type="date" name="donation_date" class="form-control" required max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                        <div class="form-text small text-muted">Cannot be a future date.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Units Donated</label>
                        <input type="number" name="units_donated" class="form-control" value="1" min="1" max="5">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Certificate / Reference No. (Optional)</label>
                        <input type="text" name="certificate_no" class="form-control" placeholder="e.g. RC-2026-98124">
                        <div class="form-text small text-muted">Certificate number or blood drive reference code provided by the center.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-lifelink-red btn-sm">Submit for Verification</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>