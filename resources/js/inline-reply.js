// Inline reply update functionality

function cancelInlineReply(replyId) {
    var bodyEl = document.getElementById('inline-reply-body-' + replyId);
    var formContainer = document.querySelector('.reply-form-' + replyId);
    var bodyDisplay = document.querySelector('.reply-body-' + replyId);

    if (bodyEl && bodyEl.dataset.originalBody) bodyEl.value = bodyEl.dataset.originalBody;
    if (formContainer) formContainer.classList.add('hidden');
    if (bodyDisplay) bodyDisplay.classList.remove('hidden');
}

// Expose globally for inline onclick handlers (ES modules don't leak to window scope)
window.cancelInlineReply = cancelInlineReply;

function initInlineReplyUpdates() {
    document.querySelectorAll('.inline-update-reply').forEach(function(button) {
        button.addEventListener('click', function() {
            var replyId = this.dataset.replyId;
            var formContainer = document.querySelector('.reply-form-' + replyId);
            var bodyDisplay = document.querySelector('.reply-body-' + replyId);

            if (formContainer && bodyDisplay) {
                formContainer.classList.remove('hidden');
                bodyDisplay.classList.add('hidden');

                var textarea = formContainer.querySelector('textarea[data-reply-id="' + replyId + '"]');
                if (textarea) textarea.focus();
            }
        });
    });

    document.querySelectorAll('textarea[data-reply-id]').forEach(function(textarea) {
        var replyId = textarea.getAttribute('data-reply-id');
        if (!replyId) return;

        var form = textarea.closest('form');
        if (!form) return;

        var submitBtn = form.querySelector('button[type="submit"]');

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            var action = form.action;
            var body = form.querySelector('textarea[data-reply-id="' + replyId + '"]');
            var bodyValue = body ? body.value || '' : '';
            var internalEl = form.querySelector('input[name="is_internal"]');
            var isInternal = internalEl && internalEl.checked ? '1' : '0';
            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';

            // Set loading state
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Updating...';
            }

            fetch(action, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ body: bodyValue, is_internal: isInternal }),
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    var bodyDisplay = document.querySelector('.reply-body-' + replyId);
                    var formContainer = document.querySelector('.reply-form-' + replyId);

                    if (bodyDisplay) bodyDisplay.textContent = bodyValue;
                    if (formContainer) formContainer.classList.add('hidden');
                    if (bodyDisplay) bodyDisplay.classList.remove('hidden');

                    var internalBadge = bodyDisplay ? bodyDisplay.previousElementSibling : null;
                    if (internalEl && internalEl.checked) {
                        if (!internalBadge || !internalBadge.classList.contains('bg-yellow-100')) {
                            var badge = document.createElement('span');
                            badge.className = 'mb-1 inline-block rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800';
                            badge.textContent = 'Internal note';
                            bodyDisplay.parentNode.insertBefore(badge, bodyDisplay);
                        }
                    } else {
                        if (internalBadge && internalBadge.classList.contains('bg-yellow-100')) internalBadge.remove();
                    }

                    var message = document.createElement('div');
                    message.className = 'text-xs text-green-600 mt-1';
                    message.textContent = data.message || 'Reply updated.';
                    bodyDisplay.parentNode.appendChild(message);
                    setTimeout(function() { message.remove(); }, 3000);
                } else {
                    var errors = data.errors || {};
                    var firstError = 'An error occurred.';
                    if (errors) {
                        var errorKeys = Object.keys(errors);
                        if (errorKeys.length > 0) {
                            var firstErrorList = errors[errorKeys[0]];
                            if (firstErrorList && firstErrorList.length > 0) {
                                firstError = firstErrorList[0];
                            }
                        }
                    }
                    alert(firstError);
                }
            })
            .catch(function(error) {
                console.error('Error updating reply:', error);
                alert('An error occurred while updating the reply.');
            })
            .finally(function() {
                // Reset loading state
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Update';
                }
            });
        });
    });
}

initInlineReplyUpdates();
