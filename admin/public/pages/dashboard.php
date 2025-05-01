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

// Count deliverable orders (For Delivery and In Transit)
function getDeliverableOrdersCount($conn) {
    $count = 0;
    $sql = "SELECT COUNT(*) as deliverable_count FROM orders WHERE status IN ('For Delivery', 'In Transit')";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $count = $row['deliverable_count'];
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
$deliverableOrdersCount = getDeliverableOrdersCount($conn);

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
        
        .notification-badge.deliverable {
            background-color: #fff3cd;
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
        
        .deliverable .notification-icon, .deliverable .notification-count, .deliverable .notification-label {
            color: #856404;
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

         .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .time-period-tabs {
            display: flex;
            gap: 10px;
        }
        
        .time-period-tab {
            padding: 6px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #f8f9fa;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .time-period-tab.active {
            background-color: #28a745;
            color: white;
            border-color: #28a745;
        }
        
        .time-period-tab:hover:not(.active) {
            background-color: #e2e6ea;
        }

        /* Orders sold styles */
        .packs-sold-container {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 20px;
        }

        .packs-sold-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .packs-sold-dropdown {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px 8px;
            font-size: 14px;
        }

        .packs-sold-count {
            font-size: 32px;
            font-weight: bold;
            margin: 15px 0;
        }

        .packs-comparison-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .packs-comparison {
            color: #45A049;
        }

        /* Sales department styles */
        .sales-department-chart {
            height: 300px;
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
                    
                    <?php if ($deliverableOrdersCount > 0): ?>
                    <a href="/admin/public/pages/deliverable_orders.php" style="text-decoration: none;">
                        <div class="notification-badge deliverable">
                            <i class="fas fa-truck notification-icon"></i>
                            <span class="notification-count"><?php echo $deliverableOrdersCount; ?></span>
                            <span class="notification-label">Deliverables</span>
                        </div>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="top-section">
                <div class="client-orders-container dashboard-section">
                    <div class="chart-header">
                        <h3>CLIENT ORDERS</h3>
                        <select id="year-select" class="year-select">
                            <?php foreach($availableYears as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo ($year == $selectedYear) ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endforeach; ?>
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
                            <?php foreach($availableYears as $year): ?>
                                <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="packs-sold-count" id="packs-sold-count">0 Orders</div>

                    <div class="packs-comparison-row">
                        <span id="packs-sold-percentage" class="packs-comparison">0% since</span>
                        <select id="packs-sold-compare-year" class="packs-sold-dropdown">
                            <?php foreach($availableYears as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo ($year != $availableYears[0]) ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="sales-department-container dashboard-section">
                <div class="chart-header">
                    <h3>SALES PER DEPARTMENT</h3>
                    <div class="time-period-tabs">
                        <button class="time-period-tab active" data-period="weekly">Weekly</button>
                        <button class="time-period-tab" data-period="monthly">Monthly</button>
                    </div>
                </div>
                <div class="sales-department-chart">
                    <canvas id="salesPerDepartmentChart"></canvas>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> 
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        console.log("Dashboard.js loaded. Current path:", window.location.pathname);

        /*** ===========================
         *  CLIENT ORDERS PIE CHART
         *  =========================== ***/

        // Professional color array with 30 colors
        const chartColors = [
            'rgba(69, 160, 73, 0.85)',    // Green
            'rgba(71, 120, 209, 0.85)',   // Royal Blue
            'rgba(235, 137, 49, 0.85)',   // Orange
            'rgba(165, 84, 184, 0.85)',   // Purple
            'rgba(214, 68, 68, 0.85)',    // Red
            'rgba(60, 179, 163, 0.85)',   // Turquoise
            'rgba(201, 151, 63, 0.85)',   // Golden Brown
            'rgba(86, 120, 141, 0.85)',   // Steel Blue
            'rgba(182, 73, 141, 0.85)',   // Magenta
            'rgba(110, 146, 64, 0.85)',   // Olive Green
            'rgba(149, 82, 81, 0.85)',    // Rust
            'rgba(95, 61, 150, 0.85)',    // Deep Purple
            'rgba(170, 166, 57, 0.85)',   // Mustard
            'rgba(87, 144, 176, 0.85)',   // Sky Blue
            'rgba(192, 88, 116, 0.85)',   // Rose
            'rgba(85, 156, 110, 0.85)',   // Sea Green
            'rgba(161, 88, 192, 0.85)',   // Orchid
            'rgba(169, 106, 76, 0.85)',   // Brown
            'rgba(78, 130, 162, 0.85)',   // Blue Gray
            'rgba(190, 117, 50, 0.85)',   // Bronze
            'rgba(111, 83, 150, 0.85)',   // Lavender
            'rgba(158, 126, 74, 0.85)',   // Sand
            'rgba(92, 153, 123, 0.85)',   // Sage
            'rgba(173, 76, 104, 0.85)',   // Berry
            'rgba(67, 134, 147, 0.85)',   // Ocean Blue
            'rgba(187, 96, 68, 0.85)',    // Terra Cotta
            'rgba(124, 110, 163, 0.85)',  // Dusty Purple
            'rgba(146, 134, 54, 0.85)',   // Dark Yellow
            'rgba(82, 137, 110, 0.85)',   // Forest
            'rgba(155, 89, 136, 0.85)'    // Plum
        ];

        let clientOrdersChart = null;

        // Function to initialize the chart
        function initializeClientOrdersChart(data) {
            const ctx = document.getElementById('clientOrdersChart');
            if (!ctx) return;

            const chartContext = ctx.getContext('2d');
            
            const labels = data.map(item => item.username);
            const values = data.map(item => item.count);
            const colors = data.map((_, index) => chartColors[index % chartColors.length]);

            if (clientOrdersChart) {
                clientOrdersChart.destroy();
            }

            clientOrdersChart = new Chart(chartContext, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors,
                        borderColor: colors.map(color => color.replace('0.85)', '1)')),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 15,
                                padding: 15
                            }
                        },
                        title: {
                            display: true,
                            text: `Completed Orders Distribution - ${document.getElementById('year-select')?.value || new Date().getFullYear()}`,
                            font: {
                                size: 16
                            }
                        }
                    }
                }
            });
        }

        // Function to load client orders for a specific year
        function loadClientOrders(year) {
            const url = `/admin/backend/get_client_orders.php?year=${year}`;
            console.log("Fetching client orders from:", url);
            
            fetch(url)
                .then(response => {
                    console.log("Client orders response status:", response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log("Client orders data received:", data);
                    initializeClientOrdersChart(data);
                })
                .catch(error => console.error('Error loading client orders:', error));
        }

        // Initialize the year select event listener
        const yearSelect = document.getElementById('year-select');
        if (yearSelect) {
            yearSelect.addEventListener('change', function() {
                loadClientOrders(this.value);
            });
            
            // Load initial data
            if (yearSelect.value) {
                loadClientOrders(yearSelect.value);
            }
        }

        /*** ===========================
         *  ORDERS SOLD SECTION
         *  =========================== ***/
        const ordersSoldYear = document.getElementById("packs-sold-year");
        const ordersSoldCompareYear = document.getElementById("packs-sold-compare-year");
        const ordersSoldCount = document.getElementById("packs-sold-count");
        const ordersSoldPercentage = document.getElementById("packs-sold-percentage");

        // Function to get order counts from database
        function getOrderCounts(year) {
            if (!year) return 0;
            
            const url = `/admin/backend/get_order_counts.php?year=${year}`;
            console.log("Fetching order counts from:", url);
            
            return fetch(url)
                .then(response => {
                    console.log("Order counts response status:", response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .catch(error => {
                    console.error('Error fetching order counts:', error);
                    return 0;
                });
        }

        // Function to Update Orders Sold Display
        async function updateOrdersSold() {
            if (!ordersSoldYear || !ordersSoldCompareYear) return;
            
            const selectedYear = ordersSoldYear.value;
            const compareYear = ordersSoldCompareYear.value;
            
            // Get order counts for both years
            const currentOrders = await getOrderCounts(selectedYear);
            const previousOrders = await getOrderCounts(compareYear);

            // Update Orders Count
            if (ordersSoldCount) {
                ordersSoldCount.textContent = `${currentOrders} Orders`;
            }

            // Update Percentage Change
            if (previousOrders > 0) {
                const percentageChange = (((currentOrders - previousOrders) / previousOrders) * 100).toFixed(2);
                if (ordersSoldPercentage) {
                    ordersSoldPercentage.textContent = `${percentageChange > 0 ? "+" : ""}${percentageChange}% since`;
                    ordersSoldPercentage.style.color = percentageChange >= 0 ? "#45A049" : "#FF4444";
                }
            } else {
                if (ordersSoldPercentage) {
                    ordersSoldPercentage.textContent = "N/A since";
                    ordersSoldPercentage.style.color = "#666";
                }
            }
        }

        // Add Event Listeners to Dropdowns
        if (ordersSoldYear) {
            ordersSoldYear.addEventListener("change", updateOrdersSold);
        }
        
        if (ordersSoldCompareYear) {
            ordersSoldCompareYear.addEventListener("change", updateOrdersSold);
        }

        // Initial update for orders sold
        updateOrdersSold();

        /*** ===========================
        *  SALES PER DEPARTMENT BAR CHART
        *  =========================== ***/
        const ctxSalesPerDepartment = document.getElementById("salesPerDepartmentChart")?.getContext("2d");
        let salesPerDepartmentChart = null; // Define the chart variable globally
        let currentTimePeriod = 'weekly'; // Default time period

        // Function to load sales data by category with specified time period
        function loadSalesByCategory(timePeriod) {
            if (!ctxSalesPerDepartment) return;
            
            const url = `/admin/backend/get_sales_by_category.php?period=${timePeriod}`;
            console.log(`Fetching ${timePeriod} sales data from:`, url);
            
            fetch(url)
                .then(response => {
                    console.log(`${timePeriod} sales data response status:`, response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(text => {
                    console.log(`Raw ${timePeriod} sales data response:`, text);
                    try {
                        const data = JSON.parse(text);
                        if (data.error) {
                            console.error('Server Error:', data.message);
                            return;
                        }

                        // Destroy existing chart if it exists
                        if (salesPerDepartmentChart) {
                            salesPerDepartmentChart.destroy();
                        }

                        // Create Sales per Department Chart (Bar)
                        salesPerDepartmentChart = new Chart(ctxSalesPerDepartment, {
                            type: "bar",
                            data: {
                                labels: data.categories,
                                datasets: [
                                    {
                                        label: `${data.currentYear.year} Sales`,
                                        data: data.currentYear.data,
                                        backgroundColor: "#28a745", // Green
                                        borderWidth: 1,
                                        borderRadius: 5
                                    },
                                    {
                                        label: `${data.lastYear.year} Sales`,
                                        data: data.lastYear.data,
                                        backgroundColor: "#999999", // Gray
                                        borderWidth: 1,
                                        borderRadius: 5
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'top',
                                    },
                                    title: {
                                        display: true,
                                        text: `Sales by Department - ${timePeriod.charAt(0).toUpperCase() + timePeriod.slice(1)}`,
                                        font: {
                                            size: 16
                                        }
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        title: {
                                            display: true,
                                            text: 'Number of Orders',
                                            font: {
                                                size: 14,
                                                weight: 'bold'
                                            }
                                        },
                                        ticks: {
                                            callback: function(value) {
                                                const values = this.chart.data.datasets.flatMap(d => d.data);
                                                const max = Math.max(...values);
                                                // Only return values for 0 and max
                                                return value === 0 || value === max ? value : '';
                                            },
                                            font: {
                                                size: 12
                                            }
                                        }
                                    },
                                    x: {
                                        ticks: {
                                            autoSkip: false,
                                        }
                                    }
                                }
                            }
                        });
                    } catch (e) {
                        console.error('Error parsing response:', text);
                        console.error('Parse error:', e);
                    }
                })
                .catch(error => console.error(`Error loading ${timePeriod} sales data:`, error));
        }

        // Set up event listeners for the time period tabs
        document.querySelectorAll('.time-period-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs
                document.querySelectorAll('.time-period-tab').forEach(t => {
                    t.classList.remove('active');
                });
                
                // Add active class to the clicked tab
                this.classList.add('active');
                
                // Get the time period from the data attribute
                const timePeriod = this.getAttribute('data-period');
                currentTimePeriod = timePeriod;
                
                // Load the data for this time period
                loadSalesByCategory(timePeriod);
            });
        });

        // Call the function when the document loads
        if (ctxSalesPerDepartment) {
            // Initial load with the default time period
            loadSalesByCategory(currentTimePeriod);
        }
    });
    </script>

</body>
</html>