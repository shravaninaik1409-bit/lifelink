<?php
/**
 * LifeLink - Reusable User Incident Reporting Modal Component
 * Can be triggered from Donor cards, Blood Request cards, and detail pages.
 */
$isUserLoggedIn = isLoggedIn();
$reportCsrfToken = $isUserLoggedIn ? getCSRFToken() : '';
$currentRequestUri = htmlspecialchars($_SERVER['REQUEST_URI'] ?? BASE_URL, ENT_QUOTES, 'UTF-8');
?>

<!-- Universal Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-danger bg-opacity-10 text-danger" style="width: 38px; height: 38px;">
                        <i class="fa-solid fa-flag"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="reportModalLabel">Submit Incident Report</h5>
                        <small class="text-muted" style="font-size: 0.78rem;">Help keep the LifeLink community safe and trustworthy.</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4 pt-3">
                <?php if (!$isUserLoggedIn): ?>
                    <div class="text-center py-3">
                        <div class="mb-3 text-warning">
                            <i class="fa-solid fa-shield-halved fa-3x"></i>
                        </div>
                        <h6 class="fw-bold text-dark">Authentication Required</h6>
                        <p class="small text-muted mb-4" style="max-width: 360px; margin: 0 auto;">
                            To prevent malicious reports and maintain community accountability, you must be signed in to submit an incident report.
                        </p>
                        <div class="d-flex justify-content-center gap-2">
                            <a href="<?= BASE_URL ?>/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI'] ?? '') ?>" class="btn btn-lifelink-red btn-sm px-4">
                                <i class="fa-solid fa-right-to-bracket me-1"></i> Log In
                            </a>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="POST" action="<?= BASE_URL ?>/submit_report.php" id="lifelinkReportForm">
                        <input type="hidden" name="csrf_token" value="<?= $reportCsrfToken ?>">
                        <input type="hidden" name="target_type" id="reportTargetType" value="donor">
                        <input type="hidden" name="target_id" id="reportTargetId" value="0">
                        <input type="hidden" name="return_url" id="reportReturnUrl" value="<?= $currentRequestUri ?>">

                        <!-- Target Summary Card -->
                        <div class="p-2 px-3 rounded-3 bg-light border mb-3 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-circle-info text-primary flex-shrink-0"></i>
                            <div class="small text-truncate">
                                <span class="text-muted" id="reportTargetTypeLabel">Reporting Donor:</span>
                                <strong class="text-dark d-block text-truncate" id="reportTargetTitle">Selected Profile</strong>
                            </div>
                        </div>

                        <!-- Report Category / Reason -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-dark mb-1">
                                Reason for Report <span class="text-danger">*</span>
                            </label>
                            <select name="report_reason" id="reportReasonSelect" class="form-select form-select-sm" required>
                                <option value="">Select a reason...</option>
                            </select>
                        </div>

                        <!-- Description Field -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-dark mb-1">
                                Additional Details / Explanation <span class="text-danger">*</span>
                            </label>
                            <textarea name="description" id="reportDescription" rows="4" class="form-control form-control-sm" 
                                      placeholder="Please describe why this profile or request is suspicious, inaccurate, or inappropriate..." 
                                      minlength="5" maxlength="1000" required></textarea>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted" style="font-size: 0.72rem;">Min 5 characters</small>
                                <small class="text-muted" style="font-size: 0.72rem;"><span id="reportCharCount">0</span>/1000</small>
                            </div>
                        </div>

                        <!-- Notice -->
                        <div class="alert alert-light border border-secondary-subtle py-2 px-3 rounded-3 small text-muted mb-3 d-flex gap-2 align-items-center" style="font-size: 0.76rem;">
                            <i class="fa-solid fa-triangle-exclamation text-warning flex-shrink-0"></i>
                            <div>False or abusive reports against genuine donors or emergency patients violate platform terms.</div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger btn-sm px-3 fw-semibold" id="btnSubmitReport">
                                <i class="fa-solid fa-paper-plane me-1"></i> Submit Report
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const reportModal = document.getElementById('reportModal');
    if (!reportModal) return;

    const donorReasons = [
        'Fake or Incorrect Information',
        'Unreachable / Invalid Phone Number',
        'Demanded Money / Commercial Solicitation',
        'Inappropriate or Abusive Behavior',
        'Not Available / Refused Donation',
        'Other Inappropriate Activity'
    ];

    const requestReasons = [
        'Fake or Fraudulent Patient Requirement',
        'Duplicate Blood Request',
        'Commercial Resale / Blood Black Market',
        'Incorrect Hospital or Location Details',
        'Already Fulfilled / Outdated Request',
        'Other Suspicious Activity'
    ];

    reportModal.addEventListener('show.bs.modal', function (event) {
        const triggerBtn = event.relatedTarget;
        if (!triggerBtn) return;

        const targetType = triggerBtn.getAttribute('data-target-type') || 'donor';
        const targetId = triggerBtn.getAttribute('data-target-id') || '0';
        const targetTitle = triggerBtn.getAttribute('data-target-title') || 'Target Record';

        const typeInput = document.getElementById('reportTargetType');
        const idInput = document.getElementById('reportTargetId');
        const typeLabel = document.getElementById('reportTargetTypeLabel');
        const titleLabel = document.getElementById('reportTargetTitle');
        const reasonSelect = document.getElementById('reportReasonSelect');

        if (typeInput) typeInput.value = targetType;
        if (idInput) idInput.value = targetId;
        if (titleLabel) titleLabel.textContent = targetTitle;
        if (typeLabel) {
            typeLabel.textContent = (targetType === 'donor') ? 'Reporting Donor:' : 'Reporting Blood Request:';
        }

        if (reasonSelect) {
            reasonSelect.innerHTML = '<option value="">Select a reason...</option>';
            const reasons = (targetType === 'donor') ? donorReasons : requestReasons;
            reasons.forEach(function (reason) {
                const opt = document.createElement('option');
                opt.value = reason;
                opt.textContent = reason;
                reasonSelect.appendChild(opt);
            });
        }

        const desc = document.getElementById('reportDescription');
        if (desc) desc.value = '';
        const charCount = document.getElementById('reportCharCount');
        if (charCount) charCount.textContent = '0';
    });

    const descField = document.getElementById('reportDescription');
    const charCounter = document.getElementById('reportCharCount');
    if (descField && charCounter) {
        descField.addEventListener('input', function () {
            charCounter.textContent = this.value.length;
        });
    }
});
</script>
