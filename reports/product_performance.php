<?php
session_start();
include "../backend/db_connection.php";
include "../backend/check_role.php";
checkRole('Reports');

// Set default values
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Query for product performance
$sql = "SELECT 
            JSON_EXTRACT(o.item, '$.item_description') as product_name,
            JSON_EXTRACT(o.item, '$.category') as category,
            COUNT(*) as order_count,
            SUM(JSON_EXTRACT(o.item, '$.quantity')) as total_quantity,
            SUM(JSON_EXTRACT(o.item, '$.quantity') * JSON_EXTRACT(o.item, '$.price')) as total_revenue
        FROM 
            orders o,
            JSON_TABLE(o.orders, '$[*]' COLUMNS(
                item JSON PATH '$'
            )) as items
        WHERE 
            o.order_date BETWEEN ? AND ?
            AND o.status = 'Completed'
        GROUP BY 
            JSON_EXTRACT(o.item, '$.item_description'),
            JSON_EXTRACT(o.item, '$.category')
        ORDER BY 
            total_revenue DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$product_data = [];
while ($row = $result->fetch_assoc()) {
    // Clean up JSON strings
    $row['product_name'] = trim($row['product_name'], '"');
    $row['category'] = trim($row['category'], '"');
    $product_data[] = $row;
}

// Calculate totals
$total_revenue = 0;
$total_quantity = 0;
foreach ($product_data as $data) {
    $total_revenue += $data['total_revenue'];
    $total_quantity += $data['total_quantity'];
}

// Get category data for pie chart
$category_data = [];
foreach ($product_data as $data) {
    if (!isset($category_data[$data['category']])) {
        $category_data[$data['category']] = 0;
    }
    $category_data[$data['category']] += $data['total_revenue'];
}

// Include header
include_once "../admin-dashboard/header.php";
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Product Performance Report</h1>
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
                <div class="col-md-5 mb-3">
                    <label for="start_date">Start Date</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-5 mb-3">
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
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Revenue</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">₱<?php echo number_format($total_revenue, 2); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Total Quantity Sold</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_quantity); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-box fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Total Products Sold</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($product_data); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row">
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Top 10 Products by Revenue</h6>
                </div>
                <div class="card-body">
                    <div class="chart-bar">
                        <canvas id="topProductsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Sales by Category</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie">
                        <canvas id="categorySalesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Data Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Product Performance</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="productTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Order Count</th>
                            <th>Quantity Sold</th>
                            <th>Total Revenue</th>
                            <th>% of Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($product_data as $data): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($data['product_name']); ?></td>
                            <td><?php echo htmlspecialchars($data['category']); ?></td>
                            <td><?php echo $data['order_count']; ?></td>
                            <td><?php echo number_format($data['total_quantity']); ?></td>
                            <td>₱<?php echo number_format($data['total_revenue'], 2); ?></td>
                            <td><?php echo number_format(($data['total_revenue'] / $total_revenue) * 100, 1); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    $('#productTable').DataTable();
    
    // Setup Top Products Chart
    var topProducts = <?php 
        $top_products = array_slice($product_data, 0, 10);
        $labels = [];
        $revenues = [];
        $quantities = [];
        
        foreach($top_products as $product) {
            $labels[] = $product['product_name'];
            $revenues[] = $product['total_revenue'];
            $quantities[] = $product['total_quantity'];
        }
        
        echo json_encode([
            'labels' => $labels,
            'revenues' => $revenues,
            'quantities' => $quantities
        ]);
    ?>;
    
    var ctx = document.getElementById("topProductsChart");
    var topProductsChart = new Chart(ctx, {
        type: 'horizontalBar',
        data: {
            labels: topProducts.labels,
            datasets: [{
                label: "Revenue",
                backgroundColor: "rgba(78, 115, 223, 0.8)",
                hoverBackgroundColor: "rgba(78, 115, 223, 1)",
                borderColor: "rgba(78, 115, 223, 1)",
                data: topProducts.revenues,
            }],
        },
        options: {
            maintainAspectRatio: false,
            layout: {
                padding: {
                    left: 10,
                    right: 25,
                    top: 25,
                    bottom: 0
                }
            },
            scales: {
                xAxes: [{
                    ticks: {
                        beginAtZero: true,
                        callback: function(value) {
                            return '₱' + number_format(value);
                        }
                    },
                    gridLines: {
                        display: false,
                        drawBorder: false
                    },
                    maxBarThickness: 25,
                }],
                yAxes: [{
                    ticks: {
                        callback: function(value) {
                            if (value.length > 15) {
                                return value.substr(0, 15) + '...';
                            }
                            return value;
                        }
                    }
                }],
            },
            tooltips: {
                callbacks: {
                    label: function(tooltipItem, chart) {
                        return 'Revenue: ₱' + number_format(tooltipItem.xLabel);
                    }
                }
            }
        }
    });
    
    // Setup Category Sales Chart
    var categoryData = <?php 
        $category_labels = array_keys($category_data);
        $category_values = array_values($category_data);
        
        echo json_encode([
            'labels' => $category_labels,
            'values' => $category_values
        ]);
    ?>;
    
    var ctx2 = document.getElementById("categorySalesChart");
    var categorySalesChart = new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: categoryData.labels,
            datasets: [{
                data: categoryData.values,
                backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#5a5c69', '#e83e8c', '#fd7e14'],
                hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf', '#dda20a', '#be2617', '#60616f', '#373840', '#c71666', '#c85e07'],
                hoverBorderColor: "rgba(234, 236, 244, 1)",
            }],
        },
        options: {
            maintainAspectRatio: false,
            tooltips: {
                callbacks: {
                    label: function(tooltipItem, data) {
                        var dataset = data.datasets[tooltipItem.datasetIndex];
                        var total = dataset.data.reduce(function(previousValue, currentValue) {
                            return previousValue + currentValue;
                        });
                        var currentValue = dataset.data[tooltipItem.index];
                        var percentage = Math.round((currentValue/total) * 100);
                        return data.labels[tooltipItem.index] + ': ₱' + number_format(currentValue) + ' (' + percentage + '%)';
                    }
                }
            },
            legend: {
                position: 'right'
            },
            cutoutPercentage: 70,
        },
    });
    
    // Export functionality
    document.getElementById('exportCSV').addEventListener('click', function() {
        exportTableToCSV('product_performance_<?php echo date("Y-m-d"); ?>.csv');
    });
    
    document.getElementById('exportPDF').addEventListener('click', function() {
        window.print();
    });
    
    function number_format(number) {
        return new Intl.NumberFormat().format(number);
    }
    
    function exportTableToCSV(filename) {
        var csv = [];
        var rows = document.querySelectorAll("#productTable tr");
        
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
// Include footer
include_once "../admin-dashboard/footer.php";
?>