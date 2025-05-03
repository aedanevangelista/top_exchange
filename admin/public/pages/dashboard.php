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

// --- Existing PHP Functions (Keep as they are) ---
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
    $sql = "SELECT COUNT(*) as active_count FROM orders WHERE status = 'Active'"; // Assuming 'Active' means processed/completed for revenue
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
    // Ensure current year is available if no orders exist yet
    if (empty($years)) { $years[] = date('Y'); }
    return $years;
}

// --- NEW: KPI Functions ---

// Helper to get date range condition for SQL
function getDateRangeCondition($period) {
    $currentYear = date('Y');
    $currentMonth = date('m');
    $today = date('Y-m-d');

    switch ($period) {
        case 'this_month':
            // Use placeholders for prepared statements if data comes from user input
            // For internally defined periods like this, direct embedding is generally okay,
            // but ensure proper casting/validation if $currentYear/$currentMonth could be manipulated.
            return "YEAR(order_date) = $currentYear AND MONTH(order_date) = $currentMonth";
        case 'this_year':
            return "YEAR(order_date) = $currentYear";
        case 'today':
             return "DATE(order_date) = '$today'";
        // Add more periods like 'last_month', 'last_7_days' if needed
        default:
            return "1=1"; // No filter for unknown period
    }
}

// Get Total Revenue for a period (Assuming 'Active' or 'Completed' status counts towards revenue)
function getTotalRevenue($conn, $period = 'this_month') {
    $totalRevenue = 0;
    $dateCondition = getDateRangeCondition($period);
    // IMPORTANT: Adjust the status check based on when an order is considered revenue-generating
    // Ensure 'total_amount' is the correct column name for the order total.
    $sql = "SELECT SUM(total_amount) as total_revenue FROM orders WHERE status IN ('Active', 'Completed', 'Delivered') AND $dateCondition"; // Added 'Completed', 'Delivered' as examples
    $result = $conn->query($sql); // Use prepared statements if $period comes from user input
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $totalRevenue = $row['total_revenue'] ?? 0; // Use null coalescing operator
    }
    return $totalRevenue;
}

// Get Total Orders Count for a period
function getTotalOrdersCount($conn, $period = 'this_month') {
    $count = 0;
    $dateCondition = getDateRangeCondition($period);
    $sql = "SELECT COUNT(*) as total_orders FROM orders WHERE $dateCondition";
    $result = $conn->query($sql); // Use prepared statements if $period comes from user input
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $count = $row['total_orders'] ?? 0;
    }
    return $count;
}

// Calculate Average Order Value (AOV)
function getAverageOrderValue($totalRevenue, $totalOrders) {
    if ($totalOrders > 0) {
        return $totalRevenue / $totalOrders;
    }
    return 0; // Avoid division by zero
}

// --- NEW: Recent Orders Function ---
function getRecentOrders($conn, $limit = 5) {
    $orders = [];

    // ******** IMPORTANT: FIX THIS QUERY BASED ON YOUR DATABASE SCHEMA ********
    // The error "Table '...users' doesn't exist" means 'users' is the wrong table name,
    // or 'client_id'/'user_id'/'username' are the wrong column names.
    // Replace 'users', 'u.user_id', 'o.client_id', and 'u.username' with your actual table and column names.
    $sql = "SELECT o.order_id, o.order_date, o.status, o.total_amount, u.username
            FROM orders o
            LEFT JOIN users u ON o.client_id = u.user_id -- <<< FIX THIS LINE!
            ORDER BY o.order_date DESC
            LIMIT ?";
    // *************************************************************************

    $stmt = $conn->prepare($sql); // This line caused the error because the table name was wrong
    if ($stmt) {
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $orders[] = $row;
            }
        } else {
             error_log("Error getting result for recent orders: " . $stmt->error);
        }
        $stmt->close();
    } else {
        // Handle prepare error if necessary
        error_log("Error preparing statement for recent orders: " . $conn->error);
    }
    return $orders;
}


