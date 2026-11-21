<?php
/**
 * LifeLink - User Registration Page (Donor & Requester)
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? 'donor';
    header("Location: " . BASE_URL . "/{$role}/dashboard.php");
    exit();
}

$pageTitle = "Register — LifeLink Blood Network";
$selectedRole = $_GET['role'] ?? 'donor';
if (!in_array($selectedRole, ['donor', 'requester'])) {
    $selectedRole = 'donor';
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        $error = "Security validation failed. Please refresh the page.";
    } else {
        $role = in_array($_POST['role'] ?? '', ['donor', 'requester']) ? $_POST['role'] : 'donor';
        $fullName = sanitize($_POST['full_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $agreeTerms = isset($_POST['agree_terms']);

        // Donor specific fields
        $bloodGroup = sanitize($_POST['blood_group'] ?? '');
        $dob = sanitize($_POST['dob'] ?? '');
        $gender = sanitize($_POST['gender'] ?? 'Male');
        $state = sanitize($_POST['state'] ?? '');
        $city = sanitize($_POST['city'] ?? '');
        $area = sanitize($_POST['area'] ?? '');
        $pinCode = sanitize($_POST['pin_code'] ?? '');
        $availability = sanitize($_POST['availability'] ?? 'Available');

        // Donor preliminary screening questionnaire answers
        $screeningRecent = sanitize($_POST['screening_recent_donation'] ?? 'No');
        $screeningFeelingWell = sanitize($_POST['screening_feeling_well'] ?? 'Yes');
        $screeningMedicalAdvice = sanitize($_POST['screening_medical_advice'] ?? 'No');
        $screeningWilling = sanitize($_POST['screening_willing_to_donate'] ?? 'Yes');
        $screeningReviewed = sanitize($_POST['screening_reviewed_guidelines'] ?? 'Yes');

        $screeningAnswers = [
            'donated_recently' => $screeningRecent,
            'feeling_well' => $screeningFeelingWell,
            'medical_advice' => $screeningMedicalAdvice,
            'willing_to_donate' => $screeningWilling,
            'reviewed_guidelines' => $screeningReviewed,
            'completed_at' => date('Y-m-d H:i:s')
        ];
        $screeningAnswersJson = json_encode($screeningAnswers);

        // Validation
        if (empty($fullName) || empty($email) || empty($phone) || empty($password)) {
            $error = "Please fill in all mandatory account fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please provide a valid email address.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } elseif ($password !== $confirmPassword) {
            $error = "Passwords do not match.";
        } elseif (!$agreeTerms) {
            $error = "You must accept the terms of service and privacy policy to register.";
        } elseif ($role === 'donor' && (empty($bloodGroup) || empty($city) || empty($state) || empty($pinCode))) {
            $error = "Please provide your blood group, city, state, and PIN code for donor matching.";
        } else {
            try {
                if ($db) {
                    // Check duplicate email
                    $chk = $db->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
                    $chk->execute([$email]);
                    if ($chk->fetch()) {
                        $error = "An account with this email address already exists. Please sign in.";
                    } else {
                        $db->beginTransaction();

                        // Hash password securely
                        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                        // New accounts start with 'pending' verification (Pending Admin Review)
                        $verificationStatus = 'pending';

                        $stmt = $db->prepare("INSERT INTO users (full_name, email, phone, password, role, account_status, verification_status) VALUES (?, ?, ?, ?, ?, 'active', ?)");
                        $stmt->execute([$fullName, $email, $phone, $hashedPassword, $role, $verificationStatus]);
                        $newUserId = $db->lastInsertId();

                        // If donor, insert into donors table including screening fields
                        if ($role === 'donor') {
                            $dStmt = $db->prepare("INSERT INTO donors (user_id, blood_group, date_of_birth, gender, state, city, area, pin_code, availability, badges, screening_completed, screening_answers, screening_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'First Donation', 1, ?, NOW())");
                            $dStmt->execute([$newUserId, $bloodGroup, !empty($dob) ? $dob : null, $gender, $state, $city, $area, $pinCode, $availability, $screeningAnswersJson]);
                        }

                        // Create welcome notification explaining screening & verification
                        if ($role === 'donor') {
                            createNotification($db, $newUserId, "Profile Submitted for Verification", "Your donor profile and preliminary screening information have been received. An administrator will verify your profile details. Please note that medical eligibility is determined by healthcare staff at the time of donation.", "system");
                        } else {
                            createNotification($db, $newUserId, "Welcome to LifeLink!", "Thank you for registering on LifeLink. Your account is active and ready to post blood requirements.", "system");
                        }

                        $db->commit();

                        // Auto-login and initialize session for a smooth user experience
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $newUserId;
                        $_SESSION['full_name'] = $fullName;
                        $_SESSION['email'] = $email;
                        $_SESSION['role'] = $role;

                        if ($role === 'donor') {
                            $dStmt = $db->prepare("SELECT donor_id FROM donors WHERE user_id = ?");
                            $dStmt->execute([$newUserId]);
                            $_SESSION['donor_id'] = $dStmt->fetchColumn();
                            $_SESSION['blood_group'] = $bloodGroup;
                            $_SESSION['availability'] = $availability;
                            $_SESSION['flash_msg'] = "Welcome to LifeLink, {$fullName}! Your preliminary screening is completed. Your profile verification is currently pending review.";
                            header("Location: " . BASE_URL . "/donor/dashboard.php");
                        } else {
                            $_SESSION['flash_msg'] = "Welcome to LifeLink, {$fullName}! Your requester account is now active.";
                            header("Location: " . BASE_URL . "/requester/dashboard.php");
                        }
                        exit();
                    }
                } else {
                    $error = "Database connection error.";
                }
            } catch (Exception $e) {
                if ($db && $db->inTransaction()) {
                    $db->rollBack();
                }
                $error = "Registration error: " . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="p-4 p-md-5 text-center text-white" style="background: linear-gradient(135deg, #0B192C 0%, #1E3A5F 100%);">
                    <h2 class="fw-bold mb-1 text-white">Create Your LifeLink Account</h2>
                    <p class="text-white-50 mb-0">Join our life-saving community today</p>
                </div>

                <div class="p-4 p-md-5 bg-white">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                            <i class="fa-solid fa-circle-exclamation me-1"></i><?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Role Switcher Tabs -->
                    <div class="d-flex rounded-3 p-1 bg-light border mb-4">
                        <a href="?role=donor" class="btn flex-fill fw-semibold py-2 <?= $selectedRole === 'donor' ? 'btn-danger text-white shadow-sm' : 'text-secondary' ?>">
                            <i class="fa-solid fa-heart-pulse me-1"></i> Register as Blood Donor
                        </a>
                        <a href="?role=requester" class="btn flex-fill fw-semibold py-2 <?= $selectedRole === 'requester' ? 'btn-primary text-white shadow-sm' : 'text-secondary' ?>">
                            <i class="fa-solid fa-hospital-user me-1"></i> Requester / Hospital
                        </a>
                    </div>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                        <input type="hidden" name="role" value="<?= htmlspecialchars($selectedRole) ?>">

                        <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-user text-secondary me-2"></i>Account Information</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-secondary">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control" placeholder="e.g. Rahul Sharma" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-secondary">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" placeholder="e.g. rahul@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-secondary">Mobile Number <span class="text-danger">*</span></label>
                                <input type="tel" name="phone" class="form-control" placeholder="e.g. +91 9876543210" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold small text-secondary">Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" placeholder="Min 6 chars" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold small text-secondary">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
                            </div>
                        </div>

                        <?php if ($selectedRole === 'donor'): ?>
                            <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-droplet text-danger me-2"></i>Donor Profile & Location</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-secondary">Blood Group <span class="text-danger">*</span></label>
                                    <select name="blood_group" class="form-select" required>
                                        <option value="">Select Group</option>
                                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                                            <option value="<?= $bg ?>" <?= ($_POST['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-secondary">Date of Birth</label>
                                    <input type="date" name="dob" class="form-control" value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-secondary">Gender</label>
                                    <select name="gender" class="form-select">
                                        <option value="Male" <?= ($_POST['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= ($_POST['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                        <option value="Other" <?= ($_POST['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small text-secondary">State <span class="text-danger">*</span></label>
                                    <input type="text" name="state" class="form-control" placeholder="e.g. Maharashtra" required value="<?= htmlspecialchars($_POST['state'] ?? '') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small text-secondary">City <span class="text-danger">*</span></label>
                                    <input type="text" name="city" class="form-control" placeholder="e.g. Mumbai" required value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small text-secondary">Area / Locality</label>
                                    <input type="text" name="area" class="form-control" placeholder="e.g. Andheri West" value="<?= htmlspecialchars($_POST['area'] ?? '') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small text-secondary">PIN Code <span class="text-danger">*</span></label>
                                    <input type="text" name="pin_code" class="form-control" placeholder="e.g. 400053" required value="<?= htmlspecialchars($_POST['pin_code'] ?? '') ?>">
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-semibold small text-secondary">Initial Availability Status</label>
                                    <select name="availability" class="form-select">
                                        <option value="Available" selected>Available (Ready to receive blood requests)</option>
                                        <option value="Temporarily Unavailable">Temporarily Unavailable (Will enable later)</option>
                                        <option value="Not Accepting Requests">Not Accepting Requests</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Preliminary Donor Eligibility Screening Questionnaire -->
                            <h5 class="fw-bold text-dark mb-2 pb-2 border-bottom">
                                <i class="fa-solid fa-clipboard-question text-danger me-2"></i>Preliminary Eligibility Questionnaire
                            </h5>
                            <div class="alert alert-info py-2 px-3 small mb-3">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="fa-solid fa-circle-info text-primary mt-1"></i>
                                    <div>
                                        <strong>Medical Screening Policy:</strong> This questionnaire collects preliminary screening information. The system does not automatically approve or disqualify donors. Final eligibility must be determined by qualified healthcare professionals or blood bank staff at the time of donation.
                                    </div>
                                </div>
                            </div>

                            <div class="bg-light p-3 rounded-3 border mb-4">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label fw-semibold small text-dark d-block mb-1">
                                            1. Have you donated blood recently (e.g. within the last 3 months)?
                                        </label>
                                        <div class="d-flex flex-wrap gap-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="screening_recent_donation" id="sRecentNo" value="No" checked>
                                                <label class="form-check-label small" for="sRecentNo">No (More than 3 months ago or first time)</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="screening_recent_donation" id="sRecentYes" value="Yes">
                                                <label class="form-check-label small" for="sRecentYes">Yes (Within last 3 months)</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-semibold small text-dark d-block mb-1">
                                            2. Are you currently feeling healthy and well?
                                        </label>
                                        <div class="d-flex flex-wrap gap-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="screening_feeling_well" id="sWellYes" value="Yes" checked>
                                                <label class="form-check-label small" for="sWellYes">Yes (Healthy, no active fever or illness)</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="screening_feeling_well" id="sWellNo" value="No">
                                                <label class="form-check-label small" for="sWellNo">No (Currently unwell or recovering)</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-semibold small text-dark d-block mb-1">
                                            3. Have you been advised by a healthcare professional not to donate?
                                        </label>
                                        <div class="d-flex flex-wrap gap-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="screening_medical_advice" id="sAdviceNo" value="No" checked>
                                                <label class="form-check-label small" for="sAdviceNo">No (No restriction advised)</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="screening_medical_advice" id="sAdviceYes" value="Yes">
                                                <label class="form-check-label small" for="sAdviceYes">Yes (Medical restriction advised)</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-semibold small text-dark d-block mb-1">
                                            4. Are you willing to donate if contacted for a matching request?
                                        </label>
                                        <div class="d-flex flex-wrap gap-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="screening_willing_to_donate" id="sWillingYes" value="Yes" checked>
                                                <label class="form-check-label small" for="sWillingYes">Yes (Willing to respond)</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="screening_willing_to_donate" id="sWillingNo" value="No">
                                                <label class="form-check-label small" for="sWillingNo">No (Currently not available)</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-semibold small text-dark d-block mb-1">
                                            5. Have you reviewed the basic blood donation eligibility information?
                                        </label>
                                        <div class="d-flex flex-wrap gap-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="screening_reviewed_guidelines" id="sReviewedYes" value="Yes" checked>
                                                <label class="form-check-label small" for="sReviewedYes">Yes (I have reviewed the guidelines)</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="screening_reviewed_guidelines" id="sReviewedNo" value="No">
                                                <label class="form-check-label small" for="sReviewedNo">No</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" name="agree_terms" id="agreeTerms" required>
                            <label class="form-check-label small text-muted" for="agreeTerms">
                                I confirm that all provided information is accurate and agree to the <a href="#" class="text-danger">Terms of Service</a> and <a href="#" class="text-danger">Privacy Guidelines</a>. I understand that medical eligibility will be verified prior to any donation.
                            </label>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-lifelink-red py-3 fw-semibold">
                                <i class="fa-solid fa-user-check me-2"></i> Complete Registration
                            </button>
                        </div>

                        <div class="text-center small text-muted">
                            Already registered? <a href="<?= BASE_URL ?>/login.php" class="text-danger fw-semibold">Sign In Here</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
