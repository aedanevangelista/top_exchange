$(document).ready(function() {
    function handleAjaxResponse(response) {
        try {
            if (typeof response === 'string') {
                response = JSON.parse(response);
            }
            if (typeof response !== 'object' || response === null) {
                throw new Error('Invalid JSON format');
            }
        } catch (e) {
            console.error('Invalid JSON response:', response);
            toastr.error('Unexpected server response. Check console for details.');
            return;
        }
    
        if (response.success) {
            location.reload();
        } else {
            toastr.error(response.message || 'Failed to process request.');
        }
    }
    
    function handleAjaxError(jqXHR, textStatus, errorThrown) {
        console.error('AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
        toastr.error('AJAX Error: ' + textStatus);

        // If the response is not JSON, log it
        try {
            let response = JSON.parse(jqXHR.responseText);
            toastr.error(response.message || 'Server error.');
        } catch (e) {
            console.error('Non-JSON response:', jqXHR.responseText);
        }
    }

    function submitForm(form, formType) {
        var formData = new FormData(form);
        formData.append('ajax', true);
        formData.append('formType', formType);

        // Store username for toast notification
        let username = '';
        if (formType === 'add') {
            username = $('#username').val();
        } else if (formType === 'edit') {
            username = $('#edit-username').val();
        }

        $.ajax({
            type: 'POST',
            url: '/top_exchange/public/pages/accounts.php',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success toast notification for adding or editing an account
                    if (formType === 'add') {
                        showToast(`${username} has been added in the Accounts (Admin).`, 'success');
                    } else if (formType === 'edit') {
                        showToast(`${username} has been edited in the Accounts (Admin).`, 'success');
                    }
                    
                    // Wait a moment for the toast to be visible before reloading
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    toastr.error(response.message || 'Failed to process request.');
                }
            },
            error: handleAjaxError,
            complete: function(jqXHR) {
                console.log("Raw server response:", jqXHR.responseText);
            }
        });
    }

    $('#addAccountForm').submit(function(event) {
        event.preventDefault();
        submitForm(this, 'add');
    });

    $('#editAccountForm').submit(function(event) {
        event.preventDefault();
        submitForm(this, 'edit');
    });

    // Make filterByStatus function available globally
    window.filterByStatus = function() {
        const status = document.getElementById('statusFilter').value;
        window.location.href = `?status=${status}`;
    };
});

function openAddAccountForm() {
    $('#addAccountOverlay').show();
}

function closeAddAccountForm() {
    $('#addAccountOverlay').hide();
}

function openEditAccountForm(id, username, password, role) {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-username').value = username;
    document.getElementById('edit-password').value = password || ''; // In case password is null or undefined
    document.getElementById('edit-role').value = role;
    document.getElementById('editAccountOverlay').style.display = 'flex';
}

function closeEditAccountForm() {
    $('#editAccountOverlay').hide();
}

function openStatusModal(id, username) {
    $('#statusMessage').text(`Change status for ${username}`);
    $('#statusModal').data('id', id).show();
    $('#statusModal').data('username', username).show();
}

function closeStatusModal() {
    $('#statusModal').hide();
}

function changeStatus(status) {
    var id = $('#statusModal').data('id');
    var username = $('#statusModal').data('username');
    
    $.ajax({
        type: 'POST',
        url: '/top_exchange/public/pages/accounts.php',
        data: { id: id, status: status, formType: 'status', ajax: true },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Convert status to lowercase for consistency in toast types
                let toastType = status.toLowerCase();
                
                // Standardize status names for CSS classes
                if (toastType === 'archived') {
                    toastType = 'remove';
                } else if (toastType === 'reject') {
                    toastType = 'reject';
                } else if (toastType === 'active') {
                    toastType = 'active';
                }
                
                // Show toast notification for status change
                showToast(`Changed status for ${username} to ${status}.`, toastType);
                
                // Wait a moment for the toast to be visible before reloading
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                toastr.error('Failed to change status.');
            }
        },
        error: function() {
            toastr.error('Failed to change status.');
        }
    });
}

// Make sure the showToast function is available globally
if (typeof showToast !== 'function') {
    // Fallback implementation if the function doesn't exist
    function showToast(message, type = 'success') {
        const toastContainer = document.getElementById('toast-container') || 
            (() => { 
                const container = document.createElement('div'); 
                container.id = 'toast-container';
                // Explicitly set positioning in lower right
                container.style.position = 'fixed';
                container.style.bottom = '30px';
                container.style.right = '30px';
                container.style.zIndex = '9999';
                container.style.display = 'flex';
                container.style.flexDirection = 'column';
                container.style.alignItems = 'flex-end';
                document.body.appendChild(container);
                return container;
            })();
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        const icon = document.createElement('i');
        
        // Set appropriate icon based on type
        if (type === 'success') {
            icon.className = 'fas fa-check-circle';
        } else if (type === 'error' || type === 'remove') {
            icon.className = 'fas fa-times-circle';
        } else if (type === 'info') {
            icon.className = 'fas fa-info-circle';
        } else if (type === 'active') {
            icon.className = 'fas fa-check';
        } else if (type === 'pending') {
            icon.className = 'fas fa-clock';
        } else if (type === 'reject') {
            icon.className = 'fas fa-ban';
        } else if (type === 'complete') {
            icon.className = 'fas fa-check-circle';
        }
        
        const text = document.createElement('span');
        text.textContent = message;
        
        toast.appendChild(icon);
        toast.appendChild(text);
        toastContainer.appendChild(toast);
        
        // Remove toast after 3 seconds
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => {
                toastContainer.removeChild(toast);
            }, 300);
        }, 3000);
        
        return toast;
    }
    
    window.showToast = showToast;
}