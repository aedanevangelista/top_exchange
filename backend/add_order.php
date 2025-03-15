<?php
include 'db_connection.php'; // Ensure this includes your database connection logic

header('Content-Type: application/json'); // Always return JSON

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Collect form data
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $order_date = isset($_POST['order_date']) ? $_POST['order_date'] : '';
    $delivery_date = isset($_POST['delivery_date']) ? $_POST['delivery_date'] : '';
    $po_number = isset($_POST['po_number']) ? $_POST['po_number'] : '';
    $orders_json = isset($_POST['orders']) ? $_POST['orders'] : '';
    $total_amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;
    
    // Basic validation
    if (empty($username) || empty($order_date) || empty($delivery_date) || empty($po_number) || empty($orders_json)) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields'
        ]);
        exit;
    }
    
    // Validate JSON data
    $orders = json_decode($orders_json, true);
    if ($orders === null && json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON data: ' . json_last_error_msg() . ' - ' . htmlspecialchars($orders_json)
        ]);
        exit;
    }
    
    // Set default status
    $status = "Pending";
    
    // Determine the correct column name
    $orderColumnName = 'products_ordered'; // Change this if your column is named differently
    
    // Check if the column exists
    $result = $conn->query("SHOW COLUMNS FROM orders LIKE '$orderColumnName'");
    if ($result->num_rows == 0) {
        $orderColumnName = 'orders'; // Fallback to the original column name
        
        // Double check if this column exists
        $result = $conn->query("SHOW COLUMNS FROM orders LIKE '$orderColumnName'");
        if ($result->num_rows == 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Database error: Neither products_ordered nor orders column exists in the orders table'
            ]);
            exit;
        }
    }
    
    // Insert into orders table
    try {
        $query = "INSERT INTO orders (po_number, username, order_date, delivery_date, $orderColumnName, total_amount, status) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)";
                 
        $insertOrder = $conn->prepare($query);
        
        if (!$insertOrder) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $insertOrder->bind_param("ssssdss", $po_number, $username, $order_date, $delivery_date, $orders_json, $total_amount, $status);
        
        if ($insertOrder->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Order successfully added!'
            ]);
        } else {
            throw new Exception("Execute failed: " . $insertOrder->error);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>