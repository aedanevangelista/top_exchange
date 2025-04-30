<?php
session_start();
include "../db_connection.php";
include "../check_role.php";
checkRole('Orders');

// Get the JSON data from the request
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

// Check for required fields
if (!isset($data['po_number']) || !isset($data['driver_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$po_number = $data['po_number'];
$driver_id = $data['driver_id'];

// Begin transaction for data integrity
$conn->begin_transaction();

try {
    // If driver_id is -1, it means remove driver assignment
    if ($driver_id == -1) {
        // Get the current driver to update their delivery count
        $currentDriverSql = "SELECT driver_id FROM driver_assignments WHERE po_number = ?";
        $currentDriverStmt = $conn->prepare($currentDriverSql);
        $currentDriverStmt->bind_param("s", $po_number);
        $currentDriverStmt->execute();
        $result = $currentDriverStmt->get_result();
        
        if ($result->num_rows > 0) {
            $currentDriverRow = $result->fetch_assoc();
            $oldDriverId = $currentDriverRow['driver_id'];
            
            // Decrease the old driver's current_deliveries count
            $decreaseOldDriverSql = "UPDATE drivers SET current_deliveries = current_deliveries - 1 WHERE id = ? AND current_deliveries > 0";
            $decreaseOldDriverStmt = $conn->prepare($decreaseOldDriverSql);
            $decreaseOldDriverStmt->bind_param("i", $oldDriverId);
            $decreaseOldDriverStmt->execute();
        }
        
        // Update orders table to mark driver as not assigned
        $updateOrderSql = "UPDATE orders SET driver_assigned = 0 WHERE po_number = ?";
        $updateOrderStmt = $conn->prepare($updateOrderSql);
        $updateOrderStmt->bind_param("s", $po_number);
        $updateOrderStmt->execute();
        
        // Remove assignment from driver_assignments table
        $deleteAssignmentSql = "DELETE FROM driver_assignments WHERE po_number = ?";
        $deleteAssignmentStmt = $conn->prepare($deleteAssignmentSql);
        $deleteAssignmentStmt->bind_param("s", $po_number);
        $deleteAssignmentStmt->execute();
        
        // Commit transaction
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Driver assignment removed successfully']);
        exit;
    }
    
    // Get current driver assigned (if any)
    $currentDriverSql = "SELECT driver_id FROM driver_assignments WHERE po_number = ?";
    $currentDriverStmt = $conn->prepare($currentDriverSql);
    $currentDriverStmt->bind_param("s", $po_number);
    $currentDriverStmt->execute();
    $result = $currentDriverStmt->get_result();
    $hasCurrentDriver = $result->num_rows > 0;
    
    if ($hasCurrentDriver) {
        $currentDriverRow = $result->fetch_assoc();
        $oldDriverId = $currentDriverRow['driver_id'];
        
        // Update the driver assignment
        $updateAssignmentSql = "UPDATE driver_assignments SET driver_id = ? WHERE po_number = ?";
        $updateAssignmentStmt = $conn->prepare($updateAssignmentSql);
        $updateAssignmentStmt->bind_param("is", $driver_id, $po_number);
        $updateAssignmentStmt->execute();
        
        // Decrease the old driver's current_deliveries count
        $decreaseOldDriverSql = "UPDATE drivers SET current_deliveries = current_deliveries - 1 WHERE id = ? AND current_deliveries > 0";
        $decreaseOldDriverStmt = $conn->prepare($decreaseOldDriverSql);
        $decreaseOldDriverStmt->bind_param("i", $oldDriverId);
        $decreaseOldDriverStmt->execute();
    } else {
        // Create a new driver assignment
        $createAssignmentSql = "INSERT INTO driver_assignments (po_number, driver_id) VALUES (?, ?)";
        $createAssignmentStmt = $conn->prepare($createAssignmentSql);
        $createAssignmentStmt->bind_param("si", $po_number, $driver_id);
        $createAssignmentStmt->execute();
    }
    
    // Increase the new driver's current_deliveries count
    $increaseNewDriverSql = "UPDATE drivers SET current_deliveries = current_deliveries + 1 WHERE id = ?";
    $increaseNewDriverStmt = $conn->prepare($increaseNewDriverSql);
    $increaseNewDriverStmt->bind_param("i", $driver_id);
    $increaseNewDriverStmt->execute();
    
    // Mark the order as having a driver assigned
    $updateOrderSql = "UPDATE orders SET driver_assigned = 1 WHERE po_number = ?";
    $updateOrderStmt = $conn->prepare($updateOrderSql);
    $updateOrderStmt->bind_param("s", $po_number);
    $updateOrderStmt->execute();
    
    // Commit the transaction
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Driver assigned successfully']);
} catch (Exception $e) {
    // Rollback the transaction if there was an error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>