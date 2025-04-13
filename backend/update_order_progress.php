<?php
session_start();
include "../backend/db_connection.php";
include "../backend/check_role.php";
checkRole('Orders'); // Ensure the user has access to the Orders page

header('Content-Type: application/json');

// Get the raw POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['po_number']) || !isset($data['completed_items']) || !isset($data['progress'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$po_number = $data['po_number'];
$completed_items = json_encode($data['completed_items']);
$progress = intval($data['progress']);
$auto_complete = isset($data['auto_complete']) ? $data['auto_complete'] : false;

// New field for quantity-level tracking
$quantity_progress_data = isset($data['quantity_progress_data']) ? json_encode($data['quantity_progress_data']) : '{}';

$conn->begin_transaction();

try {
    // Update the order progress with both regular and quantity progress data
    $stmt = $conn->prepare("UPDATE orders SET completed_items = ?, quantity_progress_data = ?, progress = ? WHERE po_number = ?");
    $stmt->bind_param("ssis", $completed_items, $quantity_progress_data, $progress, $po_number);
    $stmt->execute();
    $stmt->close();
    
    // If progress is 100%, automatically update status to Completed
    if ($auto_complete && $progress === 100) {
        $stmt = $conn->prepare("UPDATE orders SET status = 'Completed' WHERE po_number = ?");
        $stmt->bind_param("s", $po_number);
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Failed to update order progress: ' . $e->getMessage()]);
}
exit;
?>