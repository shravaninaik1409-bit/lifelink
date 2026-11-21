/**
 * LifeLink - Core Client-side Scripts
 */

document.addEventListener('DOMContentLoaded', function() {
    // Enable Bootstrap Tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Mobile sidebar toggle for dashboard
    var sidebarToggleBtn = document.getElementById('sidebarToggle');
    var sidebar = document.querySelector('.dashboard-sidebar');
    if (sidebarToggleBtn && sidebar) {
        sidebarToggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }

    // Auto-dismiss flash alerts after 5 seconds
    var alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 6000);
    });
});

/**
 * Confirm delete / sensitive action
 */
function confirmAction(message) {
    return confirm(message || 'Are you sure you want to proceed with this action?');
}
