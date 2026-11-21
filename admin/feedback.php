<?php
/**
 * LifeLink - Admin Feedback, Contact Messages & Reports
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
$currentUser = getCurrentUser();
$adminId = $currentUser['user_id'];
$pageTitle = "Feedback & Reports — LifeLink";

// Handle actions via secure POST + CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['id'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        $_SESSION['flash_error'] = "Security validation failed. Please refresh and try again.";
        header("Location: " . BASE_URL . "/admin/feedback.php");
        exit();
    }

    $targetId = (int)$_POST['id'];
    $action = $_POST['action'];
    $type = $_POST['type'] ?? 'message';

    try {
        if ($type === 'message') {
            if ($action === 'mark_read') {
                $db->prepare("UPDATE contact_messages SET is_read = 1 WHERE message_id = ?")->execute([$targetId]);
                $_SESSION['flash_msg'] = "Message marked as read.";
            } elseif ($action === 'delete') {
                $db->prepare("DELETE FROM contact_messages WHERE message_id = ?")->execute([$targetId]);
                $_SESSION['flash_msg'] = "Message deleted successfully.";
            }
        } elseif ($type === 'report') {
            if ($action === 'resolve') {
                $db->prepare("UPDATE reports SET status = 'Resolved' WHERE report_id = ?")->execute([$targetId]);
                logAdminAction($db, $adminId, "Report Resolved", "Resolved Report #{$targetId}");
                $_SESSION['flash_msg'] = "Report marked as resolved.";
            } elseif ($action === 'dismiss') {
                $db->prepare("UPDATE reports SET status = 'Dismissed' WHERE report_id = ?")->execute([$targetId]);
                $_SESSION['flash_msg'] = "Report dismissed.";
            }
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Action error: " . $e->getMessage();
    }
    header("Location: " . BASE_URL . "/admin/feedback.php");
    exit();
}

// Fetch Contact Messages
$messages = $db->query("SELECT * FROM contact_messages ORDER BY is_read ASC, created_at DESC")->fetchAll();

// Fetch User Reports
$reports = $db->query("SELECT r.*, u.full_name as reporter_name FROM reports r JOIN users u ON r.reported_by = u.user_id ORDER BY r.status = 'Pending' DESC, r.created_at DESC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout">
    <?php require_once __DIR__ . '/../includes/dashboard_sidebar.php'; ?>

    <div class="dashboard-main">
        <?php require_once __DIR__ . '/../includes/dashboard_topbar.php'; ?>

        <div class="dashboard-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark">Feedback & User Inquiries</h3>
                    <p class="text-muted small mb-0">Public contact form inquiries and community incident reports.</p>
                </div>
            </div>

            <?php if (!empty($_SESSION['flash_msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show small" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($_SESSION['flash_msg']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_msg']); ?>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Contact Messages -->
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden h-100">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-envelope text-primary me-2"></i>Contact Inquiries (<?= count($messages) ?>)</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($messages)): ?>
                                <p class="text-muted p-4 text-center small mb-0">No contact messages received.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($messages as $m): ?>
                                        <div class="list-group-item p-3 <?= !$m['is_read'] ? 'bg-light' : '' ?>">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <div>
                                                    <strong class="text-dark"><?= htmlspecialchars($m['name']) ?></strong>
                                                    <small class="text-muted">&bull; <?= htmlspecialchars($m['email']) ?></small>
                                                </div>
                                                <small class="text-muted"><?= timeAgo($m['created_at']) ?></small>
                                            </div>
                                            <h6 class="fw-semibold text-danger small mb-1"><?= htmlspecialchars($m['subject']) ?></h6>
                                            <p class="small text-muted mb-2"><?= nl2br(htmlspecialchars($m['message'])) ?></p>
                                            <div class="d-flex gap-2 align-items-center">
                                                <?php if (!$m['is_read']): ?>
                                                    <form method="POST" action="" class="d-inline m-0 p-0">
                                                        <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                                        <input type="hidden" name="action" value="mark_read">
                                                        <input type="hidden" name="type" value="message">
                                                        <input type="hidden" name="id" value="<?= $m['message_id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;">
                                                            <i class="fa-solid fa-check me-1"></i> Mark as Read
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <a href="mailto:<?= htmlspecialchars($m['email']) ?>?subject=Re: <?= urlencode($m['subject']) ?>" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.75rem;">
                                                    <i class="fa-solid fa-reply me-1"></i> Reply
                                                </a>
                                                <form method="POST" action="" class="d-inline m-0 p-0">
                                                    <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="type" value="message">
                                                    <input type="hidden" name="id" value="<?= $m['message_id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-light text-danger py-0 px-2" style="font-size: 0.75rem;" onclick="return confirmAction('Delete this message?');">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Incident Reports -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden h-100">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-flag text-danger me-2"></i>Reported Issues (<?= count($reports) ?>)</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($reports)): ?>
                                <p class="text-muted p-4 text-center small mb-0">No active incident reports submitted.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($reports as $r): ?>
                                        <div class="list-group-item p-3">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <strong class="text-danger small"><?= htmlspecialchars($r['report_reason']) ?></strong>
                                                <span class="badge <?= $r['status'] === 'Resolved' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                                    <?= htmlspecialchars($r['status']) ?>
                                                </span>
                                            </div>
                                            <p class="small text-muted mb-2"><?= htmlspecialchars($r['description']) ?></p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted" style="font-size: 0.72rem;">By <?= htmlspecialchars($r['reporter_name']) ?></small>
                                                <?php if ($r['status'] === 'Pending'): ?>
                                                    <div class="d-flex gap-1 align-items-center">
                                                        <form method="POST" action="" class="d-inline m-0 p-0">
                                                            <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                                            <input type="hidden" name="action" value="resolve">
                                                            <input type="hidden" name="type" value="report">
                                                            <input type="hidden" name="id" value="<?= $r['report_id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-success py-0 px-2" style="font-size: 0.72rem;">Resolve</button>
                                                        </form>
                                                        <form method="POST" action="" class="d-inline m-0 p-0">
                                                            <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                                            <input type="hidden" name="action" value="dismiss">
                                                            <input type="hidden" name="type" value="report">
                                                            <input type="hidden" name="id" value="<?= $r['report_id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-light py-0 px-2" style="font-size: 0.72rem;">Dismiss</button>
                                                        </form>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
