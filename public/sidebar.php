<?php
// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include_once "../../backend/db_connection.php";
include_once "../../backend/check_role.php";

// Determine which session context we're in and get appropriate user info
if (isset($_SESSION['admin_user_id'])) {
    $username = $_SESSION['admin_username'] ?? 'Guest';
    $role = $_SESSION['admin_role'] ?? 'guest';
} else if (isset($_SESSION['client_user_id'])) {
    $username = $_SESSION['client_username'] ?? 'Guest';
    $role = $_SESSION['client_role'] ?? 'guest';
} else {
    // Fallback to traditional session variables
    $username = $_SESSION['username'] ?? 'Guest';
    $role = $_SESSION['role'] ?? 'guest';
}

// Fetch pages for the user role
$stmt = $conn->prepare("SELECT pages FROM roles WHERE role_name = ? AND status = 'active'");
$stmt->bind_param("s", $role);
$stmt->execute();
$stmt->bind_result($pages);
$stmt->fetch();
$stmt->close();

// Convert pages to an array and trim whitespace
$allowedPages = array_map('trim', explode(',', $pages));
?>

<div class="sidebar">
    <div>
        <!-- MAIN MENU Section -->
        <div class="menu-section">
            <span class="menu-title"><b>MAIN MENU</b></span>
            <hr>
            <?php if (in_array('Dashboard', $allowedPages)): ?>
                <a href="/public/pages/dashboard.php" class="menu-item">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            <?php endif; ?>
            
            <!-- Production Menu with Submenu for Delivery Calendar (formerly Forecast) -->
            <?php if (in_array('Forecast', $allowedPages)): ?>
                <div class="submenu">
                    <span class="menu-item" style="cursor: pointer;">
                        <i class="fas fa-industry"></i> Production <span style="float: right;">▼</span>
                    </span>
                    <div class="submenu-items" style="display: none;">
                        <a href="/public/pages/forecast.php" class="submenu-item">
                            <i class="fas fa-arrow-right"></i> Delivery Calendar
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Ordering Menu with Submenus for Orders, Pending Orders, and Order History -->
            <?php if (in_array('Orders', $allowedPages) || in_array('Order History', $allowedPages) || in_array('Pending Orders', $allowedPages)): ?>
                <div class="submenu">
                    <span class="menu-item" style="cursor: pointer;">
                        <i class="fas fa-shopping-cart"></i> Ordering <span style="float: right;">▼</span>
                    </span>
                    <div class="submenu-items" style="display: none;">
                        <?php if (in_array('Orders', $allowedPages)): ?>
                            <a href="/public/pages/orders.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Orders
                            </a>
                        <?php endif; ?>
                        <?php if (in_array('Pending Orders', $allowedPages)): ?>
                            <a href="/public/pages/pending_orders.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Pending Orders
                            </a>
                        <?php endif; ?>
                        <?php if (in_array('Order History', $allowedPages)): ?>
                            <a href="/public/pages/order_history.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Order History
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Payments Menu with Submenu for Payment History -->
            <?php if (in_array('Payment History', $allowedPages)): ?>
                <div class="submenu">
                    <span class="menu-item" style="cursor: pointer;">
                        <i class="fas fa-money-bill-wave"></i> Payments <span style="float: right;">▼</span>
                    </span>
                    <div class="submenu-items" style="display: none;">
                        <a href="/public/pages/payment_history.php" class="submenu-item">
                            <i class="fas fa-arrow-right"></i> Payment History
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (in_array('Sales Data', $allowedPages)): ?>
                <a href="/public/pages/sales.php" class="menu-item">
                    <i class="fas fa-chart-bar"></i> Sales Data
                </a>
            <?php endif; ?>
        </div>

        <!-- DATA Section -->
        <div class="menu-section">
            <span class="menu-title"><b>DATA</b></span>
            <hr>
            
            <!-- Accounts Menu with Submenus -->
            <?php if (in_array('Accounts - Admin', $allowedPages) || in_array('Accounts - Clients', $allowedPages) || in_array('User Roles', $allowedPages)): ?>
                <div class="submenu">
                    <span class="menu-item" style="cursor: pointer;">
                        <i class="fas fa-user"></i> Accounts <span style="float: right;">▼</span>
                    </span>
                    <div class="submenu-items" style="display: none;">
                        <?php if (in_array('Accounts - Admin', $allowedPages)): ?>
                            <a href="/public/pages/accounts.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Admin
                            </a>
                        <?php endif; ?>
                        <?php if (in_array('Accounts - Clients', $allowedPages)): ?>
                            <a href="/public/pages/accounts_clients.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Clients
                            </a>
                        <?php endif; ?>
                        <?php if (in_array('User Roles', $allowedPages)): ?>
                            <a href="/public/pages/user_roles.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> User Roles
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Inventory Menu with Submenus -->
            <?php if (in_array('Inventory', $allowedPages)): ?>
                <div class="submenu">
                    <span class="menu-item" style="cursor: pointer;">
                        <i class="fas fa-box"></i> Inventory <span style="float: right;">▼</span>
                    </span>
                    <div class="submenu-items" style="display: none;">
                        <a href="/public/pages/inventory.php" class="submenu-item">
                            <i class="fas fa-arrow-right"></i> Finished Products
                        </a>
                        <a href="/public/pages/raw_materials.php" class="submenu-item">
                            <i class="fas fa-arrow-right"></i> Raw Materials
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Fixed Account & Logout Section -->
    <div class="account-section">
        <div class="account-info">
            Logged in as: 
            <strong>
                <?php echo htmlspecialchars($username); ?>
            </strong> (<?= htmlspecialchars(ucfirst($role)) ?>)
        </div>
        <a href="/backend/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>

<!-- Inline JavaScript for direct testing -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    console.log("Inline sidebar script loaded");
    
    // Select all menu items that have submenus
    const submenuToggles = document.querySelectorAll(".submenu > .menu-item");
    console.log("Found submenu toggles:", submenuToggles.length);

    submenuToggles.forEach(toggle => {
        toggle.addEventListener("click", function() {
            console.log("Menu item clicked:", this.textContent.trim());
            
            // Toggle submenu visibility directly with inline style
            const submenu = this.nextElementSibling;
            if (submenu && submenu.classList.contains("submenu-items")) {
                if (submenu.style.display === "none" || submenu.style.display === "") {
                    submenu.style.display = "flex";
                    // Rotate arrow if present
                    const arrow = this.querySelector("span:last-child");
                    if (arrow) arrow.style.transform = "rotate(180deg)";
                } else {
                    submenu.style.display = "none";
                    // Reset arrow rotation if present
                    const arrow = this.querySelector("span:last-child");
                    if (arrow) arrow.style.transform = "rotate(0deg)";
                }
                console.log("Toggled submenu display to:", submenu.style.display);
            }
        });
    });
});
</script>