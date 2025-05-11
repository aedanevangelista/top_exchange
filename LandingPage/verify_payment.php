<?php
// Start the session
session_start();

// Check if user is logged in - using username instead of loggedin flag
if (!isset($_SESSION['username'])) {
    // Log the session data for debugging
    error_log("Verify Payment - Session not authenticated: " . json_encode($_SESSION));
    header("Location: login.php");
    exit;
}

// Log the session data for debugging
error_log("Verify Payment - Session authenticated for user: " . $_SESSION['username']);

// Check if payment reference and order ID are provided
if (!isset($_GET['reference']) || empty($_GET['reference']) || !isset($_GET['order_id']) || empty($_GET['order_id'])) {
    header("Location: dashboard.php");
    exit;
}

$paymentReference = $_GET['reference'];
$orderId = $_GET['order_id'];

// Verify that the payment reference matches the one in the session
if (!isset($_SESSION['payment_reference']) || $_SESSION['payment_reference'] !== $paymentReference) {
    $_SESSION['error'] = "Invalid payment reference.";
    header("Location: dashboard.php");
    exit;
}

// Connect to database
include_once('db_connection.php');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND username = ?");
$stmt->bind_param("is", $orderId, $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Order not found or doesn't belong to this user
    $_SESSION['error'] = "Order not found.";
    header("Location: dashboard.php");
    exit;
}

$order = $result->fetch_assoc();

// Check if payment_method column exists and is QR payment
if (isset($order['payment_method'])) {
    if ($order['payment_method'] !== 'qr_payment') {
        header("Location: order_confirmation.php?id=" . $orderId);
        exit;
    }

    // Check if payment is already completed
    if (isset($order['payment_status']) && $order['payment_status'] === 'Completed') {
        header("Location: order_confirmation.php?id=" . $orderId);
        exit;
    }
} else {
    // If payment_method column doesn't exist, redirect to order confirmation
    header("Location: order_confirmation.php?id=" . $orderId);
    exit;
}

// Check if payment_status column exists
$checkColumnQuery = "SHOW COLUMNS FROM `orders` LIKE 'payment_status'";
$checkResult = $conn->query($checkColumnQuery);
$hasPaymentStatusColumn = $checkResult && $checkResult->num_rows > 0;

if ($hasPaymentStatusColumn) {
    // Update payment status to completed
    $updateStmt = $conn->prepare("UPDATE orders SET payment_status = 'Completed' WHERE id = ?");
    $updateStmt->bind_param("i", $orderId);
    $updateResult = $updateStmt->execute();

    if (!$updateResult) {
        $_SESSION['error'] = "Failed to update payment status. Please contact support.";
        header("Location: dashboard.php");
        exit;
    }
}

// Clear the payment reference from the session
unset($_SESSION['payment_reference']);

// Set success message
$_SESSION['payment_success'] = true;

// Redirect to order confirmation page
header("Location: order_confirmation.php?id=" . $orderId);
exit;
?>
