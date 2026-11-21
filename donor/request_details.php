<?php
/**
 * LifeLink - Donor Request Details & Response Action
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('donor');
$currentUser = getCurrentUser();
$userId = $currentUser['user_id'];
$requestId = (int)($_GET['id'] ?? 0);

if (!$requestId) {
    header("Location: " . BASE_URL . "/donor/requests.php");
    exit();
}

$stmt = $db->prepare("SELECT donor_id, blood_group, city FROM donors WHERE user_id = ?");
$stmt->execute([$userId]);
$donor = $stmt->fetch();
$donorId = $donor['donor_id'];

// Fetch Request Details
$rStmt = $db->prepare("SELECT r.*, u.full_name as requester_name, u.email as requester_email, u.phone as requester_phone 
                       FROM blood_requests r 
                       JOIN users u ON r.requester_id = u.user_id 
                       WHERE r.request_id = ?");
$rStmt->execute([$requestId]);
$req = $rStmt->fetch();

if (!$req) {
    die("Blood request not found.");
}

$pageTitle = "Blood Request #" . htmlspecialchars($req['unique_request_number']) . " — LifeLink";

// Check existing response
$respStmt = $db->prepare("SELECT * FROM request_responses WHERE request_id = ? AND donor_id = ?");
$respStmt->execute([$requestId, $donorId]);
$existingResponse = $respStmt->fetch();

$error = '';
$success = '';

// Handle Response Action (Confirm & Respond / Decline Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_response'])) {
    $actionType = sanitize($_POST['response_action'] ?? '');
    $message = sanitize($_POST['response_message'] ?? '');

    $consentReviewed = isset($_POST['consent_reviewed']) ? 1 : 0;
    $consentInterest = isset($_POST['consent_interest']) ? 1 : 0;
    $consentMedical = isset($_POST['consent_medical_confirmation']) ? 1 : 0;
    $consentContact = isset($_POST['consent_contact_sharing']) ? 1 : 0;

    if ($actionType === 'confirm') {
        if (!$consentReviewed || !$consentInterest || !$consentMedical || !$consentContact) {
            $error = "Please complete all confirmation checkboxes to acknowledge medical policy and share contact details.";
        } else {
            $actionStatus = 'Accepted';
        }
    } elseif ($actionType === 'decline') {
        $actionStatus = 'Rejected';
    } else {
        $error = "Please choose whether to Confirm & Respond or Decline the request.";
    }

    if (empty($error)) {
        try {
            $db->beginTransaction();

            if ($existingResponse) {
                $upd = $db->prepare("UPDATE request_responses SET response_status = ?, response_message = ?, consent_reviewed = ?, consent_interest = ?, consent_medical_confirmation = ?, consent_contact_sharing = ?, consent_date = NOW(), response_date = NOW() WHERE response_id = ?");
                $upd->execute([$actionStatus, $message, $consentReviewed, $consentInterest, $consentMedical, $consentContact, $existingResponse['response_id']]);
            } else {
                $ins = $db->prepare("INSERT INTO request_responses (request_id, donor_id, response_status, response_message, consent_reviewed, consent_interest, consent_medical_confirmation, consent_contact_sharing, consent_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $ins->execute([$requestId, $donorId, $actionStatus, $message, $consentReviewed, $consentInterest, $consentMedical, $consentContact]);
            }

            // Update blood request status
            if ($actionStatus === 'Accepted') {
                $statusUpd = $db->prepare("UPDATE blood_requests SET status = 'Accepted' WHERE request_id = ?");
                $statusUpd->execute([$requestId]);

                // Notify Requester
                $notifTitle = "Donor Confirmed Willingness to Respond!";
                $notifMsg = "Donor {$currentUser['full_name']} ({$donor['blood_group']}) has responded to request {$req['unique_request_number']}. Note: On-site medical assessment at the healthcare facility is required prior to transfusion.";
                createNotification($db, $req['requester_id'], $notifTitle, $notifMsg, 'response', $requestId);
            } else {
                // If rejected
                $statusUpd = $db->prepare("UPDATE blood_requests SET status = 'Donor Response Received' WHERE request_id = ? AND status = 'Pending'");
                $statusUpd->execute([$requestId]);

                $notifTitle = "Donor Response Update";
                $notifMsg = "A donor was unable to fulfill blood request {$req['unique_request_number']}. Other matching donors remain active.";
                createNotification($db, $req['requester_id'], $notifTitle, $notifMsg, 'response', $requestId);
            }

            $db->commit();
            $success = ($actionStatus === 'Accepted') 
                ? "Your response and consent confirmation have been recorded! The requester has been notified to coordinate on-site medical clearance." 
                : "You have declined this request. Your response has been recorded.";

            // Refresh existing response
            $respStmt->execute([$requestId, $donorId]);
            $existingResponse = $respStmt->fetch();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $error = "Failed to record response: " . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/donor_navbar.php';
?>

<div class="bg-light min-vh-100 py-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="<?= BASE_URL ?>/donor/requests.php" class="text-muted small d-inline-block mb-1">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back to Requests List
                    </a>
                    <h3 class="fw-bold mb-0 text-dark">
                        Request #<?= htmlspecialchars($req['unique_request_number']) ?>
                    </h3>
                </div>
                <div>
                    <?= getUrgencyBadge($req['urgency']) ?>
                    <span class="ms-1"><?= getStatusBadge($req['status']) ?></span>
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

            <div class="row g-4">
                <!-- Request Details Card -->
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white mb-4">
                        <h5 class="fw-bold text-dark mb-4 pb-2 border-bottom">
                            <i class="fa-solid fa-notes-medical text-danger me-2"></i>Patient & Clinical Need
                        </h5>

                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <label class="small text-muted d-block">Patient Name</label>
                                <strong class="fs-5 text-dark"><?= htmlspecialchars($req['patient_name']) ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <label class="small text-muted d-block">Required Blood Group</label>
                                <span class="blood-badge d-inline-flex" style="width: 48px; height: 48px; font-size: 1.2rem;">
                                    <?= htmlspecialchars($req['blood_group']) ?>
                                </span>
                            </div>
                            <div class="col-sm-6">
                                <label class="small text-muted d-block">Quantity Needed</label>
                                <span class="fw-bold fs-5 text-danger"><?= (int)$req['units_required'] ?> Unit(s)</span>
                            </div>
                            <div class="col-sm-6">
                                <label class="small text-muted d-block">Required By Date</label>
                                <span class="fw-bold text-dark fs-6">
                                    <i class="fa-solid fa-calendar-day text-secondary me-1"></i>
                                    <?= date('d M Y', strtotime($req['required_date'])) ?>
                                </span>
                            </div>
                        </div>

                        <h5 class="fw-bold text-dark mb-4 pb-2 border-bottom">
                            <i class="fa-solid fa-hospital text-danger me-2"></i>Hospital Information
                        </h5>

                        <div class="mb-3">
                            <strong class="d-block text-dark fs-6"><?= htmlspecialchars($req['hospital_name']) ?></strong>
                            <p class="text-muted small mb-1"><?= nl2br(htmlspecialchars($req['hospital_address'])) ?></p>
                            <span class="badge bg-light text-dark border">
                                <i class="fa-solid fa-location-dot text-danger me-1"></i>
                                <?= htmlspecialchars($req['city']) ?>, <?= htmlspecialchars($req['state']) ?> - <?= htmlspecialchars($req['pin_code']) ?>
                            </span>
                        </div>

                        <div class="p-3 bg-light rounded-3 mb-4">
                            <strong class="small d-block text-secondary mb-1">Additional Information / Medical Notes</strong>
                            <p class="small text-muted mb-0"><?= nl2br(htmlspecialchars($req['additional_information'] ?: 'No extra instructions provided.')) ?></p>
                        </div>

                        <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                            <i class="fa-solid fa-address-book text-danger me-2"></i>Contact Person
                        </h5>
                        <div class="d-flex justify-content-between align-items-center p-3 rounded-3 border bg-white">
                            <div>
                                <strong class="d-block text-dark"><?= htmlspecialchars($req['contact_person']) ?></strong>
                                <small class="text-muted">Requester / Representative</small>
                            </div>
                            <a href="tel:<?= htmlspecialchars($req['contact_phone']) ?>" class="btn btn-outline-success btn-sm">
                                <i class="fa-solid fa-phone me-1"></i> <?= htmlspecialchars($req['contact_phone']) ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Donor Action & Response Card -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white sticky-top" style="top: 90px;">
                        <h5 class="fw-bold text-dark mb-3">
                            <i class="fa-solid fa-hand-holding-heart text-danger me-2"></i>Donor Confirmation & Response
                        </h5>

                        <?php if ($existingResponse): ?>
                            <div class="alert <?= $existingResponse['response_status'] === 'Accepted' ? 'alert-success' : 'alert-secondary' ?> mb-4">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <strong>Status: <?= $existingResponse['response_status'] === 'Accepted' ? 'Interested & Willing to Respond' : 'Declined' ?></strong>
                                    <small class="text-muted"><?= timeAgo($existingResponse['response_date']) ?></small>
                                </div>
                                <p class="small mb-1">"<?= htmlspecialchars($existingResponse['response_message'] ?: 'Willing to respond.') ?>"</p>
                                <?php if ($existingResponse['response_status'] === 'Accepted'): ?>
                                    <small class="text-success d-block fw-semibold" style="font-size: 0.75rem;">
                                        <i class="fa-solid fa-circle-check me-1"></i> Consent recorded & acknowledged on <?= date('d M Y, h:i A', strtotime($existingResponse['consent_date'] ?? $existingResponse['response_date'])) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <input type="hidden" name="submit_response" value="1">

                            <div class="p-3 bg-light rounded-3 border mb-3">
                                <label class="fw-bold small text-dark d-block mb-2">
                                    <i class="fa-solid fa-clipboard-check text-primary me-1"></i> Donor Confirmation & Consent:
                                </label>
                                <div class="form-check mb-2">
                                    <input class="form-check-input consent-box" type="checkbox" name="consent_reviewed" id="chkReviewed" value="1" <?= (!empty($existingResponse['consent_reviewed']) || !$existingResponse) ? 'checked' : '' ?> required>
                                    <label class="form-check-label small text-secondary" for="chkReviewed">
                                        I have reviewed this blood request.
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input consent-box" type="checkbox" name="consent_interest" id="chkInterest" value="1" <?= (!empty($existingResponse['consent_interest']) || !$existingResponse) ? 'checked' : '' ?> required>
                                    <label class="form-check-label small text-secondary" for="chkInterest">
                                        I am interested in responding to this request.
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input consent-box" type="checkbox" name="consent_medical_confirmation" id="chkMedical" value="1" <?= (!empty($existingResponse['consent_medical_confirmation']) || !$existingResponse) ? 'checked' : '' ?> required>
                                    <label class="form-check-label small text-secondary" for="chkMedical">
                                        I understand that my eligibility will need to be confirmed by medical professionals.
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input consent-box" type="checkbox" name="consent_contact_sharing" id="chkContact" value="1" <?= (!empty($existingResponse['consent_contact_sharing']) || !$existingResponse) ? 'checked' : '' ?> required>
                                    <label class="form-check-label small text-secondary" for="chkContact">
                                        I consent to sharing my necessary contact information with the requester for this request.
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold small text-secondary">Optional Message / Estimated Arrival</label>
                                <textarea name="response_message" rows="3" class="form-control" placeholder="e.g. I am available today and can arrive at the hospital by 2 PM..."><?= htmlspecialchars($existingResponse['response_message'] ?? '') ?></textarea>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" name="response_action" value="confirm" class="btn btn-success py-2 fw-semibold">
                                    <i class="fa-solid fa-circle-check me-1"></i> Confirm & Respond
                                </button>
                                <button type="submit" name="response_action" value="decline" class="btn btn-outline-secondary py-2 small" onclick="document.querySelectorAll('.consent-box').forEach(el => el.removeAttribute('required'));">
                                    <i class="fa-solid fa-ban me-1"></i> Decline Request
                                </button>
                            </div>
                        </form>

                        <div class="mt-3 pt-3 border-top">
                            <small class="text-muted d-block" style="font-size: 0.78rem;">
                                <i class="fa-solid fa-shield-halved text-danger me-1"></i>
                                <strong>Medical Notice:</strong> Confirming your response indicates your willingness to assist. Final pre-donation eligibility is determined by qualified healthcare personnel at the hospital or blood bank.
                            </small>
                        </div>

                        <div class="mt-4 pt-3 border-top">
                            <small class="text-muted d-block">
                                <i class="fa-solid fa-shield-halved text-primary me-1"></i>
                                By accepting, your contact details and pledge will be communicated to the patient's representative.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
