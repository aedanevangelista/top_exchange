<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";

// Check if the user is logged in as an admin
if (!isset($_SESSION['admin_user_id'])) {
    // Redirect to admin login page
    header("Location: ../login.php");
    exit();
}

// Check role permission for Dashboard
checkRole('Dashboard');

// Count pending orders
function getPendingOrdersCount($conn) {
    $count = 0;
    $sql = "SELECT COUNT(*) as pending_count FROM orders WHERE status = 'Pending'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $count = $row['pending_count'];
    }
    return $count;
}

// Count rejected orders
function getRejectedOrdersCount($conn) {
    $count = 0;
    $sql = "SELECT COUNT(*) as rejected_count FROM orders WHERE status = 'Rejected'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $count = $row['rejected_count'];
    }
    return $count;
}

// Count active orders
function getActiveOrdersCount($conn) {
    $count = 0;
    $sql = "SELECT COUNT(*) as active_count FROM orders WHERE status = 'Active'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $count = $row['active_count'];
    }
    return $count;
}

// Count for delivery orders
function getForDeliveryOrdersCount($conn) {
    $count = 0;
    $sql = "SELECT COUNT(*) as for_delivery_count FROM orders WHERE status = 'For Delivery'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $count = $row['for_delivery_count'];
    }
    return $count;
}

// Count in transit orders
function getInTransitOrdersCount($conn) {
    $count = 0;
    $sql = "SELECT COUNT(*) as in_transit_count FROM orders WHERE status = 'In Transit'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $count = $row['in_transit_count'];
    }
    return $count;
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
$pendingOrdersCount = getPendingOrdersCount($conn);
$rejectedOrdersCount = getRejectedOrdersCount($conn);
$activeOrdersCount = getActiveOrdersCount($conn);
$forDeliveryOrdersCount = getForDeliveryOrdersCount($conn);
$inTransitOrdersCount = getInTransitOrdersCount($conn);

