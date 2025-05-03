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
.submenu-items {
    display: block;
    margin-left: 20px;
    margin-top: 0;
    overflow: hidden;
    max-height: 0;
    opacity: 0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    pointer-events: none;
}

.submenu-items.visible {
    max-height: 300px;
    opacity: 1;
    margin-top: 5px;
    pointer-events: all;
}

.menu-item .fa-chevron-down {
    font-size: 13px;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    opacity: 0.8;
    margin-left: auto;
}

.menu-item.active .fa-chevron-down {
    transform: rotate(180deg);
    opacity: 1;
}

.submenu > .menu-item {
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.submenu-item {
    display: block;
    padding: 8px 10px 8px 0;
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
}
/* Style for top-level menu items (direct links) */
a.menu-item {
    display: block; /* Or flex if needed */
    padding: 10px 15px; /* Example padding */
    text-decoration: none;
    color: inherit;
    transition: background-color 0.2s ease;
}
a.menu-item:hover {
     background-color: rgba(0, 0, 0, 0.05); /* Subtle hover effect */
}
a.menu-item i {
    margin-right: 10px; /* Space between icon and text */
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
                        <i class="fas fa-home"></i>Dashboard
                    </div>
                </a>
            <?php endif; ?>

            <!-- Production Menu -->
            <?php if (in_array('Forecast', $allowedPages) || in_array('Department Forecast', $allowedPages)): ?>
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

            <!-- Payments Menu (Now only Payment History) -->
            <?php if (in_array('Payment History', $allowedPages)): // Only check for Payment History now ?>
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
                        <?php // Reporting link removed from here ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Reporting Menu (New Top-Level Item) -->
            <?php if (in_array('Reporting', $allowedPages)): ?>
                <a href="/public/pages/reporting.php" class="menu-item">
                    <div>
                        <i class="fas fa-chart-line"></i>Reporting
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
            <?php if (in_array('Staff', $allowedPages) || in_array('Drivers', $allowedPages)): ?>
                <div class="submenu">
                    <span class="menu-item" onclick="toggleSubmenu(this)">
                        <div>
                            <i class="fas fa-users-cog"></i> Staff
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </span>
                    <div class="submenu-items">
                         <?php if (in_array('Staff', $allowedPages)): ?>
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
            <?php if (in_array('Inventory', $allowedPages) || in_array('Raw Materials', $allowedPages)): ?>
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
function toggleSubmenu(element) {
    const allSubmenus = document.querySelectorAll('.sidebar .submenu');
    const targetSubmenuItems = element.nextElementSibling;
    const isOpening = !element.classList.contains('active');

    allSubmenus.forEach(submenu => {
        const menuItem = submenu.querySelector('.menu-item');
        const submenuItems = submenu.querySelector('.submenu-items');

        if (menuItem !== element && menuItem.classList.contains('active')) {
            menuItem.classList.remove('active');
            submenuItems.classList.remove('visible');
        }
    });

    element.classList.toggle('active');
    targetSubmenuItems.classList.toggle('visible');
}
</script>