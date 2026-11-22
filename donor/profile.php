<?php
/**
 * LifeLink - Donor Profile View
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('donor');
$currentUser = getCurrentUser();
$userId = $currentUser['user_id'];
$pageTitle = "Donor Profile — LifeLink";

$stmt = $db->prepare("SELECT d.*, u.full_name, u.email, u.phone, u.verification_status, u.created_at as joined_date 
                      FROM donors d 
                      JOIN users u ON d.user_id = u.user_id 
                      WHERE d.user_id = ?");
$stmt->execute([$userId]);
$donor = $stmt->fetch();

if (!$donor) {
    die("Donor not found.");
}

// Fetch donation counts
$donationsCount = (int)$db->query("SELECT COUNT(*) FROM donations WHERE donor_id = " . (int)$donor['donor_id'] . " AND verification_status IN ('Verified', 'Approved')")->fetchColumn();

// Fetch response history
$respStmt = $db->prepare("SELECT rr.*, br.unique_request_number, br.blood_group as req_bg, br.hospital_name, br.city as req_city, br.urgency, br.status as req_status 
                          FROM request_responses rr 
                          JOIN blood_requests br ON rr.request_id = br.request_id 
                          WHERE rr.donor_id = ? 
                          ORDER BY rr.response_date DESC LIMIT 6");
$respStmt->execute([$donor['donor_id']]);
$responses = $respStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/donor_navbar.php';
?>

<div class="bg-light min-vh-100 py-4">
    <div class="container">
        <?php if (!empty($_SESSION['flash_msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($_SESSION['flash_msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash_msg']); ?>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1 text-dark">Donor Profile</h3>
                <p class="text-muted small mb-0">Manage your publicly displayed donor details and preferences.</p>
            </div>
            <a href="<?= BASE_URL ?>/donor/edit_profile.php" class="btn btn-lifelink-red">
                <i class="fa-solid fa-user-pen me-2"></i> Edit Profile
            </a>
        </div>

        <div class="row g-4">
            <!-- Main Details Card -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 text-center p-4 bg-white mb-4">
                    <div class="blood-badge mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                        <?= htmlspecialchars($donor['blood_group']) ?>
                    </div>
                    <h4 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($donor['full_name']) ?></h4>
                    <p class="text-muted small mb-3">
                        <i class="fa-solid fa-location-dot text-danger me-1"></i>
                        <?= htmlspecialchars($donor['city']) ?>, <?= htmlspecialchars($donor['state']) ?>
                    </p>

                    <!-- 3 Independent Statuses -->
                    <div class="p-3 bg-light rounded-4 text-start mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <span class="small text-muted fw-semibold">Profile Verification:</span>
                            <div><?= getVerificationBadge($donor['verification_status']) ?></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <span class="small text-muted fw-semibold">Current Availability:</span>
                            <div>
                                <?php if ($donor['availability'] === 'Available'): ?>
                                    <span class="badge-available"><i class="fa-solid fa-circle fa-2xs me-1"></i>Available</span>
                                <?php elseif ($donor['availability'] === 'Temporarily Unavailable'): ?>
                                    <span class="badge-unavailable"><i class="fa-solid fa-clock fa-2xs me-1"></i>Temporarily Unavailable</span>
                                <?php else: ?>
                                    <span class="badge-inactive">Not Accepting Requests</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <span class="small text-muted fw-semibold">Clinical Eligibility:</span>
                            <div><?= getMedicalEligibilityBadge() ?></div>
                        </div>
                        <p class="small text-muted mb-0" style="font-size: 0.72rem; line-height: 1.35;">
                            <i class="fa-solid fa-circle-info text-primary me-1"></i>
                            <em>Profile verification does not guarantee eligibility to donate. Final clinical assessment is performed by qualified medical personnel at the donation facility.</em>
                        </p>
                    </div>

                    <!-- Last Donation voluntary info -->
                    <div class="p-3 border rounded-3 bg-white text-start mb-3">
                        <span class="small text-muted d-block fw-semibold mb-1"><i class="fa-solid fa-calendar-check text-danger me-1"></i>Last Donation Record:</span>
                        <?php if (!empty($donor['last_donation_date'])): ?>
                            <strong class="text-dark small d-block"><?= date('d M Y', strtotime($donor['last_donation_date'])) ?></strong>
                            <small class="text-muted" style="font-size: 0.75rem;">(<?= timeAgo($donor['last_donation_date']) ?>)</small>
                        <?php else: ?>
                            <span class="badge bg-light text-secondary border">No previous donations logged</span>
                        <?php endif; ?>
                    </div>

                    <div class="row g-2 text-center pt-2 border-top">
                        <div class="col-6">
                            <strong class="d-block text-danger fs-4 fw-bold"><?= $donationsCount ?></strong>
                            <small class="text-muted">Verified Donations</small>
                        </div>
                        <div class="col-6">
                            <strong class="d-block text-primary fs-4 fw-bold">
                                <?= !empty($donor['badges']) ? count(explode(',', $donor['badges'])) : 1 ?>
                            </strong>
                            <small class="text-muted">Honors / Badges</small>
                        </div>
                    </div>
                </div>

                <!-- Privacy Status Card -->
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                    <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-shield-halved text-primary me-2"></i>Privacy Settings</h6>
                    <ul class="list-unstyled small text-muted mb-0">
                        <li class="mb-2 d-flex justify-content-between">
                            <span>Phone Number Privacy:</span>
                            <strong class="<?= $donor['hide_phone'] ? 'text-danger' : 'text-success' ?>">
                                <?= $donor['hide_phone'] ? 'Hidden from public' : 'Visible for requests' ?>
                            </strong>
                        </li>
                        <li class="mb-2 d-flex justify-content-between">
                            <span>Exact Address:</span>
                            <strong class="text-success">Masked / Protected</strong>
                        </li>
                        <li class="d-flex justify-content-between">
                            <span>Email Address:</span>
                            <strong class="text-success">Private & Safe</strong>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Comprehensive Details -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white mb-4">
                    <h5 class="fw-bold text-dark mb-4 pb-2 border-bottom"><i class="fa-solid fa-circle-info text-secondary me-2"></i>Personal & Medical Information</h5>
                    
                    <div class="row g-4 mb-4">
                        <div class="col-sm-6">
                            <label class="text-muted small d-block">Full Legal Name</label>
                            <span class="fw-semibold text-dark fs-6"><?= htmlspecialchars($donor['full_name']) ?></span>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted small d-block">Blood Group</label>
                            <span class="fw-bold text-danger fs-6"><?= htmlspecialchars($donor['blood_group']) ?></span>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted small d-block">Email Address</label>
                            <span class="fw-semibold text-dark fs-6"><?= htmlspecialchars($donor['email']) ?></span>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted small d-block">Contact Phone</label>
                            <span class="fw-semibold text-dark fs-6"><?= htmlspecialchars($donor['phone']) ?></span>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted small d-block">Date of Birth / Age</label>
                            <span class="fw-semibold text-dark fs-6">
                                <?= !empty($donor['date_of_birth']) ? date('d M Y', strtotime($donor['date_of_birth'])) : 'Not specified' ?>
                            </span>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted small d-block">Gender</label>
                            <span class="fw-semibold text-dark fs-6"><?= htmlspecialchars($donor['gender'] ?? 'Not specified') ?></span>
                        </div>
                    </div>

                    <h5 class="fw-bold text-dark mb-4 pb-2 border-bottom"><i class="fa-solid fa-map-location-dot text-secondary me-2"></i>Geographic Location</h5>
                    <div class="row g-4 mb-4">
                        <div class="col-sm-6">
                            <label class="text-muted small d-block">State</label>
                            <span class="fw-semibold text-dark fs-6"><?= htmlspecialchars($donor['state']) ?></span>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted small d-block">City</label>
                            <span class="fw-semibold text-dark fs-6"><?= htmlspecialchars($donor['city']) ?></span>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted small d-block">Area / Neighborhood</label>
                            <span class="fw-semibold text-dark fs-6"><?= htmlspecialchars($donor['area'] ?: 'N/A') ?></span>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted small d-block">Postal PIN Code</label>
                            <span class="fw-semibold text-dark fs-6"><?= htmlspecialchars($donor['pin_code']) ?></span>
                        </div>
                    </div>

                    <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom"><i class="fa-solid fa-align-left text-secondary me-2"></i>Bio & Motivation</h5>
                    <div class="p-3 bg-light rounded-3 text-muted">
                        <?= nl2br(htmlspecialchars($donor['profile_description'] ?: 'No personal notes provided. Consider editing your profile to mention your readiness for emergency requests.')) ?>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex gap-2">
                        <a href="<?= BASE_URL ?>/donor/edit_profile.php" class="btn btn-lifelink-red">
                            <i class="fa-solid fa-pen-to-square me-1"></i> Edit Profile Information
                        </a>
                        <a href="<?= BASE_URL ?>/donor/donation_history.php" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-history me-1"></i> View Donation History
                        </a>
                    </div>
                </div>

                <!-- Donor Response History Section -->
                <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                        <div>
                            <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-reply-all text-secondary me-2"></i>My Request Response History</h5>
                            <p class="text-muted small mb-0">Record of blood requests you have responded to on the network.</p>
                        </div>
                        <span class="badge bg-light text-dark border"><?= count($responses) ?> Responses</span>
                    </div>

                    <?php if (empty($responses)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fa-solid fa-inbox fa-3x mb-3 text-secondary opacity-50"></i>
                            <h6 class="fw-bold mb-1">No Response Records Yet</h6>
                            <p class="small mb-0">When you receive and respond to blood requests, your responses and consent will be archived here.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th>Request #</th>
                                        <th>Hospital / Facility</th>
                                        <th>Response</th>
                                        <th>Consent Acknowledged</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody class="small">
                                    <?php foreach ($responses as $r): ?>
                                        <tr>
                                            <td>
                                                <span class="fw-bold text-dark">#<?= htmlspecialchars($r['unique_request_number']) ?></span>
                                                <span class="badge bg-danger-subtle text-danger ms-1"><?= htmlspecialchars($r['req_bg']) ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark"><?= htmlspecialchars($r['hospital_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($r['req_city'] ?? '') ?></small>
                                            </td>
                                            <td>
                                                <?php if ($r['response_status'] === 'Accepted'): ?>
                                                    <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i>Accepted</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Declined</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($r['consent_medical_confirmation'])): ?>
                                                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle" title="Consent recorded on <?= htmlspecialchars($r['consent_date'] ?? '') ?>">
                                                        <i class="fa-solid fa-clipboard-check me-1"></i>Consent Recorded
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted text-nowrap">
                                                <?= date('d M Y', strtotime($r['response_date'])) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