// --- Fetch Data ---
$selectedYear = $_GET['year'] ?? date('Y');
$availableYears = getAvailableYears($conn);

// Existing Counts for Badges
$pendingOrdersCount = getPendingOrdersCount($conn);
$rejectedOrdersCount = getRejectedOrdersCount($conn);
$activeOrdersCount = getActiveOrdersCount($conn); // Used for badge
$deliverableOrdersCount = getDeliverableOrdersCount($conn);

// NEW: Fetch KPI Data (Defaulting to 'this_month')
$kpiPeriod = 'this_month'; // You could make this selectable later via GET param or JS
$totalRevenueThisMonth = getTotalRevenue($conn, $kpiPeriod);
$totalOrdersThisMonth = getTotalOrdersCount($conn, $kpiPeriod);
$averageOrderValueThisMonth = getAverageOrderValue($totalRevenueThisMonth, $totalOrdersThisMonth);

// NEW: Fetch Recent Orders
$recentOrders = getRecentOrders($conn, 5); // Get top 5

// NOTE: Connection $conn remains open as it might be used by AJAX calls triggered by JS below.
// If not, consider closing it: $conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <!-- Link CSS Files -->
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="/css/dashboard.css"> <!-- Make sure this path is correct -->
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Add specific styles if needed, or update dashboard.css -->
    <style>
        /* Basic styling for new elements - Refine in dashboard.css */
        .kpi-container {
            display: flex;
            gap: 15px; /* Spacing between KPI cards */
            margin-bottom: 20px; /* Space below KPI row */
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
        }
        .kpi-card {
            background-color: #fff;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            flex: 1; /* Allow cards to grow */
            min-width: 180px; /* Minimum width before wrapping */
            text-align: center;
        }
        .kpi-card h4 {
            margin: 0 0 5px 0;
            font-size: 0.9em;
            color: #555;
            text-transform: uppercase;
        }
        .kpi-card .kpi-value {
            font-size: 1.8em;
            font-weight: bold;
            color: #333;
            margin: 0;
        }

        .recent-orders-container {
             background-color: #fff;
             padding: 20px;
             border-radius: 8px;
             box-shadow: 0 2px 4px rgba(0,0,0,0.1);
             margin-bottom: 20px; /* Space below recent orders */
        }
         .recent-orders-container h3 {
             margin-top: 0;
             margin-bottom: 15px;
             border-bottom: 1px solid #eee;
             padding-bottom: 10px;
         }
        .recent-orders-table {
            width: 100%;
            border-collapse: collapse;
        }
        .recent-orders-table th,
        .recent-orders-table td {
            text-align: left;
            padding: 8px 12px;
            border-bottom: 1px solid #eee;
            font-size: 0.9em;
        }
        .recent-orders-table th {
             background-color: #f8f9fa;
             font-weight: bold;
        }
         .recent-orders-table tr:last-child td {
             border-bottom: none;
         }
         .recent-orders-table td a { /* Style for potential links */
             color: #007bff;
             text-decoration: none;
         }
         .recent-orders-table td a:hover {
             text-decoration: underline;
         }
        .status-badge { /* Simple status badges */
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            color: #fff;
            display: inline-block; /* Ensure badge displays correctly */
            white-space: nowrap; /* Prevent status text wrapping */
        }
        /* Define colors for various statuses */
        .status-Pending { background-color: #ffc107; color: #333;} /* Yellow */
        .status-Active { background-color: #28a745; } /* Green */
        .status-Completed { background-color: #28a745; } /* Green */
        .status-Delivered { background-color: #007bff; } /* Blue */
        .status-Rejected { background-color: #dc3545; } /* Red */
        .status-Cancelled { background-color: #6c757d; } /* Gray */
        .status-For.Delivery { background-color: #17a2b8; } /* Teal */
        .status-In.Transit { background-color: #fd7e14; } /* Orange */
        /* Add more status colors as needed */

        /* Ensure consistent spacing and layout */
        .main-content > .dashboard-section,
        .main-content > .stats-container,
        .main-content > .kpi-container,
        .main-content > .overview-container { /* Apply to overview too */
            margin-bottom: 25px; /* Add consistent bottom margin */
        }

    </style>
</head>
<body>

    <?php include '../sidebar.php'; ?>

    <div class="main-content">
        <div class="overview-container"> <!-- Removed dashboard-section class here if sidebar handles top-level layout -->
            <h2>Dashboard</h2>

            <!-- Order Status Notification Badges (Existing) -->
            <div class="notification-badges">
                 <?php if ($pendingOrdersCount > 0): ?>
                 <a href="/public/pages/orders.php?status=Pending" class="notification-badge pending">
                     <i class="fas fa-clock notification-icon"></i>
                     <span class="notification-count"><?php echo $pendingOrdersCount; ?></span>
                     <span class="notification-label">Pending</span>
                 </a>
                 <?php endif; ?>
                 <?php if ($rejectedOrdersCount > 0): ?>
                 <a href="/public/pages/orders.php?status=Rejected" class="notification-badge rejected">
                     <i class="fas fa-times-circle notification-icon"></i>
                     <span class="notification-count"><?php echo $rejectedOrdersCount; ?></span>
                     <span class="notification-label">Rejected</span>
                 </a>
                 <?php endif; ?>
                 <?php if ($activeOrdersCount > 0): ?>
                 <a href="/public/pages/orders.php?status=Active" class="notification-badge active">
                     <i class="fas fa-check-circle notification-icon"></i>
                     <span class="notification-count"><?php echo $activeOrdersCount; ?></span>
                     <span class="notification-label">Active</span>
                 </a>
                 <?php endif; ?>
                 <?php if ($deliverableOrdersCount > 0): ?>
                 <a href="/public/pages/deliverable_orders.php" class="notification-badge deliverable">
                     <i class="fas fa-truck notification-icon"></i>
                     <span class="notification-count"><?php echo $deliverableOrdersCount; ?></span>
                     <span class="notification-label">Deliverables</span>
                 </a>
                 <?php endif; ?>
                 <?php // Optional: Message if no badges are shown
                    if (empty($pendingOrdersCount) && empty($rejectedOrdersCount) && empty($activeOrdersCount) && empty($deliverableOrdersCount)): ?>
                    <!-- <p style="margin-top: 10px; color: #666;">No orders require immediate attention.</p> -->
                 <?php endif; ?>
            </div>
        </div>

        <!-- NEW: KPI Section -->
        <div class="kpi-container">
            <div class="kpi-card">
                <h4>Revenue (This Month)</h4>
                <!-- Assuming currency is USD, adjust symbol if needed -->
                <p class="kpi-value">$<?php echo number_format($totalRevenueThisMonth, 2); ?></p>
                 <!-- Optional: Add comparison % later if needed -->
            </div>
            <div class="kpi-card">
                <h4>Total Orders (This Month)</h4>
                <p class="kpi-value"><?php echo number_format($totalOrdersThisMonth); ?></p>
                 <!-- Optional: Add comparison % later if needed -->
            </div>
            <div class="kpi-card">
                <h4>Avg. Order Value (This Month)</h4>
                <p class="kpi-value">$<?php echo number_format($averageOrderValueThisMonth, 2); ?></p>
                 <!-- Optional: Add comparison % later if needed -->
            </div>
        </div>

        <!-- Existing Chart/Stats Sections -->
        <div class="stats-container">
            <!-- Client Orders Pie Chart -->
            <div class="stat-card client-orders-card">
                <div class="chart-header">
                    <h3>CLIENT ORDERS (<?php echo htmlspecialchars($selectedYear); ?>)</h3> <!-- Added htmlspecialchars -->
                    <select id="year-select" class="year-select">
                        <?php foreach($availableYears as $year): ?>
                            <option value="<?php echo htmlspecialchars($year); ?>" <?php echo ($year == $selectedYear) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($year); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="stat-card-content">
                    <canvas id="clientOrdersChart"></canvas>
                </div>
            </div>

            <!-- Orders Sold Comparison -->
            <div class="stat-card packs-sold-card">
                 <div class="packs-sold-header">
                    <span>Orders sold in</span>
                    <select id="packs-sold-year" class="packs-sold-dropdown">
                        <?php foreach($availableYears as $year): ?>
                            <option value="<?php echo htmlspecialchars($year); ?>" <?php echo ($year == ($availableYears[0] ?? date('Y'))) ? 'selected' : ''; ?>> <!-- Added default current year if array empty -->
                                <?php echo htmlspecialchars($year); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="packs-sold-count" id="packs-sold-count">Loading...</div> <!-- Indicate loading -->
                <div class="packs-comparison-row">
                    <span id="packs-sold-percentage" class="packs-comparison">N/A since</span>
                    <select id="packs-sold-compare-year" class="packs-sold-dropdown">
                        <?php
                        $compareYearDefault = count($availableYears) > 1 ? $availableYears[1] : ($availableYears[0] ?? date('Y')); // Default compare year
                        foreach($availableYears as $year): ?>
                            <option value="<?php echo htmlspecialchars($year); ?>" <?php echo ($year == $compareYearDefault) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($year); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Sales Per Department Chart -->
        <div class="dashboard-section sales-department-container"> <!-- Ensure this has consistent styling -->
            <div class="chart-header">
                <h3>SALES PER DEPARTMENT</h3>
                <div class="time-period-tabs">
                    <button class="time-period-tab active" data-period="weekly">Weekly</button>
                    <button class="time-period-tab" data-period="monthly">Monthly</button>
                </div>
            </div>
            <div class="stat-card-content">
                <canvas id="salesPerDepartmentChart"></canvas>
            </div>
        </div>

         <!-- NEW: Recent Orders Section -->
        <div class="dashboard-section recent-orders-container"> <!-- Added dashboard-section for consistency -->
            <h3>Recent Orders</h3>
            <?php if (!empty($recentOrders)): ?>
                <div style="overflow-x:auto;"> <!-- Add horizontal scroll for small screens -->
                    <table class="recent-orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Customer</th> <!-- This column depends on the JOIN working -->
                                <th>Status</th>
                                <th style="text-align: right;">Total</th> <!-- Align total right -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order):
                                // Replace spaces with dots for CSS class compatibility and handle potential nulls
                                $statusDisplay = htmlspecialchars($order['status'] ?? 'Unknown');
                                $statusClass = str_replace(' ', '.', $statusDisplay);
                            ?>
                                <tr>
                                    <td>
                                        <!-- Make this a link to the order details page - UPDATE PATH IF NEEDED -->
                                        <a href="/public/pages/order_details.php?id=<?php echo htmlspecialchars($order['order_id']); ?>">
                                            #<?php echo htmlspecialchars($order['order_id']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($order['order_date']))); ?></td>
                                    <td><?php echo htmlspecialchars($order['username'] ?? 'N/A'); // Handle null username - THIS REQUIRES THE JOIN TO WORK ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $statusClass; ?>">
                                            <?php echo $statusDisplay; ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;">$<?php echo number_format($order['total_amount'] ?? 0, 2); // Handle null total ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No recent orders found (or error fetching orders).</p> <!-- Updated message -->
            <?php endif; ?>
        </div>


    </div> <!-- End main-content -->

    <!-- Complete JavaScript Block -->
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        console.log("Dashboard JS Initializing...");

        /*** ===========================
         *  CLIENT ORDERS PIE CHART
         *  =========================== ***/
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
            if (!ctxClientOrders) {
                console.error("Client Orders Chart canvas not found");
                return;
            }
            const chartContext = ctxClientOrders.getContext('2d');
            // Handle case where data might be empty or not an array
            const labels = Array.isArray(data) ? data.map(item => item.username || 'Unknown') : [];
            const values = Array.isArray(data) ? data.map(item => item.count || 0) : [];
            const colors = Array.isArray(data) ? data.map((_, index) => chartColors[index % chartColors.length]) : [];

            if (clientOrdersChart) {
                clientOrdersChart.destroy(); // Destroy previous chart instance
            }

            // Display a message if no data
            if (labels.length === 0) {
                 chartContext.clearRect(0, 0, ctxClientOrders.width, ctxClientOrders.height);
                 chartContext.fillStyle = '#6c757d'; // Gray color
                 chartContext.textAlign = 'center';
                 chartContext.fillText('No client order data available for this year.', ctxClientOrders.width / 2, ctxClientOrders.height / 2);
                 return; // Don't initialize chart if no data
            }

            clientOrdersChart = new Chart(chartContext, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors,
                        borderColor: colors.map(color => color.replace('0.85)', '1)')), // Make border solid
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false, // Allow chart to fill container height
                    plugins: {
                        legend: {
                            position: 'right', // Adjust as needed ('top', 'bottom', 'left', 'right')
                            labels: {
                                boxWidth: 15,
                                padding: 15,
                                // Optional: Limit number of legend items shown directly
                                // filter: function(legendItem, chartData) {
                                //     return legendItem.index < 10; // Show only top 10 for example
                                // }
                            }
                        },
                        title: {
                            display: false // Title is handled by the chart-header h3
                        },
                        tooltip: {
                             callbacks: {
                                 label: function(context) {
                                     let label = context.label || '';
                                     if (label) {
                                         label += ': ';
                                     }
                                     if (context.parsed !== null) {
                                         label += context.parsed; // Show count in tooltip
                                     }
                                     return label;
                                 }
                             }
                         }
                    }
                }
            });
        }

        function loadClientOrders(year) {
            // IMPORTANT: Update this URL path if your backend file structure changes
            const url = `/backend/get_client_orders.php?year=${year}`;
            console.log("Fetching client orders from:", url);

             // Optional: Show loading state on chart
            if (ctxClientOrders) {
                 const chartContext = ctxClientOrders.getContext('2d');
                 chartContext.clearRect(0, 0, ctxClientOrders.width, ctxClientOrders.height);
                 chartContext.fillStyle = '#6c757d';
                 chartContext.textAlign = 'center';
                 chartContext.fillText('Loading...', ctxClientOrders.width / 2, ctxClientOrders.height / 2);
            }


            fetch(url)
                .then(response => {
                    if (!response.ok) {
                         throw new Error(`HTTP error! Status: ${response.status} - ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log("Client orders data received:", data);
                    if (data.error) { // Check for backend error message
                         console.error("Server error loading client orders:", data.message);
                         initializeClientOrdersChart([]); // Pass empty array to show message
                    } else {
                         initializeClientOrdersChart(data);
                    }
                })
                .catch(error => {
                     console.error('Error loading client orders:', error);
                     // Display error on chart
                     if (ctxClientOrders) {
                         const chartContext = ctxClientOrders.getContext('2d');
                         chartContext.clearRect(0, 0, ctxClientOrders.width, ctxClientOrders.height);
                         chartContext.fillStyle = '#dc3545'; // Red color
                         chartContext.textAlign = 'center';
                         chartContext.fillText('Error loading chart data.', ctxClientOrders.width / 2, ctxClientOrders.height / 2);
                     }
                 });
        }

        const yearSelect = document.getElementById('year-select');
        if (yearSelect) {
            yearSelect.addEventListener('change', function() {
                loadClientOrders(this.value);
                // Update the H3 title dynamically if needed
                const clientOrdersHeader = document.querySelector('.client-orders-card .chart-header h3');
                if(clientOrdersHeader) {
                    clientOrdersHeader.textContent = `CLIENT ORDERS (${this.value})`;
                }
            });
            // Initial load based on the selected value in PHP
            if (yearSelect.value) {
                loadClientOrders(yearSelect.value);
            }
        } else {
            console.error("Year select dropdown ('year-select') not found");
        }


        /*** ===========================
         *  ORDERS SOLD SECTION
         *  =========================== ***/
        const ordersSoldYearSelect = document.getElementById("packs-sold-year");
        const ordersSoldCompareYearSelect = document.getElementById("packs-sold-compare-year");
        const ordersSoldCountEl = document.getElementById("packs-sold-count");
        const ordersSoldPercentageEl = document.getElementById("packs-sold-percentage");

        function getOrderCounts(year) {
            if (!year) return Promise.resolve(0); // Return promise resolving to 0 if no year
            // IMPORTANT: Update this URL path if your backend file structure changes
            const url = `/backend/get_order_counts.php?year=${year}`;
            console.log("Fetching order counts from:", url);
            return fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status} - ${response.statusText}`);
                    }
                    // Check content type before parsing JSON
                    const contentType = response.headers.get("content-type");
                    if (contentType && contentType.indexOf("application/json") !== -1) {
                        return response.json();
                    } else {
                        // Handle non-JSON response (e.g., plain number or error message)
                        return response.text().then(text => {
                             console.warn(`Received non-JSON response for order count (${year}):`, text);
                             // Try to parse as number, default to 0
                             const count = Number(text);
                             return isNaN(count) ? 0 : count;
                        });
                    }
                })
                .then(data => {
                     console.log(`Order count for ${year}:`, data);
                     // Ensure data is a number, default to 0 if not
                     // If data is an object (e.g., {count: 10}), access the property
                     let count = 0;
                     if (typeof data === 'number') {
                         count = data;
                     } else if (typeof data === 'object' && data !== null && typeof data.count === 'number') {
                         count = data.count;
                     } else {
                          console.warn(`Unexpected data format for order count (${year}):`, data);
                     }
                     return isNaN(count) ? 0 : count;
                })
                .catch(error => {
                    console.error(`Error fetching order counts for ${year}:`, error);
                    return 0; // Return 0 on error
                });
        }

        async function updateOrdersSold() {
             // Ensure all elements exist before proceeding
             if (!ordersSoldYearSelect || !ordersSoldCompareYearSelect || !ordersSoldCountEl || !ordersSoldPercentageEl) {
                 console.error("One or more Orders Sold elements not found. IDs: packs-sold-year, packs-sold-compare-year, packs-sold-count, packs-sold-percentage");
                 // Optionally disable the widget or show an error message
                 if(ordersSoldCountEl) ordersSoldCountEl.textContent = 'Error';
                 if(ordersSoldPercentageEl) ordersSoldPercentageEl.textContent = 'Setup Error';
                 return;
             }

            const selectedYear = ordersSoldYearSelect.value;
            const compareYear = ordersSoldCompareYearSelect.value;
            ordersSoldCountEl.textContent = 'Loading...'; // Show loading state
            ordersSoldPercentageEl.textContent = 'Calculating...';
            ordersSoldPercentageEl.className = 'packs-comparison'; // Reset class

            try {
                // Fetch counts concurrently
                const [currentOrders, previousOrders] = await Promise.all([
                    getOrderCounts(selectedYear),
                    getOrderCounts(compareYear)
                ]);

                ordersSoldCountEl.textContent = `${currentOrders} Orders`;

                if (selectedYear === compareYear) {
                     ordersSoldPercentageEl.textContent = `Comparing same year`;
                } else if (previousOrders > 0) {
                    const percentageChange = (((currentOrders - previousOrders) / previousOrders) * 100);
                    const sign = percentageChange >= 0 ? "+" : "";
                    ordersSoldPercentageEl.textContent = `${sign}${percentageChange.toFixed(1)}% since`;
                    // Add class for styling (e.g., green for positive, red for negative)
                    ordersSoldPercentageEl.className = 'packs-comparison ' + (percentageChange >= 0 ? 'positive' : 'negative');
                } else if (currentOrders > 0) {
                     // Previous year had 0 orders, current has some
                     ordersSoldPercentageEl.textContent = `+${currentOrders} orders (prev 0)`;
                     ordersSoldPercentageEl.className = 'packs-comparison positive';
                } else {
                    // Both years have 0 orders or previous was 0 and current is 0
                    ordersSoldPercentageEl.textContent = "N/A since"; // Or "0% change"
                }
            } catch (error) {
                 console.error("Error updating orders sold widget:", error);
                 ordersSoldCountEl.textContent = `Error`;
                 ordersSoldPercentageEl.textContent = "Error loading data";
                 ordersSoldPercentageEl.className = 'packs-comparison negative'; // Indicate error visually
            }
        }

        // Add event listeners only if elements exist
        if (ordersSoldYearSelect) ordersSoldYearSelect.addEventListener("change", updateOrdersSold);
        if (ordersSoldCompareYearSelect) ordersSoldCompareYearSelect.addEventListener("change", updateOrdersSold);

        // Initial update only if elements exist
        if (ordersSoldYearSelect && ordersSoldCompareYearSelect && ordersSoldCountEl && ordersSoldPercentageEl) {
             updateOrdersSold();
        }


        /*** ===========================
        *  SALES PER DEPARTMENT BAR CHART
        *  =========================== ***/
        const ctxSalesPerDepartment = document.getElementById("salesPerDepartmentChart");
        let salesPerDepartmentChart = null;
        let currentTimePeriod = 'weekly'; // Default period

        function loadSalesByCategory(timePeriod) {
            if (!ctxSalesPerDepartment) {
                console.error("Sales Per Department Chart canvas (ID: salesPerDepartmentChart) not found");
                return;
            }
            const chartContext = ctxSalesPerDepartment.getContext('2d');
            // IMPORTANT: Update this URL path if your backend file structure changes
            const url = `/backend/get_sales_by_category.php?period=${timePeriod}`;
            console.log(`Fetching ${timePeriod} sales data from:`, url);

            // Optional: Show loading state
            chartContext.clearRect(0, 0, ctxSalesPerDepartment.width, ctxSalesPerDepartment.height);
            chartContext.fillStyle = '#6c757d';
            chartContext.textAlign = 'center';
            chartContext.fillText(`Loading ${timePeriod} data...`, ctxSalesPerDepartment.width / 2, ctxSalesPerDepartment.height / 2);


            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status} - ${response.statusText}`);
                    }
                    // Get raw text first to help debug potential JSON issues
                    return response.text();
                })
                .then(text => {
                    // console.log(`Raw ${timePeriod} sales data response:`, text); // Uncomment for debugging
                    try {
                        const data = JSON.parse(text); // Attempt to parse the text as JSON

                        // Check for a specific error structure from your backend
                        if (data.error) {
                            throw new Error(`Server Error: ${data.message || 'Unknown error'}`);
                        }

                        // Validate expected data structure (basic check)
                        if (!data || !Array.isArray(data.categories) || !data.currentYear || !data.lastYear) {
                             throw new Error("Invalid data structure received from backend.");
                        }


                        if (salesPerDepartmentChart) {
                             salesPerDepartmentChart.destroy(); // Destroy existing chart instance
                        }

                         // Display a message if no data categories exist
                        if (data.categories.length === 0) {
                             chartContext.clearRect(0, 0, ctxSalesPerDepartment.width, ctxSalesPerDepartment.height);
                             chartContext.fillStyle = '#6c757d';
                             chartContext.textAlign = 'center';
                             chartContext.fillText(`No sales data available for this period.`, ctxSalesPerDepartment.width / 2, ctxSalesPerDepartment.height / 2);
                             return;
                         }


                        salesPerDepartmentChart = new Chart(chartContext, {
                            type: "bar",
                            data: {
                                labels: data.categories, // Expecting an array of category names
                                datasets: [
                                    {
                                        label: `${data.currentYear.year || 'Current'} Sales`, // Expecting currentYear.year and currentYear.data
                                        data: data.currentYear.data || [],
                                        backgroundColor: "rgba(74, 144, 226, 0.8)", // Blue
                                        borderColor: "rgba(74, 144, 226, 1)",
                                        borderWidth: 1,
                                        borderRadius: 4 // Rounded corners for bars
                                    },
                                    {
                                        label: `${data.lastYear.year || 'Previous'} Sales`, // Expecting lastYear.year and lastYear.data
                                        data: data.lastYear.data || [],
                                        backgroundColor: "rgba(155, 155, 155, 0.7)", // Gray
                                        borderColor: "rgba(155, 155, 155, 1)",
                                        borderWidth: 1,
                                        borderRadius: 4
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false, // Allow chart to resize freely
                                plugins: {
                                    legend: {
                                        position: 'top' // Position legend at the top
                                    },
                                    title: {
                                        display: false // Title handled by chart-header h3
                                    },
                                    tooltip: {
                                        mode: 'index', // Show tooltips for both bars on hover
                                        intersect: false,
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true, // Start Y-axis at 0
                                        title: {
                                            display: true,
                                            text: 'Number of Orders' // Y-axis label
                                        }
                                    },
                                    x: {
                                        title: {
                                             display: true,
                                             text: 'Department / Category' // X-axis label
                                         },
                                        ticks: {
                                            autoSkip: false // Prevent labels from being skipped automatically
                                            // Consider rotating labels if they overlap:
                                            // maxRotation: 90,
                                            // minRotation: 45
                                        }
                                    }
                                }
                            }
                        });
                    } catch (e) {
                        // Catch JSON parsing errors or other errors during processing
                        console.error(`Error processing ${timePeriod} sales data:`, e, "Raw text was:", text);
                         // Display error message on the chart canvas
                         chartContext.clearRect(0, 0, ctxSalesPerDepartment.width, ctxSalesPerDepartment.height);
                         chartContext.fillStyle = '#dc3545'; // Red color
                         chartContext.textAlign = 'center';
                         chartContext.fillText('Error loading chart data.', ctxSalesPerDepartment.width / 2, ctxSalesPerDepartment.height / 2);
                    }
                })
                .catch(error => {
                     // Catch fetch errors (network issues, server errors)
                     console.error(`Error fetching ${timePeriod} sales data:`, error);
                     if (ctxSalesPerDepartment) {
                          const chartContext = ctxSalesPerDepartment.getContext('2d');
                          chartContext.clearRect(0, 0, ctxSalesPerDepartment.width, ctxSalesPerDepartment.height);
                          chartContext.fillStyle = '#dc3545'; // Red color
                          chartContext.textAlign = 'center';
                          chartContext.fillText('Error loading chart data.', ctxSalesPerDepartment.width / 2, ctxSalesPerDepartment.height / 2);
                     }
                });
        }

        // Add event listeners to time period tabs
        const timePeriodTabs = document.querySelectorAll('.time-period-tab');
        if (timePeriodTabs.length > 0) {
            timePeriodTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove 'active' class from all tabs
                    timePeriodTabs.forEach(t => t.classList.remove('active'));
                    // Add 'active' class to the clicked tab
                    this.classList.add('active');
                    // Get the new time period
                    currentTimePeriod = this.getAttribute('data-period');
                    // Load data for the new period
                    loadSalesByCategory(currentTimePeriod);
                });
            });
        } else {
            console.warn("Time period tabs (.time-period-tab) not found.");
        }


        // Initial load for the sales per department chart if canvas exists
        if (ctxSalesPerDepartment) {
             // Load data based on the initially active tab
             const activeTab = document.querySelector('.time-period-tab.active');
             if (activeTab) {
                 currentTimePeriod = activeTab.getAttribute('data-period');
             } // else defaults to 'weekly'
             loadSalesByCategory(currentTimePeriod);
        } else {
             console.error("Sales per Department canvas element (ID: salesPerDepartmentChart) not found on initial load.");
        }

        console.log("Dashboard JS Fully Initialized.");
    });
    </script>

</body>
</html>