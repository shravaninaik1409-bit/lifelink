<?php
/**
 * LifeLink - Clean Dashboard Footer Component
 * Minimalist, organized dashboard footer without public website marketing clutter.
 */
?>
        </div><!-- /dashboard-body -->
        
        <footer class="dashboard-footer text-center py-3 text-muted border-top bg-white mt-auto">
            <div class="container-fluid d-flex flex-wrap justify-content-between align-items-center px-4">
                <small class="mb-0">
                    &copy; <?= date('Y') ?> <strong>LifeLink</strong> &bull; Online Blood Donor Finder and Management System
                </small>
                <div class="d-flex align-items-center gap-3">
                    <small><i class="fa-solid fa-shield-heart text-danger me-1"></i> Emergency Hotline: <strong>1800-LIFELINK</strong></small>
                    <a href="<?= BASE_URL ?>/index.php" class="small text-muted text-decoration-none">Public Site</a>
                </div>
            </div>
        </footer>
    </div><!-- /dashboard-main -->
</div><!-- /dashboard-layout -->

<!-- Bootstrap 5.3 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Main App JS -->
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
