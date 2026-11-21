<?php
/**
 * LifeLink - Secure Password Reset Workflow (Phase 1)
 * Features cryptographically secure token generation, SHA-256 token hashing,
 * expiration checks, single-use invalidation, and CSRF defense.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? 'donor';
    header("Location: " . BASE_URL . "/{$role}/dashboard.php");
    exit();
}

$pageTitle = "Reset Password — LifeLink";
$error = '';
$success = '';
$info = '';

// Check if a reset token is provided in GET or POST
$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$isTokenMode = !empty($token);
$tokenValid = false;
$resetRecord = null;

if ($isTokenMode) {
    // Validate token format (64-character hexadecimal)
    if (strlen($token) !== 64 || !ctype_xdigit($token)) {
        $error = "This password reset link is malformed or invalid.";
    } else {
        try {
            if ($db) {
                $tokenHash = hash('sha256', $token);
                $stmt = $db->prepare("
                    SELECT pr.*, u.user_id, u.email, u.full_name, u.account_status,
                           (pr.expires_at > NOW()) as is_not_expired
                    FROM password_resets pr 
                    JOIN users u ON pr.user_id = u.user_id 
                    WHERE pr.token_hash = ? 
                    LIMIT 1
                ");
                $stmt->execute([$tokenHash]);
                $resetRecord = $stmt->fetch();

                if (!$resetRecord) {
                    $error = "This password reset link is invalid or does not exist.";
                } elseif (!empty($resetRecord['used_at'])) {
                    $error = "This password reset link has already been used. Please request a new one.";
                } elseif (empty($resetRecord['is_not_expired'])) {
                    $error = "This password reset link has expired (links are valid for 60 minutes). Please request a new one.";
                } elseif ($resetRecord['account_status'] !== 'active') {
                    $error = "Your account is not active. Please contact support.";
                } else {
                    $tokenValid = true;
                }
            } else {
                $error = "Database connection error. Please ensure MySQL is running in XAMPP.";
            }
        } catch (Exception $e) {
            $error = "System error: " . $e->getMessage();
        }
    }

    // Handle Password Update Form Submission
    if ($tokenValid && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!verifyCSRFToken($csrfToken)) {
            $error = "Security validation failed. Please refresh the page and try again.";
        } else {
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($newPassword) || empty($confirmPassword)) {
                $error = "Please fill in all password fields.";
            } elseif (strlen($newPassword) < 6) {
                $error = "New password must be at least 6 characters long.";
            } elseif ($newPassword !== $confirmPassword) {
                $error = "Passwords do not match.";
            } else {
                try {
                    $db->beginTransaction();

                    $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
                    $upd = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $upd->execute([$hashed, $resetRecord['user_id']]);

                    // Invalidate current token immediately
                    $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?")->execute([$resetRecord['id']]);

                    // Invalidate any other open tokens for this user
                    $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")->execute([$resetRecord['user_id']]);

                    createNotification(
                        $db, 
                        $resetRecord['user_id'], 
                        "Password Reset Successful", 
                        "Your account password was updated successfully.", 
                        "system"
                    );

                    $db->commit();
                    $success = "Your password has been reset successfully! You can now sign in with your new password.";
                    $tokenValid = false; // Hide form upon success
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    $error = "Error updating password: " . $e->getMessage();
                }
            }
        }
    }
} else {
    // Step 1: Request Password Reset Link
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!verifyCSRFToken($csrfToken)) {
            $error = "Security validation failed. Please refresh the page and try again.";
        } else {
            $email = trim($_POST['email'] ?? '');
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Please enter a valid email address.";
            } else {
                try {
                    if ($db) {
                        $stmt = $db->prepare("SELECT user_id, email, full_name, account_status FROM users WHERE email = ? LIMIT 1");
                        $stmt->execute([$email]);
                        $user = $stmt->fetch();

                        // If user exists and is active, create token and send email
                        if ($user && $user['account_status'] === 'active') {
                            $rawToken = bin2hex(random_bytes(32));
                            $tokenHash = hash('sha256', $rawToken);

                            // Invalidate previous tokens for this user
                            $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")->execute([$user['user_id']]);

                            // Insert new token hash (1-hour expiration evaluated on database server)
                            $ins = $db->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
                            $ins->execute([$user['user_id'], $tokenHash]);

                            // Dispatch password reset email
                            $resetLink = BASE_URL . "/forgot_password.php?token=" . urlencode($rawToken);
                            sendPasswordResetEmail($user['email'], $user['full_name'], $resetLink);
                        }

                        // Always display identical message to prevent account enumeration
                        $info = "If an account exists for " . htmlspecialchars($email) . ", a password reset link has been dispatched. Please check your inbox and spam folder. The link is valid for 60 minutes.";
                    } else {
                        $error = "Database connection error. Please ensure MySQL is running in XAMPP.";
                    }
                } catch (Exception $e) {
                    $error = "Error processing request: " . $e->getMessage();
                }
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="p-4 text-center text-white" style="background: linear-gradient(135deg, #0B192C 0%, #1E3A5F 100%);">
                    <h4 class="fw-bold mb-1 text-white">
                        <i class="fa-solid <?= $isTokenMode ? 'fa-lock' : 'fa-key' ?> text-warning me-2"></i>
                        <?= $isTokenMode ? 'Set New Password' : 'Reset Password' ?>
                    </h4>
                    <p class="text-white-50 small mb-0">
                        <?= $isTokenMode ? 'Choose a strong new password for your account' : 'Enter your registered email and we will send a secure reset link' ?>
                    </p>
                </div>

                <div class="p-4 p-md-5 bg-white">
                    <?php if ($error): ?>
                        <div class="alert alert-danger small mb-4">
                            <i class="fa-solid fa-circle-exclamation me-1"></i><?= htmlspecialchars($error) ?>
                            <?php if ($isTokenMode && !$tokenValid): ?>
                                <div class="mt-2 pt-2 border-top">
                                    <a href="<?= BASE_URL ?>/forgot_password.php" class="fw-bold text-danger text-decoration-none">
                                        <i class="fa-solid fa-arrow-rotate-left me-1"></i> Request a new reset link
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($info): ?>
                        <div class="alert alert-info small mb-4">
                            <i class="fa-solid fa-circle-info me-1"></i><?= $info ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success small mb-4 text-center">
                            <i class="fa-solid fa-circle-check fa-2x mb-2 d-block text-success"></i>
                            <?= htmlspecialchars($success) ?><br><br>
                            <a href="<?= BASE_URL ?>/login.php" class="btn btn-success btn-sm px-4">
                                <i class="fa-solid fa-right-to-bracket me-1"></i> Click here to Sign In
                            </a>
                        </div>
                    <?php elseif ($isTokenMode && $tokenValid): ?>
                        <!-- Step 2: Set New Password Form -->
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                            <div class="mb-3">
                                <label class="form-label fw-semibold small text-secondary">New Password</label>
                                <input type="password" name="new_password" class="form-control" required placeholder="Min 6 characters" autofocus>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-semibold small text-secondary">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" required placeholder="Repeat new password">
                            </div>
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-lifelink-red py-2">
                                    <i class="fa-solid fa-check me-1"></i> Update Password
                                </button>
                            </div>
                        </form>
                    <?php elseif (!$isTokenMode && empty($info)): ?>
                        <!-- Step 1: Request Reset Link Form -->
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">

                            <div class="mb-4">
                                <label class="form-label fw-semibold small text-secondary">Registered Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0"><i class="fa-regular fa-envelope"></i></span>
                                    <input type="email" name="email" class="form-control border-start-0" required placeholder="name@example.com" autofocus>
                                </div>
                                <div class="form-text small text-muted">We will dispatch a secure 1-hour verification link to this email address.</div>
                            </div>
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-lifelink-red py-2">
                                    <i class="fa-solid fa-paper-plane me-1"></i> Send Reset Link
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <div class="text-center small mt-3 pt-3 border-top">
                        <a href="<?= BASE_URL ?>/login.php" class="text-muted text-decoration-none">
                            <i class="fa-solid fa-arrow-left me-1"></i> Back to Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>