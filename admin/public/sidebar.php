<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// CORRECTED PATHS: Changed ../../backend/ to ../backend/
include_once __DIR__ . '/../backend/db_connection.php';
include_once __DIR__ . '/../backend/check_role.php';

if (isset($_SESSION['admin_user_id'])) {
    $username = $_SESSION['admin_username'] ?? 'Guest';
    $role = $_SESSION['admin_role'] ?? 'guest';
} else if (isset($_SESSION['client_user_id'])) {
    $username = $_SESSION['client_username'] ?? 'Guest';
    $role = $_SESSION['client_role'] ?? 'guest';
} else {
    $username = $_SESSION['username'] ?? 'Guest';
    $role = $_SESSION['role'] ?? 'guest';
}

$allowedPages = [];
if ($role !== 'guest' && isset($conn)) {
    $stmt = $conn->prepare("SELECT pages FROM roles WHERE role_name = ? AND status = 'active'");
    if ($stmt) {
        $stmt->bind_param("s", $role);
        $stmt->execute();
        $stmt->bind_result($pages);
        if ($stmt->fetch()) {
            $allowedPages = array_map('trim', explode(',', $pages ?? ''));
        }
        $stmt->close();
    } else {
        error_log("Failed to prepare statement to fetch roles: " . $conn->error);
    }
}
?>

<style>
/* General Sidebar Styles */
.sidebar {
    /* Add any general sidebar container styles here if needed */
}

.menu-section {
    margin-bottom: 15px; /* Space between sections */
}

.menu-title {
    display: block;
    padding: 10px 15px;
    font-size: 0.8em;
    color: #888; /* Lighter color for titles */
    text-transform: uppercase;
}

hr {
    border: 0;
    height: 1px;
    background-color: #eee;
    margin: 5px 15px 10px 15px;
}

/* --- Menu Item Styling --- */

/* Base style for all clickable items (links and submenu triggers) */
.menu-item {
    display: flex; /* Use flex for alignment */
    align-items: center; /* Vertically center icon and text */
    padding: 10px 15px;
    text-decoration: none;
    color: inherit; /* Use sidebar's text color */
    transition: background-color 0.2s ease;
    cursor: pointer;
    width: 100%; /* Ensure full width for click/hover */
    box-sizing: border-box; /* Include padding in width */
}

/* Hover effect specifically for ACTUAL LINKS (<a> tags) */
a.menu-item:hover {
    background-color: rgba(0, 0, 0, 0.05); /* Apply hover to the link */
}

/* Style for the icon within any menu item */
.menu-item i.fas { /* Target FontAwesome icons */
    margin-right: 10px; /* Space between icon and text */
    width: 1.2em; /* Give icon fixed width for alignment */
    text-align: center;
}

/* --- Submenu Specific Styling --- */

/* Style for the SPAN that triggers the submenu */
.submenu > span.menu-item {
    justify-content: space-between; /* Push chevron to the right */
}

/* Chevron icon styling */
.submenu > span.menu-item .fa-chevron-down {
    font-size: 12px; /* Smaller chevron */
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    opacity: 0.7;
    margin-left: auto; /* Push to far right */
    margin-right: 0; /* Remove margin if icon style adds it */
    width: auto; /* Override fixed width if needed */
}

.submenu > span.menu-item.active .fa-chevron-down {
    transform: rotate(180deg);
    opacity: 1;
}

/* Container for submenu items */
.submenu-items {
    display: block;
    padding-left: 25px; /* Indent submenu items more */
    overflow: hidden;
    max-height: 0;
    opacity: 0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1), padding 0s; /* Smooth transition */
    pointer-events: none; /* Prevent interaction when hidden */
}

.submenu-items.visible {
    max-height: 400px; /* Adjust max height as needed */
    opacity: 1;
    pointer-events: all; /* Allow interaction when visible */
    padding-top: 5px;
    padding-bottom: 5px;
}

/* Individual links within a submenu */
.submenu-item {
    display: flex; /* Use flex for alignment */
    align-items: center;
    padding: 8px 10px 8px 0; /* Adjust padding */
    color: inherit;
    text-decoration: none;
    font-size: 0.9em;
    transition: background-color 0.2s ease;
}

.submenu-item:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

.submenu-item i.fas.fa-arrow-right {
    margin-right: 8px;
    font-size: 0.8em;
    opacity: 0.7;
    width: 1em; /* Align with other icons */
    text-align: center;
}

/* --- Account Section --- */
.account-section {
    /* Add styles if needed */
    padding: 15px;
    border-top: 1px solid #eee;
}
.account-info {
    font-size: 0.9em;
    margin-bottom: 10px;
    color: #555;
}
.logout-btn {
     display: block;
     /* Add styles for logout button */
     color: #dc3545; /* Example danger color */
     text-decoration: none;
}
.logout-btn:hover {
    text-decoration: underline;
}
.logout-btn i {
    margin-right: 5px;
}

</style>

