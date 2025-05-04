<?php
session_start();
include "db_connection.php";
include "check_role.php";

// Ensure the user is logged in
if (!isset($_SESSION['admin_user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Set content type to JSON
header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get data from either POST or JSON input
    $data = [];
    $input = file_get_contents('php://input');
    
    if (!empty($input)) {
        $data = json_decode($input, true);
    } else {
        $data = $_POST;
    }
    
    if (!isset($data['po_number']) || !isset($data['status'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }
    
    $po_number = $data['po_number'];
    $new_status = $data['status'];
    
    // Validate status
    if ($new_status !== 'For Delivery' && $new_status !== 'In Transit') {
        echo json_encode(['success' => false, 'message' => 'Invalid status value']);
        exit;
    }
    
    // Log the received data
    error_log("Toggle Transit Status - PO: $po_number, New Status: $new_status");
    
    // Begin transaction
    $conn->begin_transaction();

    try {
        // Get current status for logging
        $stmt = $conn->prepare("SELECT status FROM orders WHERE po_number = ?");
        $stmt->bind_param("s", $po_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Order not found");
        }
        
        $row = $result->fetch_assoc();
        $old_status = $row['status'];
        
        // Only allow toggling between 'For Delivery' and 'In Transit'
        if ($old_status !== 'For Delivery' && $old_status !== 'In Transit') {
            throw new Exception("Cannot change status: Order is not in a deliverable state");
        }
        
        // Update the order status
        $update_stmt = $conn->prepare("UPDATE orders SET status = ? WHERE po_number = ?");
        $update_stmt->bind_param("ss", $new_status, $po_number);
        $update_stmt->execute();
        
        if ($update_stmt->affected_rows === 0) {
            error_log("No rows affected when updating order status");
        }
        
        // Update driver assignment status as well
        $update_driver_stmt = $conn->prepare("UPDATE driver_assignments SET status = ? WHERE po_number = ?");
        $driver_status = ($new_status === 'In Transit') ? 'In Transit' : 'Assigned';
        $update_driver_stmt->bind_param("ss", $driver_status, $po_number);
        $update_driver_stmt->execute();
        
        // Log the status change
        $log_stmt = $conn->prepare("INSERT INTO order_status_logs (po_number, old_status, new_status, changed_by, changed_at) VALUES (?, ?, ?, ?, NOW())");
        $changed_by = $_SESSION['username'] ?? 'system';
        $log_stmt->bind_param("ssss", $po_number, $old_status, $new_status, $changed_by);
        $log_stmt->execute();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error in toggle_transit_status: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
exit;
?>