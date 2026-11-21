<?php
/**
 * LifeLink - Reusable Donor Portal Top Navbar
 * Keeps Main Menu and Shortcuts cleanly in the top navbar.
 */
if (!isset($currentUser)) {
    $currentUser = getCurrentUser();
}
$currentScript = basename($_SERVER['PHP_SELF']);
$donorUser = $donor ?? null;

// If $donor is not set, fetch basic donor info for availability & blood group
if (!$donorUser && isset($currentUser['user_id']) && $db) {
    try {
        $stmtD = $db->prepare("SELECT * FROM donors WHERE user_id = ?");
        $stmtD->execute([$currentUser['user_id']]);
        $donorUser = $stmtD->fetch();
    } catch (Exception $e) {}
}

$donorBg = $donorUser['blood_group'] ?? ($_SESSION['blood_group'] ?? 'O+');
$donorAvail = $donorUser['availability'] ?? ($_SESSION['availability'] ?? 'Available');
$donorCity = $donorUser['city'] ?? '';

// Unread notifications
$unreadCount = 0;
if ($currentUser && $db) {
    $unreadCount = getUnreadNotificationsCount($db, $currentUser['user_id']);
}

// Matching requests count
$matchingCount = 0;
if ($db && !empty($donorBg)) {
    try {
        $cntStmt = $db->prepare("SELECT COUNT(*) FROM blood_requests WHERE blood_group = ? AND status != 'Cancelled'");
        $cntStmt->execute([$donorBg]);
        $matchingCount = (int)$cntStmt->fetchColumn();
    } catch (Exception $e) {}
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-2 px-3 border-bottom shadow-sm sticky-top" style="background-color: #0B192C !important; z-index: 1030;">
    <div class="container-fluid px-lg-4">
        <!-- Brand Logo -->
        <a class="navbar-brand fw-bold text-white d-flex align-items-center gap-2 me-lg-4" href="<?= BASE_URL ?>/donor/dashboard.php">
            <span class="blood-badge shadow-sm" style="width: 32px; height: 32px; font-size: 0.9rem; margin-right: 2px;">
                <i class="fa-solid fa-droplet text-white"></i>
            </span>
            <span class="fs-5 tracking-tight">Life<span class="text-danger">Link</span></span>
            <span class="badge bg-danger bg-opacity-75 ms-1 fw-semibold d-none d-sm-inline" style="font-size: 0.68rem; letter-spacing: 0.05em;">DONOR PORTAL</span>
        </a>

        <!-- Mobile Toggler -->
        <button class="navbar-toggler border-0 shadow-none p-2" type="button" data-bs-toggle="collapse" data-bs-target="#donorNavbar" aria-controls="donorNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <i class="fa-solid fa-bars text-white fs-5"></i>
        </button>

        <!-- Navbar Menu Items -->
        <div class="collapse navbar-collapse" id="donorNavbar">
            <!-- Main Menu (Left) -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 gap-1 align-items-lg-center">
                <li class="nav-item">
                    <a class="nav-link px-3 py-2 rounded-3 <?= $currentScript === 'dashboard.php' ? 'active fw-bold text-white shadow-sm' : 'text-white-50' ?>" 
                       href="<?= BASE_URL ?>/donor/dashboard.php"
                       style="<?= $currentScript === 'dashboard.php' ? 'background: rgba(220, 38, 38, 0.25); border: 1px solid rgba(220, 38, 38, 0.4);' : '' ?>">
                        <i class="fa-solid fa-gauge-high <?= $currentScript === 'dashboard.php' ? 'text-danger' : 'text-secondary' ?> me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3 py-2 rounded-3 <?= in_array($currentScript, ['requests.php', 'request_details.php']) ? 'active fw-bold text-white shadow-sm' : 'text-white-50' ?>" 
                       href="<?= BASE_URL ?>/donor/requests.php"
                       style="<?= in_array($currentScript, ['requests.php', 'request_details.php']) ? 'background: rgba(220, 38, 38, 0.25); border: 1px solid rgba(220, 38, 38, 0.4);' : '' ?>">
                        <i class="fa-solid fa-hand-holding-droplet me-1"></i> Blood Requests
                        <?php if ($matchingCount > 0): ?>
                            <span class="badge bg-danger rounded-pill ms-1" style="font-size: 0.68rem;"><?= $matchingCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3 py-2 rounded-3 <?= in_array($currentScript, ['profile.php', 'edit_profile.php']) ? 'active fw-bold text-white shadow-sm' : 'text-white-50' ?>" 
                       href="<?= BASE_URL ?>/donor/profile.php"
                       style="<?= in_array($currentScript, ['profile.php', 'edit_profile.php']) ? 'background: rgba(220, 38, 38, 0.25); border: 1px solid rgba(220, 38, 38, 0.4);' : '' ?>">
                        <i class="fa-solid fa-id-card me-1"></i> My Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3 py-2 rounded-3 <?= $currentScript === 'donation_history.php' ? 'active fw-bold text-white shadow-sm' : 'text-white-50' ?>" 
                       href="<?= BASE_URL ?>/donor/donation_history.php"
                       style="<?= $currentScript === 'donation_history.php' ? 'background: rgba(220, 38, 38, 0.25); border: 1px solid rgba(220, 38, 38, 0.4);' : '' ?>">
                        <i class="fa-solid fa-clock-rotate-left me-1"></i> Donation History
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3 py-2 rounded-3 position-relative <?= $currentScript === 'notifications.php' ? 'active fw-bold text-white shadow-sm' : 'text-white-50' ?>" 
                       href="<?= BASE_URL ?>/donor/notifications.php"
                       style="<?= $currentScript === 'notifications.php' ? 'background: rgba(220, 38, 38, 0.25); border: 1px solid rgba(220, 38, 38, 0.4);' : '' ?>">
                        <i class="fa-solid fa-bell me-1"></i> Notifications
                        <?php if ($unreadCount > 0): ?>
                            <span class="badge bg-danger rounded-pill ms-1" style="font-size: 0.68rem;"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <!-- Shortcuts Section in Nav -->
                <li class="nav-item d-none d-lg-flex align-items-center px-1">
                    <span class="text-white-50 opacity-25">|</span>
                </li>

                <li class="nav-item">
                    <a class="nav-link px-3 py-2 text-white-50 hover-text-white" href="<?= BASE_URL ?>/find_donors.php">
                        <i class="fa-solid fa-magnifying-glass me-1 text-info"></i> Find Donors
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3 py-2 text-white-50 hover-text-white" href="<?= BASE_URL ?>/index.php">
                        <i class="fa-solid fa-house me-1"></i> Public Home
                    </a>
                </li>
            </ul>

            <!-- Right Controls (Availability Pill & Profile Dropdown) -->
            <div class="d-flex align-items-center gap-3 pt-2 pt-lg-0 border-top border-lg-0 border-secondary">
                <!-- Availability Status Badge -->
                <?php if ($donorAvail === 'Available'): ?>
                    <span class="badge bg-success bg-opacity-25 text-success border border-success-subtle px-3 py-2 rounded-pill small" title="Ready for incoming requests">
                        <i class="fa-solid fa-circle fa-2xs me-1"></i> Available
                    </span>
                <?php elseif ($donorAvail === 'Temporarily Unavailable'): ?>
                    <span class="badge bg-warning bg-opacity-25 text-warning border border-warning-subtle px-3 py-2 rounded-pill small" title="Temporarily busy">
                        <i class="fa-solid fa-clock fa-2xs me-1"></i> Temp Unavailable
                    </span>
                <?php else: ?>
                    <span class="badge bg-secondary bg-opacity-25 text-light border border-secondary px-3 py-2 rounded-pill small">
                        <i class="fa-solid fa-ban fa-2xs me-1"></i> Inactive
                    </span>
                <?php endif; ?>

                <!-- User Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-dark border border-secondary dropdown-toggle d-flex align-items-center gap-2 rounded-pill px-3 py-1 text-white shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="blood-badge" style="width: 26px; height: 26px; font-size: 0.75rem; background: linear-gradient(135deg, #DC2626, #991B1B);">
                            <?= htmlspecialchars($donorBg) ?>
                        </span>
                        <span class="fw-semibold small text-truncate" style="max-width: 130px;"><?= htmlspecialchars($currentUser['full_name']) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2 rounded-3" style="min-width: 220px;">
                        <li class="px-3 py-2 border-bottom bg-light rounded-top">
                            <strong class="d-block text-dark small text-truncate"><?= htmlspecialchars($currentUser['full_name']) ?></strong>
                            <small class="text-muted d-block text-truncate" style="font-size: 0.75rem;"><?= htmlspecialchars($currentUser['email']) ?></small>
                            <div class="d-flex align-items-center gap-1 mt-1">
                                <span class="badge bg-danger-subtle text-danger fw-bold" style="font-size: 0.7rem;"><?= htmlspecialchars($donorBg) ?></span>
                                <?php if (!empty($donorCity)): ?>
                                    <span class="badge bg-light text-secondary border" style="font-size: 0.7rem;"><?= htmlspecialchars($donorCity) ?></span>
                                <?php endif; ?>
                            </div>
                        </li>
                        <li><a class="dropdown-item py-2 small" href="<?= BASE_URL ?>/donor/profile.php"><i class="fa-solid fa-id-card me-2 text-primary"></i>My Profile</a></li>
                        <li><a class="dropdown-item py-2 small" href="<?= BASE_URL ?>/donor/edit_profile.php"><i class="fa-solid fa-gear me-2 text-secondary"></i>Edit Profile & Privacy</a></li>
                        <li><a class="dropdown-item py-2 small" href="<?= BASE_URL ?>/donor/donation_history.php"><i class="fa-solid fa-clock-rotate-left me-2 text-danger"></i>Donation Records</a></li>
                        <li><a class="dropdown-item py-2 small" href="<?= BASE_URL ?>/donor/notifications.php"><i class="fa-solid fa-bell me-2 text-warning"></i>Notifications</a></li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item py-2 small text-danger fw-semibold" href="<?= BASE_URL ?>/logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Sign Out</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>
