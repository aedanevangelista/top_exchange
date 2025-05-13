/**
 * Terms and Conditions Manager
 *
 * This script handles the display and acceptance of the terms and conditions popup.
 */

// Use a namespace to avoid conflicts
const TermsConditionsManager = {
    // Key for storing acceptance in localStorage
    storageKey: 'terms_conditions_accepted',

    // Check if terms have been accepted
    hasAccepted: function() {
        try {
            return localStorage.getItem(this.storageKey) === 'true';
        } catch (e) {
            console.error('Error checking terms acceptance:', e);
            return false;
        }
    },

    // Save acceptance to localStorage
    saveAcceptance: function() {
        try {
            localStorage.setItem(this.storageKey, 'true');
            return true;
        } catch (e) {
            console.error('Error saving terms acceptance:', e);
            return false;
        }
    },

    // Clear acceptance from localStorage
    clearAcceptance: function() {
        try {
            localStorage.removeItem(this.storageKey);
            return true;
        } catch (e) {
            console.error('Error clearing terms acceptance:', e);
            return false;
        }
    },

    // Show the terms and conditions modal
    showModal: function() {
        // Check if using Bootstrap 5 or 4
        if (typeof bootstrap !== 'undefined') {
            // Bootstrap 5
            const termsModal = new bootstrap.Modal(document.getElementById('termsModal'));
            termsModal.show();

            // Fix z-index issues with modal and backdrop
            setTimeout(function() {
                $('.modal-backdrop').css('z-index', '1040');
                $('#termsModal').css('z-index', '1050');
            }, 100);
        } else {
            // Bootstrap 4
            try {
                $('#termsModal').modal('dispose');
            } catch (e) {
                console.log('Modal not initialized yet, continuing...');
            }

            $('#termsModal').modal({
                backdrop: 'static',
                keyboard: false,
                show: true
            });

            // Fix z-index issues with modal and backdrop
            setTimeout(function() {
                $('.modal-backdrop').css('z-index', '1040');
                $('#termsModal').css('z-index', '1050');
            }, 100);
        }
    }
};

// Document ready function
$(document).ready(function() {
    console.log('Terms and conditions script loaded');

    // Check if terms have been accepted
    if (!TermsConditionsManager.hasAccepted()) {
        // Show the terms and conditions modal
        setTimeout(function() {
            console.log('Showing terms modal');
            TermsConditionsManager.showModal();
        }, 1000); // Delay to ensure page is fully loaded
    }

    // Handle checkbox change
    $('#agreeTermsCheckbox').on('change', function() {
        // Enable or disable the accept button based on checkbox state
        $('#acceptTermsBtn').prop('disabled', !this.checked);

        // If checkbox is checked, hide any previous warning
        if (this.checked) {
            $('#termsWarning').removeClass('d-block').addClass('d-none');
        }
    });

    // Handle accept button click
    $('#acceptTermsBtn').on('click', function() {
        console.log('Accept button clicked');

        // Check if the checkbox is checked
        if (!$('#agreeTermsCheckbox').is(':checked')) {
            // Show warning message if checkbox is not checked
            $('#termsWarning').removeClass('d-none').addClass('d-block');
            return;
        }

        // Save acceptance
        TermsConditionsManager.saveAcceptance();

        // Close the modal
        if (typeof bootstrap !== 'undefined') {
            // Bootstrap 5
            const termsModal = bootstrap.Modal.getInstance(document.getElementById('termsModal'));
            if (termsModal) {
                termsModal.hide();
            }
        } else {
            // Bootstrap 4
            $('#termsModal').modal('hide');
        }

        // Show confirmation message
        showPopup('Terms and Conditions accepted. Thank you!');
    });

    // Handle decline button click
    $('#declineTermsBtn').on('click', function() {
        console.log('Decline button clicked');
        // Show warning message
        $('#termsWarning').removeClass('d-none').addClass('d-block');
    });

    // Prevent modal from closing when clicking outside or pressing escape
    $('#termsModal').on('hide.bs.modal', function(e) {
        // If terms haven't been accepted yet, prevent the modal from closing
        if (!TermsConditionsManager.hasAccepted()) {
            e.preventDefault();
            $('#termsWarning').removeClass('d-none').addClass('d-block');
        }
    });

    // Additional fix for modal backdrop issue
    $(document).on('shown.bs.modal', '#termsModal', function() {
        // Ensure the modal is above the backdrop and clickable
        $('.modal-backdrop').css({
            'z-index': '1040',
            'pointer-events': 'none'  // This allows clicks to pass through to the modal
        });

        $('#termsModal').css({
            'z-index': '1050',
            'display': 'block'
        });

        $('#termsModal .modal-dialog').css({
            'z-index': '1051',
            'pointer-events': 'all'
        });

        $('#termsModal .modal-content').css({
            'position': 'relative',
            'z-index': '1052',
            'pointer-events': 'auto'
        });

        // Force redraw to ensure proper rendering
        $('#termsModal').hide().show(0);
    });

    // Handle close button click (only allow if terms have been accepted)
    $('.modal-header .close').on('click', function() {
        console.log('Close button clicked');

        // Only allow closing if terms have been accepted
        if (TermsConditionsManager.hasAccepted()) {
            if (typeof bootstrap !== 'undefined') {
                // Bootstrap 5
                const termsModal = bootstrap.Modal.getInstance(document.getElementById('termsModal'));
                if (termsModal) {
                    termsModal.hide();
                }
            } else {
                // Bootstrap 4
                $('#termsModal').modal('hide');
            }
        } else {
            // Show warning message
            $('#termsWarning').removeClass('d-none').addClass('d-block');
        }
    });
});
