/**
 * Clear Terms Acceptance Script
 * 
 * This script helps with testing the terms and conditions modal by clearing
 * the acceptance status from localStorage.
 * 
 * Usage:
 * 1. Open your browser's developer console (F12 or right-click > Inspect)
 * 2. Copy and paste this entire script into the console
 * 3. Press Enter to execute
 * 4. Refresh the page to see the terms and conditions modal again
 */

// Clear the terms acceptance from localStorage
localStorage.removeItem('terms_conditions_accepted');

// Provide feedback
console.log('Terms and conditions acceptance has been cleared from localStorage.');
console.log('Refresh the page to see the terms and conditions modal again.');

// Alert the user
alert('Terms and conditions acceptance has been cleared. Refresh the page to see the modal again.');
