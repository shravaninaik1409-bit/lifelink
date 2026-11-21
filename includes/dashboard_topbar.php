<?php
/**
 * LifeLink - Reusable Dashboard Top Bar
 */
if (!isset($currentUser)) {
    $currentUser = getCurrentUser();
}
$unreadCount = 0;
if ($currentUser && $db) {
    $unreadCount = getUnreadNotificationsCount($db, $currentUser['user_id']);
}
$notifUrl = BASE_URL . '/' . ($currentUser['role'] ?? 'donor') . '/notifications.php';
?>
<header class="dashboard-topbar">
    <div class="d-flex align-items-center gap-3">
        <button type="button" id="sidebarToggle" class="btn btn-light d-lg-none p-2 border">
            <i class="fa-solid fa-bars"></i>
        </button>
        <span class="fw-bold text-dark d-none d-sm-inline"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></span>
    </div>

    <div class="d-flex align-items-center gap-3">
        <!-- Notification Icon -->
        <a href="<?= $notifUrl ?>" class="btn btn-light position-relative rounded-circle p-2 border" title="Notifications">
            <i class="fa-regular fa-bell text-secondary"></i>
            <?php if ($unreadCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                    <?= $unreadCount ?>
                </span>
            <?php endif; ?>
        </a>

        <!-- User Profile Dropdown -->
        <div class="dropdown">
            <button class="btn btn-white border dropdown-toggle d-flex align-items-center gap-2 rounded-pill px-3 py-1" type="button" data-bs-toggle="dropdown">
                <span class="user-avatar-circle" style="width: 28px; height: 28px; font-size: 0.75rem;">
                    <?= strtoupper(substr($currentUser['full_name'] ?? 'U', 0, 1)) ?>
                </span>
                <span class="fw-semibold small text-truncate" style="max-width: 130px;"><?= htmlspecialchars($currentUser['full_name']) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                <li class="px-3 py-2 border-bottom">
                    <small class="text-muted d-block">Role</small>
                    <strong class="text-capitalize text-danger"><?= htmlspecialchars($currentUser['role']) ?></strong>
                </li>
                <?php if ($currentUser['role'] === 'donor'): ?>
                    <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/donor/profile.php"><i class="fa-solid fa-id-card me-2 text-muted"></i>Profile Settings</a></li>
                <?php endif; ?>
                <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/index.php"><i class="fa-solid fa-house me-2 text-muted"></i>Back to Website</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item py-2 text-danger" href="<?= BASE_URL ?>/logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Sign Out</a></li>
            </ul>
        </div>
    </div>
</header>
