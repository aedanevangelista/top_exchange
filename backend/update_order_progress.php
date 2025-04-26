<?php
session_start();
include "db_connection.php";
include "check_role.php";
checkRole('Orders'); // Ensure the user has access to the Orders page

header('Content-Type: application/json');

// Get the raw POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['po_number']) || !isset($data['progress'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$po_number = $data['po_number'];
$progress = intval($data['progress']);
$auto_complete = isset($data['auto_complete']) ? $data['auto_complete'] : false;
$driver_id = isset($data['driver_id']) ? intval($data['driver_id']) : null;

// For backward compatibility
$completed_items = isset($data['completed_items']) ? json_encode($data['completed_items']) : '[]';

// Detailed progress tracking
$quantity_progress_data = isset($data['quantity_progress_data']) ? json_encode($data['quantity_progress_data']) : '{}';
$item_progress_percentages = isset($data['item_progress_percentages']) ? json_encode($data['item_progress_percentages']) : '{}';

$conn->begin_transaction();

try {
    // Update the order progress with all tracking data
    $stmt = $conn->prepare("UPDATE orders SET completed_items = ?, quantity_progress_data = ?, item_progress_percentages = ?, progress = ? WHERE po_number = ?");
    $stmt->bind_param("sssis", $completed_items, $quantity_progress_data, $item_progress_percentages, $progress, $po_number);
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
    
    // If progress is 100% and auto_complete is true, check if a driver is assigned
    if ($auto_complete && $progress === 100) {
        // We know a driver is assigned if we got here with auto_complete and a driver_id
        if ($driver_id) {
            // Update order status to completed
            $completeOrderStmt = $conn->prepare("UPDATE orders SET status = 'Completed' WHERE po_number = ?");
            $completeOrderStmt->bind_param("s", $po_number);
            $completeOrderStmt->execute();
            $completeOrderStmt->close();
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