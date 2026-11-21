<?php
/**
 * LifeLink - My Blood Requests List
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('requester');
$currentUser = getCurrentUser();
$userId = $currentUser['user_id'];
$pageTitle = "My Blood Requests — LifeLink";

// Handle request cancellation via secure POST + CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel' && isset($_POST['id'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        $_SESSION['flash_error'] = "Security validation failed. Please refresh and try again.";
        header("Location: " . BASE_URL . "/requester/my_requests.php");
        exit();
    }

    $cancelId = (int)$_POST['id'];
    try {
        // Enforce requester ownership and ensure non-completed request
        $cStmt = $db->prepare("UPDATE blood_requests SET status = 'Cancelled' WHERE request_id = ? AND requester_id = ? AND status != 'Completed'");
        $cStmt->execute([$cancelId, $userId]);
        if ($cStmt->rowCount() > 0) {
            $_SESSION['flash_msg'] = "Blood request was cancelled successfully.";
        } else {
            $_SESSION['flash_error'] = "Request could not be cancelled or has already been completed.";
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Could not cancel request: " . $e->getMessage();
    }
    header("Location: " . BASE_URL . "/requester/my_requests.php");
    exit();
}

$statusFilter = trim($_GET['status'] ?? '');
$urgencyFilter = trim($_GET['urgency'] ?? '');
$search = trim($_GET['search'] ?? '');

$sql = "SELECT r.*, 
        (SELECT COUNT(*) FROM request_responses rr WHERE rr.request_id = r.request_id) as total_responses,
        (SELECT COUNT(*) FROM request_responses rr WHERE rr.request_id = r.request_id AND rr.response_status = 'Accepted') as accepted_responses
        FROM blood_requests r
        WHERE r.requester_id = ?";
$params = [$userId];

if (!empty($statusFilter)) {
    $sql .= " AND r.status = ?";
    $params[] = $statusFilter;
}

if (!empty($urgencyFilter)) {
    $sql .= " AND r.urgency = ?";
    $params[] = $urgencyFilter;
}

if (!empty($search)) {
    $sql .= " AND (r.patient_name LIKE ? OR r.hospital_name LIKE ? OR r.unique_request_number LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY (r.urgency = 'Emergency') DESC, r.created_at DESC";

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
                    <h3 class="fw-bold mb-1 text-dark">My Blood Requests</h3>
                    <p class="text-muted small mb-0">Track real-time donor responses and manage your active requests.</p>
                </div>
                <a href="<?= BASE_URL ?>/requester/create_request.php" class="btn btn-lifelink-red">
                    <i class="fa-solid fa-plus-circle me-1"></i> New Blood Request
                </a>
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

            <!-- Filters Bar -->
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-4">
                <form method="GET" action="" class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search patient, hospital, or ID..." value="<?= htmlspecialchars($search) ?>">
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
                            <option value="Emergency" <?= $urgencyFilter === 'Emergency' ? 'selected' : '' ?>>Emergency</option>
                            <option value="Urgent" <?= $urgencyFilter === 'Urgent' ? 'selected' : '' ?>>Urgent</option>
                            <option value="Normal" <?= $urgencyFilter === 'Normal' ? 'selected' : '' ?>>Normal</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-lifelink-red btn-sm flex-grow-1">Filter</button>
                        <a href="<?= BASE_URL ?>/requester/my_requests.php" class="btn btn-outline-secondary btn-sm">Reset</a>
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
                                <th>Patient Name</th>
                                <th>Blood & Units</th>
                                <th>Hospital</th>
                                <th>Required By</th>
                                <th>Urgency & Status</th>
                                <th>Responses</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($requests)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-inbox fa-3x mb-3 text-secondary opacity-50"></i>
                                        <p class="mb-0">No blood requests found. <a href="<?= BASE_URL ?>/requester/create_request.php">Create your first request here.</a></p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($requests as $r): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-dark border fw-bold"><?= htmlspecialchars($r['unique_request_number']) ?></span>
                                        </td>
                                        <td>
                                            <strong class="text-dark d-block"><?= htmlspecialchars($r['patient_name']) ?></strong>
                                            <small class="text-muted">Contact: <?= htmlspecialchars($r['contact_person']) ?></small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="blood-badge" style="width: 36px; height: 36px; font-size: 0.9rem;">
                                                    <?= htmlspecialchars($r['blood_group']) ?>
                                                </span>
                                                <span class="fw-bold text-danger"><?= (int)$r['units_required'] ?> Unit(s)</span>
                                            </div>
                                        </td>
                                        <td>
                                            <small class="d-block text-dark fw-semibold"><?= htmlspecialchars($r['hospital_name']) ?></small>
                                            <small class="text-muted"><?= htmlspecialchars($r['city']) ?></small>
                                        </td>
                                        <td>
                                            <small class="text-dark fw-semibold">
                                                <?= date('d M Y', strtotime($r['required_date'])) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="mb-1"><?= getUrgencyBadge($r['urgency']) ?></div>
                                            <?= getStatusBadge($r['status']) ?>
                                        </td>
                                        <td>
                                            <?php if ($r['total_responses'] > 0): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                                    <i class="fa-solid fa-users me-1"></i><?= (int)$r['accepted_responses'] ?> Accepted
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted small">0 Responses</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="<?= BASE_URL ?>/requester/request_details.php?id=<?= $r['request_id'] ?>" class="btn btn-sm btn-outline-danger" title="Track Request Timeline">
                                                    Track
                                                </a>
                                                <?php if (!in_array($r['status'], ['Completed', 'Cancelled'])): ?>
                                                    <form method="POST" action="" class="d-inline m-0 p-0">
                                                        <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                                        <input type="hidden" name="action" value="cancel">
                                                        <input type="hidden" name="id" value="<?= $r['request_id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-light text-danger" onclick="return confirmAction('Are you sure you want to cancel this request?');" title="Cancel Request">
                                                            <i class="fa-solid fa-ban"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
