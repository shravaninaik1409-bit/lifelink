<?php
/**
 * LifeLink - Admin User Management
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
$currentUser = getCurrentUser();
$adminId = $currentUser['user_id'];
$pageTitle = "User Management — LifeLink";

// Handle Actions (Status Toggle, Verification, Deletion) via secure POST + CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['id'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        $_SESSION['flash_error'] = "Security validation failed. Please refresh and try again.";
        header("Location: " . BASE_URL . "/admin/users.php");
        exit();
    }

    $targetId = (int)$_POST['id'];
    $action = $_POST['action'];

    // Prevent self-modification
    if ($targetId === (int)$adminId && ($action === 'delete' || $action === 'deactivate' || $action === 'suspend')) {
        $_SESSION['flash_error'] = "You cannot modify your own administrator account status.";
    } else {
        try {
            if ($action === 'activate') {
                $db->prepare("UPDATE users SET account_status = 'active' WHERE user_id = ?")->execute([$targetId]);
                logAdminAction($db, $adminId, "Account Activated", "Activated User #{$targetId}");
                $_SESSION['flash_msg'] = "User account activated successfully.";
            } elseif ($action === 'deactivate') {
                $db->prepare("UPDATE users SET account_status = 'deactivated' WHERE user_id = ?")->execute([$targetId]);
                logAdminAction($db, $adminId, "Account Deactivated", "Deactivated User #{$targetId}");
                $_SESSION['flash_msg'] = "User account deactivated successfully.";
            } elseif ($action === 'suspend') {
                $db->prepare("UPDATE users SET account_status = 'suspended' WHERE user_id = ?")->execute([$targetId]);
                logAdminAction($db, $adminId, "Account Suspended", "Suspended User #{$targetId}");
                $_SESSION['flash_msg'] = "User account suspended successfully.";
            } elseif ($action === 'verify') {
                $db->prepare("UPDATE users SET verification_status = 'verified' WHERE user_id = ?")->execute([$targetId]);
                logAdminAction($db, $adminId, "User Verified", "Verified credentials for User #{$targetId}");
                createNotification($db, $targetId, "Profile Verified", "Your account and profile credentials have been verified by administration.", "system");
                $_SESSION['flash_msg'] = "User verified successfully.";
            } elseif ($action === 'reject') {
                $db->prepare("UPDATE users SET verification_status = 'rejected' WHERE user_id = ?")->execute([$targetId]);
                logAdminAction($db, $adminId, "Verification Rejected", "Rejected verification for User #{$targetId}");
                createNotification($db, $targetId, "Verification Rejected", "Your profile verification could not be approved at this time. Please review your account details.", "system");
                $_SESSION['flash_msg'] = "User verification marked as rejected.";
            } elseif ($action === 'mark_pending') {
                $db->prepare("UPDATE users SET verification_status = 'pending' WHERE user_id = ?")->execute([$targetId]);
                logAdminAction($db, $adminId, "Verification Reset to Pending", "Reset verification status for User #{$targetId}");
                $_SESSION['flash_msg'] = "User verification reset to pending.";
            } elseif ($action === 'delete') {
                $db->prepare("DELETE FROM users WHERE user_id = ?")->execute([$targetId]);
                logAdminAction($db, $adminId, "User Deleted", "Permanently removed User #{$targetId}");
                $_SESSION['flash_msg'] = "User permanently deleted.";
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Action failed: " . $e->getMessage();
        }
    }
    header("Location: " . BASE_URL . "/admin/users.php");
    exit();
}

$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "SELECT u.*, 
        (SELECT blood_group FROM donors d WHERE d.user_id = u.user_id) as donor_bg,
        (SELECT city FROM donors d WHERE d.user_id = u.user_id) as donor_city
        FROM users u WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if (!empty($roleFilter)) {
    $sql .= " AND u.role = ?";
    $params[] = $roleFilter;
}

if (!empty($statusFilter)) {
    $sql .= " AND u.account_status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout">
    <?php require_once __DIR__ . '/../includes/dashboard_sidebar.php'; ?>

    <div class="dashboard-main">
        <?php require_once __DIR__ . '/../includes/dashboard_topbar.php'; ?>

        <div class="dashboard-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark">User Management</h3>
                    <p class="text-muted small mb-0">Oversee donors, requesters, and administrative team accounts.</p>
                </div>
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

            <!-- Search & Filter Card -->
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-4">
                <form method="GET" action="" class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search by name, email, or phone..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="role" class="form-select form-select-sm">
                            <option value="">All Roles</option>
                            <option value="donor" <?= $roleFilter === 'donor' ? 'selected' : '' ?>>Donors</option>
                            <option value="requester" <?= $roleFilter === 'requester' ? 'selected' : '' ?>>Requesters</option>
                            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Administrators</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="deactivated" <?= $statusFilter === 'deactivated' ? 'selected' : '' ?>>Deactivated</option>
                            <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-lifelink-red btn-sm flex-grow-1">Filter</button>
                        <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Users Table Card -->
            <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>User Profile</th>
                                <th>Role</th>
                                <th>Blood / City</th>
                                <th>Account Status</th>
                                <th>Verification</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        No users matching your search filters.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td><?= $u['user_id'] ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="user-avatar-circle" style="width: 36px; height: 36px; font-size: 0.85rem;">
                                                    <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <strong class="d-block text-dark"><?= htmlspecialchars($u['full_name']) ?></strong>
                                                    <small class="text-muted"><?= htmlspecialchars($u['email']) ?> &bull; <?= htmlspecialchars($u['phone']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($u['role'] === 'admin'): ?>
                                                <span class="badge bg-danger">Admin</span>
                                            <?php elseif ($u['role'] === 'donor'): ?>
                                                <span class="badge bg-primary">Donor</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Requester</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($u['donor_bg'])): ?>
                                                <span class="badge bg-light text-danger border fw-bold"><?= htmlspecialchars($u['donor_bg']) ?></span>
                                                <small class="text-muted d-block"><?= htmlspecialchars($u['donor_city'] ?? '') ?></small>
                                            <?php else: ?>
                                                <span class="text-muted small">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($u['account_status'] === 'active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php elseif ($u['account_status'] === 'deactivated'): ?>
                                                <span class="badge bg-secondary">Deactivated</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Suspended</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= getVerificationBadge($u['verification_status'] ?? 'unverified') ?>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?= date('d M Y', strtotime($u['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-light btn-sm border dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    Manage
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                    <?php if ($u['verification_status'] !== 'verified'): ?>
                                                        <li>
                                                            <form method="POST" action="" class="m-0 p-0">
                                                                <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                                                <input type="hidden" name="action" value="verify">
                                                                <input type="hidden" name="id" value="<?= $u['user_id'] ?>">
                                                                <button type="submit" class="dropdown-item small text-primary"><i class="fa-solid fa-circle-check me-2"></i>Verify Profile</button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if ($u['verification_status'] !== 'rejected'): ?>
                                                        <li>
                                                            <form method="POST" action="" class="m-0 p-0">
                                                                <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                                                <input type="hidden" name="action" value="reject">
                                                                <input type="hidden" name="id" value="<?= $u['user_id'] ?>">
                                                                <button type="submit" class="dropdown-item small text-danger"><i class="fa-solid fa-ban me-2"></i>Reject Verification</button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if ($u['verification_status'] !== 'pending'): ?>
                                                        <li>
                                                            <form method="POST" action="" class="m-0 p-0">
                                                                <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                                                <input type="hidden" name="action" value="mark_pending">
                                                                <input type="hidden" name="id" value="<?= $u['user_id'] ?>">
                                                                <button type="submit" class="dropdown-item small text-secondary"><i class="fa-solid fa-clock-rotate-left me-2"></i>Reset to Pending</button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <?php if ($u['account_status'] !== 'active'): ?>
                                                        <li>
                                                            <form method="POST" action="" class="m-0 p-0">
                                                                <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                                                <input type="hidden" name="action" value="activate">
                                                                <input type="hidden" name="id" value="<?= $u['user_id'] ?>">
                                                                <button type="submit" class="dropdown-item small text-success"><i class="fa-solid fa-user-check me-2"></i>Activate Account</button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if ($u['account_status'] === 'active'): ?>
                                                        <li>
                                                            <form method="POST" action="" class="m-0 p-0">
                                                                <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                                                <input type="hidden" name="action" value="deactivate">
                                                                <input type="hidden" name="id" value="<?= $u['user_id'] ?>">
                                                                <button type="submit" class="dropdown-item small text-warning" onclick="return confirmAction('Deactivate this user account?');"><i class="fa-solid fa-user-minus me-2"></i>Deactivate Account</button>
                                                            </form>
                                                        </li>
                                                        <li>
                                                            <form method="POST" action="" class="m-0 p-0">
                                                                <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                                                <input type="hidden" name="action" value="suspend">
                                                                <input type="hidden" name="id" value="<?= $u['user_id'] ?>">
                                                                <button type="submit" class="dropdown-item small text-secondary" onclick="return confirmAction('Suspend this user account?');"><i class="fa-solid fa-user-lock me-2"></i>Suspend Account</button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form method="POST" action="" class="m-0 p-0">
                                                            <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?= $u['user_id'] ?>">
                                                            <button type="submit" class="dropdown-item small text-danger" onclick="return confirmAction('Permanently delete this user account?');"><i class="fa-solid fa-trash me-2"></i>Delete User</button>
                                                        </form>
                                                    </li>
                                                </ul>
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
