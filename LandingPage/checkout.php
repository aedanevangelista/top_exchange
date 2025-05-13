<?php
// Start the session and initialize cart if it hasn't been started already
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Process checkout form submission first, before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process the form submission (this code will be moved from below)
    processCheckoutForm();
}

// Set page-specific variables before including header
$pageTitle = "Checkout | Top Exchange Food Corp";
$pageDescription = "Complete your order with Top Food Exchange Corp. - Premium Filipino food products since 1998.";

// Log session data for debugging
error_log("Checkout.php - Session data: " . json_encode([
    'username' => $_SESSION['username'] ?? 'not set',
    'cart_count' => isset($_SESSION['cart']) ? count($_SESSION['cart']) : 'cart not set',
    'cart_items' => isset($_SESSION['cart']) ? array_keys($_SESSION['cart']) : 'cart not set'
]));

// Include the header now that we've processed any form submissions
require_once 'header.php';

// Handle redirects from form processing, but only if we're not already on the checkout page
if (isset($_SESSION['redirect']) && !isset($_POST['delivery_address'])) {
    // Only redirect to order_confirmation.php if this is a new order
    if (strpos($_SESSION['redirect'], 'order_confirmation.php') !== false &&
        (!isset($_SESSION['new_order']) || $_SESSION['new_order'] !== true)) {
        // This is not a new order, so don't redirect to order confirmation
        unset($_SESSION['redirect']);
        error_log("Prevented unnecessary redirect to order_confirmation.php");
    } else {
        $redirectUrl = $_SESSION['redirect'];
        unset($_SESSION['redirect']);
        echo "<script>window.location.href = '$redirectUrl';</script>";
        exit();
    }
}

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo "<script>window.location.href = 'login.php';</script>";
    exit();
}

// Check if cart is empty
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart']) || count($_SESSION['cart']) === 0) {
    // Initialize cart if it doesn't exist
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $_SESSION['error'] = "Your cart is empty. Please add items before checking out.";
    echo "<script>window.location.href = 'ordering.php';</script>";
    exit();
}

