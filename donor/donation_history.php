<?php
/**
 * LifeLink - Donor Donation History & Badges
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('donor');
$currentUser = getCurrentUser();
$userId = $currentUser['user_id'];
$pageTitle = "Donation History — LifeLink";

$stmt = $db->prepare("SELECT donor_id, badges FROM donors WHERE user_id = ?");
$stmt->execute([$userId]);
$donor = $stmt->fetch();
$donorId = $donor['donor_id'];

$error = '';
$success = '';

// Handle manual donation log submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_donation'])) {
    $hospital = sanitize($_POST['hospital_name'] ?? '');
    $donationDate = sanitize($_POST['donation_date'] ?? '');
    $units = (int)($_POST['units_donated'] ?? 1);

    if (empty($hospital) || empty($donationDate)) {
        $error = "Please fill in hospital name and donation date.";
    } else {
        try {
            $ins = $db->prepare("INSERT INTO donations (donor_id, donation_date, hospital_name, units_donated, verification_status) VALUES (?, ?, ?, ?, 'Verified')");
            $ins->execute([$donorId, $donationDate, $hospital, $units]);

            // Update last donation date in donors table
            $upd = $db->prepare("UPDATE donors SET last_donation_date = ? WHERE donor_id = ?");
            $upd->execute([$donationDate, $donorId]);

            $success = "Donation record logged and verified successfully!";
        } catch (Exception $e) {
            $error = "Failed to log donation: " . $e->getMessage();
        }
    }
}

// Fetch donations
$dStmt = $db->prepare("SELECT d.*, r.unique_request_number 
                       FROM donations d 
                       LEFT JOIN blood_requests r ON d.request_id = r.request_id 
                       WHERE d.donor_id = ? 
                       ORDER BY d.donation_date DESC");
$dStmt->execute([$donorId]);
$donations = $dStmt->fetchAll();

$totalUnits = array_sum(array_column($donations, 'units_donated'));

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/donor_navbar.php';
?>

<div class="bg-light min-vh-100 py-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark">Donation History & Badges</h3>
                    <p class="text-muted small mb-0">Track your past blood donations and community milestones.</p>
                </div>
                <button type="button" class="btn btn-lifelink-red" data-bs-toggle="modal" data-bs-target="#logDonationModal">
                    <i class="fa-solid fa-plus me-1"></i> Log New Donation
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

            <!-- Badges & Milestone Showcase -->
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-medal text-warning me-2"></i>Recognition Badges</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="p-3 rounded-3 border bg-light d-flex align-items-center gap-3">
                            <div class="user-avatar-circle bg-danger"><i class="fa-solid fa-droplet"></i></div>
                            <div>
                                <strong class="text-dark d-block">First Donation</strong>
                                <small class="text-muted">Awarded on completing your very first pledge.</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded-3 border bg-light d-flex align-items-center gap-3">
                            <div class="user-avatar-circle bg-primary"><i class="fa-solid fa-award"></i></div>
                            <div>
                                <strong class="text-dark d-block">Verified Donor</strong>
                                <small class="text-muted">Identity and blood group validated by admin.</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded-3 border bg-light d-flex align-items-center gap-3">
                            <div class="user-avatar-circle bg-success"><i class="fa-solid fa-shield-heart"></i></div>
                            <div>
                                <strong class="text-dark d-block">Community Hero</strong>
                                <small class="text-muted">Saved multiple lives across critical events.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Ribbon -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6">
                    <div class="metric-card">
                        <div>
                            <div class="metric-label">Total Donation Sessions</div>
                            <div class="metric-value text-danger"><?= count($donations) ?></div>
                        </div>
                        <div class="metric-icon-box bg-danger bg-opacity-10 text-danger">
                            <i class="fa-solid fa-hand-holding-medical"></i>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="metric-card">
                        <div>
                            <div class="metric-label">Estimated Lives Impacted</div>
                            <div class="metric-value text-primary"><?= max(count($donations) * 3, count($donations)) ?></div>
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
                                <th>Associated Request</th>
                                <th>Units</th>
                                <th>Verification Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($donations)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-clock-rotate-left fa-3x mb-3 text-secondary opacity-50"></i>
                                        <p class="mb-0">No past donation records logged yet. Click "Log New Donation" above to record one.</p>
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
                                        </td>
                                        <td>
                                            <?php if (!empty($d['unique_request_number'])): ?>
                                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($d['unique_request_number']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted small">Voluntary Drive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-danger"><?= (int)$d['units_donated'] ?> Unit(s)</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i><?= htmlspecialchars($d['verification_status']) ?></span>
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

<!-- Modal: Log Donation -->
<div class="modal fade" id="logDonationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Log Past Blood Donation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="log_donation" value="1">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Hospital / Blood Center Name <span class="text-danger">*</span></label>
                        <input type="text" name="hospital_name" class="form-control" placeholder="e.g. Red Cross Blood Center, Mumbai" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Donation Date <span class="text-danger">*</span></label>
                        <input type="date" name="donation_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Units Donated</label>
                        <input type="number" name="units_donated" class="form-control" value="1" min="1" max="5">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-lifelink-red btn-sm">Save Donation Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
