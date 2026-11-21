<?php
/**
 * LifeLink - Navigation Bar Component
 */
$currentPage = basename($_SERVER['PHP_SELF']);
$currentUser = getCurrentUser();
$unreadCount = 0;
if ($currentUser && $db) {
    $unreadCount = getUnreadNotificationsCount($db, $currentUser['user_id']);
}
?>
<nav class="navbar navbar-expand-lg lifelink-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand lifelink-brand" href="<?= BASE_URL ?>/index.php">
            <i class="fa-solid fa-droplet text-danger"></i>
            <span>Life<span class="text-danger">Link</span></span>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php">
                        <i class="fa-solid fa-house me-1"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'find_donors.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/find_donors.php">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Find Donors
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/index.php#how-it-works">
                        <i class="fa-solid fa-circle-question me-1"></i> How It Works
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'about.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/about.php">
                        <i class="fa-solid fa-shield-heart me-1"></i> About Us
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'contact.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/contact.php">
                        <i class="fa-solid fa-headset me-1"></i> Contact
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-2">
                <?php if ($currentUser): ?>
                    <!-- Notification Bell Shortcut -->
                    <?php 
                        $notifUrl = BASE_URL . '/' . $currentUser['role'] . '/notifications.php';
                        $dashboardUrl = BASE_URL . '/' . $currentUser['role'] . '/dashboard.php';
                    ?>
                    <a href="<?= $notifUrl ?>" class="btn btn-light position-relative rounded-circle p-2" title="Notifications">
                        <i class="fa-regular fa-bell text-dark"></i>
                        <?php if ($unreadCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                                <?= $unreadCount ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <!-- User Dropdown Menu -->
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle d-flex align-items-center gap-2 rounded-pill px-3 py-1" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="badge bg-danger rounded-circle p-1"><i class="fa-solid fa-user-circle"></i></span>
                            <span class="fw-semibold small text-truncate" style="max-width: 120px;"><?= htmlspecialchars($currentUser['full_name']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                            <li class="px-3 py-2 border-bottom">
                                <small class="text-muted d-block">Signed in as</small>
                                <strong class="d-block text-capitalize text-danger"><?= htmlspecialchars($currentUser['role']) ?></strong>
                            </li>
                            <li>
                                <a class="dropdown-item py-2" href="<?= $dashboardUrl ?>">
                                    <i class="fa-solid fa-gauge-high me-2 text-primary"></i> Dashboard
                                </a>
                            </li>
                            <?php if ($currentUser['role'] === 'donor'): ?>
                                <li>
                                    <a class="dropdown-item py-2" href="<?= BASE_URL ?>/donor/profile.php">
                                        <i class="fa-solid fa-id-badge me-2 text-secondary"></i> My Profile
                                    </a>
                                </li>
                            <?php elseif ($currentUser['role'] === 'requester'): ?>
                                <li>
                                    <a class="dropdown-item py-2" href="<?= BASE_URL ?>/requester/create_request.php">
                                        <i class="fa-solid fa-hand-holding-droplet me-2 text-danger"></i> Request Blood
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item py-2 text-danger" href="<?= BASE_URL ?>/logout.php">
                                    <i class="fa-solid fa-right-from-bracket me-2"></i> Log Out
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/login.php" class="btn btn-lifelink-outline">
                        <i class="fa-solid fa-right-to-bracket me-1"></i> Sign In
                    </a>
                    <a href="<?= BASE_URL ?>/register.php" class="btn btn-lifelink-red">
                        <i class="fa-solid fa-user-plus me-1"></i> Register
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