// Connect to database
include_once('db_connection.php');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to process checkout form
function processCheckoutForm() {
    // Connect to database
    include_once('db_connection.php');

    // Check connection
    if ($conn->connect_error) {
        $_SESSION['error'] = "Database connection failed. Please try again later.";
        return;
    }

    // Validate and sanitize input
    $deliveryAddress = trim($_POST['delivery_address']);
    $deliveryDate = $_POST['delivery_date'];
    $specialInstructions = trim($_POST['special_instructions']);
    $contactNumber = trim($_POST['contact_number']);
    $paymentMethod = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'check_payment';

    // Log the payment method for debugging
    error_log("Checkout - Payment method selected: " . $paymentMethod);

    // Basic validation
    if (empty($deliveryAddress) || empty($deliveryDate) || empty($contactNumber)) {
        $_SESSION['error'] = "Please fill in all required fields";
        return;
    }

    // Validate delivery day is Monday, Wednesday, or Friday
    $deliveryDay = date('l', strtotime($deliveryDate));
    if (!in_array($deliveryDay, ['Monday', 'Wednesday', 'Friday'])) {
        $_SESSION['error'] = "Delivery is only available on Monday, Wednesday, and Friday";
        return;
    }

    // Calculate order totals
    $subtotal = 0;
    $orderItems = [];
    foreach ($_SESSION['cart'] as $productId => $item) {
        // Skip invalid items
        if (!isset($item['price']) || !isset($item['quantity']) || !isset($item['name'])) {
            continue;
        }

        $price = isset($item['price']) ? (float)$item['price'] : 0;
        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;

        $itemSubtotal = $price * $quantity;
        $subtotal += $itemSubtotal;

        $orderItems[] = [
            'product_id' => $productId,
            'category' => $item['category'] ?? '',
            'item_description' => $item['name'],
            'packaging' => $item['packaging'] ?? '',
            'price' => $price,
            'quantity' => $quantity,
            'is_preorder' => isset($item['is_preorder']) ? (bool)$item['is_preorder'] : false
        ];
    }

    // Make sure we have items in the cart
    if (empty($orderItems)) {
        $_SESSION['error'] = "Your cart is empty. Please add items before checking out.";
        $_SESSION['redirect'] = "ordering.php";
        return;
    }

    // Remove delivery fee as requested
    $deliveryFee = 0;
    $total = $subtotal;

    // Generate a PO number in format PO-XXXX-00#
    $username = $_SESSION['username'];

    // Get first 4 letters of username and convert to uppercase
    $usernamePrefix = strtoupper(substr($username, 0, 4));

    // Get the next order number for this username
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE po_number LIKE ?");
    $poPattern = "PO-$usernamePrefix-%";
    $stmt->bind_param("s", $poPattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $nextOrderNum = ($row['count'] ?? 0) + 1;

    // Format the PO number
    $poNumber = sprintf("PO-%s-%03d", $usernamePrefix, $nextOrderNum);

    // Get the company name from clients_accounts
    $companyName = null;
    $companyStmt = $conn->prepare("SELECT company FROM clients_accounts WHERE username = ?");
    $companyStmt->bind_param("s", $username);
    $companyStmt->execute();
    $companyResult = $companyStmt->get_result();
    if ($companyResult->num_rows > 0) {
        $companyRow = $companyResult->fetch_assoc();
        $companyName = $companyRow['company'];
    }
    $companyStmt->close();

    // Log the generated PO number for debugging
    error_log("Generated PO number: " . $poNumber);

    try {
        // Log the order data for debugging
        error_log("Preparing to insert order: PO=" . $poNumber . ", Username=" . $username);

        // Check if payment_method and payment_status columns exist in the orders table
        $checkColumnsQuery = "SHOW COLUMNS FROM `orders` LIKE 'payment_method'";
        $checkResult = $conn->query($checkColumnsQuery);
        $hasPaymentColumns = $checkResult && $checkResult->num_rows > 0;

        // Prepare the SQL statement based on whether the payment columns exist
        if ($hasPaymentColumns) {
            // Insert order into database with payment_method and payment_status
            $stmt = $conn->prepare("INSERT INTO orders (
                po_number,
                username,
                company,
                order_date,
                delivery_date,
                delivery_address,
                contact_number,
                orders,
                total_amount,
                status,
                special_instructions,
                subtotal,
                payment_method,
                payment_status,
                order_type
            ) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, 'Pending', ?, ?, ?, 'Pending', 'Online')");
        } else {
            // Insert order into database without payment_method and payment_status
            $stmt = $conn->prepare("INSERT INTO orders (
                po_number,
                username,
                company,
                order_date,
                delivery_date,
                delivery_address,
                contact_number,
                orders,
                total_amount,
                status,
                special_instructions,
                subtotal,
                order_type
            ) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, 'Pending', ?, ?, 'Online')");
        }

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        // Ensure orderItems is properly formatted for JSON encoding
        foreach ($orderItems as &$item) {
            // Convert numeric values to ensure proper JSON encoding
            $item['price'] = (float)$item['price'];
            $item['quantity'] = (int)$item['quantity'];
            // Ensure all required fields exist
            if (!isset($item['category'])) $item['category'] = '';
            if (!isset($item['packaging'])) $item['packaging'] = '';

            // Log each item for debugging
            error_log("Order item: " . json_encode($item));
        }

        // Use JSON_UNESCAPED_UNICODE and JSON_UNESCAPED_SLASHES for better compatibility
        $jsonOrders = json_encode($orderItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($jsonOrders === false) {
            throw new Exception("JSON encoding failed: " . json_last_error_msg());
        }

        // Log the JSON string for debugging
        error_log("JSON orders string (first 200 chars): " . substr($jsonOrders, 0, 200) . "...");

        // Bind parameters based on whether payment columns exist
        if ($hasPaymentColumns) {
            // Bind parameters with payment_method
            $bindResult = $stmt->bind_param(
                "sssssssdsds",
                $poNumber,
                $username,
                $companyName,
                $deliveryDate,
                $deliveryAddress,
                $contactNumber,
                $jsonOrders,
                $total,
                $specialInstructions,
                $subtotal,
                $paymentMethod
            );
        } else {
            // Bind parameters without payment_method
            $bindResult = $stmt->bind_param(
                "sssssssdsd",
                $poNumber,
                $username,
                $companyName,
                $deliveryDate,
                $deliveryAddress,
                $contactNumber,
                $jsonOrders,
                $total,
                $specialInstructions,
                $subtotal
            );
        }

        if (!$bindResult) {
            throw new Exception("Bind failed: " . $stmt->error);
        }

        // Log before executing
        error_log("Executing order insert query...");

        $executeResult = $stmt->execute();

        if (!$executeResult) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        // Log success
        error_log("Order successfully inserted with ID: " . $stmt->insert_id);

        // Clear the cart
        $_SESSION['cart'] = [];

        // Get the inserted order ID
        $orderId = $stmt->insert_id;

        // Close statement
        $stmt->close();

        // Set a flag in session to indicate a new order was placed
        $_SESSION['new_order'] = true;
        $_SESSION['order_id'] = $orderId;

        // Log that we're setting up a new order
        error_log("New order created with ID: $orderId - Setting new_order flag");

        // Output the order ID for AJAX to capture
        echo "<div id='order_redirect' data-order-id='$orderId'>order_confirmation.php?id=$orderId</div>";

        // Also set redirect in session as fallback
        $_SESSION['redirect'] = "order_confirmation.php?id=" . $orderId;

        // Set a timestamp to track when this order was created
        $_SESSION['order_timestamp'] = time();

    } catch (Exception $e) {
        // Log detailed error information
        error_log("Order processing error: " . $e->getMessage());
        error_log("Order data: " . json_encode([
            'username' => $username,
            'poNumber' => $poNumber,
            'deliveryDate' => $deliveryDate,
            'deliveryAddress' => $deliveryAddress,
            'contactNumber' => $contactNumber,
            'total' => $total,
            'subtotal' => $subtotal,
            'itemCount' => count($orderItems)
        ]));

        // Provide a more helpful error message if possible
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            $_SESSION['error'] = "A duplicate order was detected. Please try again.";
        } else {
            $_SESSION['error'] = "Error processing your order. Please try again. Error: " . $e->getMessage();
        }
    }
}

