<?php
/**
 * LifeLink - Request Details & Dynamic Tracking Timeline
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('requester');
$currentUser = getCurrentUser();
$userId = $currentUser['user_id'];
$requestId = (int)($_GET['id'] ?? 0);

if (!$requestId) {
    header("Location: " . BASE_URL . "/requester/my_requests.php");
    exit();
}

// Fetch request record
$stmt = $db->prepare("SELECT * FROM blood_requests WHERE request_id = ? AND requester_id = ?");
$stmt->execute([$requestId, $userId]);
$req = $stmt->fetch();

if (!$req) {
    die("Blood request not found.");
}

$pageTitle = "Tracking #" . htmlspecialchars($req['unique_request_number']) . " — LifeLink";
$error = '';
$success = '';

// Handle Mark Completed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_completed'])) {
    $donorIdToCredit = (int)($_POST['donor_id'] ?? 0);
    try {
        $db->beginTransaction();

        $upd = $db->prepare("UPDATE blood_requests SET status = 'Completed', updated_at = NOW() WHERE request_id = ?");
        $upd->execute([$requestId]);

        // If a specific donor was chosen, log donation
        if ($donorIdToCredit > 0) {
            $ins = $db->prepare("INSERT INTO donations (donor_id, request_id, donation_date, hospital_name, units_donated, verification_status) VALUES (?, ?, CURDATE(), ?, ?, 'Verified')");
            $ins->execute([$donorIdToCredit, $requestId, $req['hospital_name'], $req['units_required']]);

            // Update donor last donation date
            $db->prepare("UPDATE donors SET last_donation_date = CURDATE() WHERE donor_id = ?")->execute([$donorIdToCredit]);

            // Notify donor
            $targetUserStmt = $db->prepare("SELECT user_id FROM donors WHERE donor_id = ?");
            $targetUserStmt->execute([$donorIdToCredit]);
            $targetUserId = $targetUserStmt->fetchColumn();

            if ($targetUserId) {
                createNotification($db, $targetUserId, "Donation Verified & Completed!", "Thank you! Your blood donation for request {$req['unique_request_number']} at {$req['hospital_name']} has been marked completed. You earned verified donor credits!", "system", $requestId);
            }
        }

        $db->commit();
        $success = "Blood request has been marked as Completed successfully!";
        // Refresh request
        $stmt->execute([$requestId, $userId]);
        $req = $stmt->fetch();
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = "Failed to mark completed: " . $e->getMessage();
    }
}

// Fetch donor responses
$respStmt = $db->prepare("SELECT rr.*, d.blood_group, d.city as donor_city, d.badges, u.full_name as donor_name, u.phone as donor_phone 
                          FROM request_responses rr 
                          JOIN donors d ON rr.donor_id = d.donor_id 
                          JOIN users u ON d.user_id = u.user_id 
                          WHERE rr.request_id = ? 
                          ORDER BY (rr.response_status = 'Accepted') DESC, rr.response_date DESC");
$respStmt->execute([$requestId]);
$responses = $respStmt->fetchAll();

// Determine timeline stage (1 through 6)
$timelineStage = 1; // 1: Submitted
if ($req['status'] === 'Pending') {
    $timelineStage = 2; // Matching Donors Identified
} elseif ($req['status'] === 'Donors Notified') {
    $timelineStage = 3; // Donors Notified
} elseif ($req['status'] === 'Donor Response Received') {
    $timelineStage = 4; // Responses Received
} elseif (in_array($req['status'], ['Accepted', 'In Progress'])) {
    $timelineStage = 5; // Donor Accepted / In Progress
} elseif ($req['status'] === 'Completed') {
    $timelineStage = 6; // Completed
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout">
    <?php require_once __DIR__ . '/../includes/dashboard_sidebar.php'; ?>

    <div class="dashboard-main">
        <?php require_once __DIR__ . '/../includes/dashboard_topbar.php'; ?>

        <div class="dashboard-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                <div>
                    <a href="<?= BASE_URL ?>/requester/my_requests.php" class="text-muted small d-inline-block mb-1">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back to My Requests
                    </a>
                    <h3 class="fw-bold mb-0 text-dark">
                        Request #<?= htmlspecialchars($req['unique_request_number']) ?>
                    </h3>
                </div>
                <div class="d-flex align-items-center gap-2 mt-2 mt-sm-0">
                    <?= getUrgencyBadge($req['urgency']) ?>
                    <?= getStatusBadge($req['status']) ?>
                </div>
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

            <!-- Interactive Timeline Tracking Card -->
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white mb-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-timeline text-danger me-2"></i>Live Request Progress</h5>
                    <small class="text-muted">Updated: <?= timeAgo($req['updated_at']) ?></small>
                </div>

                <?php if ($req['status'] === 'Cancelled'): ?>
                    <div class="alert alert-dark text-center py-4 mb-0">
                        <i class="fa-solid fa-ban fa-2x mb-2 text-secondary"></i>
                        <h6 class="fw-bold mb-1">Request Cancelled</h6>
                        <small class="text-muted">This blood request was cancelled by the requester.</small>
                    </div>
                <?php else: ?>
                    <div class="tracking-timeline">
                        <div class="timeline-step <?= $timelineStage >= 1 ? 'completed' : '' ?>">
                            <div class="step-node"><i class="fa-solid fa-check"></i></div>
                            <div class="step-label">1. Submitted</div>
                            <small class="text-muted d-block" style="font-size: 0.72rem;"><?= date('d M, h:i A', strtotime($req['created_at'])) ?></small>
                        </div>
                        <div class="timeline-step <?= $timelineStage > 2 ? 'completed' : ($timelineStage == 2 ? 'active' : '') ?>">
                            <div class="step-node"><?= $timelineStage > 2 ? '<i class="fa-solid fa-check"></i>' : '2' ?></div>
                            <div class="step-label">2. Matching Donors</div>
                            <small class="text-muted d-block" style="font-size: 0.72rem;"><?= htmlspecialchars($req['blood_group']) ?> in <?= htmlspecialchars($req['city']) ?></small>
                        </div>
                        <div class="timeline-step <?= $timelineStage > 3 ? 'completed' : ($timelineStage == 3 ? 'active' : '') ?>">
                            <div class="step-node"><?= $timelineStage > 3 ? '<i class="fa-solid fa-check"></i>' : '3' ?></div>
                            <div class="step-label">3. Donors Notified</div>
                            <small class="text-muted d-block" style="font-size: 0.72rem;">Alerts Dispatched</small>
                        </div>
                        <div class="timeline-step <?= $timelineStage > 4 ? 'completed' : ($timelineStage == 4 ? 'active' : '') ?>">
                            <div class="step-node"><?= $timelineStage > 4 ? '<i class="fa-solid fa-check"></i>' : '4' ?></div>
                            <div class="step-label">4. Responses Received</div>
                            <small class="text-muted d-block" style="font-size: 0.72rem;"><?= count($responses) ?> Response(s)</small>
                        </div>
                        <div class="timeline-step <?= $timelineStage > 5 ? 'completed' : ($timelineStage == 5 ? 'active' : '') ?>">
                            <div class="step-node"><?= $timelineStage > 5 ? '<i class="fa-solid fa-check"></i>' : '5' ?></div>
                            <div class="step-label">5. Donor Accepted</div>
                            <small class="text-muted d-block" style="font-size: 0.72rem;">Pledge Confirmed</small>
                        </div>
                        <div class="timeline-step <?= $timelineStage >= 6 ? 'completed' : '' ?>">
                            <div class="step-node"><?= $timelineStage >= 6 ? '<i class="fa-solid fa-check"></i>' : '6' ?></div>
                            <div class="step-label">6. Completed</div>
                            <small class="text-muted d-block" style="font-size: 0.72rem;"><?= $req['status'] === 'Completed' ? 'Life Saved' : 'Pending Transfusion' ?></small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="row g-4">
                <!-- Request Information Details -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                        <h5 class="fw-bold text-dark mb-4 pb-2 border-bottom">
                            <i class="fa-solid fa-circle-info text-danger me-2"></i>Patient & Hospital Summary
                        </h5>

                        <div class="row g-3 mb-4">
                            <div class="col-6">
                                <label class="small text-muted d-block">Patient Name</label>
                                <strong class="fs-5 text-dark"><?= htmlspecialchars($req['patient_name']) ?></strong>
                            </div>
                            <div class="col-6">
                                <label class="small text-muted d-block">Required Group & Units</label>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="blood-badge" style="width: 44px; height: 44px; font-size: 1.1rem;"><?= htmlspecialchars($req['blood_group']) ?></span>
                                    <span class="fw-bold text-danger fs-5"><?= (int)$req['units_required'] ?> Unit(s)</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="small text-muted d-block">Required By Date</label>
                                <strong class="text-dark fs-6"><?= date('d M Y', strtotime($req['required_date'])) ?></strong>
                            </div>
                            <div class="col-6">
                                <label class="small text-muted d-block">Urgency Level</label>
                                <div><?= getUrgencyBadge($req['urgency']) ?></div>
                            </div>
                        </div>

                        <div class="p-3 bg-light rounded-3 mb-4">
                            <strong class="d-block text-dark fw-semibold mb-1"><i class="fa-solid fa-hospital text-danger me-1"></i><?= htmlspecialchars($req['hospital_name']) ?></strong>
                            <p class="small text-muted mb-1"><?= nl2br(htmlspecialchars($req['hospital_address'])) ?></p>
                            <small class="text-muted"><?= htmlspecialchars($req['city']) ?>, <?= htmlspecialchars($req['state']) ?> - <?= htmlspecialchars($req['pin_code']) ?></small>
                        </div>

                        <!-- Hospital / Blood Bank Coordination Status -->
                        <div class="p-3 border rounded-3 mb-4 bg-white shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small fw-bold text-dark"><i class="fa-solid fa-hospital-user text-primary me-1"></i>Hospital Coordination:</span>
                                <div><?= getHospitalConfirmationBadge($req['hospital_confirmed'] ?? 0) ?></div>
                            </div>
                            <?php if (!empty($req['hospital_notes'])): ?>
                                <p class="small text-muted mb-2 p-2 bg-light rounded fst-italic">
                                    <i class="fa-solid fa-notes-medical text-primary me-1"></i>"<?= htmlspecialchars($req['hospital_notes']) ?>"
                                </p>
                            <?php endif; ?>
                            <div class="small text-secondary" style="font-size: 0.74rem;">
                                <i class="fa-solid fa-user-doctor text-success me-1"></i>
                                <em>Clinical Assessment: Prior to transfusion, attending medical staff at <?= htmlspecialchars($req['hospital_name']) ?> must perform mandatory physical and viral clearance on the donor.</em>
                            </div>
                        </div>

                        <div class="p-3 border rounded-3 mb-4">
                            <label class="small text-muted d-block">Contact Person on Duty</label>
                            <div class="d-flex justify-content-between align-items-center">
                                <strong class="text-dark"><?= htmlspecialchars($req['contact_person']) ?></strong>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($req['contact_phone']) ?></span>
                            </div>
                        </div>

                        <!-- Completion / Cancellation Actions -->
                        <?php if ($req['status'] !== 'Completed' && $req['status'] !== 'Cancelled'): ?>
                            <div class="mt-auto pt-3 border-top">
                                <h6 class="fw-bold text-dark mb-2">Manage Request State</h6>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-success flex-grow-1" data-bs-toggle="modal" data-bs-target="#markCompletedModal">
                                        <i class="fa-solid fa-circle-check me-1"></i> Mark as Completed
                                    </button>
                                    <a href="<?= BASE_URL ?>/requester/my_requests.php?action=cancel&id=<?= $req['request_id'] ?>" class="btn btn-outline-danger" onclick="return confirmAction('Cancel this blood request?');">
                                        Cancel
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Donor Responses Section -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                        <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                            <h5 class="fw-bold text-dark mb-0">
                                <i class="fa-solid fa-reply-all text-success me-2"></i>Donor Responses (<?= count($responses) ?>)
                            </h5>
                        </div>

                        <?php if (empty($responses)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fa-solid fa-hourglass-half fa-3x mb-3 text-secondary opacity-50"></i>
                                <h6 class="fw-bold mb-1">Awaiting Donor Responses</h6>
                                <p class="small mb-0" style="max-width: 380px; margin: 0 auto;">
                                    Matching donors in <?= htmlspecialchars($req['city']) ?> have been notified. Once a donor accepts, their direct contact details will appear here.
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($responses as $resp): ?>
                                    <div class="p-3 rounded-4 border <?= $resp['response_status'] === 'Accepted' ? 'border-success bg-success-subtle bg-opacity-25' : 'bg-light' ?>">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($resp['donor_name']) ?></h6>
                                                <small class="text-muted"><i class="fa-solid fa-location-dot text-danger me-1"></i><?= htmlspecialchars($resp['donor_city']) ?></small>
                                            </div>
                                            <div>
                                                <?php if ($resp['response_status'] === 'Accepted'): ?>
                                                    <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Accepted Pledge</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Declined</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <p class="small text-secondary mb-2 fst-italic">
                                            "<?= htmlspecialchars($resp['response_message'] ?: 'Ready to donate.') ?>"
                                        </p>

                                        <?php if ($resp['response_status'] === 'Accepted'): ?>
                                            <div class="p-2 mb-2 bg-white rounded-3 border small">
                                                <div class="d-flex align-items-center gap-1 text-success fw-semibold mb-1" style="font-size: 0.78rem;">
                                                    <i class="fa-solid fa-circle-check"></i> Donor Consent Acknowledged
                                                </div>
                                                <ul class="list-unstyled mb-1 text-muted" style="font-size: 0.72rem; line-height: 1.3;">
                                                    <li><i class="fa-solid fa-check text-success me-1"></i> Reviewed request details & confirmed interest</li>
                                                    <li><i class="fa-solid fa-check text-success me-1"></i> Acknowledged final medical confirmation at blood bank</li>
                                                    <li><i class="fa-solid fa-check text-success me-1"></i> Consented to sharing contact details for coordination</li>
                                                </ul>
                                                <div class="text-secondary" style="font-size: 0.7rem;">
                                                    <i class="fa-solid fa-stethoscope text-primary me-1"></i>
                                                    <em>Clinical Reminder: Final medical clearance is performed on-site by attending medical staff.</em>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                            <small class="text-muted" style="font-size: 0.75rem;"><?= timeAgo($resp['response_date']) ?></small>
                                            <?php if ($resp['response_status'] === 'Accepted'): ?>
                                                <a href="tel:<?= htmlspecialchars($resp['donor_phone']) ?>" class="btn btn-success btn-sm py-1 px-3">
                                                    <i class="fa-solid fa-phone me-1"></i> Call <?= htmlspecialchars($resp['donor_phone']) ?>
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
    </div>
</div>

<!-- Modal: Mark as Completed -->
<div class="modal fade" id="markCompletedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Complete Blood Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="mark_completed" value="1">
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">
                        Marking this request as completed records that the blood units were successfully received and awards recognized donation credits to the voluntary donor.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Which donor completed this donation?</label>
                        <select name="donor_id" class="form-select">
                            <option value="0">-- Select Donor or General Hospital Fulfillment --</option>
                            <?php foreach ($responses as $resp): ?>
                                <?php if ($resp['response_status'] === 'Accepted'): ?>
                                    <option value="<?= $resp['donor_id'] ?>"><?= htmlspecialchars($resp['donor_name']) ?> (<?= htmlspecialchars($resp['blood_group']) ?>)</option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm">Confirm & Complete</button>
                </div>
            </form>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
