<?php
session_start();
include "db_connection.php";
include "check_role.php";

// Ensure the request is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check if the user is logged in
if (!isset($_SESSION['admin_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Get data from the POST request
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['po_number']) || !isset($data['driver_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$po_number = $data['po_number'];
$driver_id = (int)$data['driver_id'];

// Begin transaction
$conn->begin_transaction();

try {
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
    
    $oldDriverId = 0;
    if ($checkAssignmentResult->num_rows > 0) {
        $assignmentRow = $checkAssignmentResult->fetch_assoc();
        $oldDriverId = $assignmentRow['driver_id'];
        
        // Update existing assignment
        $updateAssignmentStmt = $conn->prepare("UPDATE driver_assignments SET driver_id = ?, status = 'Assigned' WHERE po_number = ?");
        $updateAssignmentStmt->bind_param("is", $driver_id, $po_number);
        $updateAssignmentStmt->execute();
        $updateAssignmentStmt->close();
        
        // Decrease the old driver's current_deliveries count
        if ($oldDriverId > 0 && $oldDriverId != $driver_id) {
            $decrementStmt = $conn->prepare("UPDATE drivers SET current_deliveries = GREATEST(current_deliveries - 1, 0) WHERE id = ?");
            $decrementStmt->bind_param("i", $oldDriverId);
            $decrementStmt->execute();
            $decrementStmt->close();
        }
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
    
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Driver assigned successfully']);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Failed to assign driver: ' . $e->getMessage()]);
}
exit;
?>