<?php
/**
 * LifeLink - Find Donors Search Page
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = "Find Blood Donors — LifeLink";

$currentUser = getCurrentUser();

// Handle favourite toggling if logged in via secure POST + CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_fav' && $currentUser && isset($_POST['donor_id'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        $_SESSION['flash_error'] = "Security validation failed. Please try again.";
    } else {
        $donorId = (int)$_POST['donor_id'];
        try {
            // Check if already favourited with user ownership
            $chk = $db->prepare("SELECT favourite_id FROM favourites WHERE user_id = ? AND donor_id = ?");
            $chk->execute([$currentUser['user_id'], $donorId]);
            $existing = $chk->fetch();
            if ($existing) {
                $del = $db->prepare("DELETE FROM favourites WHERE favourite_id = ? AND user_id = ?");
                $del->execute([$existing['favourite_id'], $currentUser['user_id']]);
                $_SESSION['flash_msg'] = "Donor removed from your saved list.";
            } else {
                $ins = $db->prepare("INSERT INTO favourites (user_id, donor_id) VALUES (?, ?)");
                $ins->execute([$currentUser['user_id'], $donorId]);
                $_SESSION['flash_msg'] = "Donor saved to your favourites!";
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Could not update favourite status: " . $e->getMessage();
        }
    }
    // Safely redirect back preserving query parameters
    $redirectQuery = $_POST['query_string'] ?? '';
    header("Location: " . BASE_URL . "/find_donors.php" . ($redirectQuery ? '?' . $redirectQuery : ''));
    exit();
}

// User favourites list
$userFavourites = [];
if ($currentUser && $db) {
    try {
        $favStmt = $db->prepare("SELECT donor_id FROM favourites WHERE user_id = ?");
        $favStmt->execute([$currentUser['user_id']]);
        $userFavourites = $favStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}
}

// Search & Filter parameters
$bloodGroup = trim($_GET['blood_group'] ?? '');
$state = trim($_GET['state'] ?? '');
$city = trim($_GET['city'] ?? '');
$area = trim($_GET['area'] ?? '');
$pinCode = trim($_GET['pin_code'] ?? '');
$availability = trim($_GET['availability'] ?? '');
$sortBy = trim($_GET['sort'] ?? 'availability');

$donors = [];
$totalResults = 0;

if ($db) {
    $sql = "SELECT d.*, u.full_name, u.verification_status, u.account_status,
            (SELECT COUNT(*) FROM donations dn WHERE dn.donor_id = d.donor_id AND dn.verification_status IN ('Verified', 'Approved')) as total_donations
            FROM donors d
            JOIN users u ON d.user_id = u.user_id
            WHERE u.account_status = 'active'";
    $params = [];

    if (!empty($bloodGroup)) {
        $sql .= " AND d.blood_group = ?";
        $params[] = $bloodGroup;
    }
    if (!empty($state)) {
        $sql .= " AND d.state LIKE ?";
        $params[] = '%' . $state . '%';
    }
    if (!empty($city)) {
        $sql .= " AND d.city LIKE ?";
        $params[] = '%' . $city . '%';
    }
    if (!empty($area)) {
        $sql .= " AND d.area LIKE ?";
        $params[] = '%' . $area . '%';
    }
    if (!empty($pinCode)) {
        $sql .= " AND d.pin_code LIKE ?";
        $params[] = '%' . $pinCode . '%';
    }
    if (!empty($availability)) {
        $sql .= " AND d.availability = ?";
        $params[] = $availability;
    }

    // Sorting
    switch ($sortBy) {
        case 'donations':
            $sql .= " ORDER BY total_donations DESC, d.availability = 'Available' DESC";
            break;
        case 'newest':
            $sql .= " ORDER BY d.created_at DESC";
            break;
        case 'city':
            $sql .= " ORDER BY d.city ASC, d.availability = 'Available' DESC";
            break;
        default: // availability first
            $sql .= " ORDER BY (d.availability = 'Available') DESC, (d.availability = 'Temporarily Unavailable') DESC, u.verification_status = 'verified' DESC";
            break;
    }

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $donors = $stmt->fetchAll();
        $totalResults = count($donors);
    } catch (Exception $e) {
        $donors = [];
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Search Header Banner -->
<section class="py-4 text-white" style="background: linear-gradient(135deg, #0B192C 0%, #1E3A5F 100%);">
    <div class="container py-2">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="fw-bold text-white mb-1">Find Verified Blood Donors</h2>
                <p class="text-white-50 mb-0 small">Filter registered voluntary donors across cities and blood groups with live availability status.</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="<?= BASE_URL ?>/requester/create_request.php" class="btn btn-lifelink-red">
                    <i class="fa-solid fa-hand-holding-droplet me-1"></i> Post Urgent Blood Request
                </a>
            </div>
        </div>
    </div>
</section>

<div class="container py-5">
    <?php if (isset($_SESSION['flash_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($_SESSION['flash_msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash_msg']); ?>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Filter Sidebar -->
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-4 sticky-top" style="top: 90px;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-filter text-danger me-2"></i>Filters</h5>
                    <a href="<?= BASE_URL ?>/find_donors.php" class="text-muted small">Clear All</a>
                </div>

                <form id="donorSearchForm" method="GET" action="<?= BASE_URL ?>/find_donors.php">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Blood Group</label>
                        <select name="blood_group" class="form-select form-select-sm">
                            <option value="">All Blood Groups</option>
                            <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                                <option value="<?= $bg ?>" <?= $bloodGroup === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Availability</label>
                        <select name="availability" class="form-select form-select-sm">
                            <option value="">Any Status</option>
                            <option value="Available" <?= $availability === 'Available' ? 'selected' : '' ?>>Available Only</option>
                            <option value="Temporarily Unavailable" <?= $availability === 'Temporarily Unavailable' ? 'selected' : '' ?>>Temporarily Unavailable</option>
                            <option value="Not Accepting Requests" <?= $availability === 'Not Accepting Requests' ? 'selected' : '' ?>>Not Accepting</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">City</label>
                        <input type="text" name="city" class="form-control form-control-sm" placeholder="e.g. Mumbai, Bengaluru" value="<?= htmlspecialchars($city) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">State</label>
                        <input type="text" name="state" class="form-control form-control-sm" placeholder="e.g. Maharashtra, Karnataka" value="<?= htmlspecialchars($state) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Area / Locality</label>
                        <input type="text" name="area" class="form-control form-control-sm" placeholder="e.g. Andheri, Koramangala" value="<?= htmlspecialchars($area) ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold small text-secondary">PIN Code</label>
                        <input type="text" name="pin_code" class="form-control form-control-sm" placeholder="e.g. 400050" value="<?= htmlspecialchars($pinCode) ?>">
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-lifelink-red btn-sm py-2">
                            <i class="fa-solid fa-search me-1"></i> Apply Filters
                        </button>
                        <button type="button" id="resetSearchBtn" class="btn btn-outline-secondary btn-sm py-2">
                            Reset Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Donors List -->
        <div class="col-lg-9">
            <!-- Results Header & Sort -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
                <div>
                    <h5 class="fw-bold mb-0">Search Results</h5>
                    <small class="text-muted">Showing <strong><?= $totalResults ?></strong> donor(s) matching your criteria</small>
                </div>
                <div class="d-flex align-items-center gap-2 mt-2 mt-sm-0">
                    <label class="small text-muted text-nowrap">Sort by:</label>
                    <select class="form-select form-select-sm" onchange="location = this.value;">
                        <?php 
                        $currentParams = $_GET;
                        $makeSortUrl = function($s) use ($currentParams) {
                            $cp = $currentParams;
                            $cp['sort'] = $s;
                            return '?' . http_build_query($cp);
                        };
                        ?>
                        <option value="<?= $makeSortUrl('availability') ?>" <?= $sortBy === 'availability' ? 'selected' : '' ?>>Availability First</option>
                        <option value="<?= $makeSortUrl('donations') ?>" <?= $sortBy === 'donations' ? 'selected' : '' ?>>Most Donations</option>
                        <option value="<?= $makeSortUrl('newest') ?>" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest First</option>
                        <option value="<?= $makeSortUrl('city') ?>" <?= $sortBy === 'city' ? 'selected' : '' ?>>City Name</option>
                    </select>
                </div>
            </div>

            <!-- Donor Cards Grid -->
            <?php if (empty($donors)): ?>
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
                    <div class="mb-3">
                        <i class="fa-solid fa-users-slash text-muted fa-4x"></i>
                    </div>
                    <h4 class="fw-bold text-dark">No Matching Donors Found</h4>
                    <p class="text-muted mb-4" style="max-width: 500px; margin: 0 auto;">
                        We couldn't find any donors matching your specific combination of filters. Try widening your search radius or selecting all blood groups.
                    </p>
                    <div>
                        <a href="<?= BASE_URL ?>/find_donors.php" class="btn btn-outline-danger me-2">Clear All Filters</a>
                        <a href="<?= BASE_URL ?>/requester/create_request.php" class="btn btn-lifelink-red">Post a Broadcast Request</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($donors as $d): ?>
                        <?php
                            $isFav = in_array((int)$d['donor_id'], $userFavourites);
                            $badgeClass = 'badge-inactive';
                            if ($d['availability'] === 'Available') $badgeClass = 'badge-available';
                            elseif ($d['availability'] === 'Temporarily Unavailable') $badgeClass = 'badge-unavailable';
                        ?>
                        <div class="col-md-6">
                            <div class="donor-card">
                                <div>
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="blood-badge"><?= htmlspecialchars($d['blood_group']) ?></div>
                                            <div>
                                                <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($d['full_name']) ?></h5>
                                                <small class="text-muted">
                                                    <i class="fa-solid fa-location-dot text-danger me-1"></i>
                                                    <?= htmlspecialchars($d['city']) ?><?= !empty($d['area']) ? ', ' . htmlspecialchars($d['area']) : '' ?>
                                                </small>
                                            </div>
                                        </div>
                                        <?php if ($currentUser): ?>
                                            <form method="POST" action="" class="d-inline m-0 p-0">
                                                <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                                                <input type="hidden" name="action" value="toggle_fav">
                                                <input type="hidden" name="donor_id" value="<?= $d['donor_id'] ?>">
                                                <input type="hidden" name="query_string" value="<?= htmlspecialchars($_SERVER['QUERY_STRING'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <button type="submit" class="btn btn-light btn-sm rounded-circle" title="<?= $isFav ? 'Remove from Saved' : 'Save Donor' ?>">
                                                    <i class="<?= $isFav ? 'fa-solid fa-heart text-danger' : 'fa-regular fa-heart text-muted' ?>"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <span class="<?= $badgeClass ?>">
                                            <i class="fa-solid fa-circle fa-2xs me-1"></i><?= htmlspecialchars($d['availability']) ?>
                                        </span>
                                        <?= getVerificationBadge($d['verification_status']) ?>
                                        <?= getMedicalEligibilityBadge() ?>
                                        <?php if (!empty($d['badges'])): ?>
                                            <?php 
                                            $badges = explode(',', $d['badges']);
                                            foreach (array_slice($badges, 0, 2) as $bgItem): 
                                            ?>
                                                <span class="badge bg-light text-dark border">
                                                    <i class="fa-solid fa-award text-warning me-1"></i><?= htmlspecialchars(trim($bgItem)) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>

                                    <p class="text-muted small mb-3 text-truncate-2" style="min-height: 40px;">
                                        <?= !empty($d['profile_description']) ? htmlspecialchars($d['profile_description']) : 'Voluntary blood donor registered on the LifeLink health network.' ?>
                                    </p>

                                    <div class="bg-light p-2 rounded small text-muted d-flex justify-content-between mb-3">
                                        <span><i class="fa-solid fa-droplet text-danger me-1"></i><strong><?= (int)$d['total_donations'] ?></strong> donation(s)</span>
                                        <span><i class="fa-solid fa-calendar text-primary me-1"></i>Last: <?= !empty($d['last_donation_date']) ? date('M Y', strtotime($d['last_donation_date'])) : 'First time' ?></span>
                                    </div>
                                </div>

                                <div class="pt-3 border-top d-flex gap-2">
                                    <?php if ($currentUser && $currentUser['role'] === 'requester'): ?>
                                        <a href="<?= BASE_URL ?>/requester/create_request.php?blood_group=<?= urlencode($d['blood_group']) ?>&city=<?= urlencode($d['city']) ?>" class="btn btn-lifelink-red btn-sm flex-grow-1">
                                            <i class="fa-solid fa-paper-plane me-1"></i> Send Blood Request
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= BASE_URL ?>/login.php" class="btn btn-lifelink-red btn-sm flex-grow-1">
                                            <i class="fa-solid fa-hand-holding-droplet me-1"></i> Connect / Request
                                        </a>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#donorModal<?= $d['donor_id'] ?>" title="View Details">
                                        <i class="fa-solid fa-circle-info"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#reportModal" 
                                            data-target-type="donor" 
                                            data-target-id="<?= $d['donor_id'] ?>" 
                                            data-target-title="<?= htmlspecialchars($d['full_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($d['blood_group']) ?>)"
                                            title="Report Inappropriate or Suspicious Profile">
                                        <i class="fa-solid fa-flag"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Donor Details Modal -->
                        <div class="modal fade" id="donorModal<?= $d['donor_id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content rounded-4 border-0 shadow">
                                    <div class="modal-header border-0 pb-0">
                                        <h5 class="modal-title fw-bold">Donor Information</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body text-center p-4">
                                        <div class="blood-badge mx-auto mb-3" style="width: 70px; height: 70px; font-size: 1.8rem;"><?= htmlspecialchars($d['blood_group']) ?></div>
                                        <h4 class="fw-bold mb-1"><?= htmlspecialchars($d['full_name']) ?></h4>
                                        <p class="text-muted small mb-3">
                                            <i class="fa-solid fa-location-dot text-danger me-1"></i>
                                            <?= htmlspecialchars($d['city']) ?>, <?= htmlspecialchars($d['state']) ?> (PIN: <?= htmlspecialchars($d['pin_code']) ?>)
                                        </p>

                                        <div class="d-flex flex-wrap justify-content-center gap-2 mb-3">
                                            <span class="<?= $badgeClass ?>"><?= htmlspecialchars($d['availability']) ?></span>
                                            <?= getVerificationBadge($d['verification_status']) ?>
                                            <?= getMedicalEligibilityBadge() ?>
                                        </div>

                                        <div class="alert alert-info border-0 rounded-3 small text-start mb-3">
                                            <div class="d-flex gap-2">
                                                <i class="fa-solid fa-user-doctor text-primary fa-lg mt-1"></i>
                                                <div>
                                                    <strong class="d-block text-dark">Clinical Assessment Notice</strong>
                                                    <span class="text-secondary" style="font-size: 0.8rem;">
                                                        Profile verification confirms submitted account details only. Medical eligibility cannot be cleared online and must be formally assessed on-site by qualified healthcare professionals prior to donation.
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="p-3 bg-light rounded text-start mb-3">
                                            <strong class="d-block text-secondary small mb-1">Donor Bio & Readiness</strong>
                                            <p class="small text-muted mb-0"><?= nl2br(htmlspecialchars($d['profile_description'] ?: 'No additional notes provided.')) ?></p>
                                        </div>

                                        <div class="alert alert-secondary small text-start mb-0">
                                            <i class="fa-solid fa-shield-halved text-primary me-1"></i>
                                            <strong>Privacy Notice:</strong> Exact residential addresses and personal phone numbers are masked to safeguard donor privacy. Requesters can connect directly by submitting a blood request.
                                        </div>
                                    </div>
                                    <div class="modal-footer border-0 pt-0">
                                        <button type="button" class="btn btn-outline-danger btn-sm me-auto" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#reportModal" 
                                                data-target-type="donor" 
                                                data-target-id="<?= $d['donor_id'] ?>" 
                                                data-target-title="<?= htmlspecialchars($d['full_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($d['blood_group']) ?>)">
                                            <i class="fa-solid fa-flag me-1"></i> Report
                                        </button>
                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                        <?php if ($currentUser && $currentUser['role'] === 'requester'): ?>
                                            <a href="<?= BASE_URL ?>/requester/create_request.php?blood_group=<?= urlencode($d['blood_group']) ?>&city=<?= urlencode($d['city']) ?>" class="btn btn-lifelink-red btn-sm">
                                                Request Blood Now
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/report_modal.php'; ?>
<script src="<?= BASE_URL ?>/assets/js/search.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
