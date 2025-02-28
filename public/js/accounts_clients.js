$(document).ready(function() {
    $('#addAccountForm').submit(function(event) {
        event.preventDefault();
        var formData = new FormData(this);
        formData.append('ajax', true);
        formData.append('formType', 'add');

        $.ajax({
            type: 'POST',
            url: '/top_exchange/public/pages/accounts_clients.php',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    toastr.error(response.message || 'Failed to add account.');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                toastr.error('Error: ' + textStatus + '. ' + errorThrown);
            }
        });
    });

    $('#editAccountForm').submit(function(event) {
        event.preventDefault();
        var formData = new FormData(this);
        formData.append('ajax', true);
        formData.append('formType', 'edit');

        $.ajax({
            type: 'POST',
            url: '/top_exchange/public/pages/accounts_clients.php',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    toastr.error(response.message || 'Failed to update account.');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                toastr.error('Error: ' + textStatus + '. ' + errorThrown);
            }
        });
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
});
