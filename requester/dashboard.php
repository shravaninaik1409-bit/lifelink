<?php
/**
 * LifeLink - Requester Dashboard
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('requester');
$currentUser = getCurrentUser();
$userId = $currentUser['user_id'];
$pageTitle = "Requester Dashboard — LifeLink";

// Statistics
$totalRequests = 0;
$activeRequests = 0;
$totalResponses = 0;
$completedRequests = 0;

try {
    $stmtTotal = $db->prepare("SELECT COUNT(*) FROM blood_requests WHERE requester_id = ?");
    $stmtTotal->execute([$userId]);
    $totalRequests = (int)$stmtTotal->fetchColumn();

    $stmtActive = $db->prepare("SELECT COUNT(*) FROM blood_requests WHERE requester_id = ? AND status IN ('Pending', 'Donors Notified', 'Donor Response Received', 'Accepted', 'In Progress')");
    $stmtActive->execute([$userId]);
    $activeRequests = (int)$stmtActive->fetchColumn();

    $stmtComp = $db->prepare("SELECT COUNT(*) FROM blood_requests WHERE requester_id = ? AND status = 'Completed'");
    $stmtComp->execute([$userId]);
    $completedRequests = (int)$stmtComp->fetchColumn();

    $stmtResp = $db->prepare("SELECT COUNT(*) FROM request_responses rr JOIN blood_requests br ON rr.request_id = br.request_id WHERE br.requester_id = ?");
    $stmtResp->execute([$userId]);
    $totalResponses = (int)$stmtResp->fetchColumn();

    // Active requests table
    $stmtReqs = $db->prepare("SELECT r.*, 
                             (SELECT COUNT(*) FROM request_responses rr WHERE rr.request_id = r.request_id) as total_responses,
                             (SELECT COUNT(*) FROM request_responses rr WHERE rr.request_id = r.request_id AND rr.response_status = 'Accepted') as accepted_responses
                             FROM blood_requests r
                             WHERE r.requester_id = ?
                             ORDER BY (r.urgency = 'Emergency') DESC, r.created_at DESC LIMIT 5");
    $stmtReqs->execute([$userId]);
    $recentRequests = $stmtReqs->fetchAll();

    // Recent donor responses for this requester's requests
    $stmtDonorResp = $db->prepare("SELECT rr.*, r.unique_request_number, r.patient_name, u.full_name as donor_name, u.phone as donor_phone, d.blood_group, d.city as donor_city 
                                  FROM request_responses rr 
                                  JOIN blood_requests r ON rr.request_id = r.request_id 
                                  JOIN donors d ON rr.donor_id = d.donor_id 
                                  JOIN users u ON d.user_id = u.user_id 
                                  WHERE r.requester_id = ? 
                                  ORDER BY rr.response_date DESC LIMIT 5");
    $stmtDonorResp->execute([$userId]);
    $recentResponses = $stmtDonorResp->fetchAll();

} catch (Exception $e) {
    // Handle error
}

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

            <!-- Requester Header Banner -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 text-white" style="background: linear-gradient(135deg, #0B192C 0%, #1E3A5F 100%);">
                <div class="row align-items-center g-3">
                    <div class="col-lg-8">
                        <span class="badge bg-danger bg-opacity-75 text-white px-2 py-1 mb-2">Requester / Hospital Portal</span>
                        <h3 class="fw-bold mb-1 text-white">Welcome, <?= htmlspecialchars($currentUser['full_name']) ?></h3>
                        <p class="text-white-50 mb-0 small">
                            Broadcast urgent blood requirements, track live donor responses, and communicate with verified voluntary donors.
                        </p>
                    </div>
                    <div class="col-lg-4 text-lg-end">
                        <a href="<?= BASE_URL ?>/requester/create_request.php" class="btn btn-lifelink-red">
                            <i class="fa-solid fa-plus-circle me-1"></i> Post New Blood Request
                        </a>
                    </div>
                </div>
            </div>

            <!-- Stats Metrics -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="metric-card">
                        <div>
                            <div class="metric-label">Active Requests</div>
                            <div class="metric-value text-danger"><?= number_format($activeRequests) ?></div>
                        </div>
                        <div class="metric-icon-box bg-danger bg-opacity-10 text-danger">
                            <i class="fa-solid fa-heart-pulse"></i>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="metric-card">
                        <div>
                            <div class="metric-label">Total Responses</div>
                            <div class="metric-value text-primary"><?= number_format($totalResponses) ?></div>
                        </div>
                        <div class="metric-icon-box bg-primary bg-opacity-10 text-primary">
                            <i class="fa-solid fa-reply-all"></i>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="metric-card">
                        <div>
                            <div class="metric-label">Fulfillments Completed</div>
                            <div class="metric-value text-success"><?= number_format($completedRequests) ?></div>
                        </div>
                        <div class="metric-icon-box bg-success bg-opacity-10 text-success">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="metric-card">
                        <div>
                            <div class="metric-label">All-Time Requests</div>
                            <div class="metric-value text-dark"><?= number_format($totalRequests) ?></div>
                        </div>
                        <div class="metric-icon-box bg-secondary bg-opacity-10 text-secondary">
                            <i class="fa-solid fa-list-check"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Tables -->
            <div class="row g-4">
                <!-- Active Requests Table -->
                <div class="col-lg-7">
                    <div class="content-card">
                        <div class="content-card-header">
                            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-file-waveform text-danger me-2"></i>My Blood Requests</h5>
                            <a href="<?= BASE_URL ?>/requester/my_requests.php" class="btn btn-outline-danger btn-sm">View All</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID & Patient</th>
                                        <th>Blood Group</th>
                                        <th>Hospital</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentRequests)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                You haven't submitted any blood requests yet.<br>
                                                <a href="<?= BASE_URL ?>/requester/create_request.php" class="btn btn-lifelink-red btn-sm mt-2">Create Request Now</a>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentRequests as $r): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-light text-dark border d-block mb-1" style="width: fit-content;"><?= htmlspecialchars($r['unique_request_number']) ?></span>
                                                    <strong class="text-dark"><?= htmlspecialchars($r['patient_name']) ?></strong>
                                                    <div><?= getUrgencyBadge($r['urgency']) ?></div>
                                                </td>
                                                <td>
                                                    <span class="blood-badge" style="width: 42px; height: 42px; font-size: 1rem;">
                                                        <?= htmlspecialchars($r['blood_group']) ?>
                                                    </span>
                                                    <small class="d-block text-muted text-center mt-1"><?= (int)$r['units_required'] ?> Unit(s)</small>
                                                </td>
                                                <td>
                                                    <small class="text-dark d-block fw-semibold"><?= htmlspecialchars($r['hospital_name']) ?></small>
                                                    <small class="text-muted"><?= htmlspecialchars($r['city']) ?></small>
                                                </td>
                                                <td>
                                                    <?= getStatusBadge($r['status']) ?>
                                                    <?php if ($r['total_responses'] > 0): ?>
                                                        <small class="d-block text-success mt-1 fw-semibold">
                                                            <i class="fa-solid fa-check-double me-1"></i><?= (int)$r['accepted_responses'] ?> Donor(s) Accepted
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="<?= BASE_URL ?>/requester/request_details.php?id=<?= $r['request_id'] ?>" class="btn btn-sm btn-outline-danger">
                                                        Track
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Donor Responses -->
                <div class="col-lg-5">
                    <div class="content-card">
                        <div class="content-card-header">
                            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-handshake text-success me-2"></i>Incoming Donor Responses</h5>
                        </div>
                        <div class="content-card-body p-0">
                            <?php if (empty($recentResponses)): ?>
                                <div class="p-4 text-center text-muted small">
                                    No donor responses received yet. Donors in the matching city are notified when you post requests.
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recentResponses as $resp): ?>
                                        <div class="list-group-item p-3">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <div>
                                                    <strong class="text-dark"><?= htmlspecialchars($resp['donor_name']) ?></strong>
                                                    <span class="badge bg-danger ms-1"><?= htmlspecialchars($resp['blood_group']) ?></span>
                                                </div>
                                                <?php if ($resp['response_status'] === 'Accepted'): ?>
                                                    <span class="badge bg-success">Accepted</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Declined</span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted d-block mb-1">
                                                For Patient <strong><?= htmlspecialchars($resp['patient_name']) ?></strong> (<?= htmlspecialchars($resp['unique_request_number']) ?>)
                                            </small>
                                            <p class="small text-secondary mb-2 fst-italic">"<?= htmlspecialchars($resp['response_message'] ?: 'Ready to assist.') ?>"</p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted"><?= timeAgo($resp['response_date']) ?></small>
                                                <?php if ($resp['response_status'] === 'Accepted'): ?>
                                                    <a href="tel:<?= htmlspecialchars($resp['donor_phone']) ?>" class="btn btn-sm btn-outline-success py-0 px-2" style="font-size: 0.75rem;">
                                                        <i class="fa-solid fa-phone me-1"></i> Call Donor
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
