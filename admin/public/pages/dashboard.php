<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";

if (!isset($_SESSION['admin_user_id'])) {
    header("Location: ../login.php");
    exit();
}

checkRole('Dashboard');

// --- PHP Functions (Keep existing ones) ---
function getPendingOrdersCount($conn) {
    $count = 0;
    $sql = "SELECT COUNT(*) as pending_count FROM orders WHERE status = 'Pending'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) { $count = $result->fetch_assoc()['pending_count']; }
    return $count;
}

function getRejectedOrdersCount($conn) {
    $count = 0;
    $sql = "SELECT COUNT(*) as rejected_count FROM orders WHERE status = 'Rejected'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) { $count = $result->fetch_assoc()['rejected_count']; }
    return $count;
}

function getActiveOrdersCount($conn) {
    $count = 0;
    $sql = "SELECT COUNT(*) as active_count FROM orders WHERE status = 'Active'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) { $count = $result->fetch_assoc()['active_count']; }
    return $count;
}

function getDeliverableOrdersCount($conn) {
    $count = 0;
    $sql = "SELECT COUNT(*) as deliverable_count FROM orders WHERE status IN ('For Delivery', 'In Transit')";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) { $count = $result->fetch_assoc()['deliverable_count']; }
    return $count;
}

function getAvailableYears($conn) {
    $years = array();
    $sql = "SELECT DISTINCT YEAR(order_date) as year FROM orders ORDER BY year DESC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) { $years[] = $row['year']; }
    }
    if (empty($years)) { $years[] = date('Y'); }
    return $years;
}

function getDateRangeCondition($period) {
    $currentYear = date('Y');
    $currentMonth = date('m');
    $today = date('Y-m-d');
    switch ($period) {
        case 'this_month': return "YEAR(order_date) = $currentYear AND MONTH(order_date) = $currentMonth";
        case 'this_year': return "YEAR(order_date) = $currentYear";
        case 'today': return "DATE(order_date) = '$today'";
        default: return "1=1";
    }
}

function getTotalRevenue($conn, $period = 'this_month') {
    $totalRevenue = 0;
    $dateCondition = getDateRangeCondition($period);
    $sql = "SELECT SUM(total_amount) as total_revenue FROM orders WHERE status IN ('Active', 'Completed', 'For Delivery', 'In Transit') AND $dateCondition";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $totalRevenue = $row['total_revenue'] ?? 0;
    }
    return $totalRevenue;
}

function getTotalOrdersCount($conn, $period = 'this_month') {
    $count = 0;
    $dateCondition = getDateRangeCondition($period);
    $sql = "SELECT COUNT(*) as total_orders FROM orders WHERE $dateCondition";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $count = $row['total_orders'] ?? 0;
    }
    return $count;
}

function getAverageOrderValue($totalRevenue, $totalOrders) {
    return ($totalOrders > 0) ? $totalRevenue / $totalOrders : 0;
}

function getRecentOrders($conn, $limit = 5) {
    $orders = [];
    $sql = "SELECT o.id, o.po_number, o.order_date, o.status, o.total_amount, ca.username
            FROM orders o
            LEFT JOIN clients_accounts ca ON o.username = ca.username
            ORDER BY o.order_date DESC, o.id DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $orders[] = $row;
            }
        } else { error_log("Error getRecentOrders result: " . $stmt->error); }
        $stmt->close();
    } else { error_log("Error getRecentOrders prepare: " . $conn->error); }
    return $orders;
}


