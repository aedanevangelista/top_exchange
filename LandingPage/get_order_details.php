<?php
// Start the session and prevent caching
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json');

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

// Check if order number is provided
if (!isset($_GET['po_number'])) {
    echo json_encode(['error' => 'No order number provided']);
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
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Get logged in username
$username = $_SESSION['username'];

// Initialize variables
$orderDetails = null;
$orderItems = [];

// For debugging
error_log("Fetching order: " . $po_number . " for user: " . $username);

// Get order details
$query = "SELECT * FROM orders WHERE po_number = ? AND username = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $po_number, $username);
$stmt->execute();
$result = $stmt->get_result();

// For debugging
error_log("Query result rows: " . $result->num_rows);

if ($result->num_rows > 0) {
    $orderDetails = $result->fetch_assoc();

    // Format dates
    $orderDetails['formatted_order_date'] = date('F j, Y', strtotime($orderDetails['order_date']));
    $orderDetails['formatted_delivery_date'] = date('F j, Y', strtotime($orderDetails['delivery_date']));

    // Check if order_items table exists
    $tableCheckResult = $conn->query("SHOW TABLES LIKE 'order_items'");
    $orderItemsTableExists = $tableCheckResult->num_rows > 0;
    error_log("Order items table exists: " . ($orderItemsTableExists ? "Yes" : "No"));

    if ($orderItemsTableExists) {
        // Get order items from order_items table
        $itemsQuery = "SELECT * FROM order_items WHERE po_number = ?";
        $itemsStmt = $conn->prepare($itemsQuery);
        $itemsStmt->bind_param("s", $po_number);
        $itemsStmt->execute();
        $itemsResult = $itemsStmt->get_result();

        // For debugging
        error_log("Order items query result rows: " . $itemsResult->num_rows);
    } else {
        // Fallback: Create a dummy item based on order total
        $dummyItem = [
            'item_name' => 'Order #' . $po_number,
            'item_description' => 'Order details not available',
            'quantity' => 1,
            'unit_price' => $orderDetails['total_amount'],
            'item_total' => $orderDetails['total_amount'],
            'formatted_unit_price' => number_format($orderDetails['total_amount'], 2),
            'formatted_item_total' => number_format($orderDetails['total_amount'], 2)
        ];
        $orderItems[] = $dummyItem;

        // Skip the while loop below
        $itemsResult = null;
        error_log("Using fallback order item");
    }

    if ($itemsResult !== null) {
        while ($item = $itemsResult->fetch_assoc()) {
            // Calculate item total
            $item['item_total'] = $item['quantity'] * $item['unit_price'];
            $item['formatted_unit_price'] = number_format($item['unit_price'], 2);
            $item['formatted_item_total'] = number_format($item['item_total'], 2);
            $orderItems[] = $item;
        }
    }

    // Format currency values
    $orderDetails['formatted_total'] = number_format($orderDetails['total_amount'], 2);
    $orderDetails['formatted_subtotal'] = number_format($orderDetails['subtotal'] ?? $orderDetails['total_amount'], 2);

    if (isset($orderDetails['shipping_fee'])) {
        $orderDetails['formatted_shipping_fee'] = number_format($orderDetails['shipping_fee'], 2);
    }

    if (isset($orderDetails['tax'])) {
        $orderDetails['formatted_tax'] = number_format($orderDetails['tax'], 2);
    }

    if (isset($orderDetails['discount'])) {
        $orderDetails['formatted_discount'] = number_format($orderDetails['discount'], 2);
    }

    echo json_encode([
        'success' => true,
        'order' => $orderDetails,
        'items' => $orderItems
    ]);
} else {
    // Order not found or doesn't belong to this user
    echo json_encode(['error' => 'Order not found']);
}

$conn->close();
?>
