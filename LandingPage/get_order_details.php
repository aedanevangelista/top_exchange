<?php
// Start the session and prevent caching
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json');

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to view order details.'
    ]);
    exit();
}

// Check if order number is provided
if (!isset($_GET['po_number']) || empty($_GET['po_number'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No order specified.'
    ]);
    exit();
}

$po_number = $_GET['po_number'];

// Database connection
include_once('db_connection.php');

// Check connection
if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit();
}

// Get order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE po_number = ? AND username = ?");
$stmt->bind_param("ss", $po_number, $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Order not found or you don\'t have permission to view it.'
    ]);
    $conn->close();
    exit();
}

$order = $result->fetch_assoc();

// Parse the order items from JSON
$orderItems = json_decode($order['orders'], true);

// Check if JSON decoding was successful
if ($orderItems === null && json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error: " . json_last_error_msg() . " for order " . $po_number);
    echo json_encode([
        'success' => false,
        'message' => 'Error parsing order items: ' . json_last_error_msg()
    ]);
    $conn->close();
    exit();
}

// Sanitize order data for JSON output
$sanitizedOrder = [
    'id' => $order['id'],
    'po_number' => $order['po_number'],
    'username' => $order['username'],
    'order_date' => $order['order_date'],
    'delivery_date' => $order['delivery_date'],
    'delivery_address' => $order['delivery_address'],
    'contact_number' => $order['contact_number'],
    'total_amount' => $order['total_amount'],
    'status' => $order['status'],
    'special_instructions' => $order['special_instructions'],
    'subtotal' => $order['subtotal']
];

// Close the database connection
$conn->close();

// Return the order details as JSON
echo json_encode([
    'success' => true,
    'order' => $sanitizedOrder,
    'items' => $orderItems
]);
?>