// Helper function to get the next order number for a user
function getNextOrderNumber($conn, $username) {
    // Look for PO numbers in the format PO-username-00X
    $stmt = $conn->prepare("SELECT po_number FROM orders WHERE username = ? AND po_number LIKE CONCAT('PO-', ?, '-%') ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastPO = $row['po_number'];

        // Extract the numeric part
        if (preg_match('/PO-' . preg_quote($username, '/') . '-(\d+)$/', $lastPO, $matches)) {
            return (int)$matches[1];
        }
    }

    // If no previous order or format doesn't match, start with 0
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

// Additional checkout-specific styles
?>

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
            top: 96px; /* Adjusted to account for fixed header (76px header + 20px spacing) */
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

        /* Payment method styles */
        .payment-method-selection {
            margin-bottom: 15px;
        }

        .payment-options {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }

        .payment-option {
            flex: 1;
            min-width: 200px;
            margin: 0;
            padding: 0;
        }

        .payment-option .form-check-input {
            position: absolute;
            opacity: 0;
        }

        .payment-option-content {
            border: 2px solid #e0e0e0;
            border-radius: var(--border-radius);
            padding: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .payment-option .form-check-input:checked + .form-check-label .payment-option-content {
            border-color: var(--primary-color);
            background-color: rgba(154, 116, 50, 0.05);
            box-shadow: 0 2px 8px rgba(154, 116, 50, 0.15);
        }

        .payment-option-title {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 5px;
        }

        .payment-option-desc {
            font-size: 0.85rem;
            color: #666;
        }

        .payment-info-box {
            background-color: #f9f9f9;
            border-radius: var(--border-radius);
            padding: 15px;
            margin-top: 15px;
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

        /* Password confirmation styling */
        .confirmation-modal .form-group {
            text-align: left;
        }

        .confirmation-modal .form-control {
            border-radius: var(--border-radius);
            padding: 10px 15px;
            border: 1px solid #ddd;
            transition: var(--transition);
        }

        .confirmation-modal .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(154, 116, 50, 0.25);
        }

        .confirmation-modal .form-control.is-invalid {
            border-color: var(--accent-color);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='%23dc3545' viewBox='0 0 12 12'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(.375em + .1875rem) center;
            background-size: calc(.75em + .375rem) calc(.75em + .375rem);
        }

        .confirmation-modal .invalid-feedback {
            display: none;
            width: 100%;
            margin-top: .25rem;
            font-size: 80%;
            color: var(--accent-color);
        }

        .confirmation-modal .form-control.is-invalid ~ .invalid-feedback {
            display: block;
        }

        .confirmation-modal .form-control.is-valid {
            border-color: #28a745;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(.375em + .1875rem) center;
            background-size: calc(.75em + .375rem) calc(.75em + .375rem);
        }

        /* Payment info box styling */
        .payment-info-box {
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-left: 4px solid var(--primary-color);
            border-radius: var(--border-radius);
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .payment-info-box h4 {
            color: var(--primary-color);
            font-size: 1.1rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .payment-info-box h4 i {
            margin-right: 10px;
            color: #28a745;
        }

        .payment-info-box .info-text {
            margin-bottom: 0;
            color: #555;
        }

        /* Order steps styling */
        .order-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
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
            background-color: #28a745;
            border-color: #28a745;
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
            color: #28a745;
            font-weight: 600;
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

    <!-- Main Content Section -->
    <main class="main-content">
        <div class="checkout-container">
            <!-- Order Steps -->
            <div class="order-steps" data-aos="fade-up">
                <div class="step completed">
                    <div class="step-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="step-label">Shopping Cart</div>
                </div>
                <div class="step active">
                    <div class="step-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="step-label">Checkout</div>
                </div>
                <div class="step">
                    <div class="step-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="step-label">Confirmation</div>
                </div>
            </div>

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
                                <div class="payment-method-selection">
                                    <h4><i class="fas fa-credit-card"></i> Payment Method</h4>
                                    <div class="payment-options">
                                        <div class="form-check payment-option">
                                            <input class="form-check-input" type="radio" name="payment_method" id="payment_check" value="check_payment" checked>
                                            <label class="form-check-label" for="payment_check">
                                                <div class="payment-option-content">
                                                    <div class="payment-option-title">Check Payment</div>
                                                    <div class="payment-option-desc">Pay with check upon delivery</div>
                                                </div>
                                            </label>
                                        </div>
                                        <div class="form-check payment-option">
                                            <input class="form-check-input" type="radio" name="payment_method" id="payment_qr" value="qr_payment">
                                            <label class="form-check-label" for="payment_qr">
                                                <div class="payment-option-content">
                                                    <div class="payment-option-title">QR Payment</div>
                                                    <div class="payment-option-desc">Pay now by scanning a QR code</div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div id="check_payment_info" class="payment-info-box">
                                    <p class="info-text">All orders will be processed with check payment upon delivery. Please have your check ready when your order arrives.</p>
                                </div>
                                <div id="qr_payment_info" class="payment-info-box" style="display: none;">
                                    <p class="info-text">You'll be redirected to scan a QR code after confirming your order. Your order will be processed once payment is confirmed.</p>
                                </div>
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
                            $hasValidItems = false;

                            if (isset($_SESSION['cart']) && is_array($_SESSION['cart']) && !empty($_SESSION['cart'])):
                                foreach ($_SESSION['cart'] as $productId => $item):
                                    // Skip invalid items
                                    if (!isset($item['price']) || !isset($item['quantity']) || !isset($item['name'])) {
                                        continue;
                                    }

                                    $hasValidItems = true;
                                    $price = isset($item['price']) ? (float)$item['price'] : 0;
                                    $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;

                                    $itemSubtotal = $price * $quantity;
                                    $subtotal += $itemSubtotal;
                            ?>
                                <div class="order-item">
                                    <div>
                                        <div class="order-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                        <div class="order-item-details">
                                            x<?php echo $quantity; ?>
                                            <?php if (!empty($item['packaging'])): ?>
                                                • <?php echo htmlspecialchars($item['packaging']); ?>
                                            <?php endif; ?>
                                            <?php if (!empty($item['category'])): ?>
                                                • <span class="badge badge-info"><?php echo htmlspecialchars($item['category']); ?></span>
                                            <?php endif; ?>
                                            <?php if (isset($item['is_preorder']) && $item['is_preorder']): ?>
                                                • <span class="badge badge-danger">Pre-order</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div>₱<?php echo number_format($itemSubtotal, 2); ?></div>
                                </div>
                            <?php
                                endforeach;
                            endif;

                            if (!$hasValidItems):
                            ?>
                                <div class="text-center py-4">
                                    <i class="fa fa-shopping-cart fa-3x mb-3" style="color: #ddd;"></i>
                                    <h5>Your cart is empty</h5>
                                    <p>Please add items to your cart before checkout</p>
                                    <a href="ordering.php" class="btn btn-primary mt-2">Go to Products</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="order-totals">
                            <div class="total-row">
                                <span>Subtotal:</span>
                                <span id="summarySubtotal">₱<?php echo number_format($subtotal, 2); ?></span>
                            </div>

                            <div class="total-row grand-total">
                                <span>Total Amount:</span>
                                <span id="summaryTotal">₱<?php echo number_format($subtotal, 2); ?></span>
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
    <div class="modal fade confirmation-modal" id="confirmationModal" tabindex="-1" role="dialog" aria-labelledby="confirmationModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
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
                        <?php
                        $hasItems = false;
                        if (isset($_SESSION['cart']) && is_array($_SESSION['cart']) && !empty($_SESSION['cart'])):
                            foreach ($_SESSION['cart'] as $productId => $item):
                                // Skip invalid items
                                if (!isset($item['price']) || !isset($item['quantity']) || !isset($item['name'])) {
                                    continue;
                                }

                                $hasItems = true;
                                $price = isset($item['price']) ? (float)$item['price'] : 0;
                                $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
                                $itemSubtotal = $price * $quantity;
                        ?>
                            <div class="order-summary-item">
                                <span>
                                    <?php echo htmlspecialchars($item['name']) . ' x ' . $quantity; ?>
                                    <?php if (!empty($item['category'])): ?>
                                        <small class="d-block text-muted"><?php echo htmlspecialchars($item['category']); ?></small>
                                    <?php endif; ?>
                                    <?php if (isset($item['is_preorder']) && $item['is_preorder']): ?>
                                        <small class="d-block"><span class="badge badge-danger">Pre-order</span></small>
                                    <?php endif; ?>
                                </span>
                                <span>₱<?php echo number_format($itemSubtotal, 2); ?></span>
                            </div>
                        <?php
                            endforeach;
                        endif;

                        if (!$hasItems):
                        ?>
                            <div class="text-center">
                                <p>No items in cart</p>
                            </div>
                        <?php endif; ?>

                        <div class="order-summary-item mt-3 pt-2 border-top">
                            <span><strong>Subtotal:</strong></span>
                            <span><strong>₱<?php echo number_format($subtotal, 2); ?></strong></span>
                        </div>

                        <div class="order-summary-item mt-2 pt-2 border-top">
                            <span><strong>Total Amount:</strong></span>
                            <span><strong class="text-primary">₱<?php echo number_format($subtotal, 2); ?></strong></span>
                        </div>
                    </div>

                    <div class="form-group mt-4">
                        <label for="confirm_password" class="text-left d-block">Enter your password to confirm:</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" placeholder="Your password" required>
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="button" id="togglePasswordBtn">
                                    <i class="fas fa-eye" id="togglePasswordIcon"></i>
                                </button>
                            </div>
                        </div>
                        <div id="password_error" class="invalid-feedback">Please enter your password</div>
                        <div class="custom-control custom-checkbox mt-2">
                            <input type="checkbox" class="custom-control-input" id="rememberPassword">
                            <label class="custom-control-label" for="rememberPassword">Remember password for future orders</label>
                        </div>
                    </div>

                    <div class="text-center mt-3">
                        <button type="button" class="btn btn-secondary btn-cancel" data-dismiss="modal">Cancel</button>
                        <button type="button" id="confirmOrderBtn" class="btn btn-primary btn-confirm">Confirm Order</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Popup for Notifications -->
    <div id="customPopup" class="custom-popup" style="display: none;">
        <div class="popup-content">
            <span id="popupMessage"></span>
        </div>
    </div>

    <?php
    // Include the footer
    require_once 'footer.php';
    ?>
    <!-- Make sure Bootstrap JS is loaded for modals -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Password Manager -->
    <script>
    /**
     * Password Manager for Order Confirmation
     *
     * This script handles the secure storage and retrieval of passwords
     * for the order confirmation process.
     */

    // Use a namespace to avoid conflicts
    const PasswordManager = {
        // Key for storing the password in localStorage
        storageKey: 'order_confirm_pwd',

        // Save password to localStorage (encrypted)
        savePassword: function(password) {
            if (!password) return false;

            try {
                // Simple encryption (not truly secure, but better than plaintext)
                // In a production environment, consider using a proper encryption library
                const encryptedPassword = btoa(password.split('').reverse().join(''));
                localStorage.setItem(this.storageKey, encryptedPassword);
                return true;
            } catch (e) {
                console.error('Error saving password:', e);
                return false;
            }
        },

        // Get password from localStorage
        getPassword: function() {
            try {
                const encryptedPassword = localStorage.getItem(this.storageKey);
                if (!encryptedPassword) return null;

                // Decrypt the password
                return atob(encryptedPassword).split('').reverse().join('');
            } catch (e) {
                console.error('Error retrieving password:', e);
                return null;
            }
        },

        // Clear saved password
        clearPassword: function() {
            try {
                localStorage.removeItem(this.storageKey);
                return true;
            } catch (e) {
                console.error('Error clearing password:', e);
                return false;
            }
        },

        // Check if a password is saved
        hasPassword: function() {
            return localStorage.getItem(this.storageKey) !== null;
        }
    };
    </script>

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
            // Initialize all modals
            $('.modal').modal({
                show: false
            });

            // Show error message if exists
            <?php if (isset($errorMessage)): ?>
                showPopup('<?php echo addslashes($errorMessage); ?>', true);
            <?php endif; ?>

            // Handle payment method selection
            $('input[name="payment_method"]').change(function() {
                const selectedMethod = $('input[name="payment_method"]:checked').val();

                // Hide all payment info boxes
                $('.payment-info-box').hide();

                // Show the selected payment info box
                if (selectedMethod === 'check_payment') {
                    $('#check_payment_info').show();
                } else if (selectedMethod === 'qr_payment') {
                    $('#qr_payment_info').show();
                }
            });

            $('#placeOrderBtn').click(function(e) {
                e.preventDefault();
                console.log('Place Order button clicked');

                // Show a popup to confirm the button was clicked
                showPopup('Processing your order...', false);

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

                // Show confirmation modal - use direct jQuery method to ensure it works
                console.log('Showing confirmation modal');
                $('#confirmationModal').modal({
                    backdrop: 'static',
                    keyboard: false,
                    show: true
                });

                // Check if we have a saved password
                if (PasswordManager.hasPassword()) {
                    // Pre-fill the password field
                    $('#confirm_password').val(PasswordManager.getPassword());
                    // Trigger input event to validate the password
                    $('#confirm_password').trigger('input');
                    // Check the remember password checkbox
                    $('#rememberPassword').prop('checked', true);
                }
            });

            // Handle confirm order button click
            $('#confirmOrderBtn').click(function() {
                // Get the password
                const password = $('#confirm_password').val();

                // Validate password
                if (!password) {
                    $('#confirm_password').addClass('is-invalid');
                    $('#password_error').text('Please enter your password');
                    return;
                }

                // Disable button to prevent multiple clicks
                $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Verifying...');

                // Verify password via AJAX
                $.ajax({
                    url: 'verify_password.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        password: password
                    },
                    success: function(response) {
                        if (response.success) {
                            // Check if we should remember the password
                            if ($('#rememberPassword').is(':checked')) {
                                PasswordManager.savePassword(password);
                            } else {
                                // Clear any saved password
                                PasswordManager.clearPassword();
                            }

                            // Password verified, submit the form
                            $('#confirmOrderBtn').html('<i class="fas fa-spinner fa-spin"></i> Processing...');

                            // Submit the form with AJAX to avoid redirect issues
                            $.ajax({
                                url: 'checkout.php',
                                type: 'POST',
                                data: $('#checkoutForm').serialize(),
                                success: function(response) {
                                    // Check if there's a redirect URL in the session
                                    if (response.indexOf('order_confirmation.php') !== -1) {
                                        // Extract the order ID from the response
                                        let orderId = null;
                                        const match = response.match(/order_confirmation\.php\?id=(\d+)/);
                                        if (match && match[1]) {
                                            orderId = match[1];
                                        }

                                        if (orderId) {
                                            // Check if QR payment was selected
                                            const paymentMethod = $('input[name="payment_method"]:checked').val();

                                            if (paymentMethod === 'qr_payment') {
                                                // Log the redirection for debugging
                                                console.log('Redirecting to QR payment page with order ID: ' + orderId);

                                                // Store payment method in session via a hidden AJAX call
                                                $.ajax({
                                                    url: 'set_session_var.php',
                                                    type: 'POST',
                                                    data: {
                                                        key: 'payment_method',
                                                        value: 'qr_payment'
                                                    },
                                                    async: false // Make sure this completes before redirect
                                                });

                                                // Redirect to QR payment page
                                                window.location.href = 'qr_payment.php?id=' + orderId;
                                            } else {
                                                // Log the redirection for debugging
                                                console.log('Redirecting to order confirmation page with order ID: ' + orderId);

                                                // Redirect to order confirmation page
                                                window.location.href = 'order_confirmation.php?id=' + orderId;
                                            }
                                        } else {
                                            // Fallback to direct form submission
                                            $('#checkoutForm').submit();
                                        }
                                    } else {
                                        // Fallback to direct form submission
                                        $('#checkoutForm').submit();
                                    }
                                },
                                error: function() {
                                    // Fallback to direct form submission on error
                                    $('#checkoutForm').submit();
                                }
                            });
                        } else {
                            // Show error message
                            $('#confirm_password').addClass('is-invalid');
                            $('#password_error').text(response.message || 'Incorrect password');

                            // Re-enable the button
                            $('#confirmOrderBtn').prop('disabled', false).html('Confirm Order');
                        }
                    },
                    error: function(xhr, status, error) {
                        // Show error message
                        showPopup('Error verifying password. Please try again.', true);

                        // Re-enable the button
                        $('#confirmOrderBtn').prop('disabled', false).html('Confirm Order');
                    }
                });
            });

            // Remove invalid class when user starts typing
            $('input, textarea, select').on('input change', function() {
                if ($(this).val()) {
                    $(this).removeClass('is-invalid');
                }
            });

            // Live password validation
            let passwordCheckTimeout;
            $('#confirm_password').on('input', function() {
                const password = $(this).val();

                // Clear any existing timeout
                clearTimeout(passwordCheckTimeout);

                // Don't check empty passwords
                if (!password) {
                    return;
                }

                // Set a small delay to avoid too many requests
                passwordCheckTimeout = setTimeout(function() {
                    // Check password via AJAX
                    $.ajax({
                        url: 'verify_password.php',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            password: password
                        },
                        success: function(response) {
                            if (response.success) {
                                // Password is correct
                                $('#confirm_password').removeClass('is-invalid').addClass('is-valid');
                                $('#password_error').text('');
                            } else {
                                // Password is incorrect
                                $('#confirm_password').removeClass('is-valid').addClass('is-invalid');
                                $('#password_error').text(response.message || 'Incorrect password');
                            }
                        }
                    });
                }, 500); // 500ms delay
            });

            // Clear password field and error when modal is closed
            $('#confirmationModal').on('hidden.bs.modal', function() {
                $('#confirm_password').val('').removeClass('is-invalid is-valid');
                $('#password_error').text('Please enter your password');
                $('#confirmOrderBtn').prop('disabled', false).html('Confirm Order');
                // Reset password visibility
                $('#confirm_password').attr('type', 'password');
                $('#togglePasswordIcon').removeClass('fa-eye-slash').addClass('fa-eye');
                // Clear any pending password check
                clearTimeout(passwordCheckTimeout);
            });

            // Toggle password visibility
            $('#togglePasswordBtn').click(function() {
                const passwordInput = $('#confirm_password');
                const icon = $('#togglePasswordIcon');

                if (passwordInput.attr('type') === 'password') {
                    passwordInput.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    passwordInput.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });
        });
    </script>