$selectedYear = $_GET['year'] ?? date('Y');
$availableYears = getAvailableYears($conn);
$pendingOrdersCount = getPendingOrdersCount($conn);
$rejectedOrdersCount = getRejectedOrdersCount($conn);
$activeOrdersCount = getActiveOrdersCount($conn);
$deliverableOrdersCount = getDeliverableOrdersCount($conn);
$kpiPeriod = 'this_month';
$totalRevenueThisMonth = getTotalRevenue($conn, $kpiPeriod);
$totalOrdersThisMonth = getTotalOrdersCount($conn, $kpiPeriod);
$averageOrderValueThisMonth = getAverageOrderValue($totalRevenueThisMonth, $totalOrdersThisMonth);
$recentOrders = getRecentOrders($conn, 5);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="/css/sidebar.css"> <!-- Your sidebar CSS -->
    <link rel="stylesheet" href="/css/dashboard.css"> <!-- Your dashboard CSS -->

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* --- Base & Layout --- */
        body {
            background-color: #f8f9fa;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
            color: #212529;
            line-height: 1.5;
        }
        .main-content {
            padding: 20px;
        }
        .overview-container h2 {
            margin-bottom: 1.5rem;
            font-size: 1.75rem;
            font-weight: 500;
        }
        .notification-badges {
            margin-bottom: 1.5rem;
        }
        .data-container {
            background-color: #fff;
            padding: 1rem;
            border-radius: 0.375rem;
            border: 1px solid #dee2e6;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        .container-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        .container-header h3 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 600;
            color: #495057;
        }

        /* --- KPI Cards --- */
        .kpi-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .kpi-card {
            background-color: #fff;
            padding: 1rem 1.25rem;
            border-radius: 0.375rem;
            border: 1px solid #dee2e6;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            display: flex;
            align-items: center;
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .kpi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }
        .kpi-icon {
            font-size: 1.5rem;
            padding: 0.7rem;
            border-radius: 50%;
            margin-right: 0.8rem;
            color: #fff;
            width: 48px;
            height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .kpi-icon.icon-revenue { background-color: rgba(13, 110, 253, 0.2); color: #0d6efd; }
        .kpi-icon.icon-orders { background-color: rgba(25, 135, 84, 0.2); color: #198754; }
        .kpi-icon.icon-aov { background-color: rgba(255, 193, 7, 0.2); color: #ffc107; }

        .kpi-content h4 {
            margin: 0 0 0.2rem 0;
            font-size: 0.8rem;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
            white-space: nowrap;
        }
        .kpi-content .kpi-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #212529;
            margin: 0;
            line-height: 1.2;
        }

        /* --- Recent Orders --- */
        .view-all-button {
            display: inline-block;
            font-weight: 500;
            line-height: 1.5;
            color: #ffffff;
            background-color: #0d6efd;
            border: 1px solid #0d6efd;
            text-align: center;
            text-decoration: none;
            vertical-align: middle;
            cursor: pointer;
            user-select: none;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.25rem;
            transition: background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        .view-all-button:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
        .view-all-button i {
            margin-right: 0.3rem;
        }
        /* Table Styling */
        .table-wrapper {
             overflow-x: auto;
        }
        .recent-orders-table {
            width: 100%;
            margin-bottom: 1rem;
            color: #212529;
            vertical-align: middle;
            border-color: #dee2e6;
            border-collapse: collapse;
        }
        .recent-orders-table thead {
            background-color: #000000;
            color: #ffffff;
        }
        .recent-orders-table th {
            padding: 0.5rem 0.75rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            white-space: nowrap;
            border: 1px solid #454d55;
            border-bottom-width: 2px;
        }
        .recent-orders-table td {
            padding: 0.6rem 0.75rem;
            border: none;
            border-bottom: 1px solid #dee2e6;
            font-size: 0.875rem;
            text-align: left;
        }
        .recent-orders-table tbody tr:last-child td {
            border-bottom: none;
        }
        .recent-orders-table tbody tr:hover {
            background-color: #f1f1f1;
        }
        .no-orders-message {
             text-align: center;
             padding: 1rem;
             color: #6c757d;
             background-color: #f8f9fa;
             border: 1px solid #dee2e6;
             border-radius: 0.375rem;
        }

        /* --- Chart/Stats Section --- */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .client-orders-card .stat-card-content {
             height: 300px;
             position: relative;
        }
        .packs-sold-card {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 300px;
        }
        .chart-header, .packs-sold-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            width: 100%;
        }
        .packs-comparison-row {
             display: flex;
             justify-content: center;
             align-items: center;
             width: 100%;
             margin-top: 0.5rem;
        }
        .data-container h3 {
            font-size: 0.9rem;
            font-weight: 600;
            margin: 0;
            text-transform: uppercase;
            color: #495057;
        }
        .year-select, .packs-sold-dropdown {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            border: 1px solid #ced4da;
            font-size: 0.875rem;
            background-color: #fff;
            max-width: 100px;
        }
        .packs-sold-count {
            font-size: 1.8rem;
            font-weight: 700;
            text-align: center;
            margin-top: 0.5rem;
            margin-bottom: 0.5rem;
            color: #212529;
        }
        .packs-comparison {
             font-size: 0.9rem;
             color: #6c757d;
             margin-right: 0.5rem;
        }
        .packs-comparison.positive { color: #198754; }
        .packs-comparison.negative { color: #dc3545; }

        /* Sales Dept Chart */
        .sales-department-container .stat-card-content {
             height: 300px;
             position: relative;
        }
        .time-period-tabs .time-period-tab {
             background: none;
             border: none;
             padding: 0.5rem 1rem;
             color: #6c757d;
             font-weight: 500;
             border-bottom: 2px solid transparent;
             cursor: pointer;
        }
        .time-period-tabs .time-period-tab.active {
             color: #0d6efd;
             border-bottom-color: #0d6efd;
        }

        /* --- Status Badges --- */
        .status-badge { padding: 0.25em 0.65em; border-radius: 50rem; font-size: 0.75em; font-weight: 600; display: inline-block; white-space: nowrap; vertical-align: baseline; line-height: 1; }
        .status-pending { background-color: #ffc107; color: #fff;} /* White text */
        .status-active { background-color: #198754; color: #fff; } /* Green */
        .status-completed { background-color: #198754; color: #fff; } /* Green */
        /* Removed .status-delivered */
        .status-rejected { background-color: #dc3545; color: #fff; } /* Red */
        .status-for-delivery { background-color: #0dcaf0; color: #fff; } /* Cyan, White text */
        .status-in-transit { background-color: #fd7e14; color: #fff; } /* Orange, White text */

    </style>
</head>
<body>

    <?php include '../sidebar.php'; ?>

    <div class="main-content">
        <div class="overview-container">
            <h2>Dashboard</h2>
            <div class="notification-badges">
                 <?php if ($pendingOrdersCount > 0): ?><a href="/public/pages/orders.php?status=Pending" class="notification-badge pending"><i class="fas fa-clock notification-icon"></i><span class="notification-count"><?php echo $pendingOrdersCount; ?></span><span class="notification-label">Pending</span></a><?php endif; ?>
                 <?php if ($rejectedOrdersCount > 0): ?><a href="/public/pages/orders.php?status=Rejected" class="notification-badge rejected"><i class="fas fa-times-circle notification-icon"></i><span class="notification-count"><?php echo $rejectedOrdersCount; ?></span><span class="notification-label">Rejected</span></a><?php endif; ?>
                 <?php if ($activeOrdersCount > 0): ?><a href="/public/pages/orders.php?status=Active" class="notification-badge active"><i class="fas fa-check-circle notification-icon"></i><span class="notification-count"><?php echo $activeOrdersCount; ?></span><span class="notification-label">Active</span></a><?php endif; ?>
                 <?php if ($deliverableOrdersCount > 0): ?><a href="/public/pages/deliverable_orders.php" class="notification-badge deliverable"><i class="fas fa-truck notification-icon"></i><span class="notification-count"><?php echo $deliverableOrdersCount; ?></span><span class="notification-label">Deliverables</span></a><?php endif; ?>
            </div>
        </div>

        <div class="kpi-container">
            <div class="kpi-card">
                <span class="kpi-icon icon-revenue"><i class="fas fa-dollar-sign"></i></span>
                <div class="kpi-content">
                    <h4>Revenue (This Month)</h4>
                    <p class="kpi-value">₱<?php echo number_format($totalRevenueThisMonth, 2); ?></p>
                </div>
            </div>
            <div class="kpi-card">
                <span class="kpi-icon icon-orders"><i class="fas fa-shopping-cart"></i></span>
                <div class="kpi-content">
                    <h4>Total Orders (This Month)</h4>
                    <p class="kpi-value"><?php echo number_format($totalOrdersThisMonth); ?></p>
                </div>
            </div>
            <div class="kpi-card">
                 <span class="kpi-icon icon-aov"><i class="fas fa-chart-line"></i></span>
                <div class="kpi-content">
                    <h4>Avg. Order Value (This Month)</h4>
                    <p class="kpi-value">₱<?php echo number_format($averageOrderValueThisMonth, 2); ?></p>
                </div>
            </div>
        </div>

        <div class="recent-orders-container data-container">
            <div class="container-header">
                <h3>Recent Orders</h3>
                <a href="/public/pages/orders.php" class="view-all-button">
                    <i class="fas fa-list"></i> View All Orders
                </a>
            </div>
            <?php if (!empty($recentOrders)): ?>
                <div class="table-wrapper">
                    <table class="recent-orders-table">
                        <thead>
                            <tr>
                                <th>PO Number</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Status</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order):
                                $statusDisplay = htmlspecialchars($order['status'] ?? 'Unknown');
                                // Create CSS class - replace space with hyphen, make lowercase
                                $statusClass = str_replace(' ', '-', strtolower($statusDisplay));
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['po_number'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($order['order_date']))); ?></td>
                                    <td><?php echo htmlspecialchars($order['username'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $statusClass; ?>" data-status="<?php echo htmlspecialchars($statusDisplay); ?>">
                                            <?php echo $statusDisplay; ?>
                                        </span>
                                    </td>
                                    <td>₱<?php echo number_format($order['total_amount'] ?? 0, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-orders-message">
                    No recent orders found.
                </div>
            <?php endif; ?>
        </div>

        <div class="stats-container">
             <!-- Client Orders Chart -->
            <div class="client-orders-card data-container">
                <div class="chart-header"><h3>CLIENT ORDERS (<?php echo htmlspecialchars($selectedYear); ?>)</h3><select id="year-select" class="year-select"><?php foreach($availableYears as $year): ?><option value="<?php echo htmlspecialchars($year); ?>" <?php echo ($year == $selectedYear) ? 'selected' : ''; ?>><?php echo htmlspecialchars($year); ?></option><?php endforeach; ?></select></div>
                <div class="stat-card-content"><canvas id="clientOrdersChart"></canvas></div>
            </div>
             <!-- Orders Sold Card (Centered Content) -->
            <div class="packs-sold-card data-container">
                 <div class="packs-sold-header"><span>Orders sold in</span><select id="packs-sold-year" class="packs-sold-dropdown"><?php foreach($availableYears as $year): ?><option value="<?php echo htmlspecialchars($year); ?>" <?php echo ($year == ($availableYears[0] ?? date('Y'))) ? 'selected' : ''; ?>><?php echo htmlspecialchars($year); ?></option><?php endforeach; ?></select></div>
                 <div class="packs-sold-count" id="packs-sold-count">Loading...</div>
                 <div class="packs-comparison-row"><span id="packs-sold-percentage" class="packs-comparison">N/A since</span><select id="packs-sold-compare-year" class="packs-sold-dropdown"><?php $compareYearDefault = count($availableYears) > 1 ? $availableYears[1] : ($availableYears[0] ?? date('Y')); foreach($availableYears as $year): ?><option value="<?php echo htmlspecialchars($year); ?>" <?php echo ($year == $compareYearDefault) ? 'selected' : ''; ?>><?php echo htmlspecialchars($year); ?></option><?php endforeach; ?></select></div>
            </div>
        </div>
         <!-- Sales Dept Chart -->
        <div class="sales-department-container data-container">
            <div class="container-header">
                <h3>SALES PER DEPARTMENT</h3>
                <div class="time-period-tabs">
                    <button class="time-period-tab active" data-period="weekly">Weekly</button>
                    <button class="time-period-tab" data-period="monthly">Monthly</button>
                </div>
            </div>
            <div class="stat-card-content"><canvas id="salesPerDepartmentChart"></canvas></div>
        </div>

    </div>

    <!-- No Bootstrap JS needed -->
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        console.log("Dashboard JS Initializing...");

        // --- Chart Initialization and AJAX calls (Unchanged) ---
        const chartColors = [
            'rgba(69, 160, 73, 0.85)', 'rgba(71, 120, 209, 0.85)', 'rgba(235, 137, 49, 0.85)',
            'rgba(165, 84, 184, 0.85)', 'rgba(214, 68, 68, 0.85)', 'rgba(60, 179, 163, 0.85)',
            'rgba(201, 151, 63, 0.85)', 'rgba(86, 120, 141, 0.85)', 'rgba(182, 73, 141, 0.85)',
            'rgba(110, 146, 64, 0.85)', 'rgba(149, 82, 81, 0.85)', 'rgba(95, 61, 150, 0.85)',
            'rgba(170, 166, 57, 0.85)', 'rgba(87, 144, 176, 0.85)', 'rgba(192, 88, 116, 0.85)',
            'rgba(85, 156, 110, 0.85)', 'rgba(161, 88, 192, 0.85)', 'rgba(169, 106, 76, 0.85)',
            'rgba(78, 130, 162, 0.85)', 'rgba(190, 117, 50, 0.85)', 'rgba(111, 83, 150, 0.85)',
            'rgba(158, 126, 74, 0.85)', 'rgba(92, 153, 123, 0.85)', 'rgba(173, 76, 104, 0.85)',
            'rgba(67, 134, 147, 0.85)', 'rgba(187, 96, 68, 0.85)', 'rgba(124, 110, 163, 0.85)',
            'rgba(146, 134, 54, 0.85)', 'rgba(82, 137, 110, 0.85)', 'rgba(155, 89, 136, 0.85)'
        ];
        let clientOrdersChart = null;
        const ctxClientOrders = document.getElementById('clientOrdersChart');

        function initializeClientOrdersChart(data) {
            if (!ctxClientOrders) { console.error("Client Orders Chart canvas not found"); return; }
            const chartContext = ctxClientOrders.getContext('2d');
            const labels = Array.isArray(data) ? data.map(item => item.username || 'Unknown') : [];
            const values = Array.isArray(data) ? data.map(item => item.count || 0) : [];
            const colors = Array.isArray(data) ? data.map((_, index) => chartColors[index % chartColors.length]) : [];

            if (clientOrdersChart) { clientOrdersChart.destroy(); }

            if (labels.length === 0) {
                 chartContext.clearRect(0, 0, ctxClientOrders.width, ctxClientOrders.height);
                 chartContext.fillStyle = '#6c757d'; chartContext.textAlign = 'center';
                 chartContext.fillText('No client order data available for this year.', ctxClientOrders.width / 2, ctxClientOrders.height / 2);
                 return;
            }

            clientOrdersChart = new Chart(chartContext, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{ data: values, backgroundColor: colors, borderColor: colors.map(c => c.replace('0.85)', '1)')), borderWidth: 1 }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right', labels: { boxWidth: 15, padding: 15 } },
                        title: { display: false },
                        tooltip: { callbacks: { label: (c) => `${c.label || ''}: ${c.parsed || 0}` } }
                    }
                }
            });
        }

        function loadClientOrders(year) {
            const url = `../../backend/get_client_orders.php?year=${year}`;
            console.log("Fetching client orders from:", url);
            if (ctxClientOrders) {
                 const ctx = ctxClientOrders.getContext('2d'); ctx.clearRect(0, 0, ctxClientOrders.width, ctxClientOrders.height);
                 ctx.fillStyle = '#6c757d'; ctx.textAlign = 'center'; ctx.fillText('Loading...', ctxClientOrders.width / 2, ctxClientOrders.height / 2);
            }
            fetch(url)
                .then(response => response.ok ? response.json() : Promise.reject(`HTTP error! Status: ${response.status}`))
                .then(data => { console.log("Client orders data:", data); initializeClientOrdersChart(data.error ? [] : data); if(data.error) console.error("Server error:", data.message); })
                .catch(error => {
                     console.error('Error loading client orders:', error);
                     if (ctxClientOrders) {
                         const ctx = ctxClientOrders.getContext('2d'); ctx.clearRect(0, 0, ctxClientOrders.width, ctxClientOrders.height);
                         ctx.fillStyle = '#dc3545'; ctx.textAlign = 'center'; ctx.fillText('Error loading chart data.', ctxClientOrders.width / 2, ctxClientOrders.height / 2);
                     }
                 });
        }

        const yearSelect = document.getElementById('year-select');
        if (yearSelect) {
            yearSelect.addEventListener('change', function() {
                loadClientOrders(this.value);
                const h = document.querySelector('.client-orders-card .chart-header h3'); if(h) h.textContent = `CLIENT ORDERS (${this.value})`;
            });
            if (yearSelect.value) loadClientOrders(yearSelect.value);
        } else { console.error("Year select dropdown ('year-select') not found"); }

        const ordersSoldYearSelect = document.getElementById("packs-sold-year");
        const ordersSoldCompareYearSelect = document.getElementById("packs-sold-compare-year");
        const ordersSoldCountEl = document.getElementById("packs-sold-count");
        const ordersSoldPercentageEl = document.getElementById("packs-sold-percentage");

        function getOrderCounts(year) {
             if (!year) return Promise.resolve(0);
            const url = `../../backend/get_order_counts.php?year=${year}`;
            console.log("Fetching order counts from:", url);
            return fetch(url)
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                    const contentType = response.headers.get("content-type");
                    return (contentType && contentType.includes("application/json"))
                        ? response.json()
                        : response.text().then(text => { console.warn(`Non-JSON response (${year}):`, text); return Number(text) || 0; });
                })
                .then(data => {
                     console.log(`Order count for ${year}:`, data);
                     let count = (typeof data === 'number') ? data : (data?.count ?? 0);
                     return isNaN(count) ? 0 : count;
                })
                .catch(error => { console.error(`Error fetching counts (${year}):`, error); return 0; });
        }

        async function updateOrdersSold() {
             if (!ordersSoldYearSelect || !ordersSoldCompareYearSelect || !ordersSoldCountEl || !ordersSoldPercentageEl) {
                 console.error("Missing Orders Sold elements."); return;
             }
            const selectedYear = ordersSoldYearSelect.value; const compareYear = ordersSoldCompareYearSelect.value;
            ordersSoldCountEl.textContent = 'Loading...'; ordersSoldPercentageEl.textContent = 'Calculating...';
            ordersSoldPercentageEl.className = 'packs-comparison';

            try {
                const [currentOrders, previousOrders] = await Promise.all([ getOrderCounts(selectedYear), getOrderCounts(compareYear) ]);
                ordersSoldCountEl.textContent = `${currentOrders} Orders`;
                if (selectedYear === compareYear) { ordersSoldPercentageEl.textContent = `Comparing same year`; }
                else if (previousOrders > 0) {
                    const change = (((currentOrders - previousOrders) / previousOrders) * 100);
                    ordersSoldPercentageEl.textContent = `${change >= 0 ? "+" : ""}${change.toFixed(1)}% since`;
                    ordersSoldPercentageEl.className = `packs-comparison ${change >= 0 ? 'positive' : 'negative'}`;
                } else if (currentOrders > 0) {
                    ordersSoldPercentageEl.textContent = `+${currentOrders} orders (prev 0)`; ordersSoldPercentageEl.className = 'packs-comparison positive';
                } else { ordersSoldPercentageEl.textContent = "N/A since"; }
            } catch (error) {
                 console.error("Error updating orders sold:", error);
                 ordersSoldCountEl.textContent = `Error`; ordersSoldPercentageEl.textContent = "Error";
                 ordersSoldPercentageEl.className = 'packs-comparison negative';
            }
        }

        if (ordersSoldYearSelect) ordersSoldYearSelect.addEventListener("change", updateOrdersSold);
        if (ordersSoldCompareYearSelect) ordersSoldCompareYearSelect.addEventListener("change", updateOrdersSold);
        if (ordersSoldYearSelect && ordersSoldCompareYearSelect && ordersSoldCountEl && ordersSoldPercentageEl) { updateOrdersSold(); }

        const ctxSalesPerDepartment = document.getElementById("salesPerDepartmentChart");
        let salesPerDepartmentChart = null;
        let currentTimePeriod = 'weekly';

        function loadSalesByCategory(timePeriod) {
            if (!ctxSalesPerDepartment) { console.error("Sales chart canvas not found"); return; }
            const ctx = ctxSalesPerDepartment.getContext('2d');
            const url = `../../backend/get_sales_by_category.php?period=${timePeriod}`;
            console.log(`Fetching ${timePeriod} sales data:`, url);
            ctx.clearRect(0, 0, ctxSalesPerDepartment.width, ctxSalesPerDepartment.height);
            ctx.fillStyle = '#6c757d'; ctx.textAlign = 'center'; ctx.fillText(`Loading ${timePeriod} data...`, ctxSalesPerDepartment.width / 2, ctxSalesPerDepartment.height / 2);

            fetch(url)
                .then(response => response.ok ? response.text() : Promise.reject(`HTTP error! Status: ${response.status}`))
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.error) throw new Error(`Server Error: ${data.message || 'Unknown'}`);
                        if (!data || !Array.isArray(data.categories) || !data.currentYear || !data.lastYear) throw new Error("Invalid data structure");
                        if (salesPerDepartmentChart) salesPerDepartmentChart.destroy();

                        if (data.categories.length === 0) {
                             ctx.clearRect(0, 0, ctxSalesPerDepartment.width, ctxSalesPerDepartment.height);
                             ctx.fillStyle = '#6c757d'; ctx.textAlign = 'center'; ctx.fillText(`No sales data available.`, ctxSalesPerDepartment.width / 2, ctxSalesPerDepartment.height / 2);
                             return;
                         }

                        salesPerDepartmentChart = new Chart(ctx, {
                            type: "bar",
                            data: {
                                labels: data.categories,
                                datasets: [
                                    { label: `${data.currentYear.year || 'Current'} Sales`, data: data.currentYear.data || [], backgroundColor: "rgba(74, 144, 226, 0.8)", borderColor: "rgba(74, 144, 226, 1)", borderWidth: 1, borderRadius: 4 },
                                    { label: `${data.lastYear.year || 'Previous'} Sales`, data: data.lastYear.data || [], backgroundColor: "rgba(155, 155, 155, 0.7)", borderColor: "rgba(155, 155, 155, 1)", borderWidth: 1, borderRadius: 4 }
                                ]
                            },
                            options: {
                                responsive: true, maintainAspectRatio: false,
                                plugins: { legend: { position: 'top' }, title: { display: false }, tooltip: { mode: 'index', intersect: false } },
                                scales: { y: { beginAtZero: true, title: { display: true, text: 'Number of Orders' } }, x: { title: { display: true, text: 'Department / Category' }, ticks: { autoSkip: false } } }
                            }
                        });
                    } catch (e) {
                        console.error(`Error processing ${timePeriod} sales:`, e, "Raw:", text);
                         ctx.clearRect(0, 0, ctxSalesPerDepartment.width, ctxSalesPerDepartment.height);
                         ctx.fillStyle = '#dc3545'; ctx.textAlign = 'center'; ctx.fillText('Error loading chart data.', ctxSalesPerDepartment.width / 2, ctxSalesPerDepartment.height / 2);
                    }
                })
                .catch(error => {
                     console.error(`Error fetching ${timePeriod} sales:`, error);
                     if (ctxSalesPerDepartment) {
                          const ctx = ctxSalesPerDepartment.getContext('2d'); ctx.clearRect(0, 0, ctxSalesPerDepartment.width, ctxSalesPerDepartment.height);
                          ctx.fillStyle = '#dc3545'; ctx.textAlign = 'center'; ctx.fillText('Error loading chart data.', ctxSalesPerDepartment.width / 2, ctxSalesPerDepartment.height / 2);
                     }
                });
        }

        const timePeriodTabs = document.querySelectorAll('.time-period-tab');
        if (timePeriodTabs.length > 0) {
            timePeriodTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    timePeriodTabs.forEach(t => t.classList.remove('active')); this.classList.add('active');
                    currentTimePeriod = this.getAttribute('data-period'); loadSalesByCategory(currentTimePeriod);
                });
            });
        } else { console.warn("Time period tabs not found."); }

        if (ctxSalesPerDepartment) {
             const activeTab = document.querySelector('.time-period-tab.active');
             if (activeTab) currentTimePeriod = activeTab.getAttribute('data-period');
             loadSalesByCategory(currentTimePeriod);
        } else { console.error("Sales chart canvas not found."); }

        console.log("Dashboard JS Fully Initialized.");
    });
    </script>

</body>
</html>