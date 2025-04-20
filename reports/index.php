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

$page_title = "Reports Dashboard";
include_once "../admin-dashboard/header.php";
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Reports Dashboard</h1>
    </div>

    <!-- Report Cards -->
    <div class="row">
        <!-- Sales Report Card -->
        <div class="col-xl-6 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="h5 mb-0 font-weight-bold text-gray-800">Sales Reports</div>
                            <p class="mt-2">View comprehensive sales data over different time periods. Track sales trends, total revenue, and average order values.</p>
                            <a href="sales_report.php" class="btn btn-primary btn-sm mt-3">
                                <i class="fas fa-chart-line mr-1"></i> View Sales Reports
                            </a>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Performance Card -->
        <div class="col-xl-6 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="h5 mb-0 font-weight-bold text-gray-800">Product Performance</div>
                            <p class="mt-2">Analyze which products are selling best. See detailed metrics on quantity sold, revenue generated, and more.</p>
                            <a href="product_performance.php" class="btn btn-success btn-sm mt-3">
                                <i class="fas fa-box mr-1"></i> View Product Reports
                            </a>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-bar fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include_once "../admin-dashboard/footer.php";
?>