<?php
/**
 * LifeLink - Universal Footer Component
 */
?>
<footer class="lifelink-footer mt-auto">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="fa-solid fa-droplet text-danger fa-2x"></i>
                    <span class="fs-4 fw-bold text-white">Life<span class="text-danger">Link</span></span>
                </div>
                <p class="text-white-50 mb-4" style="max-width: 340px;">
                    Connecting lives, one donation at a time. A non-profit initiative facilitating fast, reliable, and secure blood donor connections in times of critical emergency.
                </p>
                <div class="d-flex gap-3">
                    <a href="#" class="btn btn-outline-light btn-sm rounded-circle" style="width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center;"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="#" class="btn btn-outline-light btn-sm rounded-circle" style="width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center;"><i class="fa-brands fa-twitter"></i></a>
                    <a href="#" class="btn btn-outline-light btn-sm rounded-circle" style="width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center;"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#" class="btn btn-outline-light btn-sm rounded-circle" style="width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center;"><i class="fa-brands fa-linkedin-in"></i></a>
                </div>
            </div>

            <div class="col-6 col-lg-2">
                <h5>Quick Links</h5>
                <a href="<?= BASE_URL ?>/index.php" class="footer-link"><i class="fa-solid fa-chevron-right fa-xs me-1"></i> Home</a>
                <a href="<?= BASE_URL ?>/find_donors.php" class="footer-link"><i class="fa-solid fa-chevron-right fa-xs me-1"></i> Find Donors</a>
                <a href="<?= BASE_URL ?>/about.php" class="footer-link"><i class="fa-solid fa-chevron-right fa-xs me-1"></i> About Us</a>
                <a href="<?= BASE_URL ?>/contact.php" class="footer-link"><i class="fa-solid fa-chevron-right fa-xs me-1"></i> Contact</a>
                <a href="<?= BASE_URL ?>/install.php" class="footer-link text-warning"><i class="fa-solid fa-server fa-xs me-1"></i> Database Setup</a>
            </div>

            <div class="col-6 col-lg-3">
                <h5>For Users</h5>
                <a href="<?= BASE_URL ?>/register.php?role=donor" class="footer-link"><i class="fa-solid fa-chevron-right fa-xs me-1"></i> Register as Donor</a>
                <a href="<?= BASE_URL ?>/register.php?role=requester" class="footer-link"><i class="fa-solid fa-chevron-right fa-xs me-1"></i> Hospital / Requester</a>
                <a href="<?= BASE_URL ?>/login.php" class="footer-link"><i class="fa-solid fa-chevron-right fa-xs me-1"></i> User Login</a>
                <a href="<?= BASE_URL ?>/find_donors.php?urgency=emergency" class="footer-link text-danger"><i class="fa-solid fa-bolt fa-xs me-1"></i> Emergency Blood Finder</a>
            </div>

            <div class="col-lg-3">
                <h5>Emergency Helpline</h5>
                <p class="text-white-50 mb-2 small">In acute medical emergency, contact our 24/7 hotline:</p>
                <div class="p-3 rounded bg-dark border border-secondary mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <i class="fa-solid fa-phone-volume text-danger fa-2x"></i>
                        <div>
                            <div class="text-white fw-bold fs-5">1800-LIFELINK</div>
                            <div class="text-white-50 small">(1800-543-3546)</div>
                        </div>
                    </div>
                </div>
                <small class="text-white-50 d-block">
                    <i class="fa-solid fa-circle-info text-info me-1"></i> Medical eligibility confirmed by authorized medical staff.
                </small>
            </div>
        </div>

        <div class="footer-bottom text-center">
            <p class="mb-0 text-white-50">
                &copy; <?= date('Y') ?> <strong>LifeLink</strong> – Online Blood Donor Finder and Management System. Designed for academic and community healthcare development.
            </p>
        </div>
    </div>
</footer>

<!-- Bootstrap 5.3 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Main App JS -->
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
