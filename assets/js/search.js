/**
 * LifeLink - Donor Search & Filter Utilities
 */

document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.getElementById('donorSearchForm');
    const resetBtn = document.getElementById('resetSearchBtn');

    if (resetBtn && searchForm) {
        resetBtn.addEventListener('click', function(e) {
            e.preventDefault();
            // Clear inputs
            const inputs = searchForm.querySelectorAll('input, select');
            inputs.forEach(input => {
                if (input.type === 'text') {
                    input.value = '';
                } else if (input.tagName === 'SELECT') {
                    input.selectedIndex = 0;
                }
            });
            // Submit form to show all donors
            searchForm.submit();
        });
    }
});
