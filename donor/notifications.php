<?php
/**
 * LifeLink - Donor Notifications Center
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('donor');
$currentUser = getCurrentUser();
$userId = $currentUser['user_id'];
$pageTitle = "Notifications — LifeLink";

// Handle Actions (Mark all read, Mark single, Clear single)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action === 'mark_all_read') {
        $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$userId]);
        $_SESSION['flash_msg'] = "All notifications marked as read.";
    } elseif ($action === 'mark_read' && isset($_GET['id'])) {
        $notifId = (int)$_GET['id'];
        $db->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?")->execute([$notifId, $userId]);
    } elseif ($action === 'clear' && isset($_GET['id'])) {
        $notifId = (int)$_GET['id'];
        $db->prepare("DELETE FROM notifications WHERE notification_id = ? AND user_id = ?")->execute([$notifId, $userId]);
        $_SESSION['flash_msg'] = "Notification dismissed.";
    }
    header("Location: " . BASE_URL . "/donor/notifications.php");
    exit();
}

$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/donor_navbar.php';
?>

<div class="bg-light min-vh-100 py-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1 text-dark">Notification Center</h3>
                <p class="text-muted small mb-0">Emergency blood alerts, response acknowledgements, and system messages.</p>
            </div>
            <?php if (!empty($notifications)): ?>
                <a href="?action=mark_all_read" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-check-double me-1"></i> Mark All as Read
                </a>
            <?php endif; ?>
        </div>

        <?php if (!empty($_SESSION['flash_msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show small mb-4" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($_SESSION['flash_msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash_msg']); ?>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <?php if (empty($notifications)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa-regular fa-bell-slash fa-3x mb-3 text-secondary opacity-50"></i>
                    <h5 class="fw-bold mb-1">No Notifications</h5>
                    <p class="small mb-0">You're all caught up! When matched requests or system updates occur, they will appear here.</p>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($notifications as $n): ?>
                        <div class="p-3 rounded-3 border d-flex gap-3 align-items-start <?= !$n['is_read'] ? 'bg-light border-primary' : 'bg-white' ?>">
                            <div class="notification-icon <?= $n['notification_type'] === 'emergency' ? 'bg-danger bg-opacity-10 text-danger' : ($n['notification_type'] === 'response' ? 'bg-success bg-opacity-10 text-success' : 'bg-primary bg-opacity-10 text-primary') ?>">
                                <i class="fa-solid <?= $n['notification_type'] === 'emergency' ? 'fa-bolt' : ($n['notification_type'] === 'response' ? 'fa-check' : 'fa-bell') ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h6 class="fw-bold mb-0 text-dark <?= !$n['is_read'] ? 'text-primary' : '' ?>"><?= htmlspecialchars($n['title']) ?></h6>
                                    <small class="text-muted" style="font-size: 0.75rem;"><?= timeAgo($n['created_at']) ?></small>
                                </div>
                                <p class="small text-muted mb-2"><?= nl2br(htmlspecialchars($n['message'])) ?></p>
                                <div class="d-flex gap-2">
                                    <?php if (!empty($n['related_request_id'])): ?>
                                        <a href="<?= BASE_URL ?>/donor/request_details.php?id=<?= $n['related_request_id'] ?>" class="btn btn-outline-danger btn-sm py-0 px-2" style="font-size: 0.75rem;">
                                            View Request
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!$n['is_read']): ?>
                                        <a href="?action=mark_read&id=<?= $n['notification_id'] ?>" class="btn btn-light btn-sm py-0 px-2 text-muted" style="font-size: 0.75rem;">
                                            Mark Read
                                        </a>
                                    <?php endif; ?>
                                    <a href="?action=clear&id=<?= $n['notification_id'] ?>" class="btn btn-light btn-sm py-0 px-2 text-danger" style="font-size: 0.75rem;">
                                        Dismiss
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
