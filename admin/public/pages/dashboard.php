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

// --- PHP Functions (Keep as they are) ---
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
    // Ensure current year is available if no orders exist yet
    if (empty($years)) {
        $years[] = date('Y');
    }
    return $years;
}

// --- Fetch Data ---
$selectedYear = $_GET['year'] ?? date('Y');
$availableYears = getAvailableYears($conn);
// Note: getClientOrdersCount is used by JS AJAX now, so no need to call it here
$pendingOrdersCount = getPendingOrdersCount($conn);
$rejectedOrdersCount = getRejectedOrdersCount($conn);
$activeOrdersCount = getActiveOrdersCount($conn);
$deliverableOrdersCount = getDeliverableOrdersCount($conn);

// No need to close connection here if AJAX calls use it later

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
    <!-- REMOVED INLINE <style> block -->
</head>
<body>

    <!-- Changed class from dashboard-container to body level flex handled by sidebar.css -->
    <?php include '../sidebar.php'; ?>

    <div class="main-content">
        <div class="overview-container">
            <h2>Dashboard</h2>

            <!-- Order Status Notification Badges -->
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
            </div>
        </div>

        <!-- Renamed top-section to stats-container -->
        <div class="stats-container">
            <!-- Added common class stat-card and specific class client-orders-card -->
            <div class="stat-card client-orders-card">
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
                <!-- Added stat-card-content wrapper -->
                <div class="stat-card-content">
                    <canvas id="clientOrdersChart"></canvas>
                </div>
            </div>

            <!-- Added common class stat-card and specific class packs-sold-card -->
            <div class="stat-card packs-sold-card">
                 <!-- Removed redundant dashboard-section class -->
                 <div class="packs-sold-header">
                    <span>Orders sold in</span>
                    <select id="packs-sold-year" class="packs-sold-dropdown">
                        <?php foreach($availableYears as $year): ?>
                            <!-- Select the most recent year by default -->
                            <option value="<?php echo $year; ?>" <?php echo ($year == $availableYears[0]) ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="packs-sold-count" id="packs-sold-count">0 Orders</div>

                <div class="packs-comparison-row">
                    <span id="packs-sold-percentage" class="packs-comparison">N/A since</span>
                    <select id="packs-sold-compare-year" class="packs-sold-dropdown">
                        <?php
                        // Default compare year to the second most recent, or the same if only one year exists
                        $compareYearDefault = count($availableYears) > 1 ? $availableYears[1] : $availableYears[0];
                        foreach($availableYears as $year): ?>
                            <option value="<?php echo $year; ?>" <?php echo ($year == $compareYearDefault) ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Added dashboard-section class for consistency -->
        <div class="dashboard-section sales-department-container">
            <div class="chart-header">
                <h3>SALES PER DEPARTMENT</h3>
                <div class="time-period-tabs">
                    <button class="time-period-tab active" data-period="weekly">Weekly</button>
                    <button class="time-period-tab" data-period="monthly">Monthly</button>
                </div>
            </div>
            <!-- Added stat-card-content wrapper -->
            <div class="stat-card-content">
                <canvas id="salesPerDepartmentChart"></canvas>
            </div>
        </div>

    </div> <!-- End main-content -->

    <!-- Keep existing JavaScript -->
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
                            labels: { boxWidth: 15, padding: 15 }
                        },
                        title: {
                            display: false // Title handled by chart-header h3
                        }
                    }
                }
            });
        }

        function loadClientOrders(year) {
            const url = `/backend/get_client_orders.php?year=${year}`;
            console.log("Fetching client orders from:", url);
            fetch(url)
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    console.log("Client orders data received:", data);
                    initializeClientOrdersChart(data);
                })
                .catch(error => console.error('Error loading client orders:', error));
        }

        const yearSelect = document.getElementById('year-select');
        if (yearSelect) {
            yearSelect.addEventListener('change', function() { loadClientOrders(this.value); });
            if (yearSelect.value) { loadClientOrders(yearSelect.value); } // Initial load
        } else {
            console.error("Year select dropdown not found");
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
            const url = `/backend/get_order_counts.php?year=${year}`;
            console.log("Fetching order counts from:", url);
            return fetch(url)
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                     console.log(`Order count for ${year}:`, data);
                     // Ensure data is a number, default to 0 if not
                     const count = Number(data);
                     return isNaN(count) ? 0 : count;
                })
                .catch(error => {
                    console.error(`Error fetching order counts for ${year}:`, error);
                    return 0; // Return 0 on error
                });
        }

        async function updateOrdersSold() {
             if (!ordersSoldYearSelect || !ordersSoldCompareYearSelect || !ordersSoldCountEl || !ordersSoldPercentageEl) {
                 console.error("One or more Orders Sold elements not found.");
                 return;
             }
            const selectedYear = ordersSoldYearSelect.value;
            const compareYear = ordersSoldCompareYearSelect.value;

            try {
                const currentOrders = await getOrderCounts(selectedYear);
                const previousOrders = await getOrderCounts(compareYear);

                ordersSoldCountEl.textContent = `${currentOrders} Orders`;

                if (selectedYear === compareYear) {
                     ordersSoldPercentageEl.textContent = `Comparing same year`;
                     ordersSoldPercentageEl.className = 'packs-comparison'; // Reset class
                } else if (previousOrders > 0) {
                    const percentageChange = (((currentOrders - previousOrders) / previousOrders) * 100);
                    ordersSoldPercentageEl.textContent = `${percentageChange >= 0 ? "+" : ""}${percentageChange.toFixed(1)}% since`;
                    // Add class for styling
                    ordersSoldPercentageEl.className = 'packs-comparison ' + (percentageChange >= 0 ? 'positive' : 'negative');
                } else if (currentOrders > 0) {
                     ordersSoldPercentageEl.textContent = `+${currentOrders} orders (prev 0)`;
                     ordersSoldPercentageEl.className = 'packs-comparison positive';
                }
                 else {
                    ordersSoldPercentageEl.textContent = "N/A since";
                    ordersSoldPercentageEl.className = 'packs-comparison'; // Reset class
                }
            } catch (error) {
                 console.error("Error updating orders sold:", error);
                 ordersSoldCountEl.textContent = `Error`;
                 ordersSoldPercentageEl.textContent = "Error loading data";
                 ordersSoldPercentageEl.className = 'packs-comparison negative';
            }
        }

        if (ordersSoldYearSelect) ordersSoldYearSelect.addEventListener("change", updateOrdersSold);
        if (ordersSoldCompareYearSelect) ordersSoldCompareYearSelect.addEventListener("change", updateOrdersSold);

        updateOrdersSold(); // Initial update

        /*** ===========================
        *  SALES PER DEPARTMENT BAR CHART
        *  =========================== ***/
        const ctxSalesPerDepartment = document.getElementById("salesPerDepartmentChart");
        let salesPerDepartmentChart = null;
        let currentTimePeriod = 'weekly'; // Default

        function loadSalesByCategory(timePeriod) {
            if (!ctxSalesPerDepartment) {
                console.error("Sales Per Department Chart canvas not found");
                return;
            }
            const chartContext = ctxSalesPerDepartment.getContext('2d');
            const url = `/backend/get_sales_by_category.php?period=${timePeriod}`;
            console.log(`Fetching ${timePeriod} sales data from:`, url);

            fetch(url)
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                    return response.text(); // Get raw text first
                })
                .then(text => {
                    console.log(`Raw ${timePeriod} sales data response:`, text);
                    try {
                        const data = JSON.parse(text); // Try parsing
                        if (data.error) {
                            throw new Error(`Server Error: ${data.message}`);
                        }

                        if (salesPerDepartmentChart) salesPerDepartmentChart.destroy();

                        salesPerDepartmentChart = new Chart(chartContext, {
                            type: "bar",
                            data: {
                                labels: data.categories,
                                datasets: [
                                    {
                                        label: `${data.currentYear.year} Sales`,
                                        data: data.currentYear.data,
                                        backgroundColor: "rgba(74, 144, 226, 0.8)", // Blue
                                        borderColor: "rgba(74, 144, 226, 1)",
                                        borderWidth: 1,
                                        borderRadius: 4
                                    },
                                    {
                                        label: `${data.lastYear.year} Sales`,
                                        data: data.lastYear.data,
                                        backgroundColor: "rgba(155, 155, 155, 0.7)", // Gray
                                        borderColor: "rgba(155, 155, 155, 1)",
                                        borderWidth: 1,
                                        borderRadius: 4
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { position: 'top' },
                                    title: { display: false } // Title handled by chart-header h3
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        title: { display: true, text: 'Number of Orders' }
                                    },
                                    x: { ticks: { autoSkip: false } }
                                }
                            }
                        });
                    } catch (e) {
                        console.error('Error parsing sales data JSON:', e, "Raw text:", text);
                         // Optionally display an error message on the chart canvas
                         chartContext.clearRect(0, 0, ctxSalesPerDepartment.width, ctxSalesPerDepartment.height);
                         chartContext.fillStyle = '#dc3545';
                         chartContext.textAlign = 'center';
                         chartContext.fillText('Error loading chart data.', ctxSalesPerDepartment.width / 2, ctxSalesPerDepartment.height / 2);
                    }
                })
                .catch(error => {
                     console.error(`Error loading ${timePeriod} sales data:`, error);
                     if (ctxSalesPerDepartment) {
                          const chartContext = ctxSalesPerDepartment.getContext('2d');
                          chartContext.clearRect(0, 0, ctxSalesPerDepartment.width, ctxSalesPerDepartment.height);
                          chartContext.fillStyle = '#dc3545';
                          chartContext.textAlign = 'center';
                          chartContext.fillText('Error loading chart data.', ctxSalesPerDepartment.width / 2, ctxSalesPerDepartment.height / 2);
                     }
                });
        }

        document.querySelectorAll('.time-period-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.time-period-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                currentTimePeriod = this.getAttribute('data-period');
                loadSalesByCategory(currentTimePeriod);
            });
        });

        if (ctxSalesPerDepartment) {
             loadSalesByCategory(currentTimePeriod); // Initial load
        } else {
             console.error("Sales per Department canvas element not found on initial load.");
        }

        console.log("Dashboard JS Fully Initialized.");
    });
    </script>

</body>
</html>