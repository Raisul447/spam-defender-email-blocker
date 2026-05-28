document.addEventListener('DOMContentLoaded', function() {
    // 1. Unblock Confirmation
    const unblockForms = document.querySelectorAll('.sdef-unblock-form');
    unblockForms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const email = this.querySelector('input[name="email"]').value;
            if (!confirm(`Are you sure you want to unblock "${email}"?`)) {
                e.preventDefault();
            }
        });
    });

    // 2. Auto-Dismiss Toast Alerts
    const alerts = document.querySelectorAll('.sdef-alert');
    alerts.forEach(function(alert) {
        // Handle manual close click
        const closeBtn = alert.querySelector('.sdef-alert-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                dismissAlert(alert);
            });
        }

        // Auto dismiss after 4 seconds
        setTimeout(function() {
            dismissAlert(alert);
        }, 4000);
    });

    function dismissAlert(alert) {
        if (!alert.classList.contains('sdef-alert-fadeout')) {
            alert.classList.add('sdef-alert-fadeout');
            alert.addEventListener('transitionend', function() {
                alert.remove();
            });
        }
    }
});
