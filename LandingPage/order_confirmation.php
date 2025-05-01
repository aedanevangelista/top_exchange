<?php
session_start();
include_once('db_connection.php');

// Check if order ID is provided
if (!isset($_GET['id'])) {
    header("Location: ordering.php");
    exit();
}

$orderId = $_GET['id'];
$username = $_SESSION['username'];

// Fetch order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND username = ?");
$stmt->bind_param("is", $orderId, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Log the error for debugging
    error_log("Order not found: ID=$orderId, Username=$username");

    // Redirect with error message
    $_SESSION['error'] = "Order not found. Please try again.";
    header("Location: ordering.php");
    exit();
}

$order = $result->fetch_assoc();

// Safely decode JSON orders
$orderItems = [];
if (!empty($order['orders'])) {
    $decodedItems = json_decode($order['orders'], true);
    if ($decodedItems !== null) {
        $orderItems = $decodedItems;
    } else {
        // Log JSON decode error
        error_log("JSON decode error for order ID $orderId: " . json_last_error_msg());
        error_log("Raw JSON: " . $order['orders']);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Confirmation | Top Exchange Food Corp</title>
    <link rel="stylesheet" type="text/css" href="/LandingPage/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="/LandingPage/css/style.css">
    <link rel="stylesheet" href="/LandingPage/css/responsive.css">
    <link rel="icon" href="/LandingPage/images/fevicon.png" type="image/gif" />
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #9a7432;
            --primary-hover: #b08a3e;
            --secondary-color: #333;
            --light-color: #f8f9fa;
            --dark-color: #222;
            --accent-color: #dc3545;
            --success-color: #28a745;
            --box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
            --border-radius: 8px;
        }

        body {
            background-color: #f9f9f9;
        }

        .confirmation-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 40px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        .confirmation-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 1px solid #eee;
        }

        .confirmation-icon {
            font-size: 70px;
            color: var(--success-color);
            margin-bottom: 25px;
            display: inline-block;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
            }
        }

        .confirmation-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 15px;
        }

        .confirmation-header p {
            font-size: 1.1rem;
            color: #666;
            max-width: 600px;
            margin: 0 auto;
        }

        .order-details {
            background-color: var(--light-color);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
        }

        .order-details h3 {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }

        .detail-row {
            display: flex;
            margin-bottom: 12px;
        }

        .detail-label {
            font-weight: 600;
            width: 180px;
            color: var(--secondary-color);
        }

        .detail-value {
            flex: 1;
            color: #555;
        }

        .order-items {
            margin-bottom: 30px;
        }

        .order-items h4 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .order-item-details {
            flex: 1;
        }

        .order-item-name {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }

        .order-item-meta {
            font-size: 0.9rem;
            color: #777;
        }

        .item-category {
            font-weight: 600;
            color: var(--primary-color);
        }

        .order-item-price {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 1.1rem;
            min-width: 100px;
            text-align: right;
        }

        .order-total {
            background-color: var(--light-color);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-top: 20px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .total-row.grand-total {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-color);
            padding-top: 10px;
            margin-top: 10px;
            border-top: 2px solid #ddd;
        }

        .btn-continue {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            padding: 12px 30px;
            border-radius: var(--border-radius);
            text-decoration: none;
            transition: var(--transition);
            margin-top: 30px;
            border: none;
            cursor: pointer;
        }

        .btn-continue:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            text-decoration: none;
            color: white;
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }

        .btn-secondary {
            display: inline-block;
            background-color: #f1f1f1;
            color: var(--secondary-color);
            font-weight: 600;
            padding: 12px 30px;
            border-radius: var(--border-radius);
            text-decoration: none;
            transition: var(--transition);
            border: 1px solid #ddd;
        }

        .btn-secondary:hover {
            background-color: #e5e5e5;
            text-decoration: none;
            color: var(--secondary-color);
        }

        /* Order steps styling */
        .order-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }

        .order-steps::before {
            content: '';
            position: absolute;
            top: 24px;
            left: 0;
            right: 0;
            height: 2px;
            background-color: #e0e0e0;
            z-index: 1;
        }

        .step {
            position: relative;
            z-index: 2;
            text-align: center;
            width: 33.333%;
        }

        .step-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #f8f9fa;
            border: 2px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 1.2rem;
            color: #aaa;
        }

        .step.active .step-icon {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .step.completed .step-icon {
            background-color: var(--success-color);
            border-color: var(--success-color);
            color: white;
        }

        .step-label {
            font-size: 0.9rem;
            font-weight: 500;
            color: #777;
        }

        .step.active .step-label {
            color: var(--primary-color);
            font-weight: 600;
        }

        .step.completed .step-label {
            color: var(--success-color);
            font-weight: 600;
        }

        @media (max-width: 767px) {
            .confirmation-container {
                padding: 25px;
                margin: 30px 15px;
            }

            .confirmation-header {
                margin-bottom: 30px;
                padding-bottom: 20px;
            }

            .confirmation-icon {
                font-size: 50px;
                margin-bottom: 15px;
            }

            .confirmation-header h1 {
                font-size: 1.8rem;
            }

            .order-details {
                padding: 20px;
            }

            .detail-row {
                flex-direction: column;
                margin-bottom: 15px;
            }

            .detail-label {
                width: 100%;
                margin-bottom: 5px;
            }

            .action-buttons {
                flex-direction: column;
                gap: 10px;
            }

            .btn-continue, .btn-secondary {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="header_section">
        <div class="container">
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <a class="navbar-brand" href="index.php"><img src="/LandingPage/images/resized_food_corp_logo.png" alt="Top Food Exchange Corp. Logo"></a>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="/LandingPage/index.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/LandingPage/about.php">About</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/LandingPage/ordering.php">Products</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/LandingPage/contact.php">Contact Us</a>
                        </li>
                    </ul>
                    <form class="form-inline my-2 my-lg-0">
                        <div class="login_bt">
                            <?php if (isset($_SESSION['username'])): ?>
                                <a href="#" class="cart-button" data-toggle="modal" data-target="#cartModal">
                                    <span style="color: #222222;"><i class="fa fa-shopping-cart" aria-hidden="true"></i></span>
                                    <span id="cart-count" class="badge badge-danger">0</span>
                                </a>
                                <a href="/LandingPage/logout.php">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)
                                    <span style="color: #222222;"><i class="fa fa-sign-out" aria-hidden="true"></i></span>
                                </a>
                            <?php else: ?>
                                <a href="/LandingPage/login.php">Login
                                    <span style="color: #222222;"><i class="fa fa-user" aria-hidden="true"></i></span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </nav>
        </div>
    </div>

    <div class="confirmation-container">
        <!-- Order Steps -->
        <div class="order-steps">
            <div class="step completed">
                <div class="step-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="step-label">Shopping Cart</div>
            </div>
            <div class="step completed">
                <div class="step-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="step-label">Checkout</div>
            </div>
            <div class="step active">
                <div class="step-icon">
                    <i class="fas fa-check"></i>
                </div>
                <div class="step-label">Confirmation</div>
            </div>
        </div>

        <div class="confirmation-header">
            <div class="confirmation-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Order Confirmed!</h1>
            <p>Thank you for your order. We've received it and will process it shortly. Your order will be delivered on the selected date.</p>
        </div>

        <div class="order-details">
            <h3>Order Information</h3>
            <div class="detail-row">
                <div class="detail-label">Order Number:</div>
                <div class="detail-value"><?php echo htmlspecialchars($order['po_number']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Order Date:</div>
                <div class="detail-value"><?php echo date('F j, Y', strtotime($order['order_date'])); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Delivery Date:</div>
                <div class="detail-value"><?php echo date('F j, Y', strtotime($order['delivery_date'])); ?> (<?php echo date('l', strtotime($order['delivery_date'])); ?>)</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Delivery Address:</div>
                <div class="detail-value"><?php echo htmlspecialchars($order['delivery_address']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Contact Number:</div>
                <div class="detail-value"><?php echo htmlspecialchars($order['contact_number']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Payment Method:</div>
                <div class="detail-value">Check Payment</div>
            </div>
            <?php if (!empty($order['special_instructions'])): ?>
                <div class="detail-row">
                    <div class="detail-label">Special Instructions:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($order['special_instructions']); ?></div>
                </div>
            <?php endif; ?>
        </div>

        <div class="order-items">
            <h4>Order Items</h4>
            <?php foreach ($orderItems as $item): ?>
                <div class="order-item">
                    <div class="order-item-details">
                        <div class="order-item-name"><?php echo htmlspecialchars($item['item_description']); ?></div>
                        <div class="order-item-meta">
                            <?php echo $item['quantity']; ?> x ₱<?php echo number_format($item['price'], 2); ?>
                            <?php if (!empty($item['category'])): ?>
                                • Category: <span class="item-category"><?php echo htmlspecialchars($item['category']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($item['packaging'])): ?>
                                • <?php echo htmlspecialchars($item['packaging']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="order-item-price">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="order-total">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>₱<?php echo number_format($order['subtotal'], 2); ?></span>
            </div>
            <div class="total-row grand-total">
                <span>Total Amount:</span>
                <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
            </div>
        </div>

        <div class="action-buttons">
            <a href="/LandingPage/index.php" class="btn-secondary">
                <i class="fas fa-home mr-2"></i> Back to Home
            </a>
            <a href="/LandingPage/ordering.php" class="btn-continue">
                <i class="fas fa-shopping-cart mr-2"></i> Continue Shopping
            </a>
        </div>

        <div style="text-align: center; margin-top: 30px; color: #666; font-size: 0.9rem;">
            <p>A confirmation email has been sent to your registered email address.</p>
            <p>If you have any questions about your order, please contact our customer service at <strong>support@topexchange.com</strong></p>
        </div>
    </div>

    <div class="copyright_section">
        <div class="container">
            <p class="copyright_text">2025 All Rights Reserved. Design by STI Munoz Students</p>
        </div>
    </div>

    <!-- Javascript files-->
    <script src="/LandingPage/js/jquery.min.js"></script>
    <script src="/LandingPage/js/popper.min.js"></script>
    <script src="/LandingPage/js/bootstrap.bundle.min.js"></script>
    <script src="/LandingPage/js/jquery-3.0.0.min.js"></script>
    <script src="/LandingPage/js/plugin.js"></script>
    <script src="/LandingPage/js/jquery.mCustomScrollbar.concat.min.js"></script>
    <script src="/LandingPage/js/custom.js"></script>
</body>
</html>