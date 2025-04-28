<?php
// Set content type and prevent caching
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Start session and include required files
session_start();
include "../db_connection.php"; // Adjust path if necessary

// Initial response array
$response = [
    "success" => false,
    "message" => "",
    "orderItems" => []
];

try {
    // Check if PO number was provided
    if (!isset($_GET['po_number']) || empty($_GET['po_number'])) {
        throw new Exception("No PO number provided");
    }
    
    $poNumber = $_GET['po_number'];
    
    // Add debug logging
    error_log("Fetching order items for PO: $poNumber");
    
    // Prepare SQL statement
    $stmt = $conn->prepare("SELECT orders FROM orders WHERE po_number = ?");
    
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param("s", $poNumber);
    
    // Execute the statement
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    // Get the result
    $result = $stmt->get_result();
    
    // Check if order exists
    if ($result->num_rows === 0) {
        throw new Exception("Order not found");
    }
    
    // Fetch the order data
    $row = $result->fetch_assoc();
    
    // Close the statement
    $stmt->close();
    
    // Make sure orders field is not empty
    if (empty($row['orders'])) {
        throw new Exception("Order items are empty");
    }
    
    // Log the raw orders data for debugging
    error_log("Raw orders data: " . $row['orders']);
    
    // The orders column should already contain JSON data, no need to encode it
    $orderItems = json_decode($row['orders'], true);
    
    // Check if JSON was valid
    if ($orderItems === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to parse order items: " . json_last_error_msg());
    }
    
    // Check if order items is an array
    if (!is_array($orderItems)) {
        throw new Exception("Order items data is not in the expected format");
    }
    
    // Success!
    $response["success"] = true;
    $response["orderItems"] = $orderItems;
    
} catch (Exception $e) {
    // Set error message
    $response["message"] = $e->getMessage();
    
    // Log the error for server-side debugging
    error_log("Error in get_order_items.php: " . $e->getMessage());
}

// Return the response
echo json_encode($response);
exit;
?>