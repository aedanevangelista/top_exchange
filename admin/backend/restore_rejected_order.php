<?php
// /admin/backend/restore_rejected_order.php
session_start();
include "db_connection.php";

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['admin_user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Check required parameters
if (!isset($_POST['po_number'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameter: po_number']);
    exit;
}

$poNumber = $_POST['po_number'];

// Begin transaction
$conn->begin_transaction();

try {
    // Check if the order exists and is in Rejected status
    $checkStmt = $conn->prepare("SELECT status FROM orders WHERE po_number = ?");
    $checkStmt->bind_param("s", $poNumber);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        throw new Exception("Order not found");
    }
    
    $order = $checkResult->fetch_assoc();
    if ($order['status'] !== 'Rejected') {
        throw new Exception("Order is not in Rejected status");
    }
    
    // Update the order status to Pending
    $updateStmt = $conn->prepare("UPDATE orders SET status = 'Pending' WHERE po_number = ?");
    $updateStmt->bind_param("s", $poNumber);
    $updateStmt->execute();
    
    // Log the status change
    $logStmt = $conn->prepare("INSERT INTO order_status_logs (po_number, old_status, new_status, changed_by, changed_at) 
                              VALUES (?, 'Rejected', 'Pending', ?, NOW())");
    $adminId = $_SESSION['admin_username']; // Using the username, not user_id
    $logStmt->bind_param("ss", $poNumber, $adminId);
    $logStmt->execute();
    
    // Commit the transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Order restored to Pending status"
    ]);
    
} catch (Exception $e) {
    // Roll back the transaction on error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>