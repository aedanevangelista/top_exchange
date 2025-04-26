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
    
    // If progress is 100%, check if a driver is assigned
    if ($auto_complete && $progress === 100) {
        // First check if a driver is assigned
        $stmt = $conn->prepare("SELECT driver_assigned FROM orders WHERE po_number = ?");
        $stmt->bind_param("s", $po_number);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $driver_assigned = (bool)$row['driver_assigned'];
        $stmt->close();
        
        // Only complete the order if a driver is assigned
        if ($driver_assigned) {
            $stmt = $conn->prepare("UPDATE orders SET status = 'Completed' WHERE po_number = ?");
            $stmt->bind_param("s", $po_number);
            $stmt->execute();
            $stmt->close();
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