<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Dashboard');


if (!isset($_SESSION['user_id'])) {
    // Use relative path instead of hardcoded URL
    header("Location: ../login.php");
    exit();
}

function getAvailableYears($conn) {
    $years = array();
    $sql = "SELECT DISTINCT YEAR(order_date) as year FROM orders ORDER BY year DESC";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $years[] = $row['year'];
        }
    }
    return $years;
}

function getClientOrdersCount($conn, $year) {
    $data = array();
    $sql = "SELECT username, COUNT(*) as order_count 
            FROM orders 
            WHERE YEAR(order_date) = ? 
            GROUP BY username";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $data[] = array(
                'username' => $row['username'],
                'count' => $row['order_count']
            );
        }
    }
    return $data;
}

$selectedYear = $_GET['year'] ?? date('Y');
$availableYears = getAvailableYears($conn);
$clientOrders = getClientOrdersCount($conn, $selectedYear);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="/css/dashboard.css">
    <link rel="stylesheet" href="/css/sidebar.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

    <div class="dashboard-container">
        <?php include '../sidebar.php'; ?>

        <div class="main-content">
            <div class="overview-container">
                <h2>Dashboard</h2>
            </div>

            <div class="top-section">
                <div class="client-orders-container">
                    <div class="chart-header">
                        <h3>CLIENT ORDERS</h3>
                        <select id="year-select" class="year-select">
                        </select>
                    </div>
                    <div class="client-orders" style="height: 300px; position: relative;">
                        <canvas id="clientOrdersChart"></canvas>
                    </div>
                </div>

                <div class="packs-sold-container">
                    <div class="packs-sold-header">
                        <span>Orders sold in</span>
                        <select id="packs-sold-year" class="packs-sold-dropdown">
                    
                        </select>
                    </div>

                    <div class="packs-sold-count" id="packs-sold-count">0 Orders</div>

                    <div class="packs-comparison-row">
                        <span id="packs-sold-percentage" class="packs-comparison">0% since</span>
                        <select id="packs-sold-compare-year" class="packs-sold-dropdown">

                        </select>
                    </div>
                </div>

            </div>


            <div class="sales-department-container">
                <div class="chart-header">
                    <h3>SALES PER DEPARTMENT</h3>
                </div>
                <div class="sales-department-chart">
                    <canvas id="salesPerDepartmentChart"></canvas>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> 
    <!-- Use relative path for JavaScript -->
    <script src="/js/dashboard.js"></script>

</body>
</html>