<?php
session_start();
include "db_connection.php";
include "check_role.php";

// Ensure the user has access to the Deliverable Orders page
if (!hasAccess('Deliverable Orders')) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Check if it's a POST request and the required parameters are provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['po_number']) && !empty($_POST['driver_id'])) {
    $po_number = $_POST['po_number'];
    $driver_id = (int)$_POST['driver_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Update the order status to Completed
        $stmt = $conn->prepare("UPDATE orders SET status = 'Completed' WHERE po_number = ?");
        $stmt->bind_param("s", $po_number);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            exit;
        }
        
        // 2. Update the driver_assignments status to Delivered
        $stmt = $conn->prepare("UPDATE driver_assignments SET status = 'Delivered' WHERE po_number = ? AND driver_id = ?");
        $stmt->bind_param("si", $po_number, $driver_id);
        $stmt->execute();
        
        // 3. Update the driver's current_deliveries count
        $stmt = $conn->prepare("UPDATE drivers SET current_deliveries = current_deliveries - 1 WHERE id = ? AND current_deliveries > 0");
        $stmt->bind_param("i", $driver_id);
        $stmt->execute();
        
        // 4. Log the status change
        $admin_username = $_SESSION['admin_username'] ?? 'system';
        $stmt = $conn->prepare("INSERT INTO order_status_logs (po_number, old_status, new_status, changed_by, changed_at) VALUES (?, 'Active', 'Completed', ?, NOW())");
        $stmt->bind_param("ss", $po_number, $admin_username);
        $stmt->execute();
        
        // Commit the transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Order completed successfully']);
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error occurred: ' . $e->getMessage()]);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request or missing parameters']);
    exit;
}
?>