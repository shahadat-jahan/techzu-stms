// Notification JavaScript functionality

// Toggle notification bell dropdown
function initNotificationDropdown() {
    const bellButton = document.querySelector('[data-notification-bell]');
    const dropdown = document.querySelector('[data-notification-dropdown]');

    if (!bellButton || !dropdown) return;

    // Toggle dropdown on bell click
    bellButton.addEventListener('click', function(event) {
        event.stopPropagation();
        dropdown.classList.toggle('hidden');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        if (!bellButton.contains(event.target) && !dropdown.contains(event.target)) {
            dropdown.classList.add('hidden');
        }
    });
}

// Mark notification as read via AJAX
function markNotificationAsRead(notificationId, formElement) {
    fetch(`/notifications/${notificationId}/mark-as-read`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI - mark notification as read
            const notificationElement = document.querySelector(`[data-notification-id="${notificationId}"]`);
            if (notificationElement) {
                notificationElement.classList.add('bg-gray-50');
                notificationElement.classList.remove('bg-blue-50', 'border-blue-200');

                // Remove mark-as-read button
                const markAsReadButton = notificationElement.querySelector('[data-mark-as-read]');
                if (markAsReadButton) {
                    markAsReadButton.remove();
                }

                // Update view button styling
                const viewButton = notificationElement.querySelector('[data-view-ticket]');
                if (viewButton) {
                    viewButton.classList.remove('text-blue-600');
                    viewButton.classList.add('text-gray-600');
                }
            }

            // Remove form element if passed
            if (formElement) {
                formElement.remove();
            }
        }
    })
    .catch(error => {
        console.error('Error marking notification as read:', error);
    });
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initNotificationDropdown();

    // Add click handlers to mark-as-read forms
    document.querySelectorAll('form[data-mark-as-read-form]').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const notificationId = this.dataset.notificationId;
            markNotificationAsRead(notificationId, this);
        });
    });
});