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

// Check if order number is provided
if (!isset($_GET['po_number'])) {
    header("Location: /LandingPage/orders.php");
    exit();
}

$po_number = $_GET['po_number'];

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

// Get logged in username
$username = $_SESSION['username'];

// Initialize variables
$orderDetails = null;
$orderItems = [];

// Get order details
$query = "SELECT * FROM orders WHERE po_number = ? AND username = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $po_number, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $orderDetails = $result->fetch_assoc();

    // Get order items
    $itemsQuery = "SELECT * FROM order_items WHERE po_number = ?";
    $itemsStmt = $conn->prepare($itemsQuery);
    $itemsStmt->bind_param("s", $po_number);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();

    while ($item = $itemsResult->fetch_assoc()) {
        $orderItems[] = $item;
    }
} else {
    // Order not found or doesn't belong to this user
    header("Location: /LandingPage/orders.php");
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Top Exchange Food Corp</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Favicon -->
    <link rel="icon" href="/LandingPage/images/resized_food_corp_logo.png" type="image/png" />
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

        .order-info-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.25rem;
        }

        .order-info-value {
            color: #6c757d;
            margin-bottom: 1rem;
        }

        .table th {
            font-weight: 600;
            color: #495057;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }

        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .timeline {
            position: relative;
            padding-left: 30px;
            margin-bottom: 50px;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 25px;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-item::before {
            content: "";
            position: absolute;
            left: -30px;
            top: 0;
            width: 2px;
            height: 100%;
            background-color: #dee2e6;
        }

        .timeline-item::after {
            content: "";
            position: absolute;
            left: -36px;
            top: 0;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background-color: var(--primary-color);
            border: 2px solid white;
        }

        .timeline-date {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 0.25rem;
        }

        .timeline-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .timeline-text {
            color: #6c757d;
            font-size: 0.95rem;
        }

        .order-summary {
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 1.5rem;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }

        .summary-total {
            font-weight: 700;
            font-size: 1.1rem;
            border-top: 1px solid #dee2e6;
            padding-top: 0.75rem;
            margin-top: 0.75rem;
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
                    <h1><i class="fas fa-file-invoice me-2"></i> Order Details</h1>
                    <p class="mb-0">Order #<?php echo htmlspecialchars($po_number); ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="/LandingPage/orders.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-2"></i> Back to Orders
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mb-5">
        <div class="row">
            <!-- Order Information -->
            <div class="col-lg-8">
                <!-- Order Details Card -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-info-circle me-2"></i> Order Information</span>
                        <span class="badge rounded-pill
                            <?php
                            if ($orderDetails['status'] === 'Pending') echo 'badge-pending';
                            elseif ($orderDetails['status'] === 'Active') echo 'badge-active';
                            elseif ($orderDetails['status'] === 'Completed') echo 'badge-completed';
                            elseif ($orderDetails['status'] === 'For Delivery') echo 'badge-delivery';
                            elseif ($orderDetails['status'] === 'Rejected') echo 'badge-rejected';
                            ?>">
                            <?php echo htmlspecialchars($orderDetails['status']); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="order-info-label">Order Number</div>
                                <div class="order-info-value"><?php echo htmlspecialchars($orderDetails['po_number']); ?></div>

                                <div class="order-info-label">Order Date</div>
                                <div class="order-info-value"><?php echo date('F j, Y', strtotime($orderDetails['order_date'])); ?></div>

                                <div class="order-info-label">Delivery Date</div>
                                <div class="order-info-value"><?php echo date('F j, Y', strtotime($orderDetails['delivery_date'])); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="order-info-label">Payment Method</div>
                                <div class="order-info-value"><?php echo htmlspecialchars($orderDetails['payment_method'] ?? 'Not specified'); ?></div>

                                <div class="order-info-label">Shipping Address</div>
                                <div class="order-info-value"><?php echo htmlspecialchars($orderDetails['shipping_address'] ?? 'Not specified'); ?></div>

                                <div class="order-info-label">Notes</div>
                                <div class="order-info-value"><?php echo htmlspecialchars($orderDetails['notes'] ?? 'No notes'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Items Card -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-shopping-basket me-2"></i> Order Items
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($orderItems)): ?>
                                        <?php foreach ($orderItems as $item): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-medium"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                                    <?php if (!empty($item['item_description'])): ?>
                                                        <small class="text-muted"><?php echo htmlspecialchars($item['item_description']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                                <td>₱<?php echo number_format($item['unit_price'], 2); ?></td>
                                                <td class="text-end">₱<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4">No items found for this order.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-receipt me-2"></i> Order Summary
                    </div>
                    <div class="card-body">
                        <div class="order-summary">
                            <div class="summary-item">
                                <span>Subtotal</span>
                                <span>₱<?php echo number_format($orderDetails['subtotal'] ?? $orderDetails['total_amount'], 2); ?></span>
                            </div>
                            <?php if (isset($orderDetails['shipping_fee']) && $orderDetails['shipping_fee'] > 0): ?>
                                <div class="summary-item">
                                    <span>Shipping Fee</span>
                                    <span>₱<?php echo number_format($orderDetails['shipping_fee'], 2); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($orderDetails['tax']) && $orderDetails['tax'] > 0): ?>
                                <div class="summary-item">
                                    <span>Tax</span>
                                    <span>₱<?php echo number_format($orderDetails['tax'], 2); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($orderDetails['discount']) && $orderDetails['discount'] > 0): ?>
                                <div class="summary-item">
                                    <span>Discount</span>
                                    <span>-₱<?php echo number_format($orderDetails['discount'], 2); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="summary-item summary-total">
                                <span>Total</span>
                                <span>₱<?php echo number_format($orderDetails['total_amount'], 2); ?></span>
                            </div>
                        </div>

                        <div class="mt-4">
                            <a href="/LandingPage/orders.php" class="btn btn-outline-primary w-100 mb-2">
                                <i class="fas fa-arrow-left me-2"></i> Back to Orders
                            </a>
                            <?php if ($orderDetails['status'] === 'Pending'): ?>
                                <button class="btn btn-danger w-100">
                                    <i class="fas fa-times me-2"></i> Cancel Order
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
