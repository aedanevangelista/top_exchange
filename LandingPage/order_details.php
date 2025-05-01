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
if (!isset($_GET['po_number']) || empty($_GET['po_number'])) {
    $_SESSION['error'] = "No order specified.";
    header("Location: orders.php");
    exit();
}

$po_number = $_GET['po_number'];

// Database connection
include_once('db_connection.php');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE po_number = ? AND username = ?");
$stmt->bind_param("ss", $po_number, $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Order not found or you don't have permission to view it.";
    $conn->close();
    header("Location: orders.php");
    exit();
}

$order = $result->fetch_assoc();

// Parse the order items from JSON
$orderItems = json_decode($order['orders'], true);

// Check if JSON decoding was successful
if ($orderItems === null && json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error: " . json_last_error_msg() . " for order " . $po_number);
    $orderItems = [];
}

// Calculate totals
$subtotal = 0;
foreach ($orderItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
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
            padding-top: 80px;
        }
        
        .page-header {
            background: linear-gradient(135deg, #9a7432 0%, #c9a158 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
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
        
        .order-detail-row {
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .order-detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--secondary-color);
        }
        
        .order-item {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            transition: var(--transition);
        }
        
        .order-item:hover {
            background-color: #f8f9fa;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .item-name {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.25rem;
        }
        
        .item-category {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 0.85rem;
        }
        
        .item-packaging {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .item-price {
            font-weight: 600;
            color: var(--secondary-color);
        }
        
        .item-quantity {
            background-color: #f8f9fa;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: 500;
        }
        
        .item-total {
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .order-totals {
            background-color: #f8f9fa;
            padding: 1.5rem;
            border-radius: 0 0 var(--border-radius) var(--border-radius);
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .total-row:last-child {
            margin-bottom: 0;
            padding-top: 0.5rem;
            border-top: 1px solid #dee2e6;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .btn-back {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }
        
        .btn-back:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
        }
        
        .special-instructions {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-top: 1rem;
            font-style: italic;
        }
        
        @media (max-width: 768px) {
            .order-item {
                flex-direction: column;
            }
            
            .item-details {
                margin-bottom: 1rem;
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
                    <h1><i class="fas fa-file-invoice me-2"></i> Order Details</h1>
                    <p class="mb-0">Order #<?php echo htmlspecialchars($po_number); ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="orders.php" class="btn btn-light">
                        <i class="fas fa-arrow-left me-2"></i> Back to Orders
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container mb-5">
        <div class="row">
            <div class="col-lg-8">
                <!-- Order Items -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-shopping-basket me-2"></i> Order Items
                        </div>
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
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($orderItems)): ?>
                            <?php foreach ($orderItems as $item): ?>
                                <div class="order-item">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <div class="item-name"><?php echo htmlspecialchars($item['item_description']); ?></div>
                                            <?php if (!empty($item['category'])): ?>
                                                <div class="item-category">Category: <?php echo htmlspecialchars($item['category']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($item['packaging'])): ?>
                                                <div class="item-packaging"><?php echo htmlspecialchars($item['packaging']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-2 text-md-center">
                                            <div class="item-price">₱<?php echo number_format($item['price'], 2); ?></div>
                                        </div>
                                        <div class="col-md-2 text-md-center">
                                            <span class="item-quantity"><?php echo $item['quantity']; ?></span>
                                        </div>
                                        <div class="col-md-2 text-md-end">
                                            <div class="item-total">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-4 text-center">
                                <p class="mb-0 text-muted">No items found for this order.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="order-totals">
                        <div class="total-row">
                            <span>Subtotal:</span>
                            <span>₱<?php echo number_format($order['subtotal'], 2); ?></span>
                        </div>
                        <div class="total-row">
                            <span>Total Amount:</span>
                            <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($order['special_instructions'])): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-comment-alt me-2"></i> Special Instructions
                    </div>
                    <div class="card-body">
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['special_instructions'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-4">
                <!-- Order Details -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-2"></i> Order Information
                    </div>
                    <div class="card-body">
                        <div class="order-detail-row">
                            <div class="detail-label">Order Number</div>
                            <div><?php echo htmlspecialchars($order['po_number']); ?></div>
                        </div>
                        <div class="order-detail-row">
                            <div class="detail-label">Order Date</div>
                            <div><?php echo date('F j, Y', strtotime($order['order_date'])); ?></div>
                        </div>
                        <div class="order-detail-row">
                            <div class="detail-label">Delivery Date</div>
                            <div><?php echo date('F j, Y', strtotime($order['delivery_date'])); ?> (<?php echo date('l', strtotime($order['delivery_date'])); ?>)</div>
                        </div>
                        <div class="order-detail-row">
                            <div class="detail-label">Delivery Address</div>
                            <div><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></div>
                        </div>
                        <div class="order-detail-row">
                            <div class="detail-label">Contact Number</div>
                            <div><?php echo htmlspecialchars($order['contact_number']); ?></div>
                        </div>
                        <div class="order-detail-row">
                            <div class="detail-label">Payment Method</div>
                            <div>Check Payment</div>
                        </div>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-cog me-2"></i> Actions
                    </div>
                    <div class="card-body">
                        <a href="orders.php" class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-arrow-left me-2"></i> Back to Orders
                        </a>
                        <?php if ($order['status'] === 'Pending'): ?>
                        <button class="btn btn-outline-danger w-100" onclick="confirmCancel('<?php echo htmlspecialchars($order['po_number']); ?>')">
                            <i class="fas fa-times-circle me-2"></i> Cancel Order
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script>
        function confirmCancel(orderNumber) {
            if (confirm('Are you sure you want to cancel this order? This action cannot be undone.')) {
                window.location.href = 'cancel_order.php?po_number=' + encodeURIComponent(orderNumber);
            }
        }
    </script>
</body>
</html>
