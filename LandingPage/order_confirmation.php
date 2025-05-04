<?php
// Start the session (will be ignored if already started in header.php)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Process any redirects before output
$needsRedirect = false;
$redirectUrl = '';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    $needsRedirect = true;
    $redirectUrl = 'login.php';
}

// Check if order ID is provided
if (!$needsRedirect && !isset($_GET['id'])) {
    $needsRedirect = true;
    $redirectUrl = 'ordering.php';
}

// Set page-specific variables before including header
$pageTitle = "Order Confirmation | Top Exchange Food Corp";
$pageDescription = "Order confirmation for Top Food Exchange Corp. - Premium Filipino food products since 1998.";

// Include the header at the very top
require_once 'header.php';

// Handle redirects using JavaScript after header is included
if ($needsRedirect) {
    echo "<script>window.location.href = '$redirectUrl';</script>";
    exit();
}

// Include database connection
include_once('db_connection.php');

// Log connection status
if ($conn->connect_error) {
    error_log("Database connection failed in order_confirmation.php: " . $conn->connect_error);
} else {
    error_log("Database connection successful in order_confirmation.php");
}

// Get order ID from GET parameter or session
if (isset($_GET['id'])) {
    $orderId = $_GET['id'];
} elseif (isset($_SESSION['order_id'])) {
    $orderId = $_SESSION['order_id'];
    // Clear the session variable to prevent reuse
    unset($_SESSION['order_id']);
} else {
    // No order ID found, show error
    $_SESSION['error'] = "No order ID provided. Please try again.";
    echo "<script>window.location.href = 'ordering.php';</script>";
    exit();
}

$username = $_SESSION['username'];

// Log the order ID for debugging
error_log("Processing order confirmation for Order ID: $orderId, Username: $username");

// Include the email sending functionality
require_once 'send_order_email.php';

// Check if this is a new order that needs an email sent
$sendEmail = false;
if (isset($_SESSION['new_order']) && $_SESSION['new_order'] === true && isset($_SESSION['order_id']) && $_SESSION['order_id'] == $orderId) {
    $sendEmail = true;
}

// Always clear these session variables to prevent issues with the checkout flow
$_SESSION['new_order'] = false;
unset($_SESSION['order_id']);
unset($_SESSION['redirect']);

// Log session cleanup
error_log("Cleared order session variables in order_confirmation.php");

// Fetch order details
try {
    // Log the query parameters for debugging
    error_log("Fetching order: ID=$orderId, Username=$username");

    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND username = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $bindResult = $stmt->bind_param("is", $orderId, $username);
    if (!$bindResult) {
        throw new Exception("Bind failed: " . $stmt->error);
    }

    $executeResult = $stmt->execute();
    if (!$executeResult) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Log the error for debugging
        error_log("Order not found: ID=$orderId, Username=$username");

        // Set error message and redirect with JavaScript
        $_SESSION['error'] = "Order not found. Please try again.";
        echo "<script>window.location.href = 'ordering.php';</script>";
        exit();
    }
} catch (Exception $e) {
    // Log the error
    error_log("Error fetching order: " . $e->getMessage());

    // Set error message and redirect with JavaScript
    $_SESSION['error'] = "Error retrieving order details. Please try again.";
    echo "<script>window.location.href = 'ordering.php';</script>";
    exit();
}

$order = $result->fetch_assoc();

// Safely decode JSON orders
$orderItems = [];
try {
    if (!empty($order['orders'])) {
        // Log the raw JSON for debugging
        error_log("Raw JSON orders for order ID $orderId: " . substr($order['orders'], 0, 200) . "...");

        // Attempt to decode the JSON
        $decodedItems = json_decode($order['orders'], true);

        // Check for JSON decode errors
        if ($decodedItems === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON decode error: " . json_last_error_msg());
        }

        if (is_array($decodedItems)) {
            $orderItems = $decodedItems;
            error_log("Successfully decoded " . count($orderItems) . " order items");
        } else {
            throw new Exception("Decoded JSON is not an array");
        }
    } else {
        error_log("No order items found for order ID $orderId");
    }
} catch (Exception $e) {
    // Log the error
    error_log("Error processing order items for order ID $orderId: " . $e->getMessage());
    error_log("Raw JSON: " . (isset($order['orders']) ? $order['orders'] : 'not set'));

    // Continue with empty order items rather than failing completely
    $orderItems = [];
}

// Send order confirmation email if this is a new order
if ($sendEmail) {
    $emailSent = sendOrderConfirmationEmail($conn, $orderId, $username);

    if ($emailSent) {
        // Set a success message to display to the user
        $emailSuccessMessage = "A confirmation email has been sent to your registered email address.";
    } else {
        // Set an error message
        $emailErrorMessage = "We couldn't send a confirmation email. Please contact customer support if you don't receive your order details.";
    }
}
?>

<!-- Additional styles for order confirmation page -->
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
            margin: 30px auto;
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

        /* Custom popup styling */
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

        @media (max-width: 767px) {
            .confirmation-container {
                padding: 25px;
                margin: 20px 15px;
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
            <a href="/LandingPage/ordering.php" class="btn-continue" onclick="resetOrderFlow()">
                <i class="fas fa-shopping-cart mr-2"></i> Continue Shopping
            </a>
        </div>

        <script>
            // Function to reset the order flow when continuing shopping
            function resetOrderFlow() {
                // Clear any remaining order session variables
                <?php
                echo "console.log('Resetting order flow...');";
                ?>

                // You can add additional client-side cleanup here if needed
                localStorage.removeItem('pendingOrder');
                sessionStorage.removeItem('checkoutInProgress');
            }
        </script>

        <div style="text-align: center; margin-top: 30px; color: #666; font-size: 0.9rem;">
            <?php if (isset($emailSuccessMessage)): ?>
                <p class="email-success"><i class="fas fa-envelope-open-text" style="color: #28a745; margin-right: 5px;"></i> <?php echo $emailSuccessMessage; ?></p>
            <?php elseif (isset($emailErrorMessage)): ?>
                <p class="email-error" style="color: #dc3545;"><i class="fas fa-exclamation-circle" style="margin-right: 5px;"></i> <?php echo $emailErrorMessage; ?></p>
            <?php else: ?>
                <p>If you need a copy of your order, please contact our customer service.</p>
            <?php endif; ?>
            <p>If you have any questions about your order, please contact our customer service at <strong>support@topexchange.com</strong></p>
        </div>
    </div>

    <!-- Custom Popup for Notifications -->
    <div id="customPopup" class="custom-popup" style="display: none;">
        <div class="popup-content">
            <span id="popupMessage"></span>
        </div>
    </div>

    <script>
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

        $(document).ready(function() {
            <?php if (isset($emailSuccessMessage)): ?>
                showPopup('<?php echo addslashes($emailSuccessMessage); ?>', false);
            <?php elseif (isset($emailErrorMessage)): ?>
                showPopup('<?php echo addslashes($emailErrorMessage); ?>', true);
            <?php endif; ?>
        });
    </script>

    <?php
    // Include the footer
    require_once 'footer.php';
    ?>