<?php
/**
 * LifeLink - Authentication Login Page
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to respective dashboard
if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? 'donor';
    header("Location: " . BASE_URL . "/{$role}/dashboard.php");
    exit();
}

$pageTitle = "Login — LifeLink Blood Network";
$error = '';
$success = $_SESSION['flash_msg'] ?? '';
unset($_SESSION['flash_msg']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        $error = "Security validation failed. Please refresh and try again.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            $error = "Please enter both email and password.";
        } else {
            try {
                if ($db) {
                    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch();

                    if ($user && password_verify($password, $user['password'])) {
                        // Check account status
                        if ($user['account_status'] !== 'active') {
                            $error = "Your account is currently " . htmlspecialchars($user['account_status']) . ". Please contact support.";
                        } else {
                            // Successful authentication: regenerate session ID for security
                            session_regenerate_id(true);
                            $_SESSION['user_id'] = $user['user_id'];
                            $_SESSION['full_name'] = $user['full_name'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['role'] = $user['role'];

                            // If donor, fetch donor_id and blood group
                            if ($user['role'] === 'donor') {
                                $dStmt = $db->prepare("SELECT donor_id, blood_group, availability FROM donors WHERE user_id = ?");
                                $dStmt->execute([$user['user_id']]);
                                $donor = $dStmt->fetch();
                                if ($donor) {
                                    $_SESSION['donor_id'] = $donor['donor_id'];
                                    $_SESSION['blood_group'] = $donor['blood_group'];
                                    $_SESSION['availability'] = $donor['availability'];
                                }
                            }

                            // Role-based redirection
                            if ($user['role'] === 'admin') {
                                header("Location: " . BASE_URL . "/admin/dashboard.php");
                            } elseif ($user['role'] === 'donor') {
                                header("Location: " . BASE_URL . "/donor/dashboard.php");
                            } elseif ($user['role'] === 'requester') {
                                header("Location: " . BASE_URL . "/requester/dashboard.php");
                            } else {
                                header("Location: " . BASE_URL . "/index.php");
                            }
                            exit();
                        }
                    } else {
                        $error = "Invalid email address or password.";
                    }
                } else {
                    $error = "Database connection error. Please ensure MySQL is active in XAMPP.";
                }
            } catch (Exception $e) {
                $error = "Authentication error: " . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="p-4 p-md-5 text-center" style="background: linear-gradient(135deg, #0B192C 0%, #1E3A5F 100%); color: #fff;">
                    <div class="mb-2"><i class="fa-solid fa-droplet text-danger fa-3x"></i></div>
                    <h3 class="fw-bold mb-1 text-white">Welcome Back</h3>
                    <p class="text-white-50 small mb-0">Sign in to your LifeLink portal</p>
                </div>

                <div class="p-4 p-md-5 bg-white">
                    <?php if (!empty($_SESSION['flash_error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                            <i class="fa-solid fa-circle-exclamation me-1"></i><?= htmlspecialchars($_SESSION['flash_error']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['flash_error']); ?>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                            <i class="fa-solid fa-circle-exclamation me-1"></i><?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show small" role="alert">
                            <i class="fa-solid fa-circle-check me-1"></i><?= htmlspecialchars($success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-secondary">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-envelope text-muted"></i></span>
                                <input type="email" id="loginEmail" name="email" class="form-control border-start-0" placeholder="name@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-semibold small text-secondary mb-0">Password</label>
                                <a href="<?= BASE_URL ?>/forgot_password.php" class="small text-danger">Forgot Password?</a>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-lock text-muted"></i></span>
                                <input type="password" id="loginPassword" name="password" class="form-control border-start-0" placeholder="••••••••" required>
                            </div>
                        </div>

                        <div class="d-grid mb-4">
                            <button type="submit" class="btn btn-lifelink-red py-2 fw-semibold">
                                <i class="fa-solid fa-right-to-bracket me-2"></i> Sign In
                            </button>
                        </div>
                    </form>

                    <!-- Evaluation Quick-Fill Demo Accounts -->
                    <div class="p-3 bg-light rounded-3 border mb-4">
                        <small class="fw-bold text-dark d-block mb-2"><i class="fa-solid fa-wand-magic-sparkles text-warning me-1"></i> Quick Demo Autofill:</small>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-danger btn-sm py-1 px-2" onclick="fillLogin('admin@lifelink.org', 'Admin@123')">
                                Admin
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm py-1 px-2" onclick="fillLogin('marcus.vance@lifelink.org', 'Password@123')">
                                Donor (O-)
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm py-1 px-2" onclick="fillLogin('sarah.requester@lifelink.org', 'Password@123')">
                                Requester
                            </button>
                        </div>
                    </div>

                    <div class="text-center small text-muted">
                        Don't have an account yet? <br>
                        <a href="<?= BASE_URL ?>/register.php?role=donor" class="text-danger fw-semibold">Register as Donor</a> or 
                        <a href="<?= BASE_URL ?>/register.php?role=requester" class="text-primary fw-semibold">Register as Requester</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function fillLogin(email, pass) {
    document.getElementById('loginEmail').value = email;
    document.getElementById('loginPassword').value = pass;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
