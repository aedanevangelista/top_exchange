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

<style>
/* Local styles for sidebar dropdown - keeps it self-contained */
.submenu-items {
    display: block;
    flex-direction: column;
    margin-left: 20px; /* Indentation */
    margin-top: 0;
    overflow: hidden;
    max-height: 0;
    opacity: 0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); /* Smooth easing function */
    pointer-events: none; /* Prevents clicking on hidden items */
}

.submenu-items.visible {
    max-height: 300px; /* Adjust based on your content */
    opacity: 1;
    margin-top: 5px;
    pointer-events: all; /* Re-enables clicking */
}

.menu-item .fa-chevron-down {
    font-size: 13px; /* Increased by 1px from 12px */
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    opacity: 0.8;
}

.menu-item.active .fa-chevron-down {
    transform: rotate(180deg);
    opacity: 1;
}

.submenu > .menu-item {
    cursor: pointer;
}
</style>

<div class="sidebar">
    <div>
        <!-- MAIN MENU Section -->
        <div class="menu-section">
            <span class="menu-title"><b>MAIN MENU</b></span>
            <hr>
            <?php if (in_array('Dashboard', $allowedPages)): ?>
                <a href="/admin/public/pages/dashboard.php" class="menu-item">
                    <div>
                        <i class="fas fa-home"></i> Dashboard
                    </div>
                </a>
            <?php endif; ?>
            
            <!-- Production Menu with Submenu for Delivery Calendar (formerly Forecast) -->
            <?php if (in_array('Forecast', $allowedPages)): ?>
                <div class="submenu">
                    <span class="menu-item" onclick="toggleSubmenu(this)">
                        <div>
                            <i class="fas fa-industry"></i> Production
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </span>
                    <div class="submenu-items">
                        <a href="/admin/public/pages/forecast.php" class="submenu-item">
                            <i class="fas fa-arrow-right"></i> Delivery Calendar
                        </a>
                        <a href="/admin/public/pages/department_forecast.php" class="submenu-item">
                            <i class="fas fa-arrow-right"></i> Department Forecast
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Ordering Menu with Submenus -->
            <?php if (in_array('Orders', $allowedPages) || in_array('Order History', $allowedPages) || in_array('Pending Orders', $allowedPages) || in_array('Deliverable Orders', $allowedPages)): ?>
                <div class="submenu">
                    <span class="menu-item" onclick="toggleSubmenu(this)">
                        <div>
                            <i class="fas fa-shopping-cart"></i> Ordering
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </span>
                    <div class="submenu-items">
                        <?php if (in_array('Orders', $allowedPages)): ?>
                            <a href="/admin/public/pages/orders.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Orders
                            </a>
                        <?php endif; ?>
                        <?php if (in_array('Deliverable Orders', $allowedPages)): ?>
                            <a href="/admin/public/pages/deliverable_orders.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Deliverable Orders
                            </a>
                        <?php endif; ?>
                        <?php if (in_array('Order History', $allowedPages)): ?>
                            <a href="/admin/public/pages/order_history.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Order History
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Payments Menu with Submenu -->
            <?php if (in_array('Payment History', $allowedPages)): ?>
                <div class="submenu">
                    <span class="menu-item" onclick="toggleSubmenu(this)">
                        <div>
                            <i class="fas fa-money-bill-wave"></i> Payments
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </span>
                    <div class="submenu-items">
                        <a href="/admin/public/pages/payment_history.php" class="submenu-item">
                            <i class="fas fa-arrow-right"></i> Payment History
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (in_array('Sales Data', $allowedPages)): ?>
                <a href="/admin/public/pages/sales.php" class="menu-item">
                    <div>
                        <i class="fas fa-chart-bar"></i> Sales Data
                    </div>
                </a>
            <?php endif; ?>
        </div>

        <!-- DATA Section -->
        <div class="menu-section">
            <span class="menu-title"><b>DATA</b></span>
            <hr>
            <?php if (in_array('Staff', $allowedPages) || in_array('Drivers', $allowedPages)): ?>
                <div class="submenu">
                    <span class="menu-item" onclick="toggleSubmenu(this)">
                        <div>
                            <i class="fas fa-users-cog"></i> Staff
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </span>
                    <div class="submenu-items">
                        <?php if (in_array('Drivers', $allowedPages)): ?>
                            <a href="/admin/public/pages/drivers.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Drivers
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <!-- Accounts Menu with Submenus -->
            <?php if (in_array('Accounts - Admin', $allowedPages) || in_array('Accounts - Clients', $allowedPages) || in_array('User Roles', $allowedPages)): ?>
                <div class="submenu">
                    <span class="menu-item" onclick="toggleSubmenu(this)">
                        <div>
                            <i class="fas fa-user"></i> Accounts
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </span>
                    <div class="submenu-items">
                        <?php if (in_array('Accounts - Admin', $allowedPages)): ?>
                            <a href="/admin/public/pages/accounts.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Staff
                            </a>
                        <?php endif; ?>
                        <?php if (in_array('Accounts - Clients', $allowedPages)): ?>
                            <a href="/admin/public/pages/accounts_clients.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Clients
                            </a>
                        <?php endif; ?>
                        <?php if (in_array('User Roles', $allowedPages)): ?>
                            <a href="/admin/public/pages/user_roles.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> User Roles
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Inventory Menu with Submenus -->
            <?php if (in_array('Inventory', $allowedPages)): ?>
                <div class="submenu">
                    <span class="menu-item" onclick="toggleSubmenu(this)">
                        <div>
                            <i class="fas fa-box"></i> Inventory
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </span>
                    <div class="submenu-items">
                        <a href="/admin/public/pages/inventory.php" class="submenu-item">
                            <i class="fas fa-arrow-right"></i> Finished Products
                        </a>
                        <a href="/admin/public/pages/raw_materials.php" class="submenu-item">
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
        <a href="/admin/admin/backend/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>

<script>
// Updated toggle function with smoother animation handling
function toggleSubmenu(element) {
    // First, close all other open submenus
    const allOpenSubmenus = document.querySelectorAll('.submenu-items.visible');
    const allActiveMenuItems = document.querySelectorAll('.menu-item.active');
    
    // Get the submenu we're trying to toggle
    const targetSubmenu = element.nextElementSibling;
    
    // Check if we're opening or closing this submenu
    const isOpening = !targetSubmenu.classList.contains('visible');
    
    // If we're opening this one, close all others first
    if (isOpening) {
        // Close all other open submenus
        allOpenSubmenus.forEach(menu => {
            if (menu !== targetSubmenu) {
                menu.classList.remove('visible');
            }
        });
        
        // Reset all other active menu items
        allActiveMenuItems.forEach(item => {
            if (item !== element) {
                item.classList.remove('active');
            }
        });
    }
    
    // Toggle active class on the menu item
    element.classList.toggle('active');
    
    // Toggle visibility of the submenu
    if (targetSubmenu) {
        if (isOpening) {
            targetSubmenu.classList.add('visible');
        } else {
            targetSubmenu.classList.remove('visible');
        }
    }
}
</script>