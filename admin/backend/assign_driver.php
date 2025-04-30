<?php
session_start();
include "db_connection.php";
include "check_role.php";

// For debugging
$log_file = __DIR__ . "/driver_assignment_log.txt";
file_put_contents($log_file, date('Y-m-d H:i:s') . ": Script started\n", FILE_APPEND);

// Ensure the user is logged in
if (!isset($_SESSION['admin_user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    file_put_contents($log_file, date('Y-m-d H:i:s') . ": Not authorized\n", FILE_APPEND);
    exit;
}

// Set content type to JSON
header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get data from POST or JSON input
    $data = $_POST;
    
    // If empty, try JSON input
    if (empty($data)) {
        $input = file_get_contents('php://input');
        if (!empty($input)) {
            $data = json_decode($input, true);
        }
    }
    
    // Log the received data
    file_put_contents($log_file, date('Y-m-d H:i:s') . ": Received data: " . print_r($data, true) . "\n", FILE_APPEND);
    file_put_contents($log_file, date('Y-m-d H:i:s') . ": Raw POST: " . print_r($_POST, true) . "\n", FILE_APPEND);
    file_put_contents($log_file, date('Y-m-d H:i:s') . ": Raw REQUEST: " . print_r($_REQUEST, true) . "\n", FILE_APPEND);
    
    if (!isset($data['po_number']) || !isset($data['driver_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        file_put_contents($log_file, date('Y-m-d H:i:s') . ": Missing required parameters\n", FILE_APPEND);
        exit;
    }
    
    $po_number = $data['po_number'];
    $driver_id = (int)$data['driver_id'];
    
    file_put_contents($log_file, date('Y-m-d H:i:s') . ": Processing PO: $po_number, Driver ID: $driver_id\n", FILE_APPEND);
    
    if (empty($po_number) || $driver_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters provided']);
        file_put_contents($log_file, date('Y-m-d H:i:s') . ": Invalid parameters\n", FILE_APPEND);
        exit;
    }

    // Check the order status
    $statusCheck = $conn->prepare("SELECT status FROM orders WHERE po_number = ?");
    $statusCheck->bind_param("s", $po_number);
    $statusCheck->execute();
    $statusResult = $statusCheck->get_result();

    if ($statusResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        file_put_contents($log_file, date('Y-m-d H:i:s') . ": Order not found: $po_number\n", FILE_APPEND);
        exit;
    }

    $statusRow = $statusResult->fetch_assoc();
    if ($statusRow['status'] !== 'Active') {
        echo json_encode(['success' => false, 'message' => 'Driver assignment is only allowed for Active orders']);
        file_put_contents($log_file, date('Y-m-d H:i:s') . ": Order status not Active: " . $statusRow['status'] . "\n", FILE_APPEND);
        exit;
    }
    $statusCheck->close();

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
            file_put_contents($log_file, date('Y-m-d H:i:s') . ": Driver not available: $driver_id\n", FILE_APPEND);
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
            
            file_put_contents($log_file, date('Y-m-d H:i:s') . ": Existing assignment found. Old driver ID: $oldDriverId\n", FILE_APPEND);
            
            // Update existing assignment
            $updateAssignmentStmt = $conn->prepare("UPDATE driver_assignments SET driver_id = ?, assigned_at = NOW(), status = 'Assigned' WHERE po_number = ?");
            $updateAssignmentStmt->bind_param("is", $driver_id, $po_number);
            $updateAssignmentStmt->execute();
            $updateAssignmentStmt->close();
            
            // Decrease the old driver's current_deliveries count if different from new driver
            if ($oldDriverId > 0 && $oldDriverId != $driver_id) {
                $decrementStmt = $conn->prepare("UPDATE drivers SET current_deliveries = GREATEST(current_deliveries - 1, 0) WHERE id = ?");
                $decrementStmt->bind_param("i", $oldDriverId);
                $decrementStmt->execute();
                $decrementStmt->close();
                file_put_contents($log_file, date('Y-m-d H:i:s') . ": Decremented old driver's count\n", FILE_APPEND);
            }
        } else {
            file_put_contents($log_file, date('Y-m-d H:i:s') . ": Creating new assignment\n", FILE_APPEND);
            
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
        file_put_contents($log_file, date('Y-m-d H:i:s') . ": Driver assigned successfully\n", FILE_APPEND);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to assign driver: ' . $e->getMessage()]);
        file_put_contents($log_file, date('Y-m-d H:i:s') . ": Exception: " . $e->getMessage() . "\n", FILE_APPEND);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    file_put_contents($log_file, date('Y-m-d H:i:s') . ": Invalid request method: " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);
}
exit;
?>