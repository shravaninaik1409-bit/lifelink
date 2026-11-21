<?php
/**
 * LifeLink - Reusable Dashboard Sidebar
 */
if (!isset($currentUser)) {
    $currentUser = getCurrentUser();
}
$role = $currentUser['role'] ?? 'donor';
$currentScript = basename($_SERVER['PHP_SELF']);
?>
<aside class="dashboard-sidebar">
    <div class="sidebar-brand">
        <i class="fa-solid fa-droplet text-danger"></i>
        <span>Life<span class="text-danger">Link</span></span>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar-circle">
            <?= strtoupper(substr($currentUser['full_name'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="overflow-hidden">
            <h6 class="text-white mb-0 text-truncate fw-bold" style="font-size: 0.95rem;"><?= htmlspecialchars($currentUser['full_name']) ?></h6>
            <small class="badge bg-danger bg-opacity-75 text-capitalize"><?= htmlspecialchars($role) ?></small>
            <?php if (!empty($currentUser['blood_group'])): ?>
                <small class="badge bg-light text-dark fw-bold"><?= htmlspecialchars($currentUser['blood_group']) ?></small>
            <?php endif; ?>
        </div>
    </div>

    <ul class="sidebar-menu">
        <div class="menu-header">Main Menu</div>
        
        <?php if ($role === 'donor'): ?>
            <li class="<?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/donor/dashboard.php">
                    <i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span>
                </a>
            </li>
            <li class="<?= in_array($currentScript, ['requests.php', 'request_details.php']) ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/donor/requests.php">
                    <i class="fa-solid fa-hand-holding-droplet"></i> <span>Blood Requests</span>
                </a>
            </li>
            <li class="<?= in_array($currentScript, ['profile.php', 'edit_profile.php']) ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/donor/profile.php">
                    <i class="fa-solid fa-id-card"></i> <span>My Profile</span>
                </a>
            </li>
            <li class="<?= $currentScript === 'donation_history.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/donor/donation_history.php">
                    <i class="fa-solid fa-clock-rotate-left"></i> <span>Donation History</span>
                </a>
            </li>
            <li class="<?= $currentScript === 'notifications.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/donor/notifications.php">
                    <i class="fa-solid fa-bell"></i> <span>Notifications</span>
                </a>
            </li>

        <?php elseif ($role === 'requester'): ?>
            <li class="<?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/requester/dashboard.php">
                    <i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span>
                </a>
            </li>
            <li class="<?= $currentScript === 'create_request.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/requester/create_request.php">
                    <i class="fa-solid fa-plus-circle"></i> <span>New Request</span>
                </a>
            </li>
            <li class="<?= in_array($currentScript, ['my_requests.php', 'request_details.php']) ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/requester/my_requests.php">
                    <i class="fa-solid fa-list-check"></i> <span>My Requests</span>
                </a>
            </li>
            <li class="<?= $currentScript === 'favourites.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/requester/favourites.php">
                    <i class="fa-solid fa-heart"></i> <span>Saved Donors</span>
                </a>
            </li>
            <li class="<?= $currentScript === 'notifications.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/requester/notifications.php">
                    <i class="fa-solid fa-bell"></i> <span>Notifications</span>
                </a>
            </li>

        <?php elseif ($role === 'admin'): ?>
            <li class="<?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/admin/dashboard.php">
                    <i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span>
                </a>
            </li>
            <li class="<?= $currentScript === 'verification.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/admin/verification.php">
                    <i class="fa-solid fa-user-shield text-danger"></i> <span>Verification Panel</span>
                </a>
            </li>
            <li class="<?= $currentScript === 'users.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/admin/users.php">
                    <i class="fa-solid fa-users"></i> <span>User Management</span>
                </a>
            </li>
            <li class="<?= $currentScript === 'donors.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/admin/donors.php">
                    <i class="fa-solid fa-user-doctor"></i> <span>Donor Directory</span>
                </a>
            </li>
            <li class="<?= in_array($currentScript, ['requests.php', 'request_details.php']) ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/admin/requests.php">
                    <i class="fa-solid fa-hand-holding-droplet"></i> <span>Blood Requests</span>
                </a>
            </li>
            <li class="<?= $currentScript === 'reports.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/admin/reports.php">
                    <i class="fa-solid fa-chart-pie"></i> <span>Reports & Analytics</span>
                </a>
            </li>
            <li class="<?= $currentScript === 'feedback.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/admin/feedback.php">
                    <i class="fa-solid fa-comments"></i> <span>Feedback & Reports</span>
                </a>
            </li>
            <li class="<?= $currentScript === 'logs.php' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>/admin/logs.php">
                    <i class="fa-solid fa-shield-halved"></i> <span>Audit Logs</span>
                </a>
            </li>
        <?php endif; ?>

        <div class="menu-header mt-3">Shortcuts</div>
        <li>
            <a href="<?= BASE_URL ?>/find_donors.php">
                <i class="fa-solid fa-magnifying-glass"></i> <span>Find Donors</span>
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/index.php">
                <i class="fa-solid fa-house"></i> <span>Public Home</span>
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/logout.php" class="text-danger">
                <i class="fa-solid fa-right-from-bracket text-danger"></i> <span>Log Out</span>
            </a>
        </li>
    </ul>
</aside>
