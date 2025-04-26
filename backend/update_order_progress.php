<?php
session_start();
include "db_connection.php";
include "check_role.php";

// Ensure the user has the necessary permissions
if (!isset($_SESSION['admin_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Get JSON data from the request
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['po_number']) || !isset($data['progress'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$po_number = $data['po_number'];
$progress = intval($data['progress']);
$completed_items = isset($data['completed_items']) ? json_encode($data['completed_items']) : null;
$quantity_progress_data = isset($data['quantity_progress_data']) ? json_encode($data['quantity_progress_data']) : null;
$item_progress_percentages = isset($data['item_progress_percentages']) ? json_encode($data['item_progress_percentages']) : null;
$driver_id = isset($data['driver_id']) ? intval($data['driver_id']) : 0;

// Check if we should mark order for delivery automatically
$auto_delivery = isset($data['auto_delivery']) && $data['auto_delivery'] === true;

$conn->begin_transaction();

try {
    // Update the order progress
    $stmt = $conn->prepare("UPDATE orders SET progress = ?, completed_items = ?, quantity_progress_data = ?, item_progress_percentages = ? WHERE po_number = ?");
    $stmt->bind_param("issss", $progress, $completed_items, $quantity_progress_data, $item_progress_percentages, $po_number);
    $stmt->execute();
    $stmt->close();
    
    // If a driver ID is provided, assign the driver
    if ($driver_id) {
        // First check if the driver exists and is available
        $checkDriverStmt = $conn->prepare("SELECT id FROM drivers WHERE id = ? AND availability = 'Available' AND current_deliveries < 20");
        $checkDriverStmt->bind_param("i", $driver_id);
        $checkDriverStmt->execute();
        $checkDriverResult = $checkDriverStmt->get_result();
        
        if ($checkDriverResult->num_rows === 0) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Driver is not available or has reached maximum deliveries']);
            exit;
        }
        $checkDriverStmt->close();
        
        // Check if there's already a driver assigned to this order
        $checkAssignmentStmt = $conn->prepare("SELECT driver_id FROM driver_assignments WHERE po_number = ?");
        $checkAssignmentStmt->bind_param("s", $po_number);
        $checkAssignmentStmt->execute();
        $checkAssignmentResult = $checkAssignmentStmt->get_result();
        
        if ($checkAssignmentResult->num_rows > 0) {
            // Update existing assignment
            $updateAssignmentStmt = $conn->prepare("UPDATE driver_assignments SET driver_id = ?, status = 'Assigned' WHERE po_number = ?");
            $updateAssignmentStmt->bind_param("is", $driver_id, $po_number);
            $updateAssignmentStmt->execute();
            $updateAssignmentStmt->close();
        } else {
            // Create new assignment
            $createAssignmentStmt = $conn->prepare("INSERT INTO driver_assignments (po_number, driver_id, status) VALUES (?, ?, 'Assigned')");
            $createAssignmentStmt->bind_param("si", $po_number, $driver_id);
            $createAssignmentStmt->execute();
            $createAssignmentStmt->close();
        }
        $checkAssignmentStmt->close();
        
        // Update the driver_assigned flag in the orders table
        $updateOrderStmt = $conn->prepare("UPDATE orders SET driver_assigned = 1 WHERE po_number = ?");
        $updateOrderStmt->bind_param("s", $po_number);
        $updateOrderStmt->execute();
        $updateOrderStmt->close();
        
        // Increment the driver's current_deliveries count
        $updateDriverStmt = $conn->prepare("UPDATE drivers SET current_deliveries = current_deliveries + 1 WHERE id = ?");
        $updateDriverStmt->bind_param("i", $driver_id);
        $updateDriverStmt->execute();
        $updateDriverStmt->close();
    }
    
    // If progress is 100% and auto_delivery is true, check if a driver is assigned
    if ($auto_delivery && $progress === 100) {
        // Check if a driver is assigned to this order
        $checkDriverAssignedStmt = $conn->prepare("SELECT driver_assigned FROM orders WHERE po_number = ?");
        $checkDriverAssignedStmt->bind_param("s", $po_number);
        $checkDriverAssignedStmt->execute();
        $checkDriverAssignedResult = $checkDriverAssignedStmt->get_result();
        $row = $checkDriverAssignedResult->fetch_assoc();
        $driver_assigned = (bool)$row['driver_assigned'];
        $checkDriverAssignedStmt->close();
        
        // If a driver is assigned, update order status to "For Delivery"
        if ($driver_assigned) {
            $updateStatusStmt = $conn->prepare("UPDATE orders SET status = 'For Delivery' WHERE po_number = ?");
            $updateStatusStmt->bind_param("s", $po_number);
            $updateStatusStmt->execute();
            $updateStatusStmt->close();
            
            // Create a log entry for the status change
            $createLogStmt = $conn->prepare(
                "INSERT INTO order_status_logs (po_number, old_status, new_status, changed_by, changed_at) 
                VALUES (?, 'Active', 'For Delivery', ?, NOW())"
            );
            $changed_by = $_SESSION['username'] ?? 'system';
            $createLogStmt->bind_param("ss", $po_number, $changed_by);
            $createLogStmt->execute();
            $createLogStmt->close();
        }
    }
    
    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Failed to update order progress: ' . $e->getMessage()]);
}
exit;
?>