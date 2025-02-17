<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: http://localhost/top_exchange/public/login.php");
    exit();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/sidebar.css"> <!-- Use the same sidebar.css as Inventory page -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include '../sidebar.php'; ?>

        <!-- Main content -->
        <div class="main-content">
        <div class="overview-container">
            <h2>Dashboard</h2>
            <button class="input-po-btn">Input P.O</button>
        </div>

        <div class="top-section">
            <!-- CLIENT ORDERS -->
            <div class="client-orders-container">
                <h3>CLIENT ORDERS</h3>
                <select id="client-orders-filter">
                    <option value="month">Monthly</option>
                    <option value="year">Yearly</option>
                </select>
                <div class="client-orders">
                    <canvas id="clientOrdersChart"></canvas>
                </div>
            </div>

            <!-- PACKS SOLD SINCE -->
            <div class="packs-sold-container">
    <!-- Header with Year Dropdown -->
    <div class="packs-sold-header">
        <span>Packs sold since</span>
        <select id="packs-sold-year" class="packs-sold-dropdown">
            <option value="2025">2025</option>
            <option value="2024">2024</option>
            <option value="2023">2023</option>
        </select>
    </div>

    <!-- Packs Count -->
    <div class="packs-sold-count" id="packs-sold-count">4000 Packs</div>

    <!-- Comparison with Previous Year Dropdown -->
    <div class="packs-comparison-row">
        <span id="packs-sold-percentage" class="packs-comparison">+10% since</span>
        <select id="packs-sold-compare-year" class="packs-sold-dropdown">
            <option value="2024">2024</option>
            <option value="2023">2023</option>
        </select>
    </div>
</div>

        </div>

        <!-- SALES PER DEPARTMENT -->
        <div class="sales-department-container">
            <h3>SALES PER DEPARTMENT</h3>
            <select id="sales-per-department-filter">
                <option value="week" selected>This Week</option>
                <option value="month">This Month</option>
                <option value="year">This Year</option>
            </select>
            <div class="chart-container">
                <canvas id="salesPerDepartmentChart"></canvas>
            </div>
        </div>

    </div>
    </div>

    <!-- External Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> 
    <script src="/top_exchange/public/js/dashboard.js"></script>

</body>
</html>
