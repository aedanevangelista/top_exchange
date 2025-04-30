<?php
// Check if this file is accessed directly
if (!defined('INCLUDED_FROM_INDEX')) {
    // Redirect to index to enforce proper inclusion
    header("Location: ../index.php");
    exit;
}

// Define the current page based on the URL
$current_page = basename($_SERVER['PHP_SELF']);
$current_page = str_replace('.php', '', $current_page);

// Function to check if a string contains a substring
function containsSubstring($haystack, $needle) {
    return strpos($haystack, $needle) !== false;
}

// Get the pages allowed for the user's role
$allowedPages = [];
if (isset($_SESSION['role'])) {
    $sql = "SELECT pages FROM roles WHERE role_name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $_SESSION['role']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $allowedPages = explode(", ", $row['pages']);
    }
}
?>

<div class="sidebar">
    <div class="sidebar-logo">
        <img src="/public/assets/imgs/logo_4.png" alt="Logo" class="logo-image">
        <span class="logo-text">Top Exchange</span>
    </div>
    
    <div class="sidebar-user">
        <div class="profile-img">
            <img src="/public/assets/imgs/profile.png" alt="Profile">
        </div>
        <div class="user-info">
            <span class="username"><?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest'; ?></span>
            <span class="user-role"><?php echo isset($_SESSION['role']) ? $_SESSION['role'] : 'No Role'; ?></span>
        </div>
    </div>
    
    <div class="sidebar-menu">
        <?php if (in_array('Dashboard', $allowedPages)): ?>
            <a href="/public/pages/dashboard.php" class="menu-item <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                <div>
                    <i class="fas fa-home"></i> Dashboard
                </div>
            </a>
        <?php endif; ?>
        
        <!-- Accounts Menu with Submenus -->
        <?php if (in_array('Accounts - Admin', $allowedPages) || in_array('Accounts - Clients', $allowedPages) || in_array('User Roles', $allowedPages)): ?>
            <div class="submenu">
                <span class="menu-item" onclick="toggleSubmenu(this)">
                    <div>
                        <i class="fas fa-user-circle"></i> Accounts
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </span>
                <div class="submenu-items">
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
        <?php if (in_array('Inventory', $allowedPages) || in_array('Raw Materials', $allowedPages)): ?>
            <div class="submenu">
                <span class="menu-item" onclick="toggleSubmenu(this)">
                    <div>
                        <i class="fas fa-box-open"></i> Inventory
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
        
        <!-- Production Menu with Submenus -->
        <?php if (in_array('Department Forecast', $allowedPages) || in_array('Forecast', $allowedPages)): ?>
            <div class="submenu">
                <span class="menu-item" onclick="toggleSubmenu(this)">
                    <div>
                        <i class="fas fa-chart-line"></i> Production
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </span>
                <div class="submenu-items">
                    <a href="/public/pages/forecast.php" class="submenu-item">
                        <i class="fas fa-arrow-right"></i> Delivery Calendar
                    </a>
                    <a href="/public/pages/department_forecast.php" class="submenu-item">
                        <i class="fas fa-arrow-right"></i> Department Forecast
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Ordering Menu with Submenus -->
        <?php if (in_array('Orders', $allowedPages) || in_array('Order History', $allowedPages) || in_array('Deliverable Orders', $allowedPages)): ?>
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
                    <a href="/public/pages/payment_history.php" class="submenu-item">
                        <i class="fas fa-arrow-right"></i> Payment History
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (in_array('Sales Data', $allowedPages)): ?>
            <a href="/public/pages/sales.php" class="menu-item">
                <div>
                    <i class="fas fa-chart-bar"></i> Sales Data
                </div>
            </a>
        <?php endif; ?>
        
        <?php if (in_array('Drivers', $allowedPages)): ?>
            <a href="/public/pages/drivers.php" class="menu-item">
                <div>
                    <i class="fas fa-truck"></i> Drivers
                </div>
            </a>
        <?php endif; ?>
    </div>

    <!-- DATA Section -->
    <div class="sidebar-data">
        <p class="data-time">
            <i class="fas fa-clock"></i> <?php echo date('g:i A'); ?>
        </p>
        <p class="data-date">
            <i class="fas fa-calendar"></i> <?php echo date('d M Y'); ?>
        </p>
    </div>
    
    <div class="sidebar-footer">
        <a href="/backend/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>

<script>
    function toggleSubmenu(element) {
        const submenuItems = element.nextElementSibling;
        const allSubmenus = document.querySelectorAll('.submenu-items');
        
        // Close all other open submenus
        allSubmenus.forEach(submenu => {
            if (submenu !== submenuItems && submenu.style.maxHeight) {
                submenu.style.maxHeight = null;
                submenu.previousElementSibling.querySelector('.fa-chevron-down').classList.remove('rotate');
            }
        });
        
        // Toggle the clicked submenu
        if (submenuItems.style.maxHeight) {
            submenuItems.style.maxHeight = null;
            element.querySelector('.fa-chevron-down').classList.remove('rotate');
        } else {
            submenuItems.style.maxHeight = submenuItems.scrollHeight + "px";
            element.querySelector('.fa-chevron-down').classList.add('rotate');
        }
    }
</script>