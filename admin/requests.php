<?php
/**
 * LifeLink - Admin Blood Requests Management
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
$currentUser = getCurrentUser();
$adminId = $currentUser['user_id'];
$pageTitle = "Blood Requests Management — LifeLink";

$statusFilter = trim($_GET['status'] ?? '');
$urgencyFilter = trim($_GET['urgency'] ?? '');
$search = trim($_GET['search'] ?? '');

$sql = "SELECT r.*, u.full_name as requester_name, u.email as requester_email, u.phone as requester_phone,
        (SELECT COUNT(*) FROM request_responses rr WHERE rr.request_id = r.request_id) as total_responses,
        (SELECT COUNT(*) FROM request_responses rr WHERE rr.request_id = r.request_id AND rr.response_status = 'Accepted') as accepted_responses
        FROM blood_requests r
        JOIN users u ON r.requester_id = u.user_id
        WHERE 1=1";
$params = [];

if (!empty($statusFilter)) {
    $sql .= " AND r.status = ?";
    $params[] = $statusFilter;
}

if (!empty($urgencyFilter)) {
    $sql .= " AND r.urgency = ?";
    $params[] = $urgencyFilter;
}

if (!empty($search)) {
    $sql .= " AND (r.patient_name LIKE ? OR r.hospital_name LIKE ? OR r.city LIKE ? OR r.unique_request_number LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY (r.urgency = 'Emergency') DESC, (r.status = 'Pending') DESC, r.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout">
    <?php require_once __DIR__ . '/../includes/dashboard_sidebar.php'; ?>

    <div class="dashboard-main">
        <?php require_once __DIR__ . '/../includes/dashboard_topbar.php'; ?>

        <div class="dashboard-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark">Blood Requests Management</h3>
                    <p class="text-muted small mb-0">Monitor active requests, clinical transfusions, and emergency responses.</p>
                </div>
            </div>

            <?php if (!empty($_SESSION['flash_msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show small" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($_SESSION['flash_msg']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_msg']); ?>
            <?php endif; ?>

            <!-- Search & Filters -->
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-4">
                <form method="GET" action="" class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search patient, hospital, city, or ID..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Donors Notified" <?= $statusFilter === 'Donors Notified' ? 'selected' : '' ?>>Donors Notified</option>
                            <option value="Donor Response Received" <?= $statusFilter === 'Donor Response Received' ? 'selected' : '' ?>>Response Received</option>
                            <option value="Accepted" <?= $statusFilter === 'Accepted' ? 'selected' : '' ?>>Accepted</option>
                            <option value="Completed" <?= $statusFilter === 'Completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="Cancelled" <?= $statusFilter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="urgency" class="form-select form-select-sm">
                            <option value="">All Urgency Levels</option>
                            <option value="Emergency" <?= $urgencyFilter === 'Emergency' ? 'selected' : '' ?>>Emergency Only</option>
                            <option value="Urgent" <?= $urgencyFilter === 'Urgent' ? 'selected' : '' ?>>Urgent</option>
                            <option value="Normal" <?= $urgencyFilter === 'Normal' ? 'selected' : '' ?>>Normal</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-lifelink-red btn-sm flex-grow-1">Filter</button>
                        <a href="<?= BASE_URL ?>/admin/requests.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Table Card -->
            <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Request ID</th>
                                <th>Patient & Requester</th>
                                <th>Blood & Units</th>
                                <th>Hospital & Location</th>
                                <th>Urgency</th>
                                <th>Status</th>
                                <th>Hospital Confirmation</th>
                                <th>Responses</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($requests)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">No blood requests found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($requests as $r): ?>
                                    <tr class="<?= $r['urgency'] === 'Emergency' ? 'table-danger table-opacity-10' : '' ?>">
                                        <td>
                                            <span class="badge bg-light text-dark border fw-bold"><?= htmlspecialchars($r['unique_request_number']) ?></span>
                                            <small class="text-muted d-block"><?= date('d M Y', strtotime($r['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <strong class="d-block text-dark"><?= htmlspecialchars($r['patient_name']) ?></strong>
                                            <small class="text-muted">By <?= htmlspecialchars($r['requester_name']) ?></small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="blood-badge" style="width: 36px; height: 36px; font-size: 0.95rem;"><?= htmlspecialchars($r['blood_group']) ?></span>
                                                <span class="fw-bold text-danger"><?= (int)$r['units_required'] ?> Unit(s)</span>
                                            </div>
                                        </td>
                                        <td>
                                            <small class="d-block text-dark fw-semibold"><?= htmlspecialchars($r['hospital_name']) ?></small>
                                            <small class="text-muted"><?= htmlspecialchars($r['city']) ?>, <?= htmlspecialchars($r['state']) ?></small>
                                        </td>
                                        <td><?= getUrgencyBadge($r['urgency']) ?></td>
                                        <td><?= getStatusBadge($r['status']) ?></td>
                                        <td><?= getHospitalConfirmationBadge($r['hospital_confirmed'] ?? 0) ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= (int)$r['accepted_responses'] ?> / <?= (int)$r['total_responses'] ?> Donors
                                            </span>
                                        </td>
                                        <td>
                                            <a href="<?= BASE_URL ?>/admin/request_details.php?id=<?= $r['request_id'] ?>" class="btn btn-sm btn-outline-danger">
                                                Manage
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
