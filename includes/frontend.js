document.addEventListener('DOMContentLoaded', function() {
    const commentForm = document.getElementById('commentform');
    if (!commentForm) {
        return;
    }

    const emailInput = commentForm.querySelector('input[type="email"]') || commentForm.querySelector('#email');
    if (!emailInput) {
        return;
    }

    const submitBtn = commentForm.querySelector('#submit') || commentForm.querySelector('input[type="submit"]');

    function checkEmailAjax(email, callback) {
        const formData = new FormData();
        formData.append('action', 'sdef_check_email');
        formData.append('email', email);
        formData.append('nonce', sdef_vars.nonce);

        fetch(sdef_vars.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            const blocked = data.success && data.data.blocked;
            if (blocked) {
                showError();
            } else {
                clearError();
            }
            if (callback) {
                callback(blocked);
            }
        })
        .catch(error => {
            console.error('Error validating email:', error);
            if (callback) {
                callback(false);
            }
        });
    }

    function showError() {
        let errorDiv = commentForm.querySelector('.sdef-comment-error');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'sdef-comment-error';
            errorDiv.style.backgroundColor = '#ffe4e6';
            errorDiv.style.color = '#f43f5e';
            errorDiv.style.borderLeft = '4px solid #f43f5e';
            errorDiv.style.padding = '12px 16px';
            errorDiv.style.borderRadius = '8px';
            errorDiv.style.fontWeight = '500';
            errorDiv.style.fontFamily = 'sans-serif';
            errorDiv.style.marginBottom = '20px';
            errorDiv.style.fontSize = '14px';
            errorDiv.textContent = sdef_vars.error_msg;
            commentForm.insertBefore(errorDiv, commentForm.firstChild);
        }
        if (submitBtn) {
            submitBtn.disabled = true;
        }
    }

    function clearError() {
        const error = commentForm.querySelector('.sdef-comment-error');
        if (error) {
            error.remove();
        }
        if (submitBtn) {
            submitBtn.disabled = false;
        }
    }

    emailInput.addEventListener('blur', function() {
        const email = emailInput.value.trim();
        if (email) {
            checkEmailAjax(email);
        }
    });

    emailInput.addEventListener('input', function() {
        clearError();
    });

    commentForm.addEventListener('submit', function(e) {
        if (commentForm.dataset.sdefVerified === 'true') {
            return;
        }

        const email = emailInput.value.trim();
        if (!email) {
            return;
        }

        e.preventDefault();

        // Show checking/loading state
        let originalBtnText = '';
        if (submitBtn) {
            submitBtn.disabled = true;
            originalBtnText = submitBtn.value || submitBtn.textContent;
            if (submitBtn.tagName === 'INPUT') {
                submitBtn.value = 'Checking...';
            } else {
                submitBtn.textContent = 'Checking...';
            }
        }

        checkEmailAjax(email, function(blocked) {
            if (submitBtn) {
                submitBtn.disabled = blocked;
                if (submitBtn.tagName === 'INPUT') {
                    submitBtn.value = originalBtnText;
                } else {
                    submitBtn.textContent = originalBtnText;
                }
            }

            if (!blocked) {
                commentForm.dataset.sdefVerified = 'true';
                commentForm.submit();
            }
        });
    });
});
