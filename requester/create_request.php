<?php
/**
 * LifeLink - Create Blood Request Page
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('requester');
$currentUser = getCurrentUser();
$userId = $currentUser['user_id'];
$pageTitle = "Post Blood Request — LifeLink";

// Pre-fill from GET if navigated from donor search
$prefillGroup = $_GET['blood_group'] ?? '';
$prefillCity = $_GET['city'] ?? '';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        $error = "Security validation failed. Please refresh the page and try again.";
    } else {
        $patientName = sanitize($_POST['patient_name'] ?? '');
    $bloodGroup = sanitize($_POST['blood_group'] ?? '');
    $unitsRequired = max(1, (int)($_POST['units_required'] ?? 1));
    $hospitalName = sanitize($_POST['hospital_name'] ?? '');
    $hospitalAddress = sanitize($_POST['hospital_address'] ?? '');
    $state = sanitize($_POST['state'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $pinCode = sanitize($_POST['pin_code'] ?? '');
    $requiredDate = sanitize($_POST['required_date'] ?? '');
    $urgency = in_array($_POST['urgency'] ?? '', ['Normal', 'Urgent', 'Emergency']) ? $_POST['urgency'] : 'Normal';
    $additionalInfo = sanitize($_POST['additional_information'] ?? '');
    $contactPerson = sanitize($_POST['contact_person'] ?? '');
    $contactPhone = sanitize($_POST['contact_phone'] ?? '');

    if (empty($patientName) || empty($bloodGroup) || empty($hospitalName) || empty($city) || empty($state) || empty($requiredDate) || empty($contactPerson) || empty($contactPhone)) {
        $error = "Please fill in all mandatory fields.";
    } else {
        try {
            $db->beginTransaction();

            $uniqueRequestNumber = generateUniqueRequestNumber($db);
            $initialStatus = 'Donors Notified';

            $stmt = $db->prepare("INSERT INTO blood_requests 
                (unique_request_number, requester_id, patient_name, blood_group, units_required, hospital_name, hospital_address, state, city, pin_code, required_date, urgency, additional_information, contact_person, contact_phone, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $uniqueRequestNumber,
                $userId,
                $patientName,
                $bloodGroup,
                $unitsRequired,
                $hospitalName,
                $hospitalAddress,
                $state,
                $city,
                $pinCode,
                $requiredDate,
                $urgency,
                $additionalInfo,
                $contactPerson,
                $contactPhone,
                $initialStatus
            ]);
            $requestId = $db->lastInsertId();

            // Find matching available donors to broadcast notification
            $matchingStmt = $db->prepare("SELECT user_id FROM donors WHERE blood_group = ? AND availability != 'Not Accepting Requests'");
            $matchingStmt->execute([$bloodGroup]);
            $matchingDonors = $matchingStmt->fetchAll(PDO::FETCH_COLUMN);

            $notifType = ($urgency === 'Emergency') ? 'emergency' : 'request';
            $notifTitle = ($urgency === 'Emergency') ? "EMERGENCY: Urgent {$bloodGroup} Blood Required" : "New Blood Request for {$bloodGroup}";
            $notifMsg = "Patient {$patientName} needs {$unitsRequired} unit(s) of {$bloodGroup} at {$hospitalName}, {$city}. Required by: " . date('d M Y', strtotime($requiredDate));

            foreach ($matchingDonors as $targetUserId) {
                createNotification($db, $targetUserId, $notifTitle, $notifMsg, $notifType, $requestId);
            }

            // Create notification for requester confirming submission
            createNotification($db, $userId, "Blood Request Submitted", "Request {$uniqueRequestNumber} has been broadcast to " . count($matchingDonors) . " matching donor(s).", "system", $requestId);

            $db->commit();
            $_SESSION['flash_msg'] = "Request {$uniqueRequestNumber} posted successfully! " . count($matchingDonors) . " matching donors have been notified.";
            header("Location: " . BASE_URL . "/requester/request_details.php?id=" . $requestId);
            exit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $error = "Failed to create request: " . $e->getMessage();
        }
    }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout">
    <?php require_once __DIR__ . '/../includes/dashboard_sidebar.php'; ?>

    <div class="dashboard-main">
        <?php require_once __DIR__ . '/../includes/dashboard_topbar.php'; ?>

        <div class="dashboard-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark">Create Blood Request</h3>
                    <p class="text-muted small mb-0">Broadcast patient requirements to active voluntary donors across the network.</p>
                </div>
                <a href="<?= BASE_URL ?>/requester/my_requests.php" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-list-check me-1"></i> View All Requests
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-1"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                            <!-- Urgency Selection Card -->
                            <div class="p-3 rounded-4 bg-light border mb-4">
                                <label class="form-label fw-bold small text-dark d-block mb-2">
                                    <i class="fa-solid fa-triangle-exclamation text-danger me-1"></i> Urgency Level <span class="text-danger">*</span>
                                </label>
                                <div class="row g-2">
                                    <div class="col-4">
                                        <input type="radio" class="btn-check" name="urgency" id="urgNormal" value="Normal" checked>
                                        <label class="btn btn-outline-secondary w-100 py-2 small fw-semibold" for="urgNormal">
                                            <i class="fa-solid fa-clock me-1"></i> Normal
                                        </label>
                                    </div>
                                    <div class="col-4">
                                        <input type="radio" class="btn-check" name="urgency" id="urgUrgent" value="Urgent">
                                        <label class="btn btn-outline-warning w-100 py-2 small fw-semibold text-dark" for="urgUrgent">
                                            <i class="fa-solid fa-bolt me-1"></i> Urgent (24-48 hrs)
                                        </label>
                                    </div>
                                    <div class="col-4">
                                        <input type="radio" class="btn-check" name="urgency" id="urgEmergency" value="Emergency">
                                        <label class="btn btn-outline-danger w-100 py-2 small fw-semibold" for="urgEmergency">
                                            <i class="fa-solid fa-circle-exclamation me-1"></i> Emergency (Immediate)
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <h5 class="fw-bold text-dark mb-4 pb-2 border-bottom">
                                <i class="fa-solid fa-user-injured text-danger me-2"></i>Patient & Requirement Details
                            </h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small text-secondary">Patient Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="patient_name" class="form-control" placeholder="e.g. Ramesh Kumar" required value="<?= htmlspecialchars($_POST['patient_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small text-secondary">Blood Group Needed <span class="text-danger">*</span></label>
                                    <select name="blood_group" class="form-select" required>
                                        <option value="">Select</option>
                                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                                            <option value="<?= $bg ?>" <?= ($prefillGroup === $bg || ($_POST['blood_group'] ?? '') === $bg) ? 'selected' : '' ?>><?= $bg ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small text-secondary">Units Required <span class="text-danger">*</span></label>
                                    <input type="number" name="units_required" class="form-control" min="1" max="10" value="<?= htmlspecialchars($_POST['units_required'] ?? '1') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small text-secondary">Date Blood is Needed By <span class="text-danger">*</span></label>
                                    <input type="date" name="required_date" class="form-control" required value="<?= htmlspecialchars($_POST['required_date'] ?? date('Y-m-d')) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small text-secondary">Additional Notes / Clinical Purpose</label>
                                    <input type="text" name="additional_information" class="form-control" placeholder="e.g. Emergency surgery, Chemotherapy, Trauma" value="<?= htmlspecialchars($_POST['additional_information'] ?? '') ?>">
                                </div>
                            </div>

                            <h5 class="fw-bold text-dark mb-4 pb-2 border-bottom">
                                <i class="fa-solid fa-hospital text-danger me-2"></i>Hospital / Transfusion Center Location
                            </h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold small text-secondary">Hospital Name <span class="text-danger">*</span></label>
                                    <input type="text" name="hospital_name" class="form-control" placeholder="e.g. Lilavati Hospital & Research Centre" required value="<?= htmlspecialchars($_POST['hospital_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold small text-secondary">Hospital Street Address</label>
                                    <textarea name="hospital_address" rows="2" class="form-control" placeholder="Ward / Department / Building address..."><?= htmlspecialchars($_POST['hospital_address'] ?? '') ?></textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-secondary">State <span class="text-danger">*</span></label>
                                    <input type="text" name="state" class="form-control" placeholder="e.g. Maharashtra" required value="<?= htmlspecialchars($_POST['state'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-secondary">City <span class="text-danger">*</span></label>
                                    <input type="text" name="city" class="form-control" placeholder="e.g. Mumbai" required value="<?= htmlspecialchars($prefillCity ?: ($_POST['city'] ?? '')) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-secondary">PIN Code <span class="text-danger">*</span></label>
                                    <input type="text" name="pin_code" class="form-control" placeholder="e.g. 400050" required value="<?= htmlspecialchars($_POST['pin_code'] ?? '') ?>">
                                </div>
                            </div>

                            <h5 class="fw-bold text-dark mb-4 pb-2 border-bottom">
                                <i class="fa-solid fa-phone text-danger me-2"></i>Contact Person on Duty
                            </h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small text-secondary">Contact Person Name <span class="text-danger">*</span></label>
                                    <input type="text" name="contact_person" class="form-control" placeholder="e.g. Sarah Jenkins (Family) or Dr. Saxena" required value="<?= htmlspecialchars($_POST['contact_person'] ?? $currentUser['full_name']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small text-secondary">Direct Mobile Number <span class="text-danger">*</span></label>
                                    <input type="tel" name="contact_phone" class="form-control" placeholder="e.g. +91 9822334455" required value="<?= htmlspecialchars($_POST['contact_phone'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-lifelink-red py-3 fw-semibold">
                                    <i class="fa-solid fa-paper-plane me-2"></i> Broadcast Blood Request Now
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Guidance & Tips Sidebar -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                        <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-lightbulb text-warning me-2"></i>Request Guidelines</h6>
                        <ul class="list-unstyled small text-muted mb-0">
                            <li class="mb-3 d-flex gap-2">
                                <i class="fa-solid fa-check text-success mt-1"></i>
                                <span>Ensure the hospital name and city are exact so local donors can assess travel feasibility.</span>
                            </li>
                            <li class="mb-3 d-flex gap-2">
                                <i class="fa-solid fa-check text-success mt-1"></i>
                                <span>Emergency requests will trigger high-priority alerts to matching donors registered in the area.</span>
                            </li>
                            <li class="d-flex gap-2">
                                <i class="fa-solid fa-check text-success mt-1"></i>
                                <span>Keep your phone accessible to answer confirmation calls from prospective voluntary donors.</span>
                            </li>
                        </ul>
                    </div>

                    <div class="p-3 bg-light rounded-4 border">
                        <small class="text-muted d-block mb-1">Generated ID Example:</small>
                        <code class="fw-bold fs-6 text-danger">LL-<?= date('Y') ?>-XXXXXX</code>
                        <small class="text-muted d-block mt-2">Every request receives a trackable unique ID dynamically monitored through an end-to-end timeline.</small>
                    </div>
                </div>
            </div>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>
