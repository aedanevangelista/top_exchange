document.getElementById("loginForm").addEventListener("submit", function(event) {
    event.preventDefault();

    const formData = new FormData(this);

    fetch('../backend/login.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        document.getElementById("loginMessage").innerText = data;
        if (data === "Login successful") {
            window.location.href = "dashboard.html"; // Redirect after login
        }
    });
});
