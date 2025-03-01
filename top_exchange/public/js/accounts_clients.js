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

        $.ajax({
            type: 'POST',
            url: '/top_exchange/public/pages/accounts_clients.php',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: handleAjaxResponse,
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
            url: '/top_exchange/public/pages/accounts_clients.php',
            data: { id: id, status: status, formType: 'status', ajax: true },
            dataType: 'json',
            success: handleAjaxResponse,
            error: handleAjaxError,
            complete: function(jqXHR) {
                console.log("Raw server response:", jqXHR.responseText);
            }
        });
    }
});



function openStatusModal(id, username, email) {
    $('#statusMessage').text('Change status for ' + username + ' (' + email + ')');
    $('#statusModal').data('id', id).show();
}

function closeStatusModal() {
    $('#statusModal').hide();
}

function changeStatus(status) {
    var id = $('#statusModal').data('id');
    $.ajax({
        type: 'POST',
        url: '/top_exchange/public/pages/accounts_clients.php',
        data: { id: id, status: status, formType: 'status', ajax: true },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                location.reload();
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

function openEditAccountForm(id, username, email, phone, region, city, company_address, business_proof) {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-username').value = username;
    document.getElementById('edit-password').value = ''; // Password should not be pre-filled for security reasons
    document.getElementById('edit-email').value = email;
    document.getElementById('edit-phone').value = phone;
    document.getElementById('edit-region').value = region;
    document.getElementById('edit-city').value = city;
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

    document.getElementById('editAccountOverlay').style.display = 'flex';
}

function closeEditAccountForm() {
    $('#editAccountOverlay').hide();
}