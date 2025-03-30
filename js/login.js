// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Get the login form
    const loginForm = document.querySelector('.loginForm');
    
    if (loginForm) {
        loginForm.addEventListener('submit', function(event) {
            event.preventDefault();
            
            const formData = new FormData(this);
            
            // Use the current page's URL to determine the base path
            const baseUrl = window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
            
            fetch(baseUrl + '/login.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.redirected) {
                    window.location.href = response.url;
                    return;
                }
                return response.text();
            })
            .then(data => {
                if (data) {
                    // If there's an error message, display it
                    const errorDisplay = document.createElement('p');
                    errorDisplay.style.color = 'red';
                    errorDisplay.style.textAlign = 'center';
                    errorDisplay.style.fontWeight = 'bold';
                    errorDisplay.textContent = data;
                    
                    // Remove any existing error messages
                    const existingError = loginForm.querySelector('p[style*="color: red"]');
                    if (existingError) {
                        existingError.remove();
                    }
                    
                    // Add the new error message
                    loginForm.insertBefore(errorDisplay, loginForm.firstChild);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    }
});
