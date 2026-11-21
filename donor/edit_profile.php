<?php
/**
 * LifeLink - Edit Donor Profile & Password
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('donor');
$currentUser = getCurrentUser();
$userId = $currentUser['user_id'];
$pageTitle = "Edit Donor Profile — LifeLink";

$error = '';
$success = '';

// Fetch existing details
$stmt = $db->prepare("SELECT d.*, u.full_name, u.email, u.phone, u.password 
                      FROM donors d 
                      JOIN users u ON d.user_id = u.user_id 
                      WHERE d.user_id = ?");
$stmt->execute([$userId]);
$donor = $stmt->fetch();

if (!$donor) {
    die("Donor not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        $error = "Security validation failed. Please refresh the page and try again.";
    } else {
        $action = $_POST['action'] ?? 'update_profile';

        if ($action === 'update_profile') {
            $fullName = sanitize($_POST['full_name'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $bloodGroup = sanitize($_POST['blood_group'] ?? '');
            $dob = sanitize($_POST['dob'] ?? '');
            $gender = in_array($_POST['gender'] ?? '', ['Male', 'Female', 'Other']) ? $_POST['gender'] : 'Male';
            $state = sanitize($_POST['state'] ?? '');
            $city = sanitize($_POST['city'] ?? '');
            $area = sanitize($_POST['area'] ?? '');
            $pinCode = sanitize($_POST['pin_code'] ?? '');
            $availability = in_array($_POST['availability'] ?? '', ['Available', 'Temporarily Unavailable', 'Not Accepting Requests']) 
                            ? $_POST['availability'] : 'Available';
            $lastDonationDate = sanitize($_POST['last_donation_date'] ?? '');
            $profileDesc = sanitize($_POST['profile_description'] ?? '');
            $hidePhone = isset($_POST['hide_phone']) ? 1 : 0;

            $validBloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

            if (empty($fullName) || empty($phone) || empty($bloodGroup) || empty($city) || empty($state) || empty($pinCode)) {
                $error = "Please fill in all mandatory account and location fields.";
            } elseif (!in_array($bloodGroup, $validBloodGroups)) {
                $error = "Please select a valid blood group.";
            } else {
                try {
                    $db->beginTransaction();

                    // Update users table
                    $uStmt = $db->prepare("UPDATE users SET full_name = ?, phone = ? WHERE user_id = ?");
                    $uStmt->execute([$fullName, $phone, $userId]);

                    // Update donors table
                    $dStmt = $db->prepare("UPDATE donors SET blood_group = ?, date_of_birth = ?, gender = ?, state = ?, city = ?, area = ?, pin_code = ?, availability = ?, last_donation_date = ?, profile_description = ?, hide_phone = ? WHERE user_id = ?");
                    $dStmt->execute([
                        $bloodGroup,
                        !empty($dob) ? $dob : null,
                        $gender,
                        $state,
                        $city,
                        $area,
                        $pinCode,
                        $availability,
                        !empty($lastDonationDate) ? $lastDonationDate : null,
                        $profileDesc,
                        $hidePhone,
                        $userId
                    ]);

                    $db->commit();
                    $_SESSION['full_name'] = $fullName;
                    $_SESSION['blood_group'] = $bloodGroup;
                    $_SESSION['availability'] = $availability;
                    $_SESSION['flash_msg'] = "Profile details updated successfully!";
                    header("Location: " . BASE_URL . "/donor/profile.php");
                    exit();
                } catch (Exception $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    $error = "Update error: " . $e->getMessage();
                }
            }
        } elseif ($action === 'change_password') {
            $currentPass = $_POST['current_password'] ?? '';
            $newPass = $_POST['new_password'] ?? '';
            $confirmPass = $_POST['confirm_password'] ?? '';

            // Re-fetch latest password hash to prevent stale session checks
            $pCheck = $db->prepare("SELECT password FROM users WHERE user_id = ?");
            $pCheck->execute([$userId]);
            $currentHash = $pCheck->fetchColumn();

            if (!password_verify($currentPass, $currentHash)) {
                $error = "Current password does not match our records.";
            } elseif (strlen($newPass) < 6) {
                $error = "New password must be at least 6 characters long.";
            } elseif ($newPass !== $confirmPass) {
                $error = "New passwords do not match.";
            } else {
                try {
                    $hashed = password_hash($newPass, PASSWORD_BCRYPT);
                    $pStmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $pStmt->execute([$hashed, $userId]);
                    createNotification($db, $userId, "Password Changed", "Your account password was updated successfully from your profile settings.", "system");
                    $success = "Password changed successfully!";
                } catch (Exception $e) {
                    $error = "Error updating password: " . $e->getMessage();
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/donor_navbar.php';
?>

<div class="bg-light min-vh-100 py-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1 text-dark">Edit Donor Profile</h3>
                <p class="text-muted small mb-0">Update your contact information, location, and availability status.</p>
            </div>
            <a href="<?= BASE_URL ?>/donor/profile.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Profile
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show small mb-4" role="alert">
                <i class="fa-solid fa-circle-exclamation me-1"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show small mb-4" role="alert">
                <i class="fa-solid fa-circle-check me-1"></i><?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Profile Form -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                        <input type="hidden" name="action" value="update_profile">
                        <h5 class="fw-bold text-dark mb-4 pb-2 border-bottom"><i class="fa-solid fa-user-pen text-danger me-2"></i>Personal & Medical Info</h5>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-secondary">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control" required value="<?= htmlspecialchars($donor['full_name']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-secondary">Email Address (Read Only)</label>
                                <input type="email" class="form-control bg-light" readonly value="<?= htmlspecialchars($donor['email']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-secondary">Contact Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" name="phone" class="form-control" required value="<?= htmlspecialchars($donor['phone']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-secondary">Blood Group <span class="text-danger">*</span></label>
                                <select name="blood_group" class="form-select" required>
                                    <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                                        <option value="<?= $bg ?>" <?= $donor['blood_group'] === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-secondary">Date of Birth</label>
                                <input type="date" name="dob" class="form-control" value="<?= htmlspecialchars($donor['date_of_birth'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-secondary">Gender</label>
                                <select name="gender" class="form-select">
                                    <option value="Male" <?= $donor['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= $donor['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                    <option value="Other" <?= $donor['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                        </div>

                        <h5 class="fw-bold text-dark mb-4 pb-2 border-bottom"><i class="fa-solid fa-map-location text-danger me-2"></i>Location Details</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-secondary">State <span class="text-danger">*</span></label>
                                <input type="text" name="state" class="form-control" required value="<?= htmlspecialchars($donor['state']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-secondary">City <span class="text-danger">*</span></label>
                                <input type="text" name="city" class="form-control" required value="<?= htmlspecialchars($donor['city']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-secondary">Area / Locality</label>
                                <input type="text" name="area" class="form-control" value="<?= htmlspecialchars($donor['area'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-secondary">PIN Code <span class="text-danger">*</span></label>
                                <input type="text" name="pin_code" class="form-control" required value="<?= htmlspecialchars($donor['pin_code']) ?>">
                            </div>
                        </div>

                        <h5 class="fw-bold text-dark mb-4 pb-2 border-bottom"><i class="fa-solid fa-sliders text-danger me-2"></i>Availability & Readiness</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-secondary">Availability Status</label>
                                <select name="availability" class="form-select">
                                    <option value="Available" <?= $donor['availability'] === 'Available' ? 'selected' : '' ?>>Available (Active for donor matches)</option>
                                    <option value="Temporarily Unavailable" <?= $donor['availability'] === 'Temporarily Unavailable' ? 'selected' : '' ?>>Temporarily Unavailable (Slight delay)</option>
                                    <option value="Not Accepting Requests" <?= $donor['availability'] === 'Not Accepting Requests' ? 'selected' : '' ?>>Not Accepting Requests</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-secondary">Last Whole Blood Donation Date</label>
                                <input type="date" name="last_donation_date" class="form-control" value="<?= htmlspecialchars($donor['last_donation_date'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold small text-secondary">Donor Bio / Availability Notes</label>
                                <textarea name="profile_description" rows="3" class="form-control" placeholder="e.g. Can donate on short notice during weekends in Mumbai..."><?= htmlspecialchars($donor['profile_description'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="hide_phone" id="hidePhone" <?= $donor['hide_phone'] ? 'checked' : '' ?>>
                                    <label class="form-check-label small text-secondary" for="hidePhone">
                                        Hide my phone number from public cards (requesters must submit an official blood request to reach me)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="<?= BASE_URL ?>/donor/profile.php" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-lifelink-red">Save Profile Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Password Change Form -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                    <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-lock text-warning me-2"></i>Change Password</h5>
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                        <input type="hidden" name="action" value="change_password">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-secondary">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required placeholder="••••••••">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-secondary">New Password</label>
                            <input type="password" name="new_password" class="form-control" required placeholder="Min 6 characters">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-secondary">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required placeholder="Repeat new password">
                        </div>
                        <button type="submit" class="btn btn-lifelink-navy w-100">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
