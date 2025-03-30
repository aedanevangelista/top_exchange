// Toast notification function
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

// Make the function globally available
window.showToast = showToast;