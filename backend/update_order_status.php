<?php
session_start();
include "db_connection.php";
include "check_role.php";

// Check if the user is logged in
if (!isset($_SESSION['admin_user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Set content type to JSON
header('Content-Type: application/json');

// Handle both POST and form-data
$po_number = "";
$status = "";
$deduct_materials = false;  // Default value

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['po_number']) && isset($_POST['status'])) {
        $po_number = $_POST['po_number'];
        $status = $_POST['status'];
        if (isset($_POST['deduct_materials'])) {
            $deduct_materials = filter_var($_POST['deduct_materials'], FILTER_VALIDATE_BOOLEAN);
        }
    } else {
        // Try to get data from JSON input
        $jsonData = json_decode(file_get_contents('php://input'), true);
        if (isset($jsonData['po_number']) && isset($jsonData['status'])) {
            $po_number = $jsonData['po_number'];
            $status = $jsonData['status'];
            if (isset($jsonData['deduct_materials'])) {
                $deduct_materials = filter_var($jsonData['deduct_materials'], FILTER_VALIDATE_BOOLEAN);
            }
        }
    }
}

if (empty($po_number) || empty($status)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Validate status
$validStatuses = ['Pending', 'Active', 'For Delivery', 'Rejected', 'Completed'];
if (!in_array($status, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit;
}

// Begin transaction
$conn->begin_transaction();

try {
    // Check if status is "For Delivery" and ensure both driver is assigned and progress is 100%
    if ($status === 'For Delivery') {
        $checkStmt = $conn->prepare("SELECT driver_assigned, progress FROM orders WHERE po_number = ?");
        $checkStmt->bind_param("s", $po_number);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Order not found");
        }
        
        $row = $result->fetch_assoc();
        $driver_assigned = (bool)$row['driver_assigned'];
        $progress = (int)$row['progress'];
        
        if (!$driver_assigned) {
            throw new Exception("A driver must be assigned before marking an order for delivery");
        }
        
        if ($progress < 100) {
            throw new Exception("Order progress must be 100% before marking it for delivery");
        }
    }
    
    // Get current status for logging
    $stmt = $conn->prepare("SELECT status, orders FROM orders WHERE po_number = ?");
    $stmt->bind_param("s", $po_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Order not found");
    }
    
    $row = $result->fetch_assoc();
    $old_status = $row['status'];
    $orders_json = $row['orders'];
    
    // Handle deducting materials if needed
    if ($status === 'Active' && $deduct_materials && $old_status === 'Pending') {
        // Deduct materials logic here
        // This would need to parse the orders JSON and update inventory
        // For now, we'll include a placeholder
        // You can implement the actual inventory update logic
    }
    
    // Update the order status
    $update_stmt = $conn->prepare("UPDATE orders SET status = ? WHERE po_number = ?");
    $update_stmt->bind_param("ss", $status, $po_number);
    $update_stmt->execute();
    
    // Log the status change
    $log_stmt = $conn->prepare("INSERT INTO order_status_logs (po_number, old_status, new_status, changed_by, changed_at) VALUES (?, ?, ?, ?, NOW())");
    $changed_by = $_SESSION['username'] ?? 'system';
    $log_stmt->bind_param("ssss", $po_number, $old_status, $status, $changed_by);
    $log_stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully',
        'po_number' => $po_number,
        'new_status' => $status
    ]);
    
} catch (Exception $e) {
    // Roll back transaction on error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>