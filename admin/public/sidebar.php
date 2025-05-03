<?php
// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// *** USING ORIGINAL INCLUDE PATHS ***
include_once __DIR__ . '/../../backend/db_connection.php';
include_once __DIR__ . '/../../backend/check_role.php';

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

$allowedPages = []; // Initialize to prevent errors if DB call fails
if (isset($conn) && $role !== 'guest') {
    // Fetch pages for the user role
    $stmt = $conn->prepare("SELECT pages FROM roles WHERE role_name = ? AND status = 'active'");
    if ($stmt) { // Check if prepare succeeded
        $stmt->bind_param("s", $role);
        $stmt->execute();
        $stmt->bind_result($pages);
        if ($stmt->fetch()) { // Check if a row was fetched
             // Convert pages to an array and trim whitespace
            $allowedPages = array_map('trim', explode(',', $pages ?? '')); // Use null coalescing for safety
        }
        $stmt->close();
    } else {
        // Optional: Log error if statement preparation failed
        error_log("Failed to prepare statement to fetch roles: " . $conn->error);
    }
} else if ($role === 'guest') {
     // Handle guest role if necessary, maybe assign default allowed pages?
     $allowedPages = []; // Guests see nothing by default based on this logic
} else {
     // Optional: Log error if DB connection is missing
     error_log("Database connection not available in sidebar.php");
     $allowedPages = []; // Default to no pages if DB connection fails
}

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
    margin-left: auto; /* Push chevron to the right */
}

.menu-item.active .fa-chevron-down {
    transform: rotate(180deg);
    opacity: 1;
}

.submenu > .menu-item {
    cursor: pointer;
    display: flex; /* Needed to align chevron */
    justify-content: space-between; /* Push chevron to the right */
    align-items: center; /* Vertically align */
}

/* Basic hover for top-level links (add this if missing from global styles) */
a.menu-item:hover {
    background-color: rgba(0,0,0,0.05);
}
/* Ensure submenu links also get hover */
a.submenu-item:hover {
     background-color: rgba(0,0,0,0.05);
}

/* Style for the container div inside menu items */
.menu-item > div {
    display: flex;
    align-items: center;
}
.menu-item > div > i {
    margin-right: 8px; /* Space between icon and text */
}

/* Style for submenu item icons */
.submenu-item i {
     margin-right: 8px; /* Space between icon and text */
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

            <!-- Production Menu -->
            <?php
               // Check if user has permission for *any* production page before showing the parent menu
               $canSeeProduction = in_array('Forecast', $allowedPages) || in_array('Department Forecast', $allowedPages);
            ?>
            <?php if ($canSeeProduction): ?>
                <div class="submenu">
                    <span class="menu-item" onclick="toggleSubmenu(this)">
                        <div>
                            <i class="fas fa-industry"></i> Production
                        </div>
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
             <?php
               // Check if user has permission for *any* ordering page
               $canSeeOrdering = in_array('Orders', $allowedPages) || in_array('Order History', $allowedPages) || in_array('Deliverable Orders', $allowedPages); // Removed Pending Orders check as it wasn't in the links
            ?>
            <?php if ($canSeeOrdering): ?>
                <div class="submenu">
                    <span class="menu-item" onclick="toggleSubmenu(this)">
                        <div>
                            <i class="fas fa-shopping-cart"></i> Ordering
                        </div>
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
                        <div>
                            <i class="fas fa-money-bill-wave"></i> Payments
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </span>
                    <div class="submenu-items">
                        <a href="/public/pages/payment_history.php" class="submenu-item">
                            <i class="fas fa-arrow-right"></i> Payment History
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Reporting Menu (Added Here) -->
            <?php if (in_array('Reporting', $allowedPages)): ?>
                <a href="/public/pages/reporting.php" class="menu-item">
                    <div>
                        <i class="fas fa-chart-line"></i> Reporting
                    </div>
                </a>
            <?php endif; ?>

            <?php if (in_array('Sales Data', $allowedPages)): ?>
                <a href="/public/pages/sales.php" class="menu-item">
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
             <?php
               // Check if user has permission for *any* staff page
               $canSeeStaffMenu = in_array('Staff', $allowedPages) || in_array('Drivers', $allowedPages);
            ?>
            <?php if ($canSeeStaffMenu): ?>
                <div class="submenu">
                    <span class="menu-item" onclick="toggleSubmenu(this)">
                        <div>
                            <i class="fas fa-users-cog"></i> Staff
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </span>
                    <div class="submenu-items">
                         <?php if (in_array('Staff', $allowedPages)): ?>
                            <!-- Add link if Staff page exists -->
                            <!-- <a href="/public/pages/staff.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Staff List
                            </a> -->
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
             <?php
               // Check if user has permission for *any* account page
               $canSeeAccounts = in_array('Accounts - Admin', $allowedPages) || in_array('Accounts - Clients', $allowedPages) || in_array('User Roles', $allowedPages);
            ?>
            <?php if ($canSeeAccounts): ?>
                <div class="submenu">
                    <span class="menu-item" onclick="toggleSubmenu(this)">
                        <div>
                            <i class="fas fa-user"></i> Accounts
                        </div>
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
             <?php
               // Check if user has permission for *any* inventory page
               $canSeeInventory = in_array('Inventory', $allowedPages) || in_array('Raw Materials', $allowedPages);
            ?>
            <?php if ($canSeeInventory): ?>
                <div class="submenu">
                    <span class="menu-item" onclick="toggleSubmenu(this)">
                        <div>
                            <i class="fas fa-box"></i> Inventory
                        </div>
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
// Updated toggle function with smoother animation handling
function toggleSubmenu(element) {
    // First, close all other open submenus that are not the parent of the clicked element
    const allSubmenus = document.querySelectorAll('.sidebar .submenu');
    const parentSubmenuDiv = element.closest('.submenu'); // Find the parent .submenu div
    const targetSubmenuItems = parentSubmenuDiv ? parentSubmenuDiv.querySelector('.submenu-items') : null;

    if (!targetSubmenuItems) return; // Exit if we couldn't find the items container

    const isOpening = !element.classList.contains('active');

    // Close others first
    allSubmenus.forEach(submenu => {
        const trigger = submenu.querySelector('span.menu-item');
        const items = submenu.querySelector('.submenu-items');
        if (trigger !== element && trigger.classList.contains('active')) {
            trigger.classList.remove('active');
            items.classList.remove('visible');
        }
    });

    // Toggle the current one
    if (isOpening) {
        element.classList.add('active');
        targetSubmenuItems.classList.add('visible');
    } else {
        element.classList.remove('active');
        targetSubmenuItems.classList.remove('visible');
    }
}
</script>