<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Check if cart is empty
if (empty($_SESSION['cart'])) {
    header("Location: ordering.php");
    exit();
}

// Connect to database
include_once('db_connection.php');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Process checkout form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $deliveryAddress = trim($_POST['delivery_address']);
    $deliveryDate = $_POST['delivery_date'];
    $specialInstructions = trim($_POST['special_instructions']);
    $contactNumber = trim($_POST['contact_number']);
    $paymentMethod = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'Cash on Delivery';
    
    // Basic validation
    if (empty($deliveryAddress) || empty($deliveryDate) || empty($contactNumber)) {
        $_SESSION['error'] = "Please fill in all required fields";
        header("Location: checkout.php");
        exit();
    }
    
    // Validate delivery day is Monday, Wednesday, or Friday
    $deliveryDay = date('l', strtotime($deliveryDate));
    if (!in_array($deliveryDay, ['Monday', 'Wednesday', 'Friday'])) {
        $_SESSION['error'] = "Delivery is only available on Monday, Wednesday, and Friday";
        header("Location: checkout.php");
        exit();
    }
    
    // Calculate order totals
    $subtotal = 0;
    $orderItems = [];
    foreach ($_SESSION['cart'] as $productId => $item) {
        $itemSubtotal = $item['price'] * $item['quantity'];
        $subtotal += $itemSubtotal;
        $orderItems[] = [
            'product_id' => $productId,
            'category' => $item['category'] ?? '',
            'item_description' => $item['name'],
            'packaging' => $item['packaging'] ?? '',
            'price' => $item['price'],
            'quantity' => $item['quantity']
        ];
    }
    
    $deliveryFee = ($subtotal > 500) ? 0 : 50;
    $total = $subtotal + $deliveryFee;
    
    // Generate PO number (username + incrementing number)
    $username = $_SESSION['username'];
    $poNumber = $username . '-' . (getNextOrderNumber($conn, $username) + 1);
    
    try {
        // Insert order into database
        $stmt = $conn->prepare("INSERT INTO orders (
            po_number, 
            username, 
            order_date, 
            delivery_date, 
            delivery_address, 
            contact_number,
            orders, 
            total_amount, 
            status, 
            special_instructions,
            subtotal,
            delivery_fee,
            payment_method
        ) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?)");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $jsonOrders = json_encode($orderItems, JSON_UNESCAPED_UNICODE);
        
        // Corrected bind_param with proper parameter count
        $bindResult = $stmt->bind_param(
            "ssssssdsdds", 
            $poNumber,
            $username,
            $deliveryDate,
            $deliveryAddress,
            $contactNumber,
            $jsonOrders,
            $total,
            $specialInstructions,
            $subtotal,
            $deliveryFee,
            $paymentMethod
        );
        
        if (!$bindResult) {
            throw new Exception("Bind failed: " . $stmt->error);
        }
        
        $executeResult = $stmt->execute();
        
        if (!$executeResult) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        // Clear the cart
        $_SESSION['cart'] = [];
        
        // Get the inserted order ID
        $orderId = $stmt->insert_id;
        
        // Close statement
        $stmt->close();
        
        // Redirect to confirmation page
        header("Location: order_confirmation.php?id=" . $orderId);
        exit();
        
    } catch (Exception $e) {
        error_log("Order processing error: " . $e->getMessage());
        $_SESSION['error'] = "Error processing your order. Please try again.";
        header("Location: checkout.php");
        exit();
    }
}

// Helper function to get the next order number for a user
function getNextOrderNumber($conn, $username) {
    $result = $conn->query("SELECT MAX(SUBSTRING_INDEX(po_number, '-', -1)) as max_num FROM orders WHERE username = '$username'");
    if ($result && $row = $result->fetch_assoc()) {
        return (int)$row['max_num'];
    }
    return 0;
}

// Fetch user details if available
$userDetails = [];
$stmt = $conn->prepare("SELECT company_address, phone FROM clients_accounts WHERE username = ?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $userDetails = $result->fetch_assoc();
}
$stmt->close();

// Function to get next available delivery dates
function getAvailableDeliveryDates($days = ['Monday', 'Wednesday', 'Friday'], $count = 10) {
    $dates = [];
    $currentDate = new DateTime();
    $currentDate->modify('+1 day'); // Start from tomorrow
    
    while (count($dates) < $count) {
        if (in_array($currentDate->format('l'), $days)) {
            $dates[] = $currentDate->format('Y-m-d');
        }
        $currentDate->modify('+1 day');
    }
    
    return $dates;
}

