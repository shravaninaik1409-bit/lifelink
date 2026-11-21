<?php
/**
 * LifeLink - Admin Reports & Statistical Intelligence
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
$currentUser = getCurrentUser();
$pageTitle = "Reports & Analytics — LifeLink";

// Blood Group Statistics
$bgStats = $db->query("SELECT d.blood_group, 
                       COUNT(d.donor_id) as total_donors,
                       SUM(CASE WHEN d.availability = 'Available' THEN 1 ELSE 0 END) as available_donors,
                       (SELECT COUNT(*) FROM blood_requests br WHERE br.blood_group = d.blood_group) as total_requests,
                       (SELECT COUNT(*) FROM blood_requests br WHERE br.blood_group = d.blood_group AND br.status = 'Completed') as completed_requests
                       FROM donors d
                       GROUP BY d.blood_group
                       ORDER BY total_donors DESC")->fetchAll();

// City Statistics
$cityStats = $db->query("SELECT city, state, COUNT(*) as donor_count,
                         SUM(CASE WHEN availability = 'Available' THEN 1 ELSE 0 END) as available_count
                         FROM donors
                         GROUP BY city, state
                         ORDER BY donor_count DESC LIMIT 10")->fetchAll();

// Urgency Fulfillment
$urgencyStats = $db->query("SELECT urgency, COUNT(*) as total,
                           SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
                           SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
                           FROM blood_requests
                           GROUP BY urgency")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout">
    <?php require_once __DIR__ . '/../includes/dashboard_sidebar.php'; ?>

    <div class="dashboard-main">
        <?php require_once __DIR__ . '/../includes/dashboard_topbar.php'; ?>

        <div class="dashboard-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark">Analytical Reports</h3>
                    <p class="text-muted small mb-0">System performance, demand vs supply ratios, and geographic distribution.</p>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print();">
                    <i class="fa-solid fa-print me-1"></i> Print / Export Report
                </button>
            </div>

            <!-- Blood Group Demand vs Supply -->
            <div class="card border-0 shadow-sm rounded-4 bg-white mb-4 overflow-hidden">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-scale-balanced text-danger me-2"></i>Blood Group Demand vs Supply Matrix</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Blood Group</th>
                                <th>Registered Donors</th>
                                <th>Available Donors</th>
                                <th>Total Requests</th>
                                <th>Fulfilled Requests</th>
                                <th>Fulfillment Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bgStats as $bg): ?>
                                <?php 
                                    $rate = $bg['total_requests'] > 0 ? round(($bg['completed_requests'] / $bg['total_requests']) * 100) : 100;
                                ?>
                                <tr>
                                    <td>
                                        <span class="blood-badge" style="width: 36px; height: 36px; font-size: 0.9rem;"><?= htmlspecialchars($bg['blood_group']) ?></span>
                                    </td>
                                    <td><strong><?= (int)$bg['total_donors'] ?></strong></td>
                                    <td><span class="text-success fw-bold"><?= (int)$bg['available_donors'] ?></span></td>
                                    <td><?= (int)$bg['total_requests'] ?></td>
                                    <td><?= (int)$bg['completed_requests'] ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height: 6px;">
                                                <div class="progress-bar bg-success" style="width: <?= $rate ?>%;"></div>
                                            </div>
                                            <small class="fw-bold text-muted"><?= $rate ?>%</small>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="row g-4">
                <!-- City Distribution -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 bg-white h-100 overflow-hidden">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-map-location-dot text-primary me-2"></i>City-wise Donor Concentration</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>City & State</th>
                                        <th>Total Donors</th>
                                        <th>Available Now</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cityStats as $c): ?>
                                        <tr>
                                            <td>
                                                <strong class="text-dark"><?= htmlspecialchars($c['city']) ?></strong>, 
                                                <small class="text-muted"><?= htmlspecialchars($c['state']) ?></small>
                                            </td>
                                            <td><?= (int)$c['donor_count'] ?></td>
                                            <td><span class="badge bg-success"><?= (int)$c['available_count'] ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Urgency Statistics -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 bg-white h-100 overflow-hidden">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-bolt text-warning me-2"></i>Requests by Urgency Category</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Urgency Tier</th>
                                        <th>Total Submitted</th>
                                        <th>Completed</th>
                                        <th>Cancelled</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($urgencyStats as $u): ?>
                                        <tr>
                                            <td><?= getUrgencyBadge($u['urgency']) ?></td>
                                            <td><strong><?= (int)$u['total'] ?></strong></td>
                                            <td><span class="text-success fw-bold"><?= (int)$u['completed'] ?></span></td>
                                            <td><span class="text-secondary"><?= (int)$u['cancelled'] ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
