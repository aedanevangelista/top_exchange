// JavaScript code for sidebar interactions can be added here
// For example, toggling submenus, handling active states, etc.

document.addEventListener("DOMContentLoaded", function() {
    // Example: Toggle submenu visibility
    const submenuToggles = document.querySelectorAll(".submenu > .menu-item");

    submenuToggles.forEach(toggle => {
        toggle.addEventListener("click", function() {
            const submenu = this.nextElementSibling;
            if (submenu && submenu.classList.contains("submenu-items")) {
                submenu.classList.toggle("visible");
            }
        });
    });
});