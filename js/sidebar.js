// JavaScript code for sidebar interactions
document.addEventListener("DOMContentLoaded", function() {
    // Select all menu items that have submenus
    const submenuToggles = document.querySelectorAll(".submenu > .menu-item");

    submenuToggles.forEach(toggle => {
        toggle.addEventListener("click", function(e) {
            // Prevent default behavior if it's a link
            if (this.tagName === 'A') {
                e.preventDefault();
            }
            
            // Toggle active class on the menu item
            this.classList.toggle("active");
            
            // Toggle visibility of the submenu
            const submenu = this.nextElementSibling;
            if (submenu && submenu.classList.contains("submenu-items")) {
                submenu.classList.toggle("visible");
            }
        });
    });
    
    // Remove no-hover class to ensure menu items are clickable
    document.querySelectorAll(".menu-item.no-hover").forEach(item => {
        item.classList.remove("no-hover");
    });
});