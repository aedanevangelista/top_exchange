$(document).ready(function() {
    function handleAjaxResponse(response) {
        try {
            if (typeof response !== 'object') {
                response = JSON.parse(response);
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
    $('#edit-id').val(id);
    $('#edit-username').val(username);
    $('#edit-email').val(email);
    $('#edit-phone').val(phone);
    $('#edit-region').val(region);
    $('#edit-city').val(city);
    $('#edit-company_address').val(company_address);
    $('#edit-business_proof-current').val(business_proof);
    $('#editAccountOverlay').show();
}

function closeEditAccountForm() {
    $('#editAccountOverlay').hide();
}