// Count today's deliveries
$today = date('Y-m-d');
$todayDeliveriesQuery = "SELECT COUNT(*) as today_count FROM orders WHERE status IN ('For Delivery', 'In Transit') AND DATE(delivery_date) = '$today'";
$todayDeliveriesResult = $conn->query($todayDeliveriesQuery);
$todayDeliveriesCount = 0;
if ($todayDeliveriesResult && $todayDeliveriesResult->num_rows > 0) {
    $row = $todayDeliveriesResult->fetch_assoc();
    $todayDeliveriesCount = $row['today_count'];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="/admin/css/dashboard.css">
    <link rel="stylesheet" href="/admin/css/sidebar.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Header container with notification badge */
        .overview-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .notification-badges {
            display: flex;
            gap: 10px;
        }
        
        .notification-badge {
            border-radius: 6px;
            padding: 5px 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .notification-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .notification-badge.pending {
            background-color: #f8d7da;
        }
        
        .notification-badge.rejected {
            background-color: #f8d7da;
        }
        
        .notification-badge.active {
            background-color: #d4edda;
        }
        
        .notification-icon {
            font-size: 16px;
        }
        
        .notification-count {
            font-size: 16px;
            font-weight: bold;
        }
        
        .notification-label {
            font-size: 12px;
        }
        
        .pending .notification-icon, .pending .notification-count, .pending .notification-label {
            color: #721c24;
        }
        
        .rejected .notification-icon, .rejected .notification-count, .rejected .notification-label {
            color: #721c24;
        }
        
        .active .notification-icon, .active .notification-count, .active .notification-label {
            color: #155724;
        }
        
        /* Dashboard sections */
        .dashboard-section {
            margin-bottom: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 15px;
        }
        
        /* Top section layout */
        .top-section {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .top-section > div {
            flex: 1;
        }
        
        /* Deliverable container - important to make it stand out */
        .deliverable-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #fd7e14;
        }
        
        .deliverable-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #fd7e14;
        }
        
        .deliverable-header h3 {
            margin: 0;
            color: #333;
            font-size: 18px;
        }
        
        .view-all {
            padding: 6px 12px;
            background-color: #fd7e14;
            color: white;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        
        .view-all:hover {
            background-color: #e67211;
        }
        
        .deliverable-content {
            display: flex;
            gap: 15px;
        }
        
        .deliverable-stats {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #e9ecef;
            text-align: center;
        }
        
        .stats-label {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .stats-count {
            font-size: 28px;
            font-weight: bold;
        }
        
        .for-delivery .stats-count {
            color: #fd7e14; /* Orange for For Delivery */
        }
        
        .in-transit .stats-count {
            color: #0d6efd; /* Blue for In Transit */
        }
        
        .total-today .stats-count {
            color: #20c997; /* Teal for Today's Total */
        }
        
        .stats-icon {
            margin-bottom: 8px;
            font-size: 24px;
        }
        
        .for-delivery .stats-icon {
            color: #fd7e14;
        }
        
        .in-transit .stats-icon {
            color: #0d6efd;
        }
        
        .total-today .stats-icon {
            color: #20c997;
        }
    </style>
</head>
<body>

    <div class="dashboard-container">
        <?php include '../sidebar.php'; ?>

        <div class="main-content">
            <div class="overview-container">
                <h2>Dashboard</h2>
                
                <!-- Order Status Notification Badges -->
                <div class="notification-badges">
                    <?php if ($pendingOrdersCount > 0): ?>
                    <a href="/admin/public/pages/orders.php?status=Pending" style="text-decoration: none;">
                        <div class="notification-badge pending">
                            <i class="fas fa-clock notification-icon"></i>
                            <span class="notification-count"><?php echo $pendingOrdersCount; ?></span>
                            <span class="notification-label">Pending</span>
                        </div>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($rejectedOrdersCount > 0): ?>
                    <a href="/admin/public/pages/orders.php?status=Rejected" style="text-decoration: none;">
                        <div class="notification-badge rejected">
                            <i class="fas fa-times-circle notification-icon"></i>
                            <span class="notification-count"><?php echo $rejectedOrdersCount; ?></span>
                            <span class="notification-label">Rejected</span>
                        </div>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($activeOrdersCount > 0): ?>
                    <a href="/admin/public/pages/orders.php?status=Active" style="text-decoration: none;">
                        <div class="notification-badge active">
                            <i class="fas fa-check-circle notification-icon"></i>
                            <span class="notification-count"><?php echo $activeOrdersCount; ?></span>
                            <span class="notification-label">Active</span>
                        </div>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- New Deliverable Orders Container - With separated counts -->
            <div class="deliverable-container">
                <div class="deliverable-header">
                    <h3><i class="fas fa-truck"></i> DELIVERABLES</h3>
                    <a href="/admin/public/pages/deliverable_orders.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="deliverable-content">
                    <div class="deliverable-stats for-delivery">
                        <i class="fas fa-box stats-icon"></i>
                        <span class="stats-label">For Delivery</span>
                        <span class="stats-count"><?php echo $forDeliveryOrdersCount; ?></span>
                        <a href="/admin/public/pages/deliverable_orders.php?status=For Delivery" style="margin-top: 10px; font-size: 12px;">View Details</a>
                    </div>
                    <div class="deliverable-stats in-transit">
                        <i class="fas fa-shipping-fast stats-icon"></i>
                        <span class="stats-label">In Transit</span>
                        <span class="stats-count"><?php echo $inTransitOrdersCount; ?></span>
                        <a href="/admin/public/pages/deliverable_orders.php?status=In Transit" style="margin-top: 10px; font-size: 12px;">View Details</a>
                    </div>
                    <div class="deliverable-stats total-today">
                        <i class="fas fa-calendar-day stats-icon"></i>
                        <span class="stats-label">Today's Shipments</span>
                        <span class="stats-count"><?php echo $todayDeliveriesCount; ?></span>
                        <span style="font-size: 12px; color: #6c757d; margin-top: 10px;"><?php echo date('F j, Y'); ?></span>
                    </div>
                </div>
            </div>

            <div class="top-section">
                <div class="client-orders-container dashboard-section">
                    <div class="chart-header">
                        <h3>CLIENT ORDERS</h3>
                        <select id="year-select" class="year-select">
                        </select>
                    </div>
                    <div class="client-orders" style="height: 300px; position: relative;">
                        <canvas id="clientOrdersChart"></canvas>
                    </div>
                </div>

                <div class="packs-sold-container dashboard-section">
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

            <div class="sales-department-container dashboard-section">
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
    <script src="/admin/js/dashboard.js"></script>

</body>
</html>