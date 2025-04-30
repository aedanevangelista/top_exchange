<?php
// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users
ini_set('log_errors', 1);     // Do log errors

// Set headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Start session and include database connection
session_start();

// Adjust the path as needed - this is a common issue
$base_path = realpath($_SERVER['DOCUMENT_ROOT']);
include_once $base_path . "/admin/backend/db_connection.php";

// Log the start of the request for debugging
error_log("get_order_items.php started. Looking for PO: " . ($_GET['po_number'] ?? 'none provided'));

// Initial response array
$response = [
    "success" => false,
    "message" => "",
    "orderItems" => []
];

try {
    // Validate input
    if (!isset($_GET['po_number']) || empty($_GET['po_number'])) {
        throw new Exception("No PO number provided");
    }
    
    $poNumber = trim($_GET['po_number']);
    error_log("Processing request for PO: $poNumber");
    
    // Verify database connection
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection error: " . ($conn->connect_error ?? "Connection not established"));
    }
    
    // Prepare SQL statement
    $sql = "SELECT orders FROM orders WHERE po_number = ?";
    error_log("Executing SQL: $sql with param: $poNumber");
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    
    // Bind parameter and execute
    $stmt->bind_param("s", $poNumber);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    // Get result
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception("Failed to get result: " . $stmt->error);
    }
    
    // Check if order exists
    if ($result->num_rows === 0) {
        throw new Exception("Order with PO number $poNumber not found");
    }
    
    // Fetch the order data
    $row = $result->fetch_assoc();
    $stmt->close();
    
    // Check if orders field exists and is not empty
    if (!isset($row['orders']) || empty($row['orders'])) {
        throw new Exception("Order items are empty");
    }
    
    // Log the raw orders data for debugging
    error_log("Raw orders data length: " . strlen($row['orders']));
    
    // Parse the JSON data
    $orderItems = json_decode($row['orders'], true);
    
    // Check for JSON errors
    if ($orderItems === null) {
        $jsonError = json_last_error_msg();
        error_log("JSON parse error: $jsonError for data: " . substr($row['orders'], 0, 100) . "...");
        throw new Exception("Failed to parse order items: $jsonError");
    }
    
    // Verify the data is an array
    if (!is_array($orderItems)) {
        error_log("Unexpected data format: " . gettype($orderItems));
        throw new Exception("Order items data is not in the expected array format");
    }
    
    // Success!
    $response["success"] = true;
    $response["orderItems"] = $orderItems;
    error_log("Successfully retrieved order items for PO: $poNumber");
    
} catch (Exception $e) {
    // Set error message
    $response["message"] = $e->getMessage();
    error_log("Error in get_order_items.php: " . $e->getMessage());
}

// Ensure clean output with no warnings or notices
ob_clean();

// Return the response
echo json_encode($response);
exit;
?>