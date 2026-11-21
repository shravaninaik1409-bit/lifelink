<?php
/**
 * LifeLink - About Us Page
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = "About LifeLink — Online Blood Donor Finder and Management System";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Page Banner -->
<section class="py-5 text-white" style="background: linear-gradient(135deg, #0B192C 0%, #1E3A5F 100%);">
    <div class="container text-center py-4">
        <h1 class="fw-bold mb-2 text-white">About LifeLink</h1>
        <p class="text-white-50 mb-0" style="max-width: 600px; margin: 0 auto;">
            Our mission is to eliminate blood shortages by seamlessly connecting voluntary donors with those in urgent medical need.
        </p>
    </div>
</section>

<!-- Mission & Vision -->
<section class="section-padding">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <span class="text-danger fw-bold text-uppercase small">Who We Are</span>
                <h2 class="section-title">Bridging the Gap Between Compassion & Critical Care</h2>
                <p class="text-muted mb-3">
                    Every two seconds, someone in the country needs a blood transfusion. In critical trauma surgeries, maternal hemorrhages, and chronic illnesses like thalassemia, quick access to safe, compatible blood can mean the difference between life and death.
                </p>
                <p class="text-muted mb-4">
                    <strong>LifeLink</strong> is a comprehensive digital platform developed to eliminate intermediaries and delays. By matching patient blood group requests with verified nearby donors in real-time, we ensure emergency calls are answered swiftly and transparently.
                </p>

                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="p-3 border rounded bg-white shadow-sm">
                            <h5 class="fw-bold text-danger mb-1"><i class="fa-solid fa-bullseye me-2"></i>Our Mission</h5>
                            <p class="text-muted small mb-0">To establish a frictionless, ethical, and fast blood donation ecosystem that empowers citizens to save lives.</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 border rounded bg-white shadow-sm">
                            <h5 class="fw-bold text-primary mb-1"><i class="fa-solid fa-eye me-2"></i>Our Vision</h5>
                            <p class="text-muted small mb-0">A future where no patient loses their life due to the unavailability of compatible, voluntary blood donations.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="p-4 p-md-5 bg-white rounded-4 border shadow-sm position-relative">
                    <h4 class="fw-bold text-dark mb-4"><i class="fa-solid fa-shield-heart text-danger me-2"></i>Core Pillars of LifeLink</h4>

                    <div class="d-flex gap-3 mb-4">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger flex-shrink-0">
                            <i class="fa-solid fa-lock"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Donor Privacy First</h6>
                            <p class="text-muted small mb-0">Phone numbers and private addresses are shielded from public scrapers. Connection happens through structured in-app workflows.</p>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-4">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary flex-shrink-0">
                            <i class="fa-solid fa-bolt"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Emergency Prioritization</h6>
                            <p class="text-muted small mb-0">Accident trauma and ICU requirements are marked with emergency badges and prioritized across notifications.</p>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-4">
                        <div class="stat-icon bg-success bg-opacity-10 text-success flex-shrink-0">
                            <i class="fa-solid fa-certificate"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Verified Donation Records</h6>
                            <p class="text-muted small mb-0">Donations are authenticated by participating healthcare centers, awarding donors with recognized community badges.</p>
                        </div>
                    </div>

                    <div class="d-flex gap-3">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning flex-shrink-0">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">End-to-End Tracking</h6>
                            <p class="text-muted small mb-0">From request submission to donor acceptance and final completion, every step is logged on a live timeline.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Medical Disclaimer Notice -->
<section class="section-padding bg-light">
    <div class="container">
        <div class="p-4 p-md-5 rounded-4 bg-white border border-danger-subtle shadow-sm">
            <div class="row align-items-center g-4">
                <div class="col-md-2 text-center">
                    <i class="fa-solid fa-notes-medical text-danger fa-4x"></i>
                </div>
                <div class="col-md-10">
                    <h4 class="fw-bold text-dark mb-2">Important Medical & Legal Disclaimer</h4>
                    <p class="text-muted mb-2 small">
                        LifeLink operates solely as a technological communication platform connecting voluntary blood donors and requesters. The platform does not independently test blood, evaluate medical suitability, or administer transfusions.
                    </p>
                    <p class="text-muted mb-0 small">
                        All blood donations, mandatory pre-screening tests, viral markers, and cross-matching procedures must be conducted strictly by authorized medical practitioners and licensed blood transfusion centers in compliance with national health regulations.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
