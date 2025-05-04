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
$clientData = [];
$balance = 0;
$companyInfo = '';
$contactInfo = '';
$businessProofs = [];
$orderStats = [
    'total_orders' => 0,
    'pending_orders' => 0,
    'completed_orders' => 0,
    'total_spent' => 0
];
$recentOrders = [];
$paymentStatus = 'Unpaid';

// Fetch client data from clients_accounts table
$query = "SELECT * FROM clients_accounts WHERE username = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $clientData = $result->fetch_assoc();
    $balance = $clientData['balance'] ?? 0;
    $companyInfo = $clientData['company'] ?: 'Not provided';
    $contactInfo = $clientData['phone'] ?: 'Not provided';

    // Process business proofs
    if ($clientData['business_proof'] && $clientData['business_proof'] != 'null') {
        $businessProofs = json_decode($clientData['business_proof'], true);
        if (!is_array($businessProofs)) {
            $businessProofs = [];
        }
    }

    // Get order statistics
    $orderQuery = "SELECT
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_orders,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_orders,
                    SUM(total_amount) as total_spent,
                    SUM(CASE WHEN status IN ('Active', 'Completed') THEN total_amount ELSE 0 END) as current_balance
                   FROM orders
                   WHERE username = ?";
    $orderStmt = $conn->prepare($orderQuery);
    $orderStmt->bind_param("s", $username);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();

    if ($orderResult->num_rows > 0) {
        $orderStats = $orderResult->fetch_assoc();
        // Update the balance to include active and completed orders
        $balance = $orderStats['current_balance'] ?? 0;
    }

    // Get recent orders (limit to 3)
    $recentOrderQuery = "SELECT po_number, order_date, delivery_date, total_amount, status
                         FROM orders
                         WHERE username = ?
                         ORDER BY order_date DESC
                         LIMIT 3";
    $recentOrderStmt = $conn->prepare($recentOrderQuery);
    $recentOrderStmt->bind_param("s", $username);
    $recentOrderStmt->execute();
    $recentOrderResult = $recentOrderStmt->get_result();

    while ($row = $recentOrderResult->fetch_assoc()) {
        $recentOrders[] = $row;
    }

    // Get current payment status
    $currentMonth = date('n');
    $currentYear = date('Y');
    $paymentQuery = "SELECT payment_status
                     FROM monthly_payments
                     WHERE username = ? AND month = ? AND year = ?
                     LIMIT 1";
    $paymentStmt = $conn->prepare($paymentQuery);
    $paymentStmt->bind_param("sii", $username, $currentMonth, $currentYear);
    $paymentStmt->execute();
    $paymentResult = $paymentStmt->get_result();

    if ($paymentResult->num_rows > 0) {
        $paymentData = $paymentResult->fetch_assoc();
        $paymentStatus = $paymentData['payment_status'] ?? 'Unpaid';
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Profile - Top Exchange Food Corp</title>
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

        .profile-header {
            background: linear-gradient(135deg, #9a7432 0%, #c9a158 100%);
            color: white;
            padding: 2.5rem 0;
            margin-top: auto;
            margin-bottom: 2.5rem;
            box-shadow: var(--box-shadow);
        }

        .profile-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: var(--transition);
            border: none;
        }

        .profile-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        }

        .profile-card-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1.25rem 1.75rem;
            font-weight: 600;
            font-size: 1.1rem;
            border-bottom: none;
        }

        .profile-card-body {
            padding: 1.75rem;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-card {
            text-align: center;
            padding: 1.75rem 1rem;
            border-radius: var(--border-radius);
            background-color: white;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            margin-bottom: 1.5rem;
            border: none;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            background-color: #f8f9fa;
        }

        .stat-icon {
            font-size: 2.25rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .stat-number {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
        }

        .order-status-badge {
            padding: 0.4em 0.7em;
            font-size: 0.75em;
            font-weight: 600;
            border-radius: 0.3rem;
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

        .badge-for-delivery {
            background-color: #e2e3e5;
            color: #383d41;
        }

        .proof-thumbnail {
            width: 100%;
            height: 160px;
            object-fit: cover;
            border-radius: 6px;
            cursor: pointer;
            transition: var(--transition);
            border: 1px solid #eee;
        }

        .proof-thumbnail:hover {
            transform: scale(1.03);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .payment-status {
            display: inline-block;
            padding: 0.5rem 1.25rem;
            border-radius: 50px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 1px;
        }

        .status-paid {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .status-unpaid {
            background-color: #f8d7da;
            color: #842029;
        }

        .status-partial {
            background-color: #fff3cd;
            color: #664d03;
        }

        .balance-display {
            font-size: 2.25rem;
            font-weight: 700;
            color: white;
            letter-spacing: 0.5px;
        }

        .section-title {
            position: relative;
            padding-bottom: 0.75rem;
            margin-bottom: 1.75rem;
            color: var(--secondary-color);
            font-weight: 600;
        }

        .section-title:after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 50px;
            height: 3px;
            background-color: var(--primary-color);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            font-weight: 500;
            letter-spacing: 0.5px;
            padding: 0.5rem 1.5rem;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }

        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
            font-weight: 500;
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .table th {
            font-weight: 600;
            color: #495057;
            border-top: none;
        }

        .table td {
            vertical-align: middle;
        }

        /* Modal for image preview */
        .modal-proof-img {
            max-width: 100%;
            max-height: 80vh;
            border-radius: 6px;
        }

        .info-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-size: 0.95rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }

        .client-name {
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .client-contact {
            font-size: 0.95rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <!-- Include your header -->
    <?php include 'header.php'; ?>

    <!-- Profile Header -->
    <div class="profile-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-2 text-center text-md-start">
                    <img src="/LandingPage/images/default-profile.jpg" alt="Profile Picture" class="profile-avatar">
                </div>
                <div class="col-md-6 text-center text-md-start mt-3 mt-md-0">
                    <h2 class="client-name"><?php echo htmlspecialchars($clientData['username'] ?? $username); ?></h2>
                    <p class="client-contact mb-1"><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($clientData['email'] ?? 'No email'); ?></p>
                    <p class="client-contact mb-0"><i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($contactInfo); ?></p>
                </div>
                <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                    <div class="d-flex flex-column align-items-center align-items-md-end">
                        <span class="text-white-50 mb-1" style="opacity: 0.8;">Current Balance</span>
                        <span class="balance-display">₱<?php echo number_format($balance, 2); ?></span>
                        <div class="mt-1">
                            <span class="payment-status
                                <?php
                                if ($paymentStatus === 'Fully Paid') echo 'status-paid';
                                elseif ($paymentStatus === 'Partially Paid') echo 'status-partial';
                                else echo 'status-unpaid';
                                ?>">
                                <?php echo htmlspecialchars($paymentStatus); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mb-5">
        <div class="row">
            <!-- Client Information Section -->
            <div class="col-lg-4 mb-4">
                <div class="profile-card">
                    <div class="profile-card-header">
                        <i class="fas fa-id-card me-2"></i> Client Information
                    </div>
                    <div class="profile-card-body">
                        <div class="mb-3">
                            <div class="info-label">Account Status</div>
                            <div class="info-value">
                                <span class="badge rounded-pill bg-<?php echo ($clientData['status'] ?? 'Active') === 'Active' ? 'success' : 'secondary'; ?>">
                                    <?php echo htmlspecialchars($clientData['status'] ?? 'Active'); ?>
                                </span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="info-label">Company</div>
                            <div class="info-value"><?php echo htmlspecialchars($companyInfo); ?></div>
                        </div>

                        <div class="mb-3">
                            <div class="info-label">Location</div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($clientData['city'] ?? 'Not specified'); ?>,
                                <?php echo htmlspecialchars($clientData['region'] ?? 'Not specified'); ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="info-label">Address</div>
                            <div class="info-value"><?php echo htmlspecialchars($clientData['company_address'] ?? 'Not provided'); ?></div>
                        </div>

                        <div class="mb-3">
                            <div class="info-label">Member Since</div>
                            <div class="info-value">
                                <?php
                                $createdAt = $clientData['created_at'] ?? '';
                                echo $createdAt ? date('F j, Y', strtotime($createdAt)) : 'Not available';
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Business Proofs Card -->
                <div class="profile-card">
                    <div class="profile-card-header">
                        <i class="fas fa-file-contract me-2"></i> Business Documentation
                    </div>
                    <div class="profile-card-body">
                        <?php if (!empty($businessProofs)): ?>
                            <div class="row g-3">
                                <?php foreach ($businessProofs as $proof): ?>
                                    <?php if (!empty($proof)): ?>
                                        <div class="col-6">
                                            <div class="border rounded p-2">
                                                <img src="<?php echo htmlspecialchars($proof); ?>"
                                                     class="proof-thumbnail w-100"
                                                     alt="Business Proof"
                                                     data-bs-toggle="modal"
                                                     data-bs-target="#proofModal"
                                                     data-proof-src="<?php echo htmlspecialchars($proof); ?>">
                                                <div class="text-center mt-2">
                                                    <small class="text-muted">Proof Document</small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-file-alt fa-3x mb-3 text-muted opacity-50"></i>
                                <h6 class="mb-2">No documents uploaded</h6>
                                <p class="text-muted small">This client hasn't uploaded any business proofs yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Client Activity Section -->
            <div class="col-lg-8">
                <!-- Stats Cards -->
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="stat-number"><?php echo $orderStats['total_orders']; ?></div>
                            <div class="stat-label">Total Orders</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-number"><?php echo $orderStats['pending_orders']; ?></div>
                            <div class="stat-label">Pending</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-number"><?php echo $orderStats['completed_orders']; ?></div>
                            <div class="stat-label">Completed</div>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="profile-card">
                    <div class="profile-card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-history me-2"></i> Recent Orders</span>
                        <a href="/LandingPage/orders.php" class="btn btn-sm btn-outline-light">View All</a>
                    </div>
                    <div class="profile-card-body">
                        <?php if (!empty($recentOrders)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Order #</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentOrders as $order): ?>
                                            <tr>
                                                <td class="fw-medium"><?php echo htmlspecialchars($order['po_number']); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($order['order_date'])); ?></td>
                                                <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    if ($order['status'] === 'Pending') $statusClass = 'badge-pending';
                                                    elseif ($order['status'] === 'Active') $statusClass = 'badge-active';
                                                    elseif ($order['status'] === 'Completed') $statusClass = 'badge-completed';
                                                    elseif ($order['status'] === 'For Delivery') $statusClass = 'badge-for-delivery';
                                                    ?>
                                                    <span class="order-status-badge <?php echo $statusClass; ?>">
                                                        <?php echo htmlspecialchars($order['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <button type="button"
                                                       class="btn btn-sm btn-outline-primary order-details-btn"
                                                       data-po-number="<?php echo urlencode($order['po_number']); ?>">
                                                        Details
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-clipboard-list fa-3x mb-3 text-muted opacity-50"></i>
                                <h6 class="mb-2">No recent orders</h6>
                                <p class="text-muted">This client hasn't placed any orders yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Financial Summary -->
                <div class="profile-card mt-4">
                    <div class="profile-card-header">
                        <i class="fas fa-chart-line me-2"></i> Financial Summary
                    </div>
                    <div class="profile-card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="p-3 bg-light rounded">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted small">Total Spent</span>
                                        <i class="fas fa-receipt text-primary"></i>
                                    </div>
                                    <h4 class="mb-0">₱<?php echo number_format($orderStats['total_spent'] ?? 0, 2); ?></h4>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="p-3 bg-light rounded">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted small">Current Balance</span>
                                        <i class="fas fa-wallet text-primary"></i>
                                    </div>
                                    <h4 class="mb-0">₱<?php echo number_format($balance, 2); ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">Payment Status (<?php echo date('F Y'); ?>)</span>
                                <span class="payment-status
                                    <?php
                                    if ($paymentStatus === 'Fully Paid') echo 'status-paid';
                                    elseif ($paymentStatus === 'Partially Paid') echo 'status-partial';
                                    else echo 'status-unpaid';
                                    ?>">
                                    <?php echo htmlspecialchars($paymentStatus); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Proof Modal -->
    <div class="modal fade" id="proofModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Business Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalProofImage" src="" class="modal-proof-img" alt="Business Proof">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a id="downloadProofLink" href="#" class="btn btn-primary" download>
                        <i class="fas fa-download me-2"></i> Download
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-invoice me-2"></i> Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="orderDetailsLoading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading order details...</p>
                    </div>
                    <div id="orderDetailsContent" class="d-none">
                        <!-- Order Information -->
                        <div class="card mb-4 border-0 shadow-sm">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span>Order Information</span>
                                <span id="orderStatus" class="badge rounded-pill"></span>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <div class="fw-bold mb-1">Order Number</div>
                                            <div id="orderNumber" class="text-muted"></div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="fw-bold mb-1">Order Date</div>
                                            <div id="orderDate" class="text-muted"></div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="fw-bold mb-1">Delivery Date</div>
                                            <div id="deliveryDate" class="text-muted"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <div class="fw-bold mb-1">Payment Method</div>
                                            <div id="paymentMethod" class="text-muted"></div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="fw-bold mb-1">Shipping Address</div>
                                            <div id="shippingAddress" class="text-muted"></div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="fw-bold mb-1">Notes</div>
                                            <div id="orderNotes" class="text-muted"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Order Items -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-header">
                                <span>Order Items</span>
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
                                        <tbody id="orderItemsTable">
                                            <!-- Order items will be inserted here -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Order Summary -->
                        <div class="card mt-4 border-0 shadow-sm">
                            <div class="card-header">
                                <span>Order Summary</span>
                            </div>
                            <div class="card-body">
                                <div class="bg-light p-3 rounded">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Subtotal</span>
                                        <span id="orderSubtotal"></span>
                                    </div>
                                    <div id="shippingFeeRow" class="d-flex justify-content-between mb-2 d-none">
                                        <span>Shipping Fee</span>
                                        <span id="shippingFee"></span>
                                    </div>
                                    <div id="taxRow" class="d-flex justify-content-between mb-2 d-none">
                                        <span>Tax</span>
                                        <span id="tax"></span>
                                    </div>
                                    <div id="discountRow" class="d-flex justify-content-between mb-2 d-none">
                                        <span>Discount</span>
                                        <span id="discount"></span>
                                    </div>
                                    <div class="d-flex justify-content-between fw-bold pt-2 border-top mt-2">
                                        <span>Total</span>
                                        <span id="orderTotal"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="orderDetailsError" class="d-none">
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <span id="errorMessage">Failed to load order details.</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a id="viewFullOrderLink" href="#" class="btn btn-primary" target="_blank">
                        <i class="fas fa-external-link-alt me-2"></i> View Full Details
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script>
        // Initialize proof modal
        document.addEventListener('DOMContentLoaded', function() {
            var proofModal = document.getElementById('proofModal');
            if (proofModal) {
                proofModal.addEventListener('show.bs.modal', function (event) {
                    var button = event.relatedTarget;
                    var proofSrc = button.getAttribute('data-proof-src');
                    var modalImage = proofModal.querySelector('#modalProofImage');
                    var downloadLink = proofModal.querySelector('#downloadProofLink');

                    modalImage.src = proofSrc;
                    downloadLink.href = proofSrc;
                    downloadLink.setAttribute('download', proofSrc.split('/').pop());
                });
            }

            // Order details functionality
            const orderDetailsButtons = document.querySelectorAll('.order-details-btn');
            const orderDetailsModal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));

            orderDetailsButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const poNumber = this.getAttribute('data-po-number');
                    fetchOrderDetails(poNumber);
                    document.getElementById('viewFullOrderLink').href = `order_details.php?po_number=${poNumber}`;
                    orderDetailsModal.show();
                });
            });

            function fetchOrderDetails(poNumber) {
                // Show loading state
                document.getElementById('orderDetailsLoading').classList.remove('d-none');
                document.getElementById('orderDetailsContent').classList.add('d-none');
                document.getElementById('orderDetailsError').classList.add('d-none');

                // Fetch order details
                fetch(`get_order_details.php?po_number=${poNumber}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            showError(data.error);
                            return;
                        }

                        // Populate order details
                        populateOrderDetails(data);

                        // Hide loading, show content
                        document.getElementById('orderDetailsLoading').classList.add('d-none');
                        document.getElementById('orderDetailsContent').classList.remove('d-none');
                    })
                    .catch(error => {
                        console.error('Error fetching order details:', error);
                        showError('Failed to load order details. Please try again later.');
                    });
            }

            function populateOrderDetails(data) {
                const order = data.order;
                const items = data.items;

                // Order information
                document.getElementById('orderNumber').textContent = order.po_number;
                document.getElementById('orderDate').textContent = order.formatted_order_date;
                document.getElementById('deliveryDate').textContent = order.formatted_delivery_date;
                document.getElementById('paymentMethod').textContent = order.payment_method || 'Not specified';
                document.getElementById('shippingAddress').textContent = order.shipping_address || 'Not specified';
                document.getElementById('orderNotes').textContent = order.notes || 'No notes';

                // Order status
                const orderStatus = document.getElementById('orderStatus');
                orderStatus.textContent = order.status;
                orderStatus.className = 'badge rounded-pill';

                if (order.status === 'Pending') {
                    orderStatus.classList.add('badge-pending');
                } else if (order.status === 'Active') {
                    orderStatus.classList.add('badge-active');
                } else if (order.status === 'Completed') {
                    orderStatus.classList.add('badge-completed');
                } else if (order.status === 'For Delivery') {
                    orderStatus.classList.add('badge-delivery');
                } else if (order.status === 'Rejected') {
                    orderStatus.classList.add('badge-rejected');
                }

                // Order items
                const orderItemsTable = document.getElementById('orderItemsTable');
                orderItemsTable.innerHTML = '';

                if (items.length > 0) {
                    items.forEach(item => {
                        const row = document.createElement('tr');

                        const nameCell = document.createElement('td');
                        nameCell.innerHTML = `<div class="fw-medium">${item.item_name}</div>`;
                        if (item.item_description) {
                            nameCell.innerHTML += `<small class="text-muted">${item.item_description}</small>`;
                        }

                        const quantityCell = document.createElement('td');
                        quantityCell.textContent = item.quantity;

                        const priceCell = document.createElement('td');
                        priceCell.textContent = `₱${item.formatted_unit_price}`;

                        const totalCell = document.createElement('td');
                        totalCell.textContent = `₱${item.formatted_item_total}`;
                        totalCell.className = 'text-end';

                        row.appendChild(nameCell);
                        row.appendChild(quantityCell);
                        row.appendChild(priceCell);
                        row.appendChild(totalCell);

                        orderItemsTable.appendChild(row);
                    });
                } else {
                    const emptyRow = document.createElement('tr');
                    const emptyCell = document.createElement('td');
                    emptyCell.colSpan = 4;
                    emptyCell.className = 'text-center py-4';
                    emptyCell.textContent = 'No items found for this order.';
                    emptyRow.appendChild(emptyCell);
                    orderItemsTable.appendChild(emptyRow);
                }

                // Order summary
                document.getElementById('orderSubtotal').textContent = `₱${order.formatted_subtotal}`;
                document.getElementById('orderTotal').textContent = `₱${order.formatted_total}`;

                // Optional fields
                if (order.shipping_fee) {
                    document.getElementById('shippingFee').textContent = `₱${order.formatted_shipping_fee}`;
                    document.getElementById('shippingFeeRow').classList.remove('d-none');
                } else {
                    document.getElementById('shippingFeeRow').classList.add('d-none');
                }

                if (order.tax) {
                    document.getElementById('tax').textContent = `₱${order.formatted_tax}`;
                    document.getElementById('taxRow').classList.remove('d-none');
                } else {
                    document.getElementById('taxRow').classList.add('d-none');
                }

                if (order.discount) {
                    document.getElementById('discount').textContent = `-₱${order.formatted_discount}`;
                    document.getElementById('discountRow').classList.remove('d-none');
                } else {
                    document.getElementById('discountRow').classList.add('d-none');
                }
            }

            function showError(message) {
                document.getElementById('errorMessage').textContent = message;
                document.getElementById('orderDetailsLoading').classList.add('d-none');
                document.getElementById('orderDetailsContent').classList.add('d-none');
                document.getElementById('orderDetailsError').classList.remove('d-none');
            }
        });
    </script>
</body>
</html>