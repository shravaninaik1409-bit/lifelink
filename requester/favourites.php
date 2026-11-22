<?php
/**
 * LifeLink - Saved / Favourite Donors
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('requester');
$currentUser = getCurrentUser();
$userId = $currentUser['user_id'];
$pageTitle = "Saved Donors — LifeLink";

// Handle remove favourite via secure POST + CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove' && isset($_POST['id'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        $_SESSION['flash_error'] = "Security validation failed. Please try again.";
        header("Location: " . BASE_URL . "/requester/favourites.php");
        exit();
    }

    $favId = (int)$_POST['id'];
    $stmt = $db->prepare("DELETE FROM favourites WHERE favourite_id = ? AND user_id = ?");
    $stmt->execute([$favId, $userId]);
    $_SESSION['flash_msg'] = "Donor removed from saved list.";
    header("Location: " . BASE_URL . "/requester/favourites.php");
    exit();
}

$stmt = $db->prepare("SELECT f.favourite_id, d.*, u.full_name, u.phone, u.verification_status,
                      (SELECT COUNT(*) FROM donations dn WHERE dn.donor_id = d.donor_id AND dn.verification_status IN ('Verified', 'Approved')) as total_donations
                      FROM favourites f
                      JOIN donors d ON f.donor_id = d.donor_id
                      JOIN users u ON d.user_id = u.user_id
                      WHERE f.user_id = ?
                      ORDER BY f.created_at DESC");
$stmt->execute([$userId]);
$favourites = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout">
    <?php require_once __DIR__ . '/../includes/dashboard_sidebar.php'; ?>

    <div class="dashboard-main">
        <?php require_once __DIR__ . '/../includes/dashboard_topbar.php'; ?>

        <div class="dashboard-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark">Saved Donors</h3>
                    <p class="text-muted small mb-0">Bookmarked donors for quick access during routine or critical medical needs.</p>
                </div>
                <a href="<?= BASE_URL ?>/find_donors.php" class="btn btn-lifelink-red">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Find More Donors
                </a>
            </div>

            <?php if (!empty($_SESSION['flash_msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show small" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($_SESSION['flash_msg']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_msg']); ?>
            <?php endif; ?>

            <?php if (empty($favourites)): ?>
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
                    <i class="fa-regular fa-heart fa-3x text-secondary opacity-50 mb-3"></i>
                    <h5 class="fw-bold text-dark">No Saved Donors Yet</h5>
                    <p class="text-muted small mb-4">You can bookmark verified donors while browsing the public directory for quick contact.</p>
                    <div>
                        <a href="<?= BASE_URL ?>/find_donors.php" class="btn btn-lifelink-red">Browse Donors Directory</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($favourites as $d): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="donor-card bg-white">
                                <div>
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="blood-badge"><?= htmlspecialchars($d['blood_group']) ?></div>
                                            <div>
                                                <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($d['full_name']) ?></h5>
                                                <small class="text-muted">
                                                    <i class="fa-solid fa-location-dot text-danger me-1"></i>
                                                    <?= htmlspecialchars($d['city']) ?>
                                                </small>
                                            </div>
                                        </div>
                                        <form method="POST" action="" class="d-inline m-0 p-0">
                                            <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="id" value="<?= $d['favourite_id'] ?>">
                                            <button type="submit" class="btn btn-light btn-sm text-danger rounded-circle" title="Remove" onclick="return confirmAction('Remove from saved?');">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <span class="<?= $d['availability'] === 'Available' ? 'badge-available' : 'badge-unavailable' ?>">
                                            <i class="fa-solid fa-circle fa-2xs me-1"></i><?= htmlspecialchars($d['availability']) ?>
                                        </span>
                                        <?php if ($d['verification_status'] === 'verified'): ?>
                                            <span class="badge-verified"><i class="fa-solid fa-check me-1"></i>Verified</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="bg-light p-2 rounded small text-muted d-flex justify-content-between mb-3">
                                        <span><i class="fa-solid fa-droplet text-danger me-1"></i><?= (int)$d['total_donations'] ?> donations</span>
                                        <span><i class="fa-solid fa-calendar text-primary me-1"></i><?= !empty($d['last_donation_date']) ? date('M Y', strtotime($d['last_donation_date'])) : 'New Donor' ?></span>
                                    </div>
                                </div>

                                <div class="pt-3 border-top d-grid">
                                    <a href="<?= BASE_URL ?>/requester/create_request.php?blood_group=<?= urlencode($d['blood_group']) ?>&city=<?= urlencode($d['city']) ?>" class="btn btn-lifelink-red btn-sm">
                                        <i class="fa-solid fa-paper-plane me-1"></i> Send Blood Request
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
