<?php
/**
 * LifeLink - Admin Request Details & Status Override
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
$currentUser = getCurrentUser();
$adminId = $currentUser['user_id'];
$requestId = (int)($_GET['id'] ?? 0);

if (!$requestId) {
    header("Location: " . BASE_URL . "/admin/requests.php");
    exit();
}

// Fetch request record
$stmt = $db->prepare("SELECT r.*, u.full_name as requester_name, u.email as requester_email, u.phone as requester_phone 
                       FROM blood_requests r 
                       JOIN users u ON r.requester_id = u.user_id 
                       WHERE r.request_id = ?");
$stmt->execute([$requestId]);
$req = $stmt->fetch();

if (!$req) {
    die("Blood request not found.");
}

$pageTitle = "Admin Review #" . htmlspecialchars($req['unique_request_number']) . " — LifeLink";
$error = '';
$success = '';

// Handle manual status update by admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = sanitize($_POST['status'] ?? '');
    $notes = sanitize($_POST['admin_notes'] ?? '');

    $validStatuses = ['Pending', 'Donors Notified', 'Donor Response Received', 'Accepted', 'In Progress', 'Completed', 'Cancelled'];
    if (in_array($newStatus, $validStatuses)) {
        try {
            $upd = $db->prepare("UPDATE blood_requests SET status = ?, updated_at = NOW() WHERE request_id = ?");
            $upd->execute([$newStatus, $requestId]);

            logAdminAction($db, $adminId, "Request Status Override", "Changed Request {$req['unique_request_number']} to {$newStatus}. Notes: {$notes}");

            // Notify requester of administrative status update
            createNotification($db, $req['requester_id'], "Request Status Updated by Admin", "Your blood request {$req['unique_request_number']} status was set to '{$newStatus}'.", "status", $requestId);

            $_SESSION['flash_msg'] = "Request status updated to '{$newStatus}'.";
            header("Location: " . BASE_URL . "/admin/request_details.php?id=" . $requestId);
            exit();
        } catch (Exception $e) {
            $error = "Failed to update status: " . $e->getMessage();
        }
    }
}

// Handle hospital confirmation update by admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_hospital'])) {
    $hConfirmed = isset($_POST['hospital_confirmed']) ? 1 : 0;
    $hNotes = sanitize($_POST['hospital_notes'] ?? '');

    try {
        $upd = $db->prepare("UPDATE blood_requests SET hospital_confirmed = ?, hospital_confirmed_at = NOW(), hospital_notes = ? WHERE request_id = ?");
        $upd->execute([$hConfirmed, $hNotes, $requestId]);

        logAdminAction($db, $adminId, "Hospital Coordination Updated", "Request #{$req['unique_request_number']} confirmed={$hConfirmed}. Notes: {$hNotes}");
        $_SESSION['flash_msg'] = $hConfirmed ? "Hospital coordination confirmed successfully!" : "Hospital coordination set to Pending.";
        header("Location: " . BASE_URL . "/admin/request_details.php?id=" . $requestId);
        exit();
    } catch (Exception $e) {
        $error = "Failed to update hospital status: " . $e->getMessage();
    }
}

// Fetch donor responses
$respStmt = $db->prepare("SELECT rr.*, d.blood_group, d.city as donor_city, u.full_name as donor_name, u.phone as donor_phone 
                          FROM request_responses rr 
                          JOIN donors d ON rr.donor_id = d.donor_id 
                          JOIN users u ON d.user_id = u.user_id 
                          WHERE rr.request_id = ? 
                          ORDER BY rr.response_date DESC");
$respStmt->execute([$requestId]);
$responses = $respStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout">
    <?php require_once __DIR__ . '/../includes/dashboard_sidebar.php'; ?>

    <div class="dashboard-main">
        <?php require_once __DIR__ . '/../includes/dashboard_topbar.php'; ?>

        <div class="dashboard-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                <div>
                    <a href="<?= BASE_URL ?>/admin/requests.php" class="text-muted small d-inline-block mb-1">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back to Requests
                    </a>
                    <h3 class="fw-bold mb-0 text-dark">
                        Manage Request #<?= htmlspecialchars($req['unique_request_number']) ?>
                    </h3>
                </div>
                <div>
                    <?= getUrgencyBadge($req['urgency']) ?>
                    <?= getStatusBadge($req['status']) ?>
                </div>
            </div>

            <?php if (!empty($_SESSION['flash_msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show small" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($_SESSION['flash_msg']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_msg']); ?>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-1"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Request Summary -->
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white mb-4">
                        <h5 class="fw-bold text-dark mb-4 pb-2 border-bottom">
                            <i class="fa-solid fa-circle-info text-danger me-2"></i>Clinical & Patient Details
                        </h5>

                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <label class="small text-muted d-block">Patient Name</label>
                                <strong class="fs-5 text-dark"><?= htmlspecialchars($req['patient_name']) ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <label class="small text-muted d-block">Required Group & Units</label>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="blood-badge" style="width: 42px; height: 42px; font-size: 1.1rem;"><?= htmlspecialchars($req['blood_group']) ?></span>
                                    <span class="fw-bold text-danger fs-5"><?= (int)$req['units_required'] ?> Unit(s)</span>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="small text-muted d-block">Required By Date</label>
                                <strong class="text-dark fs-6"><?= date('d M Y', strtotime($req['required_date'])) ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <label class="small text-muted d-block">Submission Timestamp</label>
                                <small class="text-muted"><?= date('d M Y, h:i A', strtotime($req['created_at'])) ?></small>
                            </div>
                        </div>

                        <div class="p-3 bg-light rounded-3 mb-4">
                            <strong class="d-block text-dark fw-semibold mb-1"><i class="fa-solid fa-hospital text-danger me-1"></i><?= htmlspecialchars($req['hospital_name']) ?></strong>
                            <p class="small text-muted mb-1"><?= nl2br(htmlspecialchars($req['hospital_address'])) ?></p>
                            <small class="text-muted"><?= htmlspecialchars($req['city']) ?>, <?= htmlspecialchars($req['state']) ?> - <?= htmlspecialchars($req['pin_code']) ?></small>
                        </div>

                        <div class="p-3 border rounded-3 mb-4">
                            <label class="small text-muted d-block">Submitted by Requester</label>
                            <strong class="text-dark"><?= htmlspecialchars($req['requester_name']) ?></strong> &bull; <?= htmlspecialchars($req['requester_email']) ?> &bull; <?= htmlspecialchars($req['requester_phone']) ?>
                        </div>

                        <!-- Responses Table -->
                        <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                            <i class="fa-solid fa-reply-all text-success me-2"></i>Donor Responses (<?= count($responses) ?>)
                        </h5>
                        <?php if (empty($responses)): ?>
                            <p class="text-muted small">No donor responses recorded yet.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($responses as $resp): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <div>
                                                <strong class="text-dark"><?= htmlspecialchars($resp['donor_name']) ?></strong>
                                                <span class="badge bg-danger ms-1"><?= htmlspecialchars($resp['blood_group']) ?></span>
                                                <small class="text-muted ms-1">(<?= htmlspecialchars($resp['donor_city']) ?>)</small>
                                            </div>
                                            <span class="badge <?= $resp['response_status'] === 'Accepted' ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= htmlspecialchars($resp['response_status']) ?>
                                            </span>
                                        </div>
                                        <p class="small text-secondary mb-1 fst-italic">"<?= htmlspecialchars($resp['response_message'] ?: 'No message') ?>"</p>
                                        <small class="text-muted"><?= timeAgo($resp['response_date']) ?> &bull; Contact: <?= htmlspecialchars($resp['donor_phone']) ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Admin Action Card -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white sticky-top" style="top: 90px;">
                        <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-sliders text-danger me-2"></i>Status Override</h5>
                        <p class="small text-muted mb-3">Administrators can manually update the lifecycle status of this blood request.</p>

                        <form method="POST" action="">
                            <input type="hidden" name="update_status" value="1">
                            <div class="mb-3">
                                <label class="form-label fw-semibold small text-secondary">Set Status</label>
                                <select name="status" class="form-select">
                                    <option value="Pending" <?= $req['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="Donors Notified" <?= $req['status'] === 'Donors Notified' ? 'selected' : '' ?>>Donors Notified</option>
                                    <option value="Donor Response Received" <?= $req['status'] === 'Donor Response Received' ? 'selected' : '' ?>>Donor Response Received</option>
                                    <option value="Accepted" <?= $req['status'] === 'Accepted' ? 'selected' : '' ?>>Accepted</option>
                                    <option value="In Progress" <?= $req['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="Completed" <?= $req['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="Cancelled" <?= $req['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold small text-secondary">Audit Reason / Notes</label>
                                <textarea name="admin_notes" rows="3" class="form-control" placeholder="e.g. Verified hospital fulfillment..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-lifelink-red w-100 py-2 fw-semibold">
                                Update Request Status
                            </button>
                        </form>

                        <!-- Hospital / Blood Bank Coordination Panel -->
                        <div class="mt-4 pt-4 border-top">
                            <h6 class="fw-bold text-dark mb-2">
                                <i class="fa-solid fa-hospital-user text-primary me-2"></i>Hospital / Blood Bank Coordination
                            </h6>
                            <p class="small text-muted mb-3">Record whether the attending healthcare facility has confirmed the match and scheduled on-site clinical screening.</p>

                            <form method="POST" action="">
                                <input type="hidden" name="update_hospital" value="1">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" role="switch" name="hospital_confirmed" id="switchHospital" value="1" <?= !empty($req['hospital_confirmed']) ? 'checked' : '' ?>>
                                    <label class="form-check-label small fw-semibold text-dark" for="switchHospital">
                                        Mark as Hospital Confirmed
                                    </label>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold small text-secondary">Hospital Blood Bank / Staff Notes</label>
                                    <textarea name="hospital_notes" rows="2" class="form-control form-control-sm" placeholder="e.g. Blood bank desk confirms unit reservation; clinical screening staff prepared..."><?= htmlspecialchars($req['hospital_notes'] ?? '') ?></textarea>
                                </div>

                                <button type="submit" class="btn btn-outline-primary btn-sm w-100 py-2 fw-semibold">
                                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Hospital Coordination
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
