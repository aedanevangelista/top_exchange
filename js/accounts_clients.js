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
            url: '/public/pages/accounts_clients.php',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success toast notification for adding or editing an account
                    if (formType === 'add') {
                        showToast(`${username} has been sucessfully added in the Accounts (Clients).`, 'success');
                    } else if (formType === 'edit') {
                        showToast(`${username} has been successfully edited.`, 'success');
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

    function changeStatus(status) {
        var id = $('#statusModal').data('id');
        $.ajax({
            type: 'POST',
            url: '/public/pages/accounts_clients.php',
            data: { id: id, status: status, formType: 'status', ajax: true },
            dataType: 'json',
            success: handleAjaxResponse,
            error: handleAjaxError,
            complete: function(jqXHR) {
                console.log("Raw server response:", jqXHR.responseText);
            }
        });
    }

    function filterByStatus() {
        const status = document.getElementById('statusFilter').value;
        window.location.href = `?status=${status}`;
    }

    window.filterByStatus = filterByStatus; // Ensure the function is globally accessible
});

function openStatusModal(id, username, email) {
    $('#statusMessage').text('Change status for ' + username + ' (' + email + ')');
    $('#statusModal').data('id', id).show();
    $('#statusModal').data('username', username).show();
    $('#statusModal').data('email', email).show();
}

function closeStatusModal() {
    $('#statusModal').hide();
}

function changeStatus(status) {
    var id = $('#statusModal').data('id');
    var username = $('#statusModal').data('username');
    var email = $('#statusModal').data('email');
    
    $.ajax({
        type: 'POST',
        url: '/public/pages/accounts_clients.php',
        data: { id: id, status: status, formType: 'status', ajax: true },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Convert status to lowercase for consistency in toast types
                // and handle variations like "Completed"/"Complete" and "Rejected"/"Reject"
                let toastType = status.toLowerCase();
                
                // Standardize status names for CSS classes
                if (toastType === 'completed') {
                    toastType = 'complete';
                } else if (toastType === 'rejected') {
                    toastType = 'reject';
                }
                
                // Show toast notification for status change
                showToast(`Changed status for ${username} (${email}) to ${status}.`, toastType);
                
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

function openAddAccountForm() {
    $('#addAccountOverlay').show();
}

function closeAddAccountForm() {
    $('#addAccountOverlay').hide();
}

function openEditAccountForm(id, username, email, phone, region, city, company, company_address, business_proof) {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-username').value = username;
    document.getElementById('edit-password').value = ''; // Password should not be pre-filled for security reasons
    document.getElementById('edit-email').value = email;
    document.getElementById('edit-phone').value = phone;
    document.getElementById('edit-region').value = region;
    document.getElementById('edit-city').value = city;
    document.getElementById('edit-company').value = company; // Add company field
    document.getElementById('edit-company_address').value = company_address;

    // Handle business proof (images)
    const businessProofContainer = document.getElementById('edit-business-proof-container');
    businessProofContainer.innerHTML = ''; // Clear existing images
    const proofs = JSON.parse(business_proof);
    proofs.forEach(proof => {
        const img = document.createElement('img');
        img.src = proof;
        img.alt = 'Business Proof';
        img.width = 50;
        businessProofContainer.appendChild(img);
    });

    // Set existing business proof in hidden input
    document.getElementById('existing-business-proof').value = JSON.stringify(proofs);

    document.getElementById('editAccountOverlay').style.display = 'flex';
}

function closeEditAccountForm() {
    $('#editAccountOverlay').hide();
}

// Make sure the showToast function is available globally
// This assumes the function is already defined in another file that's included
if (typeof showToast !== 'function') {
    // Fallback implementation if the function doesn't exist
    function showToast(message, type = 'success') {
        const toastContainer = document.getElementById('toast-container') || 
            (() => { 
                const container = document.createElement('div'); 
                container.id = 'toast-container'; 
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