<div class="sidebar">
    <div>
        <!-- MAIN MENU Section -->
        <div class="menu-section">
            <span class="menu-title"><b>MAIN MENU</b></span>
            <hr>
            <?php if (in_array('Dashboard', $allowedPages)): ?>
                <!-- Use <a> tag directly with menu-item class -->
                <a href="/public/pages/dashboard.php" class="menu-item">
                    <i class="fas fa-home"></i>Dashboard
                </a>
            <?php endif; ?>

            <!-- Production Menu -->
            <?php if (in_array('Forecast', $allowedPages) || in_array('Department Forecast', $allowedPages)): ?>
                <div class="submenu">
                    <!-- Use <span> tag for submenu trigger -->
                    <span class="menu-item" onclick="toggleSubmenu(this)">
                        <span><i class="fas fa-industry"></i>Production</span>
                        <i class="fas fa-chevron-down"></i>
                    </span>
                    <div class="submenu-items">
                        <?php if (in_array('Forecast', $allowedPages)): ?>
                        <a href="/public/pages/forecast.php" class="submenu-item">
                            <i class="fas fa-arrow-right"></i> Delivery Calendar
                        </a>
                        <?php endif; ?>
                        <?php if (in_array('Department Forecast', $allowedPages)): ?>
                        <a href="/public/pages/department_forecast.php" class="submenu-item">
                            <i class="fas fa-arrow-right"></i> Department Forecast
                        </a>
                         <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Ordering Menu -->
            <?php if (in_array('Orders', $allowedPages) || in_array('Order History', $allowedPages) || in_array('Pending Orders', $allowedPages) || in_array('Deliverable Orders', $allowedPages)): ?>
                <div class="submenu">
                    <span class="menu-item" onclick="toggleSubmenu(this)">
                        <span><i class="fas fa-shopping-cart"></i>Ordering</span>
                        <i class="fas fa-chevron-down"></i>
                    </span>
                    <div class="submenu-items">
                        <?php if (in_array('Orders', $allowedPages)): ?>
                            <a href="/public/pages/orders.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Orders
                            </a>
                        <?php endif; ?>
                        <?php if (in_array('Deliverable Orders', $allowedPages)): ?>
                            <a href="/public/pages/deliverable_orders.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Deliverable Orders
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

            <!-- Payments Menu -->
            <?php if (in_array('Payment History', $allowedPages)): ?>
                <div class="submenu">
                    <span class="menu-item" onclick="toggleSubmenu(this)">
                        <span><i class="fas fa-money-bill-wave"></i>Payments</span>
                        <i class="fas fa-chevron-down"></i>
                    </span>
                    <div class="submenu-items">
                        <a href="/public/pages/payment_history.php" class="submenu-item">
                            <i class="fas fa-arrow-right"></i> Payment History
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Reporting Menu -->
            <?php if (in_array('Reporting', $allowedPages)): ?>
                <a href="/public/pages/reporting.php" class="menu-item">
                    <i class="fas fa-chart-line"></i>Reporting
                </a>
            <?php endif; ?>

            <?php if (in_array('Sales Data', $allowedPages)): ?>
                <a href="/public/pages/sales.php" class="menu-item">
                    <i class="fas fa-chart-bar"></i>Sales Data
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
                        <span><i class="fas fa-users-cog"></i>Staff</span>
                        <i class="fas fa-chevron-down"></i>
                    </span>
                    <div class="submenu-items">
                         <?php if (in_array('Staff', $allowedPages)): ?>
                            <!-- Assuming staff link goes somewhere -->
                            <a href="/public/pages/staff.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Staff List
                            </a>
                         <?php endif; ?>
                        <?php if (in_array('Drivers', $allowedPages)): ?>
                            <a href="/public/pages/drivers.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Drivers
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <!-- Accounts Menu -->
            <?php if (in_array('Accounts - Admin', $allowedPages) || in_array('Accounts - Clients', $allowedPages) || in_array('User Roles', $allowedPages)): ?>
                <div class="submenu">
                    <span class="menu-item" onclick="toggleSubmenu(this)">
                        <span><i class="fas fa-user"></i>Accounts</span>
                        <i class="fas fa-chevron-down"></i>
                    </span>
                    <div class="submenu-items">
                        <?php if (in_array('Accounts - Admin', $allowedPages)): ?>
                            <a href="/public/pages/accounts.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Staff
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

            <!-- Inventory Menu -->
            <?php if (in_array('Inventory', $allowedPages) || in_array('Raw Materials', $allowedPages)): ?>
                <div class="submenu">
                    <span class="menu-item" onclick="toggleSubmenu(this)">
                        <span><i class="fas fa-box"></i>Inventory</span>
                        <i class="fas fa-chevron-down"></i>
                    </span>
                    <div class="submenu-items">
                         <?php if (in_array('Inventory', $allowedPages)): ?>
                            <a href="/public/pages/inventory.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Finished Products
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

<script>
function toggleSubmenu(element) {
    // Find the parent .submenu element
    const parentSubmenu = element.closest('.submenu');
    if (!parentSubmenu) return; // Should not happen with current structure

    const submenuItems = parentSubmenu.querySelector('.submenu-items');
    const isActive = element.classList.contains('active');

    // Close all other submenus first
    const allSubmenus = document.querySelectorAll('.sidebar .submenu');
    allSubmenus.forEach(submenu => {
        const trigger = submenu.querySelector('span.menu-item');
        const items = submenu.querySelector('.submenu-items');
        if (trigger !== element && trigger.classList.contains('active')) {
            trigger.classList.remove('active');
            items.classList.remove('visible');
        }
    });

    // Toggle the current one
    if (isActive) {
        element.classList.remove('active');
        submenuItems.classList.remove('visible');
    } else {
        element.classList.add('active');
        submenuItems.classList.add('visible');
    }
}

// Optional: Close submenus if clicking outside the sidebar
// document.addEventListener('click', function(event) {
//     const sidebar = document.querySelector('.sidebar');
//     if (!sidebar.contains(event.target)) {
//         const allSubmenus = document.querySelectorAll('.sidebar .submenu');
//         allSubmenus.forEach(submenu => {
//              const trigger = submenu.querySelector('span.menu-item');
//              const items = submenu.querySelector('.submenu-items');
//              if (trigger.classList.contains('active')) {
//                  trigger.classList.remove('active');
//                  items.classList.remove('visible');
//              }
//         });
//     }
// });

</script>