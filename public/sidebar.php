<?php
// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include_once "../../backend/db_connection.php";
include_once "../../backend/check_role.php";

$role = $_SESSION['role'] ?? 'guest'; // Get user role from session

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
                <a href="/top_exchange/public/pages/dashboard.php" class="menu-item">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            <?php endif; ?>

            <!-- Transactions Menu with Submenus -->
            <?php if (in_array('Transactions', $allowedPages) || in_array('Orders', $allowedPages) || in_array('Forecast', $allowedPages) || in_array('Order History', $allowedPages)): ?>
                <div class="submenu">
                    <span class="menu-item no-hover">
                        <i class="fas fa-exchange-alt"></i> Transactions
                    </span>
                    <div class="submenu-items">
                        <?php if (in_array('Orders', $allowedPages)): ?>
                            <a href="/top_exchange/public/pages/orders.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Orders
                            </a>
                        <?php endif; ?>
                        <?php if (in_array('Forecast', $allowedPages)): ?>
                            <a href="/top_exchange/public/pages/forecast.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Forecast
                            </a>
                        <?php endif; ?>
                        <?php if (in_array('Order History', $allowedPages)): ?>
                            <a href="/top_exchange/public/pages/order_history.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Order History
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (in_array('Sales Data', $allowedPages)): ?>
                <a href="/top_exchange/public/pages/sales.php" class="menu-item">
                    <i class="fas fa-chart-bar"></i> Sales Data
                </a>
            <?php endif; ?>
            <?php if (in_array('Forecast', $allowedPages)): ?>
                <a href="/top_exchange/public/pages/forecast.php" class="menu-item">
                    <i class="fas fa-chart-line"></i> Forecast
                </a>
            <?php endif; ?>
        </div>

        <!-- DATA Section -->
        <div class="menu-section">
            <span class="menu-title"><b>DATA</b></span>
            <hr>
            <?php if (in_array('Customers', $allowedPages)): ?>
                <a href="/top_exchange/public/pages/customers.php" class="menu-item">
                    <i class="fas fa-users"></i> Customers
                </a>
            <?php endif; ?>
            
            <!-- Accounts Menu with Submenus -->
            <?php if (in_array('Accounts - Admin', $allowedPages) || in_array('Accounts - Clients', $allowedPages) || in_array('User Roles', $allowedPages)): ?>
                <div class="submenu">
                    <span class="menu-item no-hover">
                        <i class="fas fa-user"></i> Accounts
                    </span>
                    <div class="submenu-items">
                        <?php if (in_array('Accounts - Admin', $allowedPages)): ?>
                            <a href="/top_exchange/public/pages/accounts.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Admin
                            </a>
                        <?php endif; ?>
                        <?php if (in_array('Accounts - Clients', $allowedPages)): ?>
                            <a href="/top_exchange/public/pages/accounts_clients.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> Clients
                            </a>
                        <?php endif; ?>
                        <?php if (in_array('User Roles', $allowedPages)): ?>
                            <a href="/top_exchange/public/pages/user_roles.php" class="submenu-item">
                                <i class="fas fa-arrow-right"></i> User Roles
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (in_array('Inventory', $allowedPages)): ?>
                <a href="/top_exchange/public/pages/inventory.php" class="menu-item">
                    <i class="fas fa-box"></i> Inventory
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Fixed Account & Logout Section -->
    <div class="account-section">
        <div class="account-info">
            Logged in as: 
            <strong>
                <?php 
                echo isset($_SESSION['username']) 
                    ? htmlspecialchars($_SESSION['username']) 
                    : "Guest"; 
                ?>
            </strong> (<?= htmlspecialchars(ucfirst($role)) ?>)
        </div>
        <a href="/top_exchange/backend/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>
