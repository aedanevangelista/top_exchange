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
    font-size: 12px;
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
                <a href="/public/pages/dashboard.php" class="menu-item">
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
                        <a href="/public/pages/forecast.php" class="submenu-item">
                            <i class="fas fa-arrow-right"></i> Delivery Calendar
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Orders Menu -->
            <?php if (in_array('Orders', $allowedPages) || 
                     in_array('Order History', $allowedPages) || 
                     in_array('Pending Orders', $allowedPages)): ?>
                <div class="submenu">
                    <span class="menu-item" onclick="toggleSubmenu(this)">
                        <div>
                            <i class="fas fa-shopping-cart"></i> Orders
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </span>
                    <div class="submenu-items">
                        <?php if (in_array('Pending Orders', $allowedPages)): ?>
                            <a href="/public/pages/pending_orders.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Pending Orders
                            </a>
                        <?php endif; ?>
                        <?php if (in_array('Orders', $allowedPages)): ?>
                            <a href="/public/pages/orders.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Active Orders
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

            <!-- Reports Menu -->
            <?php if (in_array('Reports', $allowedPages)): ?>
                <div class="submenu">
                    <span class="menu-item" onclick="toggleSubmenu(this)">
                        <div>
                            <i class="fas fa-chart-bar"></i> Reports
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </span>
                    <div class="submenu-items">
                        <a href="/reports/sales_report.php" class="submenu-item">
                            <i class="fas fa-arrow-right"></i> Sales Reports
                        </a>
                        <a href="/reports/product_performance.php" class="submenu-item">
                            <i class="fas fa-arrow-right"></i> Product Performance
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Inventory Menu -->
            <?php if (in_array('Inventory', $allowedPages) || in_array('Raw Materials', $allowedPages)): ?>
                <div class="submenu">
                    <span class="menu-item" onclick="toggleSubmenu(this)">
                        <div>
                            <i class="fas fa-warehouse"></i> Inventory
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </span>
                    <div class="submenu-items">
                        <?php if (in_array('Inventory', $allowedPages)): ?>
                            <a href="/public/pages/inventory.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Products
                            </a>
                        <?php endif; ?>
                        <?php if (in_array('Raw Materials', $allowedPages)): ?>
                            <a href="/public/pages/raw_materials.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Raw Materials
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Payment History -->
            <?php if (in_array('Payment History', $allowedPages)): ?>
                <a href="/public/pages/payment_history.php" class="menu-item">
                    <div>
                        <i class="fas fa-credit-card"></i> Payment History
                    </div>
                </a>
            <?php endif; ?>

            <!-- Accounts Menu -->
            <?php if (in_array('Accounts - Admin', $allowedPages) || 
                     in_array('Accounts - Clients', $allowedPages)): ?>
                <div class="submenu">
                    <span class="menu-item" onclick="toggleSubmenu(this)">
                        <div>
                            <i class="fas fa-users"></i> Accounts
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </span>
                    <div class="submenu-items">
                        <?php if (in_array('Accounts - Admin', $allowedPages)): ?>
                            <a href="/public/pages/accounts.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Admin Accounts
                            </a>
                        <?php endif; ?>
                        <?php if (in_array('Accounts - Clients', $allowedPages)): ?>
                            <a href="/public/pages/accounts_clients.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Client Accounts
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Customers -->
            <?php if (in_array('Customers', $allowedPages)): ?>
                <a href="/public/pages/customers.php" class="menu-item">
                    <div>
                        <i class="fas fa-user-tie"></i> Customers
                    </div>
                </a>
            <?php endif; ?>

            <!-- User Roles -->
            <?php if (in_array('User Roles', $allowedPages)): ?>
                <a href="/public/pages/user_roles.php" class="menu-item">
                    <div>
                        <i class="fas fa-user-shield"></i> User Roles
                    </div>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Function to toggle submenu visibility
function toggleSubmenu(element) {
    element.classList.toggle('active');
    const submenuItems = element.nextElementSibling;
    submenuItems.classList.toggle('visible');
}

// Check if there's a saved active submenu in sessionStorage and restore it
document.addEventListener('DOMContentLoaded', function() {
    const activeSubmenuPaths = JSON.parse(sessionStorage.getItem('activeSubmenus') || '[]');
    
    activeSubmenuPaths.forEach(path => {
        // Find all menu items
        const menuItems = document.querySelectorAll('.submenu > .menu-item');
        menuItems.forEach(item => {
            // Check if this is the one we want to activate
            const menuTitle = item.querySelector('div').innerText.trim();
            if (menuTitle === path) {
                // Activate this submenu
                item.classList.add('active');
                const submenuItems = item.nextElementSibling;
                if (submenuItems) {
                    submenuItems.classList.add('visible');
                }
            }
        });
    });
});

// Save active submenus when they're toggled
document.addEventListener('click', function(e) {
    if (e.target.closest('.menu-item') && e.target.closest('.submenu')) {
        // This is a submenu item click
        setTimeout(() => {
            // Get all active submenus
            const activeSubmenus = Array.from(document.querySelectorAll('.submenu > .menu-item.active'))
                .map(item => item.querySelector('div').innerText.trim());
            
            // Save to sessionStorage
            sessionStorage.setItem('activeSubmenus', JSON.stringify(activeSubmenus));
        }, 10);
    }
});
</script>