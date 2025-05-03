<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";

if (!isset($_SESSION['admin_user_id'])) {
    header("Location: ../login.php");
    exit();
}

checkRole('Dashboard');

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
    $sql = "SELECT SUM(total_amount) as total_revenue FROM orders WHERE status IN ('Active', 'Completed', 'Delivered', 'For Delivery', 'In Transit') AND $dateCondition";
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
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="/css/dashboard.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .kpi-container { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .kpi-card { background-color: #fff; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); flex: 1; min-width: 180px; text-align: center; }
        .kpi-card h4 { margin: 0 0 5px 0; font-size: 0.9em; color: #555; text-transform: uppercase; }
        .kpi-card .kpi-value { font-size: 1.8em; font-weight: bold; color: #333; margin: 0; }

        .recent-orders-container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .recent-orders-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .recent-orders-header h3 { margin: 0; }
        .recent-orders-table { width: 100%; border-collapse: collapse; }
        .recent-orders-table th, .recent-orders-table td { text-align: left; padding: 8px 12px; border-bottom: 1px solid #eee; font-size: 0.9em; vertical-align: middle; }
        .recent-orders-table th { background-color: #f8f9fa; font-weight: 600; }
        .recent-orders-table tr:last-child td { border-bottom: none; }
        .recent-orders-table td a { color: #0d6efd; text-decoration: none; }
        .recent-orders-table td a:hover { text-decoration: underline; }
        .recent-orders-table tbody tr:hover { background-color: #f8f9fa; }

        .status-badge { padding: 3px 8px; border-radius: 12px; font-size: 0.8em; color: #fff; display: inline-block; white-space: nowrap; }
        .status-Pending { background-color: #ffc107; color: #333;}
        .status-Active, .status-Completed { background-color: #198754; }
        .status-Delivered { background-color: #0d6efd; }
        .status-Rejected { background-color: #dc3545; }
        .status-Cancelled { background-color: #6c757d; }
        .status-For.Delivery { background-color: #0dcaf0; }
        .status-In.Transit { background-color: #fd7e14; }

        .main-content > .dashboard-section, .main-content > .stats-container, .main-content > .kpi-container, .main-content > .overview-container, .main-content > .recent-orders-container { margin-bottom: 25px; }
        .stats-container { display: flex; gap: 15px; flex-wrap: wrap; }
        .stat-card { flex: 1; min-width: 300px; }

        #orderDetailsModalTable { margin-top: 15px; }
        #orderDetailsModalTable th { background-color: #f8f9fa; }
        #modalLoadingIndicator { padding: 2rem 0; }
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
            <div class="kpi-card"><h4>Revenue (This Month)</h4><p class="kpi-value">₱<?php echo number_format($totalRevenueThisMonth, 2); ?></p></div>
            <div class="kpi-card"><h4>Total Orders (This Month)</h4><p class="kpi-value"><?php echo number_format($totalOrdersThisMonth); ?></p></div>
            <div class="kpi-card"><h4>Avg. Order Value (This Month)</h4><p class="kpi-value">₱<?php echo number_format($averageOrderValueThisMonth, 2); ?></p></div>
        </div>

        <div class="recent-orders-container">
            <div class="recent-orders-header">
                <h3>Recent Orders</h3>
                <a href="/public/pages/orders.php" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-list"></i> View All Orders
                </a>
            </div>
            <?php if (!empty($recentOrders)): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm recent-orders-table">
                        <thead>
                            <tr>
                                <th>PO Number</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Status</th>
                                <th style="text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order):
                                $statusDisplay = htmlspecialchars($order['status'] ?? 'Unknown');
                                $statusClass = str_replace(' ', '.', $statusDisplay);
                            ?>
                                <tr>
                                    <td>
                                        <a href="#"
                                           data-bs-toggle="modal"
                                           data-bs-target="#orderDetailsModal"
                                           data-order-id="<?php echo htmlspecialchars($order['id']); ?>"
                                           title="View Details for PO <?php echo htmlspecialchars($order['po_number'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($order['po_number'] ?? 'N/A'); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($order['order_date']))); ?></td>
                                    <td><?php echo htmlspecialchars($order['username'] ?? 'N/A'); ?></td>
                                    <td><span class="status-badge status-<?php echo $statusClass; ?>"><?php echo $statusDisplay; ?></span></td>
                                    <td style="text-align: right;">₱<?php echo number_format($order['total_amount'] ?? 0, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No recent orders found.</p>
            <?php endif; ?>
        </div>

        <div class="stats-container">
            <div class="stat-card client-orders-card">
                <div class="chart-header"><h3>CLIENT ORDERS (<?php echo htmlspecialchars($selectedYear); ?>)</h3><select id="year-select" class="year-select"><?php foreach($availableYears as $year): ?><option value="<?php echo htmlspecialchars($year); ?>" <?php echo ($year == $selectedYear) ? 'selected' : ''; ?>><?php echo htmlspecialchars($year); ?></option><?php endforeach; ?></select></div>
                <div class="stat-card-content"><canvas id="clientOrdersChart"></canvas></div>
            </div>
            <div class="stat-card packs-sold-card">
                 <div class="packs-sold-header"><span>Orders sold in</span><select id="packs-sold-year" class="packs-sold-dropdown"><?php foreach($availableYears as $year): ?><option value="<?php echo htmlspecialchars($year); ?>" <?php echo ($year == ($availableYears[0] ?? date('Y'))) ? 'selected' : ''; ?>><?php echo htmlspecialchars($year); ?></option><?php endforeach; ?></select></div>
                 <div class="packs-sold-count" id="packs-sold-count">Loading...</div>
                 <div class="packs-comparison-row"><span id="packs-sold-percentage" class="packs-comparison">N/A since</span><select id="packs-sold-compare-year" class="packs-sold-dropdown"><?php $compareYearDefault = count($availableYears) > 1 ? $availableYears[1] : ($availableYears[0] ?? date('Y')); foreach($availableYears as $year): ?><option value="<?php echo htmlspecialchars($year); ?>" <?php echo ($year == $compareYearDefault) ? 'selected' : ''; ?>><?php echo htmlspecialchars($year); ?></option><?php endforeach; ?></select></div>
            </div>
        </div>
        <div class="dashboard-section sales-department-container">
            <div class="chart-header"><h3>SALES PER DEPARTMENT</h3><div class="time-period-tabs"><button class="time-period-tab active" data-period="weekly">Weekly</button><button class="time-period-tab" data-period="monthly">Monthly</button></div></div>
            <div class="stat-card-content"><canvas id="salesPerDepartmentChart"></canvas></div>
        </div>

    </div>

    <div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="orderDetailsModalLabel">Order Details</h5>
                    <a href="#" id="downloadPoBtn" class="btn btn-sm btn-outline-success ms-auto" target="_blank" download>
                        <i class="fas fa-download"></i> Download PO
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="modalOrderDetailsContent">
                        <div class="text-center" id="modalLoadingIndicator">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                        <div id="modalOrderData" style="display: none;">
                            <p><strong>PO Number:</strong> <span id="modalPoNumber"></span></p>
                            <p><strong>Customer:</strong> <span id="modalCustomer"></span></p>
                            <p><strong>Order Date:</strong> <span id="modalOrderDate"></span></p>
                            <p><strong>Delivery Date:</strong> <span id="modalDeliveryDate"></span></p>
                            <p><strong>Status:</strong> <span id="modalStatus"></span></p>
                            <p><strong>Delivery Address:</strong> <span id="modalDeliveryAddress"></span></p>
                            <p><strong>Contact:</strong> <span id="modalContact"></span></p>
                            <p><strong>Special Instructions:</strong> <span id="modalInstructions"></span></p>

                            <h5>Order Items</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered" id="orderDetailsModalTable">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Item</th>
                                            <th>Packaging</th>
                                            <th>Price</th>
                                            <th>Qty</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="modalOrderItemsTbody">
                                    </tbody>
                                </table>
                            </div>
                             <p class="text-end mt-2"><strong>Subtotal:</strong> <span id="modalSubtotal"></span></p>
                            <p class="text-end"><strong>Total Amount:</strong> <span id="modalTotalAmount"></span></p>
                        </div>
                        <div id="modalError" class="alert alert-danger" style="display: none;"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        console.log("Dashboard JS Initializing...");

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
            const url = `/backend/get_client_orders.php?year=${year}`;
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
            const url = `/backend/get_order_counts.php?year=${year}`;
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
            const url = `/backend/get_sales_by_category.php?period=${timePeriod}`;
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


        const orderDetailsModal = document.getElementById('orderDetailsModal');
        const modalContent = document.getElementById('modalOrderDetailsContent');
        const modalLoading = document.getElementById('modalLoadingIndicator');
        const modalData = document.getElementById('modalOrderData');
        const modalError = document.getElementById('modalError');
        const modalPoNumber = document.getElementById('modalPoNumber');
        const modalCustomer = document.getElementById('modalCustomer');
        const modalOrderDate = document.getElementById('modalOrderDate');
        const modalDeliveryDate = document.getElementById('modalDeliveryDate');
        const modalStatus = document.getElementById('modalStatus');
        const modalDeliveryAddress = document.getElementById('modalDeliveryAddress');
        const modalContact = document.getElementById('modalContact');
        const modalInstructions = document.getElementById('modalInstructions');
        const modalOrderItemsTbody = document.getElementById('modalOrderItemsTbody');
        const modalSubtotal = document.getElementById('modalSubtotal');
        const modalTotalAmount = document.getElementById('modalTotalAmount');
        const downloadPoBtn = document.getElementById('downloadPoBtn');

        if (orderDetailsModal) {
            orderDetailsModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const orderId = button.getAttribute('data-order-id');

                modalLoading.style.display = 'block';
                modalData.style.display = 'none';
                modalError.style.display = 'none';
                modalError.textContent = '';
                modalOrderItemsTbody.innerHTML = '';
                downloadPoBtn.href = '#';
                downloadPoBtn.removeAttribute('download');

                const backendUrl = `/backend/get_order_details_for_modal.php?id=${orderId}`;
                console.log("Fetching details from:", backendUrl);

                fetch(backendUrl)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`Network response was not ok: ${response.statusText}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log("Modal data received:", data);
                        if (data.error) {
                            throw new Error(data.error);
                        }
                        if (!data.details) {
                             throw new Error("Order details not found in response.");
                        }

                        const details = data.details;
                        const items = data.items || [];

                        modalPoNumber.textContent = details.po_number || 'N/A';
                        modalCustomer.textContent = details.username || 'N/A';
                        modalOrderDate.textContent = details.order_date ? new Date(details.order_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';
                        modalDeliveryDate.textContent = details.delivery_date ? new Date(details.delivery_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';
                        modalStatus.textContent = details.status || 'N/A';
                        modalDeliveryAddress.textContent = details.delivery_address || 'N/A';
                        modalContact.textContent = details.contact_number || 'N/A';
                        modalInstructions.textContent = details.special_instructions || 'None';

                        modalOrderItemsTbody.innerHTML = '';
                        if (items.length > 0) {
                            items.forEach(item => {
                                const row = modalOrderItemsTbody.insertRow();
                                row.insertCell().textContent = item.category || 'N/A';
                                row.insertCell().textContent = item.item_description || 'N/A';
                                row.insertCell().textContent = item.packaging || 'N/A';
                                row.insertCell().textContent = `₱${parseFloat(item.price || 0).toFixed(2)}`;
                                row.insertCell().textContent = item.quantity || 0;
                                row.insertCell().textContent = `₱${parseFloat(item.total || 0).toFixed(2)}`;
                            });
                        } else {
                             const row = modalOrderItemsTbody.insertRow();
                             const cell = row.insertCell();
                             cell.colSpan = 6;
                             cell.textContent = 'No items found for this order.';
                             cell.style.textAlign = 'center';
                        }

                        modalSubtotal.textContent = `₱${parseFloat(details.subtotal || 0).toFixed(2)}`;
                        modalTotalAmount.textContent = `₱${parseFloat(details.total_amount || 0).toFixed(2)}`;

                        if (details.po_number) {
                             downloadPoBtn.href = `/backend/download_po.php?po_number=${encodeURIComponent(details.po_number)}`;
                             downloadPoBtn.download = `PO_${details.po_number}.pdf`;
                        }

                        modalLoading.style.display = 'none';
                        modalData.style.display = 'block';

                    })
                    .catch(error => {
                        console.error('Error fetching order details for modal:', error);
                        modalLoading.style.display = 'none';
                        modalData.style.display = 'none';
                        modalError.textContent = `Error loading order details: ${error.message}`;
                        modalError.style.display = 'block';
                        downloadPoBtn.href = '#';
                        downloadPoBtn.removeAttribute('download');
                    });
            });
        } else {
            console.error("Order Details Modal element not found.");
        }

        console.log("Dashboard JS Fully Initialized.");
    });
    </script>

</body>
</html>