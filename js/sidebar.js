// JavaScript code for sidebar interactions with debugging
document.addEventListener("DOMContentLoaded", function() {
    // Log that the script is loaded
    console.log("Sidebar script loaded");
    
    // Select all menu items that have submenus
    const submenuToggles = document.querySelectorAll(".submenu > .menu-item");
    console.log("Found submenu toggles:", submenuToggles.length);

    submenuToggles.forEach(toggle => {
        toggle.addEventListener("click", function(e) {
            console.log("Menu item clicked:", this.textContent.trim());
            
            // Prevent default behavior if it's a link
            if (this.tagName === 'A') {
                e.preventDefault();
            }
            
            // Toggle active class on the menu item
            this.classList.toggle("active");
            console.log("Active class toggled:", this.classList.contains("active"));
            
            // Toggle visibility of the submenu
            const submenu = this.nextElementSibling;
            console.log("Found submenu:", submenu);
            
            if (submenu && submenu.classList.contains("submenu-items")) {
                submenu.classList.toggle("visible");
                console.log("Visible class toggled:", submenu.classList.contains("visible"));
                
                // Force style update - sometimes helps with rendering issues
                submenu.style.display = submenu.classList.contains("visible") ? "flex" : "none";
            }
        });
        
        // Add a visible indication that this menu item is clickable
        toggle.style.cursor = "pointer";
        if (!toggle.querySelector(".dropdown-indicator")) {
            const indicator = document.createElement("span");
            indicator.className = "dropdown-indicator";
            indicator.textContent = " ▼";
            indicator.style.fontSize = "10px";
            indicator.style.marginLeft = "5px";
            toggle.appendChild(indicator);
        }
    });
});