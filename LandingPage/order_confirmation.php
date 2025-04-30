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
    header("Location: ordering.php");
    exit();
}

$order = $result->fetch_assoc();
$orderItems = json_decode($order['orders'], true);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Confirmation | Top Exchange Food Corp</title>
    <link rel="stylesheet" type="text/css" href="/LandingPage/admin/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="/LandingPage/admin/css/style.css">
    <link rel="stylesheet" href="/LandingPage/admin/css/responsive.css">
    <link rel="icon" href="/LandingPage/images/fevicon.png" type="image/gif" />
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .confirmation-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .confirmation-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .confirmation-icon {
            font-size: 60px;
            color: #28a745;
            margin-bottom: 20px;
        }
        .order-details {
            margin-bottom: 30px;
        }
        .order-items {
            margin-bottom: 20px;
        }
        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .order-total {
            font-size: 1.2rem;
            font-weight: bold;
            text-align: right;
            margin-top: 20px;
        }
        .btn-continue {
            display: block;
            width: 200px;
            margin: 30px auto 0;
            padding: 10px;
            text-align: center;
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
        <div class="confirmation-header">
            <div class="confirmation-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Order Confirmed!</h1>
            <p>Thank you for your order. We've received it and will process it shortly.</p>
        </div>

        <div class="order-details">
            <h3>Order #<?php echo htmlspecialchars($order['po_number']); ?></h3>
            <p><strong>Order Date:</strong> <?php echo date('F j, Y', strtotime($order['order_date'])); ?></p>
            <p><strong>Delivery Date:</strong> <?php echo date('F j, Y', strtotime($order['delivery_date'])); ?></p>
            <p><strong>Delivery Address:</strong> <?php echo htmlspecialchars($order['delivery_address']); ?></p>
            <p><strong>Contact Number:</strong> <?php echo htmlspecialchars($order['contact_number']); ?></p>
            <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></p>
            <?php if (!empty($order['special_instructions'])): ?>
                <p><strong>Special Instructions:</strong> <?php echo htmlspecialchars($order['special_instructions']); ?></p>
            <?php endif; ?>
        </div>

        <div class="order-items">
            <h4>Order Items</h4>
            <?php foreach ($orderItems as $item): ?>
                <div class="order-item">
                    <div>
                        <strong><?php echo htmlspecialchars($item['item_description']); ?></strong>
                        <div class="text-muted">
                            <?php echo $item['quantity']; ?> x ₱<?php echo number_format($item['price'], 2); ?>
                            <?php if (!empty($item['packaging'])): ?>
                                • <?php echo htmlspecialchars($item['packaging']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="order-total">
            <div>Subtotal: ₱<?php echo number_format($order['subtotal'], 2); ?></div>
            <div>Delivery Fee: ₱<?php echo number_format($order['delivery_fee'], 2); ?></div>
            <div style="font-size: 1.4rem;">Total: ₱<?php echo number_format($order['total_amount'], 2); ?></div>
        </div>

        <a href="/LandingPage/ordering.php" class="btn btn-primary btn-continue">
            Continue Shopping
        </a>
    </div>

    <div class="copyright_section">
        <div class="container">
            <p class="copyright_text">2025 All Rights Reserved. Design by STI Munoz Students</p>
        </div>
    </div>

    <!-- Javascript files-->
    <script src="/LandingPage/admin/js/jquery.min.js"></script>
    <script src="/LandingPage/admin/js/popper.min.js"></script>
    <script src="/LandingPage/admin/js/bootstrap.bundle.min.js"></script>
    <script src="/LandingPage/admin/js/jquery-3.0.0.min.js"></script>
    <script src="/LandingPage/admin/js/plugin.js"></script>
    <script src="/LandingPage/admin/js/jquery.mCustomScrollbar.concat.min.js"></script>
    <script src="/LandingPage/admin/js/custom.js"></script>
</body>
</html>