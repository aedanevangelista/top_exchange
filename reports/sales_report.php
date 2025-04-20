<?php
session_start();
include "../backend/db_connection.php";

// Check if user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    header("Location: ../index.php");
    exit;
}

// Check if user's role has access to Reports page
$userRole = $_SESSION['role'];
$hasAccess = false;

$roleQuery = "SELECT pages FROM roles WHERE role_name = ?";
$stmt = $conn->prepare($roleQuery);
$stmt->bind_param("s", $userRole);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $pages = $row['pages'];
    if (strpos($pages, 'Reports') !== false) {
        $hasAccess = true;
    }
}

if (!$hasAccess) {
    header("Location: ../admin-dashboard/dashboard.php?error=unauthorized");
    exit;
}

// Set default values
$period_type = $_GET['period_type'] ?? 'daily';
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// SQL query based on period type
$sql = "";
$group_by = "";
$date_format = "";

switch($period_type) {
    case 'daily':
        $date_format = "DATE(order_date)";
        $group_by = "DATE(order_date)";
        break;
    case 'weekly':
        $date_format = "CONCAT('Week ', WEEK(order_date), ' of ', YEAR(order_date))";
        $group_by = "WEEK(order_date), YEAR(order_date)";
        break;
    case 'monthly':
        $date_format = "CONCAT(MONTHNAME(order_date), ' ', YEAR(order_date))";
        $group_by = "MONTH(order_date), YEAR(order_date)";
        break;
}

$sql = "SELECT 
            $date_format AS period,
            COUNT(*) AS total_orders,
            SUM(total_amount) AS total_sales,
            AVG(total_amount) AS average_order_value,
            COUNT(DISTINCT username) AS unique_customers
        FROM orders 
        WHERE order_date BETWEEN ? AND ?
        AND status = 'Completed'
        GROUP BY $group_by
        ORDER BY MIN(order_date) DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$sales_data = [];
while ($row = $result->fetch_assoc()) {
    $sales_data[] = $row;
}

// Calculate summary statistics
$total_period_sales = 0;
$total_period_orders = 0;
$total_period_customers = 0;

foreach ($sales_data as $data) {
    $total_period_sales += $data['total_sales'];
    $total_period_orders += $data['total_orders'];
    $total_period_customers = max($total_period_customers, $data['unique_customers']);
}

$average_order_value = $total_period_orders > 0 ? $total_period_sales / $total_period_orders : 0;

