// Test JavaScript file
console.log('External JavaScript file loaded successfully!');

// Add a timestamp to the external test section
document.addEventListener('DOMContentLoaded', function() {
    const externalTest = document.getElementById('external-test');
    if (externalTest) {
        const timestamp = document.createElement('p');
        timestamp.className = 'success';
        timestamp.textContent = '✓ External JavaScript executed at: ' + new Date().toLocaleTimeString();
        externalTest.appendChild(timestamp);
    }
}); 