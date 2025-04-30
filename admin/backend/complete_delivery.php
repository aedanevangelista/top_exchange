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
    
    if (!isset($data['po_number'])) {
        echo json_encode(['success' => false, 'message' => 'Missing PO number']);
        exit;
    }
    
    $po_number = $data['po_number'];
    
    // Begin transaction
    $conn->begin_transaction();

    try {
        // Check current status
        $checkStmt = $conn->prepare("SELECT status FROM orders WHERE po_number = ?");
        $checkStmt->bind_param("s", $po_number);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Order not found");
        }
        
        $row = $result->fetch_assoc();
        $old_status = $row['status'];
        
        // Only allow completing from 'For Delivery' or 'In Transit' status
        if ($old_status !== 'For Delivery' && $old_status !== 'In Transit') {
            throw new Exception("Cannot complete order: Order is not in a deliverable state");
        }
        
        // 1. Update order status to Completed
        $updateOrderStmt = $conn->prepare("UPDATE orders SET status = 'Completed' WHERE po_number = ?");
        $updateOrderStmt->bind_param("s", $po_number);
        $updateOrderStmt->execute();
        
        if ($updateOrderStmt->affected_rows === 0) {
            throw new Exception("Failed to update order status");
        }
        
        // 2. Update driver assignment status
        $updateAssignmentStmt = $conn->prepare("UPDATE driver_assignments SET status = 'Completed' WHERE po_number = ?");
        $updateAssignmentStmt->bind_param("s", $po_number);
        $updateAssignmentStmt->execute();
        
        // 3. Get the driver ID
        $getDriverStmt = $conn->prepare("SELECT driver_id FROM driver_assignments WHERE po_number = ?");
        $getDriverStmt->bind_param("s", $po_number);
        $getDriverStmt->execute();
        $result = $getDriverStmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $driver_id = $row['driver_id'];
            
            // 4. Decrease the driver's current_deliveries count
            $decrementStmt = $conn->prepare("UPDATE drivers SET current_deliveries = GREATEST(current_deliveries - 1, 0) WHERE id = ?");
            $decrementStmt->bind_param("i", $driver_id);
            $decrementStmt->execute();
        }
        
        // 5. Log the status change
        $createLogStmt = $conn->prepare(
            "INSERT INTO order_status_logs (po_number, old_status, new_status, changed_by, changed_at) 
            VALUES (?, ?, 'Completed', ?, NOW())"
        );
        $changed_by = $_SESSION['username'] ?? 'system';
        $createLogStmt->bind_param("sss", $po_number, $old_status, $changed_by);
        $createLogStmt->execute();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Delivery completed successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to complete delivery: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
exit;
?>