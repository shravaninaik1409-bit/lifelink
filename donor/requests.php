<?php
/**
 * LifeLink - Donor Blood Requests Feed
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('donor');
$currentUser = getCurrentUser();
$userId = $currentUser['user_id'];
$pageTitle = "Blood Requests — LifeLink";

$stmt = $db->prepare("SELECT donor_id, blood_group, city FROM donors WHERE user_id = ?");
$stmt->execute([$userId]);
$donor = $stmt->fetch();
$donorId = $donor['donor_id'];
$bloodGroup = $donor['blood_group'];

$urgencyFilter = trim($_GET['urgency'] ?? '');
$responseFilter = trim($_GET['response'] ?? '');
$search = trim($_GET['search'] ?? '');

$sql = "SELECT r.*, 
        (SELECT response_status FROM request_responses rr WHERE rr.request_id = r.request_id AND rr.donor_id = ?) as my_response,
        (SELECT response_date FROM request_responses rr WHERE rr.request_id = r.request_id AND rr.donor_id = ?) as my_response_date
        FROM blood_requests r
        WHERE r.blood_group = ? AND r.status != 'Cancelled'";
$params = [$donorId, $donorId, $bloodGroup];

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

$sql .= " ORDER BY (r.urgency = 'Emergency') DESC, (r.urgency = 'Urgent') DESC, r.created_at DESC";

$requestsStmt = $db->prepare($sql);
$requestsStmt->execute($params);
$requests = $requestsStmt->fetchAll();

// Optional filter by response status in memory if requested
if (!empty($responseFilter)) {
    $requests = array_filter($requests, function($item) use ($responseFilter) {
        if ($responseFilter === 'unresponded') return empty($item['my_response']);
        return strtolower($item['my_response'] ?? '') === strtolower($responseFilter);
    });
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/donor_navbar.php';
?>

<div class="bg-light min-vh-100 py-4">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark">Matching Blood Requests</h3>
                    <p class="text-muted small mb-0">Live requests matching your blood group: <strong class="text-danger"><?= htmlspecialchars($bloodGroup) ?></strong></p>
                </div>
            </div>

            <!-- Search & Filters -->
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-4">
                <form method="GET" action="" class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search patient, hospital, city or ID..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="urgency" class="form-select form-select-sm">
                            <option value="">All Urgency Levels</option>
                            <option value="Emergency" <?= $urgencyFilter === 'Emergency' ? 'selected' : '' ?>>Emergency Only</option>
                            <option value="Urgent" <?= $urgencyFilter === 'Urgent' ? 'selected' : '' ?>>Urgent</option>
                            <option value="Normal" <?= $urgencyFilter === 'Normal' ? 'selected' : '' ?>>Normal</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="response" class="form-select form-select-sm">
                            <option value="">All Response States</option>
                            <option value="unresponded" <?= $responseFilter === 'unresponded' ? 'selected' : '' ?>>Not Responded Yet</option>
                            <option value="accepted" <?= $responseFilter === 'accepted' ? 'selected' : '' ?>>Accepted Pledges</option>
                            <option value="rejected" <?= $responseFilter === 'rejected' ? 'selected' : '' ?>>Declined</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-lifelink-red btn-sm flex-grow-1">Filter</button>
                        <a href="<?= BASE_URL ?>/donor/requests.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Requests List -->
            <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Request ID</th>
                                <th>Patient & Hospital</th>
                                <th>Location</th>
                                <th>Required Date</th>
                                <th>Urgency & Units</th>
                                <th>My Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($requests)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-inbox fa-3x mb-3 text-secondary opacity-50"></i>
                                        <p class="mb-0">No blood requests matching your filter criteria.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($requests as $r): ?>
                                    <tr class="<?= $r['urgency'] === 'Emergency' ? 'table-danger table-opacity-10' : '' ?>">
                                        <td>
                                            <span class="badge bg-light text-dark border fw-bold"><?= htmlspecialchars($r['unique_request_number']) ?></span>
                                        </td>
                                        <td>
                                            <strong class="d-block text-dark"><?= htmlspecialchars($r['patient_name']) ?></strong>
                                            <small class="text-muted"><i class="fa-solid fa-hospital me-1 text-danger"></i><?= htmlspecialchars($r['hospital_name']) ?></small>
                                        </td>
                                        <td>
                                            <small class="text-dark d-block"><?= htmlspecialchars($r['city']) ?>, <?= htmlspecialchars($r['state']) ?></small>
                                            <small class="text-muted"><?= htmlspecialchars($r['pin_code']) ?></small>
                                        </td>
                                        <td>
                                            <small class="text-secondary fw-semibold">
                                                <i class="fa-solid fa-calendar me-1"></i><?= date('d M Y', strtotime($r['required_date'])) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="fw-bold me-1 text-dark"><?= (int)$r['units_required'] ?> Unit(s)</span>
                                            <?= getUrgencyBadge($r['urgency']) ?>
                                        </td>
                                        <td>
                                            <?php if ($r['my_response'] === 'Accepted'): ?>
                                                <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i>Accepted</span>
                                            <?php elseif ($r['my_response'] === 'Rejected'): ?>
                                                <span class="badge bg-secondary">Declined</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark"><i class="fa-solid fa-hourglass-start me-1"></i>Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?= BASE_URL ?>/donor/request_details.php?id=<?= $r['request_id'] ?>" class="btn btn-sm btn-lifelink-red">
                                                Review & Act
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div></div></div></div>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>

