<?php
session_start();
include "../db_connection.php";

// Check if user is logged in
if (!isset($_SESSION['admin_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Verify request method and required data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id']) && isset($_POST['status'])) {
    $order_id = $_POST['order_id'];
    $status = $_POST['status'];
    $username = $_POST['username'] ?? $_SESSION['admin_username'];
    $timestamp = $_POST['timestamp'] ?? date('Y-m-d H:i:s');
    
    // Validate status (only allow Active or Rejected)
    if ($status !== 'Active' && $status !== 'Rejected') {
        echo json_encode(['success' => false, 'message' => 'Invalid status value']);
        exit();
    }
    
    // First verify the order exists and is in 'Pending' status
    $check_sql = "SELECT * FROM orders WHERE id = ? AND status = 'Pending'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $order_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Order not found or not in Pending status']);
        $check_stmt->close();
        exit();
    }
    $check_stmt->close();
    
    // Update order status
    $update_sql = "UPDATE orders SET status = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $status, $order_id);
    
    if ($update_stmt->execute()) {
        // Log the status change in order_status_history table if it exists
        $order_data = $result->fetch_assoc();
        
        // Check if order_status_history table exists (you can create this if needed)
        $table_check = $conn->query("SHOW TABLES LIKE 'order_status_history'");
        if ($table_check->num_rows > 0) {
            $log_sql = "INSERT INTO order_status_history (order_id, po_number, old_status, new_status, changed_by, changed_at) 
                        VALUES (?, ?, 'Pending', ?, ?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("issss", $order_id, $order_data['po_number'], $status, $username, $timestamp);
            $log_stmt->execute();
            $log_stmt->close();
        }
        
        $update_stmt->close();
        echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);
    } else {
        $update_stmt->close();
        echo json_encode(['success' => false, 'message' => 'Failed to update order status: ' . $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
}
?>