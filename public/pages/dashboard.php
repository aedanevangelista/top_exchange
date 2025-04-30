<?php
// Start session
session_start();

// Check if user is logged in, if not, redirect to login page
if (!isset($_SESSION['admin_user_id'])) {
    header("Location: ../../index.php");
    exit;
}

// Include database connection
include '../../backend/db_connection.php';

// Define the constant to indicate file is included from index.php
define('INCLUDED_FROM_INDEX', true);

// Get order counts by status
$stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
$stmt->execute();
$result = $stmt->get_result();
$orderCounts = [];

while ($row = $result->fetch_assoc()) {
    $orderCounts[$row['status']] = $row['count'];
}

// Get total number of orders
$totalOrders = array_sum($orderCounts);
$pendingOrdersCount = $orderCounts['Pending'] ?? 0;
$activeOrdersCount = $orderCounts['Active'] ?? 0;
$completedOrdersCount = $orderCounts['Completed'] ?? 0;

// Function to calculate percentage
function calculatePercentage($part, $total) {
    if ($total == 0) return 0;
    return round(($part / $total) * 100);
}

// Calculate percentages
$pendingPercentage = calculatePercentage($pendingOrdersCount, $totalOrders);
$activePercentage = calculatePercentage($activeOrdersCount, $totalOrders);
$completedPercentage = calculatePercentage($completedOrdersCount, $totalOrders);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Top Exchange</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Header container with notification badge */
        .overview-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
    </style>
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
                <div class="sales-department-chart" style="height: 300px; position: relative;">
                    <canvas id="salesDepartmentChart"></canvas>
                </div>
            </div>

            <div class="status-box-container">
                <h3>ORDER STATUSES</h3>
                <div class="status-boxes">
                    <div class="status-box pending">
                        <div class="status-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="status-content">
                            <div class="status-title">Pending</div>
                            <div class="status-count"><?php echo $pendingOrdersCount; ?></div>
                            <div class="status-percentage"><?php echo $pendingPercentage; ?>% of orders</div>
                        </div>
                    </div>
                    
                    <div class="status-box active">
                        <div class="status-icon">
                            <i class="fas fa-spinner"></i>
                        </div>
                        <div class="status-content">
                            <div class="status-title">Active</div>
                            <div class="status-count"><?php echo $activeOrdersCount; ?></div>
                            <div class="status-percentage"><?php echo $activePercentage; ?>% of orders</div>
                        </div>
                    </div>
                    
                    <div class="status-box completed">
                        <div class="status-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="status-content">
                            <div class="status-title">Completed</div>
                            <div class="status-count"><?php echo $completedOrdersCount; ?></div>
                            <div class="status-percentage"><?php echo $completedPercentage; ?>% of orders</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // DOM Elements
        const yearSelect = document.getElementById('year-select');
        const packsSoldYear = document.getElementById('packs-sold-year');
        const packsSoldCompareYear = document.getElementById('packs-sold-compare-year');
        const packsSoldCount = document.getElementById('packs-sold-count');
        const packsSoldPercentage = document.getElementById('packs-sold-percentage');

        // Function to get years for dropdowns
        function populateYearDropdowns() {
            const currentYear = new Date().getFullYear();
            const years = [currentYear, currentYear - 1, currentYear - 2];

            // Populate all year dropdowns
            [yearSelect, packsSoldYear, packsSoldCompareYear].forEach(dropdown => {
                dropdown.innerHTML = '';
                years.forEach(year => {
                    const option = document.createElement('option');
                    option.value = year;
                    option.textContent = year;
                    dropdown.appendChild(option);
                });
            });

            // Set default selections
            packsSoldYear.value = currentYear;
            packsSoldCompareYear.value = currentYear - 1;
        }

        // Function to fetch and display client orders data
        async function fetchAndDisplayClientOrders(year) {
            try {
                const response = await fetch(`/backend/get_client_orders.php?year=${year}`);
                const data = await response.json();

                if (data.success) {
                    renderClientOrdersChart(data.data);
                } else {
                    console.error('Error fetching client orders data:', data.message);
                }
            } catch (error) {
                console.error('Error fetching client orders data:', error);
            }
        }

        // Function to render the client orders chart
        function renderClientOrdersChart(data) {
            const ctx = document.getElementById('clientOrdersChart').getContext('2d');

            if (window.clientOrdersChart) {
                window.clientOrdersChart.destroy();
            }

            window.clientOrdersChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        label: 'Number of Orders',
                        data: data,
                        backgroundColor: '#4b7bec',
                        borderColor: '#3867d6',
                        borderWidth: 1,
                        borderRadius: 5,
                        barPercentage: 0.6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }

        // Function to fetch and display the total orders sold
        async function fetchAndDisplayPacksSold() {
            const selectedYear = packsSoldYear.value;
            const compareYear = packsSoldCompareYear.value;

            try {
                const response = await fetch(`/backend/get_packs_sold.php?year=${selectedYear}&compare_year=${compareYear}`);
                const data = await response.json();

                if (data.success) {
                    // Update the packs sold count
                    packsSoldCount.textContent = `${data.totalOrders} Orders`;

                    // Update the comparison percentage
                    const percentageText = data.percentageChange >= 0 
                        ? `+${data.percentageChange}% since` 
                        : `${data.percentageChange}% since`;
                    packsSoldPercentage.textContent = percentageText;

                    // Update color based on growth or decline
                    if (data.percentageChange > 0) {
                        packsSoldPercentage.style.color = '#2ecc71';
                    } else if (data.percentageChange < 0) {
                        packsSoldPercentage.style.color = '#e74c3c';
                    } else {
                        packsSoldPercentage.style.color = '#7f8c8d';
                    }
                } else {
                    console.error('Error fetching packs sold data:', data.message);
                }
            } catch (error) {
                console.error('Error fetching packs sold data:', error);
            }
        }

        // Function to fetch and display sales per department
        async function fetchAndDisplaySalesDepartment() {
            try {
                const response = await fetch('/backend/get_sales_department.php');
                const data = await response.json();

                if (data.success) {
                    renderSalesDepartmentChart(data.departments, data.sales);
                } else {
                    console.error('Error fetching sales department data:', data.message);
                }
            } catch (error) {
                console.error('Error fetching sales department data:', error);
            }
        }

        // Function to render the sales department chart
        function renderSalesDepartmentChart(departments, sales) {
            const ctx = document.getElementById('salesDepartmentChart').getContext('2d');

            if (window.salesDepartmentChart) {
                window.salesDepartmentChart.destroy();
            }

            window.salesDepartmentChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: departments,
                    datasets: [{
                        data: sales,
                        backgroundColor: [
                            '#4b7bec',
                            '#45aaf2',
                            '#2ecc71',
                            '#26de81',
                            '#f39c12',
                            '#fd9644',
                            '#e74c3c',
                            '#eb3b5a',
                            '#a55eea',
                            '#8854d0'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    },
                    cutout: '60%'
                }
            });
        }

        // Initialize everything when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            populateYearDropdowns();

            // Initial data fetch
            fetchAndDisplayClientOrders(yearSelect.value);
            fetchAndDisplayPacksSold();
            fetchAndDisplaySalesDepartment();

            // Set up event listeners
            yearSelect.addEventListener('change', () => {
                fetchAndDisplayClientOrders(yearSelect.value);
            });

            packsSoldYear.addEventListener('change', fetchAndDisplayPacksSold);
            packsSoldCompareYear.addEventListener('change', fetchAndDisplayPacksSold);
        });
    </script>
</body>
</html>