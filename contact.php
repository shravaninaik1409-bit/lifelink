<?php
/**
 * LifeLink - Contact Page
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = "Contact Us — LifeLink Blood Network";
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        $errorMsg = "Invalid security token. Please try again.";
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $subject = sanitize($_POST['subject'] ?? '');
        $message = sanitize($_POST['message'] ?? '');

        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            $errorMsg = "Please fill in all required fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = "Please enter a valid email address.";
        } else {
            try {
                if ($db) {
                    $stmt = $db->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $email, $subject, $message]);
                    $successMsg = "Thank you! Your message has been received. Our team will get back to you shortly.";
                } else {
                    $errorMsg = "Database connection error. Please try again later.";
                }
            } catch (Exception $e) {
                $errorMsg = "An error occurred while sending your message: " . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Banner -->
<section class="py-5 text-white" style="background: linear-gradient(135deg, #0B192C 0%, #1E3A5F 100%);">
    <div class="container text-center py-4">
        <h1 class="fw-bold mb-2 text-white">Get in Touch with LifeLink</h1>
        <p class="text-white-50 mb-0" style="max-width: 600px; margin: 0 auto;">
            Have questions regarding donor registration, hospital partnerships, or emergency coordination? We are here to help 24/7.
        </p>
    </div>
</section>

<div class="container py-5">
    <div class="row g-5">
        <!-- Contact Form -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5">
                <h3 class="fw-bold mb-2 text-dark">Send Us a Message</h3>
                <p class="text-muted small mb-4">Fill out the form below and our coordination desk will respond within 24 hours.</p>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($successMsg) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($errorMsg): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($errorMsg) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-secondary">Your Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. John Doe" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-secondary">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" placeholder="e.g. john@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small text-secondary">Subject <span class="text-danger">*</span></label>
                            <input type="text" name="subject" class="form-control" placeholder="e.g. Hospital Integration / Camp Inquiry" required value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small text-secondary">Message <span class="text-danger">*</span></label>
                            <textarea name="message" rows="5" class="form-control" placeholder="Write your message here..." required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-lifelink-red w-100 py-3 shadow-sm">
                                <i class="fa-solid fa-paper-plane me-2"></i> Send Message
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Contact Information & Emergency Helpline -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white mb-4">
                <h4 class="fw-bold text-dark mb-4"><i class="fa-solid fa-address-book text-danger me-2"></i>Coordination Centers</h4>
                
                <div class="d-flex gap-3 mb-4">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger flex-shrink-0">
                        <i class="fa-solid fa-phone-volume"></i>
                    </div>
                    <div>
                        <strong class="d-block text-dark">24x7 Emergency Hotline</strong>
                        <span class="text-danger fw-bold fs-5">1800-LIFELINK</span>
                        <small class="text-muted d-block">(Toll-Free Nationwide Emergency Support)</small>
                    </div>
                </div>

                <div class="d-flex gap-3 mb-4">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary flex-shrink-0">
                        <i class="fa-solid fa-envelope"></i>
                    </div>
                    <div>
                        <strong class="d-block text-dark">Email Support</strong>
                        <span class="text-muted">support@lifelink.org</span><br>
                        <span class="text-muted">emergency@lifelink.org</span>
                    </div>
                </div>

                <div class="d-flex gap-3">
                    <div class="stat-icon bg-success bg-opacity-10 text-success flex-shrink-0">
                        <i class="fa-solid fa-location-dot"></i>
                    </div>
                    <div>
                        <strong class="d-block text-dark">Headquarters</strong>
                        <span class="text-muted small">
                            LifeLink Health Network Building,<br>
                            Medical District, Bandra West,<br>
                            Mumbai, MH 400050
                        </span>
                    </div>
                </div>
            </div>

            <!-- Quick FAQ Box -->
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-light">
                <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-circle-question text-info me-2"></i>Immediate Emergency?</h6>
                <p class="text-muted small mb-3">
                    If you are a patient or healthcare representative requiring urgent blood for critical surgery right now, do not wait for email responses. Submit a high-priority request immediately.
                </p>
                <a href="<?= BASE_URL ?>/register.php?role=requester" class="btn btn-outline-danger btn-sm">
                    <i class="fa-solid fa-bolt me-1"></i> Post Emergency Blood Request
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