$availableDates = getAvailableDeliveryDates();

// Display any errors
if (isset($_SESSION['error'])) {
    $errorMessage = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout | Top Exchange Food Corp</title> 
    <meta name="keywords" content="checkout, order, food delivery, Filipino food">
    <meta name="description" content="Complete your order with Top Food Exchange Corp. - Premium Filipino food products since 1998.">
    <meta name="author" content="Top Food Exchange Corp.">
    <link rel="stylesheet" type="text/css" href="/LandingPage/admin/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="/LandingPage/admin/css/style.css">
    <link rel="stylesheet" href="/LandingPage/admin/css/responsive.css">
    <link rel="icon" href="/LandingPage/images/fevicon.png" type="image/gif" />
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #9a7432;
            --primary-hover: #b08a3e;
            --secondary-color: #333;
            --light-color: #f8f9fa;
            --dark-color: #222;
            --accent-color: #dc3545;
            --box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
            --border-radius: 8px;
        }

        html, body {
            height: 100%;
        }
        
        body {
            display: flex;
            flex-direction: column;
            background-color: #f9f9f9;
        }
        
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 40px 0;
        }
        
        .checkout-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .copyright_section {
            background-color: #222;
            color: white;
            padding: 20px 0;
            text-align: center;
            margin-top: auto;
        }

        .checkout-card {
            border: 1px solid #e0e0e0;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 30px;
            margin-bottom: 30px;
            background: white;
        }
        
        .checkout-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 25px;
            position: relative;
            padding-bottom: 15px;
        }
        
        .checkout-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background-color: var(--primary-color);
            border-radius: 2px;
        }
        
        .form-label {
            font-weight: 500;
            color: var(--secondary-color);
            margin-bottom: 8px;
            display: block;
        }
        
        .required-field::after {
            content: '*';
            color: var(--accent-color);
            margin-left: 4px;
        }
        
        .form-control {
            border-radius: var(--border-radius);
            padding: 10px 15px;
            border: 1px solid #ddd;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(154, 116, 50, 0.25);
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .order-summary-card {
            border: 1px solid #e0e0e0;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 25px;
            background: white;
            position: sticky;
            top: 20px;
        }
        
        .order-summary-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px dashed #eee;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-item-name {
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .order-item-details {
            font-size: 0.85rem;
            color: #666;
            margin-top: 3px;
        }
        
        .order-totals {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid #eee;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        
        .grand-total {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--primary-color);
            margin-top: 10px;
        }
        
        .btn-checkout {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 12px 20px;
            font-weight: 600;
            width: 100%;
            margin-top: 20px;
            border-radius: var(--border-radius);
            transition: var(--transition);
            font-size: 1rem;
        }
        
        .btn-checkout:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
            transform: translateY(-2px);
        }
        
        .delivery-info-group {
            margin-bottom: 20px;
        }
        
        .info-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }
        
        .confirmation-modal .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--box-shadow);
        }
        
        .confirmation-modal .modal-header {
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .confirmation-modal .modal-title {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1.5rem;
        }
        
        .confirmation-modal .modal-body {
            padding: 30px;
            text-align: center;
        }
        
        .confirmation-modal .modal-icon {
            font-size: 4rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        .confirmation-modal .modal-message {
            font-size: 1.1rem;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        .confirmation-modal .order-summary {
            background: #f9f9f9;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 25px;
            text-align: left;
        }
        
        .confirmation-modal .order-summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .confirmation-modal .btn-confirm {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 10px 25px;
            font-weight: 600;
            min-width: 120px;
        }
        
        .confirmation-modal .btn-confirm:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }
        
        .confirmation-modal .btn-cancel {
            background-color: transparent;
            color: var(--secondary-color);
            border: 1px solid #ddd;
            padding: 10px 25px;
            font-weight: 600;
            min-width: 120px;
            margin-right: 15px;
        }
        
        .confirmation-modal .btn-cancel:hover {
            background-color: #f1f1f1;
        }
        
        @media (max-width: 991.98px) {
            .order-summary-card {
                position: static;
                margin-top: 30px;
            }
            
            .main-content {
                padding: 30px 0;
            }
        }
        
        @media (max-width: 767.98px) {
            .checkout-title {
                font-size: 1.5rem;
            }
            
            .checkout-card, .order-summary-card {
                padding: 20px;
            }
            
            .confirmation-modal .modal-body {
                padding: 20px;
            }
        }

        .custom-popup {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: var(--primary-color);
            color: white;
            padding: 15px 25px;
            border-radius: 4px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            z-index: 9999;
            display: none;
            animation: slideIn 0.5s forwards, fadeOut 0.5s forwards 2.5s;
            max-width: 300px;
        }

        .popup-content {
            display: flex;
            align-items: center;
        }

        .custom-popup.error {
            background-color: var(--accent-color);
        }

        @keyframes slideIn {
            from { right: -100%; opacity: 0; }
            to { right: 20px; opacity: 1; }
        }

        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
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
                                    <span id="cart-count" class="badge badge-danger"><?php echo array_sum(array_column($_SESSION['cart'], 'quantity')); ?></span>
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
        
        <!-- Custom Popup Message -->
        <div id="customPopup" class="custom-popup">
            <div class="popup-content">
                <span id="popupMessage"></span>
            </div>
        </div>
    </div>
    
    <!-- Main Content Section -->
    <main class="main-content">
        <div class="checkout-container">
            <div class="row">
                <div class="col-lg-8">
                    <div class="checkout-card" data-aos="fade-right">
                        <h1 class="checkout-title">Delivery Information</h1>
                        
                        <form id="checkoutForm" method="POST">
                            <div class="delivery-info-group">
                                <label for="delivery_address" class="form-label required-field">Delivery Address</label>
                                <textarea class="form-control" id="delivery_address" name="delivery_address" rows="4" required><?php echo htmlspecialchars($userDetails['company_address'] ?? ''); ?></textarea>
                                <p class="info-text">Please provide complete address including building, street, and barangay</p>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="delivery-info-group">
                                        <label for="contact_number" class="form-label required-field">Contact Number</label>
                                        <input type="tel" class="form-control" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($userDetails['phone'] ?? ''); ?>" required>
                                        <p class="info-text">We'll contact you for delivery updates</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="delivery-info-group">
                                        <label for="delivery_date" class="form-label required-field">Delivery Date</label>
                                        <select class="form-control" id="delivery_date" name="delivery_date" required>
                                            <?php foreach ($availableDates as $date): 
                                                $dayName = date('l', strtotime($date));
                                                $formattedDate = date('M j, Y', strtotime($date));
                                            ?>
                                                <option value="<?php echo $date; ?>"><?php echo "$dayName - $formattedDate"; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="info-text">Delivery days: Monday, Wednesday, Friday</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="delivery-info-group">
                                <label for="payment_method" class="form-label required-field">Payment Method</label>
                                <select class="form-control" id="payment_method" name="payment_method" required>
                                    <option value="Cash on Delivery">Cash on Delivery</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="GCash">GCash</option>
                                    <option value="Credit Card">Credit Card</option>
                                </select>
                                <p class="info-text">Please select your preferred payment method</p>
                            </div>
                            
                            <div class="delivery-info-group">
                                <label for="special_instructions" class="form-label">Special Instructions</label>
                                <textarea class="form-control" id="special_instructions" name="special_instructions" rows="3" placeholder="Any special requests or notes for your order..."><?php echo isset($_SESSION['specialInstructions']) ? htmlspecialchars($_SESSION['specialInstructions']) : ''; ?></textarea>
                                <p class="info-text">e.g., Delivery time preferences, gate codes, etc.</p>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="order-summary-card" data-aos="fade-left">
                        <h3 class="order-summary-title">Order Summary</h3>
                        
                        <div id="orderItems">
                            <?php 
                            $subtotal = 0;
                            foreach ($_SESSION['cart'] as $productId => $item): 
                                $itemSubtotal = $item['price'] * $item['quantity'];
                                $subtotal += $itemSubtotal;
                            ?>
                                <div class="order-item">
                                    <div>
                                        <div class="order-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                        <div class="order-item-details">
                                            x<?php echo $item['quantity']; ?>
                                            <?php if (!empty($item['packaging'])): ?>
                                                • <?php echo htmlspecialchars($item['packaging']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div>₱<?php echo number_format($itemSubtotal, 2); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="order-totals">
                            <div class="total-row">
                                <span>Subtotal:</span>
                                <span id="summarySubtotal">₱<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            
                            <div class="total-row">
                                <span>Delivery Fee:</span>
                                <span id="summaryDeliveryFee">₱<?php echo number_format(($subtotal > 500) ? 0 : 50, 2); ?></span>
                            </div>
                            
                            <div class="total-row grand-total">
                                <span>Total Amount:</span>
                                <span id="summaryTotal">₱<?php echo number_format($subtotal + (($subtotal > 500) ? 0 : 50), 2); ?></span>
                            </div>
                        </div>
                        
                        <button type="button" id="placeOrderBtn" class="btn btn-primary btn-checkout">
                            <i class="fas fa-shopping-bag mr-2"></i> Place Order
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Order Confirmation Modal -->
    <div class="modal fade confirmation-modal" id="confirmationModal" tabindex="-1" role="dialog" aria-labelledby="confirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmationModalLabel">Confirm Your Order</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="modal-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="modal-message">
                        Please review your order details before confirming your purchase.
                    </div>
                    
                    <div class="order-summary">
                        <h6 class="text-center mb-3">Order Summary</h6>
                        <?php foreach ($_SESSION['cart'] as $productId => $item): ?>
                            <div class="order-summary-item">
                                <span><?php echo htmlspecialchars($item['name']) . ' x ' . $item['quantity']; ?></span>
                                <span>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="order-summary-item mt-3 pt-2 border-top">
                            <span><strong>Subtotal:</strong></span>
                            <span><strong>₱<?php echo number_format($subtotal, 2); ?></strong></span>
                        </div>
                        
                        <div class="order-summary-item">
                            <span>Delivery Fee:</span>
                            <span>₱<?php echo number_format(($subtotal > 500) ? 0 : 50, 2); ?></span>
                        </div>
                        
                        <div class="order-summary-item mt-2 pt-2 border-top">
                            <span><strong>Total Amount:</strong></span>
                            <span><strong class="text-primary">₱<?php echo number_format($subtotal + (($subtotal > 500) ? 0 : 50), 2); ?></strong></span>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="button" class="btn btn-secondary btn-cancel" data-dismiss="modal">Cancel</button>
                        <button type="button" id="confirmOrderBtn" class="btn btn-primary btn-confirm">Confirm Order</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer Section -->
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
    <!-- AOS Animation -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <!-- sidebar -->
    <script src="/LandingPage/admin/js/jquery.mCustomScrollbar.concat.min.js"></script>
    <script src="/LandingPage/admin/js/custom.js"></script>
    
    <script>
        // Initialize AOS animation
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });
        
        // Function to show custom popup message
        function showPopup(message, isError = false) {
            const popup = $('#customPopup');
            const popupMessage = $('#popupMessage');
            
            popupMessage.text(message);
            popup.removeClass('error');
            
            if (isError) {
                popup.addClass('error');
            }
            
            // Reset animation by briefly showing/hiding
            popup.hide().show();
            
            // Automatically hide after 3 seconds
            setTimeout(() => {
                popup.hide();
            }, 3000);
        }
        
        // Handle place order button click
        $(document).ready(function() {
            // Show error message if exists
            <?php if (isset($errorMessage)): ?>
                showPopup('<?php echo addslashes($errorMessage); ?>', true);
            <?php endif; ?>
            
            $('#placeOrderBtn').click(function(e) {
                e.preventDefault();
                
                // Validate form
                const form = $('#checkoutForm');
                let isValid = true;
                
                // Check required fields
                form.find('[required]').each(function() {
                    if (!$(this).val()) {
                        $(this).addClass('is-invalid');
                        isValid = false;
                    } else {
                        $(this).removeClass('is-invalid');
                    }
                });
                
                if (!isValid) {
                    showPopup('Please fill in all required fields', true);
                    return;
                }
                
                // Show confirmation modal
                $('#confirmationModal').modal('show');
            });
            
            // Handle confirm order button click
            $('#confirmOrderBtn').click(function() {
                // Disable button to prevent multiple clicks
                $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
                
                // Submit the form
                $('#checkoutForm').submit();
            });
            
            // Remove invalid class when user starts typing
            $('input, textarea, select').on('input change', function() {
                if ($(this).val()) {
                    $(this).removeClass('is-invalid');
                }
            });
        });
    </script>
</body>
</html>