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
        // 1. Check if the driver is available and has less than 20 deliveries
        $stmt = $conn->prepare("SELECT availability, current_deliveries FROM drivers WHERE id = ? AND availability = 'Available' AND current_deliveries < 20");
        $stmt->bind_param("i", $driver_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Selected driver is not available or has reached the maximum delivery limit']);
            exit;
        }
        
        $driver = $result->fetch_assoc();
        
        // 2. Check if the order is not already assigned to a driver
        $stmt = $conn->prepare("SELECT po_number FROM driver_assignments WHERE po_number = ?");
        $stmt->bind_param("s", $po_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'This order is already assigned to a driver']);
            exit;
        }
        
        // 3. Insert a new record in the driver_assignments table
        $stmt = $conn->prepare("INSERT INTO driver_assignments (po_number, driver_id, status) VALUES (?, ?, 'Assigned')");
        $stmt->bind_param("si", $po_number, $driver_id);
        $stmt->execute();
        
        // 4. Update the driver's current_deliveries count
        $new_delivery_count = $driver['current_deliveries'] + 1;
        $stmt = $conn->prepare("UPDATE drivers SET current_deliveries = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_delivery_count, $driver_id);
        $stmt->execute();
        
        // 5. Update the order's driver_assigned flag
        $stmt = $conn->prepare("UPDATE orders SET driver_assigned = 1 WHERE po_number = ?");
        $stmt->bind_param("s", $po_number);
        $stmt->execute();
        
        // Commit the transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Driver assigned successfully']);
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