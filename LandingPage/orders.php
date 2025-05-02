<?php
// Start the session and prevent caching
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: /LandingPage/login.php");
    exit();
}

// Database connection
$servername = "127.0.0.1:3306";
$username = "u701062148_top_exchange";
$password = "Aedanpogi123";
$dbname = "u701062148_top_exchange";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get client information
$username = $_SESSION['username'];

// Initialize variables
$orders = [];
$totalSpent = 0;
$orderStats = [
    'all' => 0,
    'pending' => 0,
    'active' => 0,
    'completed' => 0,
    'delivery' => 0,
    'rejected' => 0
];

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$dateFrom = $_GET['from'] ?? '';
$dateTo = $_GET['to'] ?? '';

// Base query
$query = "SELECT * FROM orders WHERE username = ?";

// Add status filter
if ($statusFilter !== 'all') {
    $query .= " AND status = ?";
}

// Add date range filter
if (!empty($dateFrom)) {
    $query .= " AND order_date >= ?";
}
if (!empty($dateTo)) {
    $query .= " AND order_date <= ?";
}

$query .= " ORDER BY order_date DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);

// Bind parameters based on filters
$paramTypes = "s";
$params = [$username];

if ($statusFilter !== 'all') {
    $paramTypes .= "s";
    $params[] = ucfirst($statusFilter);
}

if (!empty($dateFrom)) {
    $paramTypes .= "s";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $paramTypes .= "s";
    $params[] = $dateTo;
}

$stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Process orders
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
    $totalSpent += $row['total_amount'];

    // Update stats
    $orderStats['all']++;
    $orderStats[strtolower($row['status'])]++;
}

// Get total order stats (without filters)
$statsQuery = "SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'For Delivery' THEN 1 ELSE 0 END) as delivery,
    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
    FROM orders WHERE username = ?";
