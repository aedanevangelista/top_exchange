<?php
// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? ''; // Get user role from session
?>

<div class="sidebar">
    <div>
        <!-- MAIN MENU Section -->
        <div class="menu-section">
            <span class="menu-title"><b>MAIN MENU</b></span>
            <hr>
            <a href="/top_exchange/public/pages/dashboard.php" class="menu-item">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="/top_exchange/public/pages/sales.php" class="menu-item">
                <i class="fas fa-chart-bar"></i> Sales Data
            </a>
            <a href="/top_exchange/public/pages/forecast.php" class="menu-item">
                <i class="fas fa-chart-line"></i> Forecast
            </a>
        </div>

        <!-- DATA Section -->
        <div class="menu-section">
            <span class="menu-title"><b>DATA</b></span>
            <hr>

            <!-- Show 'Customers' only to Admin -->
            <?php if ($role === 'admin'): ?>
                <a href="/top_exchange/public/pages/customers.php" class="menu-item">
                    <i class="fas fa-users"></i> Customers
                </a>
            <?php endif; ?>

            <!-- Accounts Menu with Submenus for Admin and Clients -->
            <div class="submenu">
                <span class="menu-item no-hover">
                    <i class="fas fa-user"></i> Accounts
                </span>
                <div class="submenu-items">
                    <?php if ($role === 'admin'): ?>
                        <a href="/top_exchange/public/pages/accounts.php" class="submenu-item">
                            <i class="fas fa-arrow-right"></i> Admin
                        </a>
                        <a href="/top_exchange/public/pages/accounts_clients.php" class="submenu-item">
                            <i class="fas fa-arrow-right"></i> Clients
                        </a>
                        <a href="/top_exchange/public/pages/user_roles.php" class="submenu-item">
                            <i class="fas fa-arrow-right"></i> User Roles
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Show 'Inventory' to Admin and Secretary -->
            <?php if (in_array($role, ['admin', 'secretary'])): ?>
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