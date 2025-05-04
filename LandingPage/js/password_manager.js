/**
 * Password Manager for Order Confirmation
 * 
 * This script handles the secure storage and retrieval of passwords
 * for the order confirmation process.
 */

// Use a namespace to avoid conflicts
const PasswordManager = {
    // Key for storing the password in localStorage
    storageKey: 'order_confirm_pwd',
    
    // Save password to localStorage (encrypted)
    savePassword: function(password) {
        if (!password) return false;
        
        try {
            // Simple encryption (not truly secure, but better than plaintext)
            // In a production environment, consider using a proper encryption library
            const encryptedPassword = btoa(password.split('').reverse().join(''));
            localStorage.setItem(this.storageKey, encryptedPassword);
            return true;
        } catch (e) {
            console.error('Error saving password:', e);
            return false;
        }
    },
    
    // Get password from localStorage
    getPassword: function() {
        try {
            const encryptedPassword = localStorage.getItem(this.storageKey);
            if (!encryptedPassword) return null;
            
            // Decrypt the password
            return atob(encryptedPassword).split('').reverse().join('');
        } catch (e) {
            console.error('Error retrieving password:', e);
            return null;
        }
    },
    
    // Clear saved password
    clearPassword: function() {
        try {
            localStorage.removeItem(this.storageKey);
            return true;
        } catch (e) {
            console.error('Error clearing password:', e);
            return false;
        }
    },
    
    // Check if a password is saved
    hasPassword: function() {
        return localStorage.getItem(this.storageKey) !== null;
    }
};
