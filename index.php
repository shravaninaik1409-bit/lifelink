<?php
/**
 * LifeLink - Home Page
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = "LifeLink — Online Blood Donor Finder and Management System";

// Fetch Live Database Statistics
$totalDonors = 0;
$availableDonors = 0;
$totalRequests = 0;
$completedDonations = 0;
$emergencyRequests = [];

if ($db) {
    try {
        $totalDonors = (int)$db->query("SELECT COUNT(*) FROM donors")->fetchColumn();
        $availableDonors = (int)$db->query("SELECT COUNT(*) FROM donors WHERE availability = 'Available'")->fetchColumn();
        $totalRequests = (int)$db->query("SELECT COUNT(*) FROM blood_requests")->fetchColumn();
        $completedDonations = (int)$db->query("SELECT COUNT(*) FROM donations WHERE verification_status = 'Verified'")->fetchColumn();

        // Fetch active emergency or urgent requests for marquee / cards
        $stmt = $db->query("SELECT * FROM blood_requests WHERE status IN ('Pending', 'Donors Notified', 'Donor Response Received') AND urgency IN ('Emergency', 'Urgent') ORDER BY urgency = 'Emergency' DESC, created_at DESC LIMIT 3");
        $emergencyRequests = $stmt->fetchAll();
    } catch (Exception $e) {
        // Fallback gracefully
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container position-relative" style="z-index: 5;">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <span class="badge bg-danger bg-opacity-75 text-white px-3 py-2 rounded-pill mb-3">
                    <i class="fa-solid fa-heart-pulse me-1"></i> Rapid Healthcare Blood Network
                </span>
                <h1 class="hero-title">
                    Connecting Lives, <br><span>One Donation</span> at a Time.
                </h1>
                <p class="hero-subtitle">
                    LifeLink bridges the critical gap between voluntary blood donors and patients in need. Search by blood group and city or broadcast emergency requests within seconds.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="<?= BASE_URL ?>/find_donors.php" class="btn btn-lifelink-red btn-lg">
                        <i class="fa-solid fa-magnifying-glass me-2"></i> Find a Donor
                    </a>
                    <a href="<?= BASE_URL ?>/register.php?role=donor" class="btn btn-outline-light btn-lg">
                        <i class="fa-solid fa-hand-holding-heart me-2"></i> Become a Donor
                    </a>
                </div>
            </div>

            <div class="col-lg-5">
                <!-- Quick Search Box -->
                <div class="quick-search-box">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="fa-solid fa-bolt text-danger fs-4"></i>
                        <h4 class="mb-0 fw-bold text-dark">Quick Donor Search</h4>
                    </div>
                    <p class="text-muted small mb-4">Find verified available blood donors in your area immediately.</p>
                    
                    <form action="<?= BASE_URL ?>/find_donors.php" method="GET">
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-secondary small">Required Blood Group</label>
                            <select name="blood_group" class="form-select" required>
                                <option value="">Select Blood Group</option>
                                <option value="A+">A+ (A Positive)</option>
                                <option value="A-">A- (A Negative)</option>
                                <option value="B+">B+ (B Positive)</option>
                                <option value="B-">B- (B Negative)</option>
                                <option value="AB+">AB+ (AB Positive)</option>
                                <option value="AB-">AB- (AB Negative)</option>
                                <option value="O+">O+ (O Positive)</option>
                                <option value="O-">O- (O Negative)</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold text-secondary small">City / Location</label>
                            <input type="text" name="city" class="form-control" placeholder="e.g. Mumbai, Bengaluru, Delhi">
                        </div>
                        <button type="submit" class="btn btn-lifelink-red w-100 py-2">
                            <i class="fa-solid fa-search me-1"></i> Search Matching Donors
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Live Stat Banner -->
<div class="container stat-banner">
    <div class="row g-3">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="stat-number"><?= number_format($totalDonors) ?></div>
                <div class="stat-label">Registered Donors</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="fa-solid fa-user-check"></i>
                </div>
                <div class="stat-number"><?= number_format($availableDonors) ?></div>
                <div class="stat-label">Available Now</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="fa-solid fa-hand-holding-droplet"></i>
                </div>
                <div class="stat-number"><?= number_format($totalRequests) ?></div>
                <div class="stat-label">Blood Requests</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-info bg-opacity-10 text-info">
                    <i class="fa-solid fa-heart-pulse"></i>
                </div>
                <div class="stat-number"><?= number_format($completedDonations) ?></div>
                <div class="stat-label">Lives Touched</div>
            </div>
        </div>
    </div>
</div>

<!-- Active Emergency Alerts (if any) -->
<?php if (!empty($emergencyRequests)): ?>
<section class="section-padding bg-light pb-0">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <span class="badge bg-danger text-white px-2 py-1 mb-1"><i class="fa-solid fa-circle-exclamation me-1"></i> High Priority</span>
                <h3 class="fw-bold mb-0">Urgent Blood Requests</h3>
            </div>
            <a href="<?= BASE_URL ?>/find_donors.php" class="btn btn-outline-danger btn-sm">
                View All Needs <i class="fa-solid fa-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="row g-4">
            <?php foreach ($emergencyRequests as $req): ?>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 p-4 border-top border-4 <?= $req['urgency'] === 'Emergency' ? 'border-danger' : 'border-warning' ?>">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <span class="blood-badge"><?= htmlspecialchars($req['blood_group']) ?></span>
                            <?= getUrgencyBadge($req['urgency']) ?>
                        </div>
                        <h5 class="fw-bold mb-1 text-truncate"><?= htmlspecialchars($req['patient_name']) ?></h5>
                        <p class="text-muted small mb-2"><i class="fa-solid fa-hospital text-danger me-1"></i> <?= htmlspecialchars($req['hospital_name']) ?></p>
                        <p class="text-secondary small mb-3"><i class="fa-solid fa-location-dot text-primary me-1"></i> <?= htmlspecialchars($req['city']) ?>, <?= htmlspecialchars($req['state']) ?></p>
                        
                        <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-danger"><?= (int)$req['units_required'] ?> Unit(s) needed</span>
                            <a href="<?= BASE_URL ?>/login.php" class="btn btn-sm btn-lifelink-red">Respond</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- How It Works Section -->
<section id="how-it-works" class="section-padding">
    <div class="container text-center">
        <span class="text-danger fw-bold text-uppercase small tracking-wide">Simple & Seamless</span>
        <h2 class="section-title">How LifeLink Works</h2>
        <p class="section-subtitle">
            Our platform simplifies the blood donation lifecycle so you can find help or donate blood without unnecessary administrative friction.
        </p>

        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="step-card">
                    <span class="step-number">01</span>
                    <div class="step-icon">
                        <i class="fa-solid fa-file-medical"></i>
                    </div>
                    <h5 class="fw-bold mb-2">1. Post a Request</h5>
                    <p class="text-muted small mb-0">Requesters or hospitals submit patient requirements with urgency levels and unique tracking IDs.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="step-card">
                    <span class="step-number">02</span>
                    <div class="step-icon">
                        <i class="fa-solid fa-microchip"></i>
                    </div>
                    <h5 class="fw-bold mb-2">2. Smart Matching</h5>
                    <p class="text-muted small mb-0">Our system identifies available compatible donors in the immediate city or area automatically.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="step-card">
                    <span class="step-number">03</span>
                    <div class="step-icon">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                    <h5 class="fw-bold mb-2">3. Instant Notification</h5>
                    <p class="text-muted small mb-0">Donors receive immediate alerts on their dashboard and can accept or decline with a single click.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="step-card">
                    <span class="step-number">04</span>
                    <div class="step-icon">
                        <i class="fa-solid fa-handshake-angle"></i>
                    </div>
                    <h5 class="fw-bold mb-2">4. Life Saved</h5>
                    <p class="text-muted small mb-0">Donor arrives at the healthcare facility, donates blood, and earns verified community badges.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Blood Compatibility Matrix & Educational Awareness -->
<section class="section-padding bg-light">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <span class="text-danger fw-bold text-uppercase small">Educational Awareness</span>
                <h2 class="section-title">Blood Group Compatibility</h2>
                <p class="text-muted mb-4">
                    Knowing your blood type compatibility can save precious minutes during critical transfusions. O- donors are universal red cell donors, while AB+ individuals are universal recipients.
                </p>

                <div class="table-responsive shadow-sm">
                    <table class="compat-table">
                        <thead>
                            <tr>
                                <th>Blood Type</th>
                                <th>Can Donate To</th>
                                <th>Can Receive From</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong class="text-danger">O-</strong> <span class="badge bg-danger text-white ms-1">Universal Donor</span></td>
                                <td>All Blood Groups</td>
                                <td>O- only</td>
                            </tr>
                            <tr>
                                <td><strong class="text-danger">O+</strong></td>
                                <td>O+, A+, B+, AB+</td>
                                <td>O+, O-</td>
                            </tr>
                            <tr>
                                <td><strong class="text-danger">A-</strong></td>
                                <td>A-, A+, AB-, AB+</td>
                                <td>A-, O-</td>
                            </tr>
                            <tr>
                                <td><strong class="text-danger">A+</strong></td>
                                <td>A+, AB+</td>
                                <td>A+, A-, O+, O-</td>
                            </tr>
                            <tr>
                                <td><strong class="text-danger">B-</strong></td>
                                <td>B-, B+, AB-, AB+</td>
                                <td>B-, O-</td>
                            </tr>
                            <tr>
                                <td><strong class="text-danger">B+</strong></td>
                                <td>B+, AB+</td>
                                <td>B+, B-, O+, O-</td>
                            </tr>
                            <tr>
                                <td><strong class="text-danger">AB-</strong></td>
                                <td>AB-, AB+</td>
                                <td>AB-, A-, B-, O-</td>
                            </tr>
                            <tr>
                                <td><strong class="text-danger">AB+</strong> <span class="badge bg-primary ms-1">Universal Recipient</span></td>
                                <td>AB+ only</td>
                                <td>All Blood Groups</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="p-4 p-md-5 bg-white rounded-4 border shadow-sm">
                    <h4 class="fw-bold text-dark mb-3"><i class="fa-solid fa-clipboard-check text-success me-2"></i>General Eligibility Guidelines</h4>
                    <p class="text-muted small mb-4">
                        Please review the standard requirements before pledging to donate. Medical confirmation is performed on-site by qualified healthcare staff.
                    </p>

                    <div class="d-flex gap-3 mb-3">
                        <div class="text-danger fs-5"><i class="fa-solid fa-circle-check"></i></div>
                        <div>
                            <strong class="d-block text-dark">Age & Weight</strong>
                            <small class="text-muted">Between 18 and 65 years old, weighing at least 50 kg (110 lbs).</small>
                        </div>
                    </div>
                    <div class="d-flex gap-3 mb-3">
                        <div class="text-danger fs-5"><i class="fa-solid fa-circle-check"></i></div>
                        <div>
                            <strong class="d-block text-dark">Donation Interval</strong>
                            <small class="text-muted">At least 84 days (12 weeks) between whole blood donations for your safety.</small>
                        </div>
                    </div>
                    <div class="d-flex gap-3 mb-3">
                        <div class="text-danger fs-5"><i class="fa-solid fa-circle-check"></i></div>
                        <div>
                            <strong class="d-block text-dark">Hemoglobin & Vitals</strong>
                            <small class="text-muted">Healthy pulse, normal blood pressure, and hemoglobin levels &ge; 12.5 g/dL.</small>
                        </div>
                    </div>
                    <div class="d-flex gap-3">
                        <div class="text-danger fs-5"><i class="fa-solid fa-circle-check"></i></div>
                        <div>
                            <strong class="d-block text-dark">General Well-being</strong>
                            <small class="text-muted">Free of active cold, flu, fever, or acute infections on the day of donation.</small>
                        </div>
                    </div>

                    <div class="mt-4 p-3 bg-light rounded text-center">
                        <a href="<?= BASE_URL ?>/register.php?role=donor" class="btn btn-lifelink-red w-100">
                            <i class="fa-solid fa-user-plus me-1"></i> Register as a LifeLink Donor
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Community Testimonials -->
<section class="section-padding">
    <div class="container text-center">
        <span class="text-danger fw-bold text-uppercase small">Real Stories</span>
        <h2 class="section-title">Touched by Kindness</h2>
        <p class="section-subtitle">Read how our voluntary donors helped save lives in critical hours of need.</p>

        <div class="row g-4 text-start">
            <div class="col-md-4">
                <div class="card h-100 p-4 border-0 shadow-sm rounded-4">
                    <div class="text-warning mb-3">
                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                    </div>
                    <p class="text-muted mb-4">"When my father met with a road accident in Mumbai, we needed 3 units of O- blood urgently. Within 20 minutes of submitting our request on LifeLink, a donor confirmed and arrived at the hospital."</p>
                    <div class="d-flex align-items-center gap-3 mt-auto">
                        <div class="user-avatar-circle" style="background: #E11D48;">S</div>
                        <div>
                            <strong class="d-block text-dark">Sarah Jenkins</strong>
                            <small class="text-muted">Family Requester, Mumbai</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100 p-4 border-0 shadow-sm rounded-4">
                    <div class="text-warning mb-3">
                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                    </div>
                    <p class="text-muted mb-4">"As a universal donor, I always wanted to contribute where it matters most. LifeLink lets me toggle my availability with one tap and only notifies me when patients are genuinely in need."</p>
                    <div class="d-flex align-items-center gap-3 mt-auto">
                        <div class="user-avatar-circle" style="background: #0284C7;">M</div>
                        <div>
                            <strong class="d-block text-dark">Dr. Marcus Vance</strong>
                            <small class="text-muted">Verified O- Donor</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100 p-4 border-0 shadow-sm rounded-4">
                    <div class="text-warning mb-3">
                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                    </div>
                    <p class="text-muted mb-4">"Managing blood inventory for surgery cases is stressful. LifeLink helps our hospital coordinator identify verified voluntary donors quickly during shortage spikes."</p>
                    <div class="d-flex align-items-center gap-3 mt-auto">
                        <div class="user-avatar-circle" style="background: #10B981;">C</div>
                        <div>
                            <strong class="d-block text-dark">City Care Hospital</strong>
                            <small class="text-muted">Medical Partner, Bengaluru</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action Banner -->
<section class="py-5" style="background: linear-gradient(135deg, #0B192C, #1E3A5F); color: #fff;">
    <div class="container text-center py-4">
        <h2 class="fw-bold mb-3 text-white">Ready to Make a Life-Saving Difference?</h2>
        <p class="text-white-50 mb-4" style="max-width: 600px; margin: 0 auto;">
            Every single donation has the potential to save up to three lives. Join our community of voluntary donors today.
        </p>
        <div class="d-flex justify-content-center gap-3">
            <a href="<?= BASE_URL ?>/register.php?role=donor" class="btn btn-lifelink-red btn-lg">
                <i class="fa-solid fa-droplet me-2"></i> Register as Donor
            </a>
            <a href="<?= BASE_URL ?>/find_donors.php" class="btn btn-outline-light btn-lg">
                <i class="fa-solid fa-magnifying-glass me-2"></i> Find Donors
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
