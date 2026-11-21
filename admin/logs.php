<?php
/**
 * LifeLink - Admin Audit Logs
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
$currentUser = getCurrentUser();
$pageTitle = "Audit Logs — LifeLink";

$logs = $db->query("SELECT l.*, u.full_name as admin_name 
                    FROM admin_logs l 
                    LEFT JOIN users u ON l.admin_id = u.user_id 
                    ORDER BY l.created_at DESC LIMIT 50")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout">
    <?php require_once __DIR__ . '/../includes/dashboard_sidebar.php'; ?>

    <div class="dashboard-main">
        <?php require_once __DIR__ . '/../includes/dashboard_topbar.php'; ?>

        <div class="dashboard-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark">Administrative Audit Logs</h3>
                    <p class="text-muted small mb-0">Security audit trail of user verifications, status changes, and administrative actions.</p>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Timestamp</th>
                                <th>Administrator</th>
                                <th>Action Performed</th>
                                <th>Audit Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">No audit logs recorded yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $l): ?>
                                    <tr>
                                        <td><?= $l['log_id'] ?></td>
                                        <td>
                                            <small class="text-dark fw-semibold d-block"><?= date('d M Y', strtotime($l['created_at'])) ?></small>
                                            <small class="text-muted"><?= date('h:i:s A', strtotime($l['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <i class="fa-solid fa-user-shield me-1 text-danger"></i>
                                                <?= htmlspecialchars($l['admin_name'] ?: 'System') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="text-dark"><?= htmlspecialchars($l['action']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="small text-muted"><?= htmlspecialchars($l['details'] ?: 'N/A') ?></span>
                                        </td>
                                        <td>
                                            <code><?= htmlspecialchars($l['ip_address'] ?? '127.0.0.1') ?></code>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