$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bind_param("s", $username);
$statsStmt->execute();
$statsResult = $statsStmt->get_result();
$totalStats = $statsResult->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Top Exchange Food Corp</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #9a7432;
            --primary-hover: #b08a3e;
            --secondary-color: #333;
            --light-color: #f8f9fa;
            --dark-color: #222;
            --accent-color: #dc3545;
            --box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
            --border-radius: 8px;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Montserrat', sans-serif;
            color: #495057;
            padding-top: 0;
        }

        .page-header {
            background: linear-gradient(135deg, #9a7432 0%, #c9a158 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: var(--box-shadow);
        }

        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            margin-bottom: 2rem;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        }

        .card-header {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            border-bottom: none;
            padding: 1.25rem 1.75rem;
        }

        .badge-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .badge-active {
            background-color: #d1e7ff;
            color: #084298;
        }

        .badge-completed {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .badge-delivery {
            background-color: #e2e3e5;
            color: #383d41;
        }

        .badge-rejected {
            background-color: #f8d7da;
            color: #842029;
        }

        .status-filter {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }

        .status-filter .btn {
            border-radius: 50px;
            padding: 0.5rem 1.25rem;
            font-weight: 500;
            border: 1px solid #dee2e6;
        }

        .status-filter .btn.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .order-item {
            transition: var(--transition);
            border-left: 3px solid transparent;
        }

        .order-item:hover {
            background-color: #f8f9fa;
        }

        .order-item.pending {
            border-left-color: #ffc107;
        }

        .order-item.active {
            border-left-color: #0d6efd;
        }

        .order-item.completed {
            border-left-color: #198754;
        }

        .order-item.delivery {
            border-left-color: #6c757d;
        }

        .order-item.rejected {
            border-left-color: #dc3545;
        }

        .order-id {
            font-weight: 600;
            color: var(--secondary-color);
        }

        .order-date {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .order-amount {
            font-weight: 600;
            color: var(--secondary-color);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 0;
        }

        .empty-state i {
            font-size: 3rem;
            color: #adb5bd;
            margin-bottom: 1rem;
        }

        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .pagination .page-link {
            color: var(--primary-color);
        }

        .date-filter {
            background-color: white;
            padding: 1rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 1.5rem;
        }

        .stats-card {
            text-align: center;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            background-color: white;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            margin-bottom: 1.5rem;
        }

        .stats-card .number {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--secondary-color);
        }

        .stats-card .label {
            color: #6c757d;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stats-card.total .number {
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .table-responsive {
                border: none;
            }

            .order-item {
                padding: 1rem 0;
            }

            .status-filter {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Include your header -->
    <?php include 'header.php'; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1><i class="fas fa-shopping-cart me-2"></i> My Orders</h1>
                    <p class="mb-0">View and track your order history</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="text-white-50 mb-1">Total Orders Placed</div>
                    <h2 class="mb-0"><?php echo $totalStats['total']; ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mb-5">
        <!-- Order Stats -->
        <div class="row mb-4">
            <div class="col-md-2 col-6">
                <div class="stats-card total">
                    <div class="number"><?php echo $totalStats['total']; ?></div>
                    <div class="label">Total</div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stats-card">
                    <div class="number"><?php echo $totalStats['pending']; ?></div>
                    <div class="label">Pending</div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stats-card">
                    <div class="number"><?php echo $totalStats['active']; ?></div>
                    <div class="label">Active</div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stats-card">
                    <div class="number"><?php echo $totalStats['completed']; ?></div>
                    <div class="label">Completed</div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stats-card">
                    <div class="number"><?php echo $totalStats['delivery']; ?></div>
                    <div class="label">Delivery</div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stats-card">
                    <div class="number"><?php echo $totalStats['rejected']; ?></div>
                    <div class="label">Rejected</div>
                </div>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-filter me-2"></i> Filter Orders
            </div>
            <div class="card-body">
                <form method="get" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Order Status</label>
                            <div class="status-filter">
                                <a href="?status=all<?php echo !empty($dateFrom) ? '&from='.$dateFrom : ''; ?><?php echo !empty($dateTo) ? '&to='.$dateTo : ''; ?>"
                                   class="btn btn-sm <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">
                                    All Orders
                                </a>
                                <a href="?status=pending<?php echo !empty($dateFrom) ? '&from='.$dateFrom : ''; ?><?php echo !empty($dateTo) ? '&to='.$dateTo : ''; ?>"
                                   class="btn btn-sm <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>">
                                    Pending
                                </a>
                                <a href="?status=active<?php echo !empty($dateFrom) ? '&from='.$dateFrom : ''; ?><?php echo !empty($dateTo) ? '&to='.$dateTo : ''; ?>"
                                   class="btn btn-sm <?php echo $statusFilter === 'active' ? 'active' : ''; ?>">
                                    Processing
                                </a>
                                <a href="?status=completed<?php echo !empty($dateFrom) ? '&from='.$dateFrom : ''; ?><?php echo !empty($dateTo) ? '&to='.$dateTo : ''; ?>"
                                   class="btn btn-sm <?php echo $statusFilter === 'completed' ? 'active' : ''; ?>">
                                    Completed
                                </a>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="dateFrom" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="dateFrom" name="from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="dateTo" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="dateTo" name="to" value="<?php echo htmlspecialchars($dateTo); ?>">
                        </div>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter me-1"></i> Apply Filters
                        </button>
                        <a href="orders.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> Clear All
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Orders List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-list-ol me-2"></i> Order List
                    <?php if ($statusFilter !== 'all' || !empty($dateFrom) || !empty($dateTo)): ?>
                        <small class="ms-2 text-white">(Filtered results)</small>
                    <?php endif; ?>
                </div>
                <div class="text-white">
                    <?php echo count($orders); ?> order(s) found
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($orders)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Order #</th>
                                    <th>Order Date</th>
                                    <th>Delivery Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr class="order-item <?php echo strtolower($order['status']); ?>">
                                        <td class="order-id"><?php echo htmlspecialchars($order['po_number']); ?></td>
                                        <td class="order-date"><?php echo date('M j, Y', strtotime($order['order_date'])); ?></td>
                                        <td class="order-date"><?php echo date('M j, Y', strtotime($order['delivery_date'])); ?></td>
                                        <td class="order-amount">₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="badge rounded-pill
                                                <?php
                                                if ($order['status'] === 'Pending') echo 'badge-pending';
                                                elseif ($order['status'] === 'Active') echo 'badge-active';
                                                elseif ($order['status'] === 'Completed') echo 'badge-completed';
                                                elseif ($order['status'] === 'For Delivery') echo 'badge-delivery';
                                                elseif ($order['status'] === 'Rejected') echo 'badge-rejected';
                                                ?>">
                                                <?php echo htmlspecialchars($order['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="order_details.php?po_number=<?php echo urlencode($order['po_number']); ?>"
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye me-1"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h5>No orders found</h5>
                        <p class="text-muted">No orders match your current filters.</p>
                        <a href="orders.php" class="btn btn-primary">
                            <i class="fas fa-undo me-1"></i> Reset Filters
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($orders)): ?>
                <div class="card-footer bg-white">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <p class="mb-0">Showing <?php echo count($orders); ?> order(s)</p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <p class="mb-0">Filtered Total: <strong>₱<?php echo number_format($totalSpent, 2); ?></strong></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script>
        // Set today as default end date if from date is set
        document.addEventListener('DOMContentLoaded', function() {
            const dateFrom = document.getElementById('dateFrom');
            const dateTo = document.getElementById('dateTo');

            if (dateFrom && dateTo) {
                dateFrom.addEventListener('change', function() {
                    if (this.value && !dateTo.value) {
                        dateTo.valueAsDate = new Date();
                    }
                });
            }
        });
    </script>
</body>
</html>