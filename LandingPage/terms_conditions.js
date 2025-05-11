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
        } else {
            // Bootstrap 4
            $('#termsModal').modal({
                backdrop: 'static',
                keyboard: false,
                show: true
            });
        }
    }
};

// Document ready function
$(document).ready(function() {
    // Check if terms have been accepted
    if (!TermsConditionsManager.hasAccepted()) {
        // Show the terms and conditions modal
        setTimeout(function() {
            TermsConditionsManager.showModal();
        }, 1000); // Delay to ensure page is fully loaded
    }
    
    // Handle accept button click
    $(document).on('click', '#acceptTermsBtn', function() {
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
    $(document).on('click', '#declineTermsBtn', function() {
        // Show warning message
        $('#termsWarning').removeClass('d-none').addClass('d-block');
    });
});
