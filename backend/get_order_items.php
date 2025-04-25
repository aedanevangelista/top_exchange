<?php
session_start();
include "db_connection.php";
include "check_role.php";

// Ensure the user is logged in
if (!isset($_SESSION['admin_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Check if a PO number is provided
if (!empty($_GET['po_number'])) {
    $po_number = $_GET['po_number'];
    
    // Get order items
    $stmt = $conn->prepare("SELECT orders FROM orders WHERE po_number = ?");
    $stmt->bind_param("s", $po_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    $row = $result->fetch_assoc();
    $items = json_decode($row['orders'], true);
    
    echo json_encode(['success' => true, 'items' => $items]);
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'No PO number provided']);
    exit;
}
?>