$page_title = "Sales Reports";
include_once "../admin-dashboard/header.php";
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Sales Reports</h1>
        <div>
            <button id="exportPDF" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-download fa-sm text-white-50"></i> Export PDF
            </button>
            <button id="exportCSV" class="d-none d-sm-inline-block btn btn-sm btn-success shadow-sm ml-2">
                <i class="fas fa-file-csv fa-sm text-white-50"></i> Export CSV
            </button>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Options</h6>
        </div>
        <div class="card-body">
            <form method="get" class="row">
                <div class="col-md-4 mb-3">
                    <label for="period_type">Report Type</label>
                    <select name="period_type" id="period_type" class="form-control">
                        <option value="daily" <?php echo $period_type == 'daily' ? 'selected' : ''; ?>>Daily Report</option>
                        <option value="weekly" <?php echo $period_type == 'weekly' ? 'selected' : ''; ?>>Weekly Report</option>
                        <option value="monthly" <?php echo $period_type == 'monthly' ? 'selected' : ''; ?>>Monthly Report</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="start_date">Start Date</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="end_date">End Date</label>
                    <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-2 mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-block">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Stats Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Sales</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">₱<?php echo number_format($total_period_sales, 2); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Total Orders</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_period_orders; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Average Order Value</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">₱<?php echo number_format($average_order_value, 2); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tags fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Unique Customers</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_period_customers; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Data Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Sales Data</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="salesTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Total Orders</th>
                            <th>Total Sales</th>
                            <th>Avg. Order Value</th>
                            <th>Unique Customers</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($sales_data as $data): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($data['period']); ?></td>
                            <td><?php echo $data['total_orders']; ?></td>
                            <td>₱<?php echo number_format($data['total_sales'], 2); ?></td>
                            <td>₱<?php echo number_format($data['average_order_value'], 2); ?></td>
                            <td><?php echo $data['unique_customers']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Sales Chart -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Sales Trend</h6>
        </div>
        <div class="card-body">
            <div class="chart-area">
                <canvas id="salesChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    $('#salesTable').DataTable();
    
    // Setup Chart
    var ctx = document.getElementById("salesChart");
    var data = <?php 
        $labels = [];
        $sales = [];
        $orders = [];
        
        // Reverse array to show oldest data first
        $chart_data = array_reverse($sales_data);
        
        foreach($chart_data as $data) {
            $labels[] = $data['period'];
            $sales[] = $data['total_sales'];
            $orders[] = $data['total_orders'];
        }
        
        echo json_encode([
            'labels' => $labels,
            'sales' => $sales,
            'orders' => $orders
        ]);
    ?>;
    
    var salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: "Sales Amount",
                lineTension: 0.3,
                backgroundColor: "rgba(78, 115, 223, 0.05)",
                borderColor: "rgba(78, 115, 223, 1)",
                pointRadius: 3,
                pointBackgroundColor: "rgba(78, 115, 223, 1)",
                pointBorderColor: "rgba(78, 115, 223, 1)",
                pointHoverRadius: 3,
                pointHoverBackgroundColor: "rgba(78, 115, 223, 1)",
                pointHoverBorderColor: "rgba(78, 115, 223, 1)",
                pointHitRadius: 10,
                pointBorderWidth: 2,
                data: data.sales,
                yAxisID: 'y-axis-1',
            }, {
                label: "Order Count",
                lineTension: 0.3,
                backgroundColor: "rgba(28, 200, 138, 0.05)",
                borderColor: "rgba(28, 200, 138, 1)",
                pointRadius: 3,
                pointBackgroundColor: "rgba(28, 200, 138, 1)",
                pointBorderColor: "rgba(28, 200, 138, 1)",
                pointHoverRadius: 3,
                pointHoverBackgroundColor: "rgba(28, 200, 138, 1)",
                pointHoverBorderColor: "rgba(28, 200, 138, 1)",
                pointHitRadius: 10,
                pointBorderWidth: 2,
                data: data.orders,
                yAxisID: 'y-axis-2',
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                xAxes: [{
                    gridLines: {
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        maxTicksLimit: 7
                    }
                }],
                yAxes: [{
                    id: 'y-axis-1',
                    position: 'left',
                    ticks: {
                        beginAtZero: true,
                        callback: function(value) {
                            return '₱' + value;
                        }
                    },
                    gridLines: {
                        color: "rgb(234, 236, 244)",
                        zeroLineColor: "rgb(234, 236, 244)",
                        drawBorder: false,
                        borderDash: [2],
                        zeroLineBorderDash: [2]
                    }
                }, {
                    id: 'y-axis-2',
                    position: 'right',
                    ticks: {
                        beginAtZero: true
                    },
                    gridLines: {
                        display: false
                    }
                }],
            },
            legend: {
                display: true
            },
            tooltips: {
                backgroundColor: "rgb(255,255,255)",
                bodyFontColor: "#858796",
                titleMarginBottom: 10,
                titleFontColor: '#6e707e',
                titleFontSize: 14,
                borderColor: '#dddfeb',
                borderWidth: 1,
                xPadding: 15,
                yPadding: 15,
                displayColors: false,
                intersect: false,
                mode: 'index',
                caretPadding: 10,
                callbacks: {
                    label: function(tooltipItem, chart) {
                        var datasetLabel = chart.datasets[tooltipItem.datasetIndex].label || '';
                        if (tooltipItem.datasetIndex === 0) {
                            return datasetLabel + ': ₱' + number_format(tooltipItem.yLabel);
                        } else {
                            return datasetLabel + ': ' + tooltipItem.yLabel;
                        }
                    }
                }
            }
        }
    });
    
    // Export functionality
    document.getElementById('exportCSV').addEventListener('click', function() {
        exportTableToCSV('sales_report_<?php echo date("Y-m-d"); ?>.csv');
    });
    
    document.getElementById('exportPDF').addEventListener('click', function() {
        window.print();
    });
    
    function number_format(number) {
        return new Intl.NumberFormat().format(number);
    }
    
    function exportTableToCSV(filename) {
        var csv = [];
        var rows = document.querySelectorAll("#salesTable tr");
        
        for (var i = 0; i < rows.length; i++) {
            var row = [], cols = rows[i].querySelectorAll("td, th");
            
            for (var j = 0; j < cols.length; j++) 
                row.push('"' + cols[j].innerText + '"');
            
            csv.push(row.join(","));        
        }
        
        // Download CSV file
        downloadCSV(csv.join("\n"), filename);
    }
    
    function downloadCSV(csv, filename) {
        var csvFile;
        var downloadLink;

        // CSV file
        csvFile = new Blob([csv], {type: "text/csv"});

        // Download link
        downloadLink = document.createElement("a");

        // File name
        downloadLink.download = filename;

        // Create a link to the file
        downloadLink.href = window.URL.createObjectURL(csvFile);

        // Hide download link
        downloadLink.style.display = "none";

        // Add the link to DOM
        document.body.appendChild(downloadLink);

        // Click download link
        downloadLink.click();
    }
});
</script>

<?php
include_once "../admin-dashboard/footer.php";
?>