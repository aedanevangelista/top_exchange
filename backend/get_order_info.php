<?php
session_start();
include "db_connection.php";
include "check_role.php";

// Check if user is logged in
if (!isset($_SESSION['admin_user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Set content type to JSON
header('Content-Type: application/json');

// Get po_number from GET request
$po_number = isset($_GET['po_number']) ? $_GET['po_number'] : '';

if (empty($po_number)) {
    echo json_encode(['success' => false, 'message' => 'Missing PO number']);
    exit;
}

try {
    // Get order details
    $stmt = $conn->prepare("
        SELECT * FROM orders 
        WHERE po_number = ?
    ");
    $stmt->bind_param("s", $po_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    $order = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true, 
        'order' => $order
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;
?>