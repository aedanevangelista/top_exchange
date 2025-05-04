<?php
session_start();
include "db_connection.php";
include "check_role.php";

// Set content type to JSON
header('Content-Type: application/json');

// Ensure the user is logged in
if (!isset($_SESSION['admin_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Check if a PO number is provided
if (!empty($_GET['po_number'])) {
    $po_number = $_GET['po_number'];
    
    // Check if a driver is assigned to this order and get the progress
    $stmt = $conn->prepare("SELECT driver_assigned, progress FROM orders WHERE po_number = ?");
    $stmt->bind_param("s", $po_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    $row = $result->fetch_assoc();
    $driver_assigned = (bool)$row['driver_assigned'];
    $progress = (int)$row['progress'];
    
    echo json_encode([
        'success' => true, 
        'driver_assigned' => $driver_assigned,
        'progress' => $progress
    ]);
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle POST requests too
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['po_number'])) {
        $po_number = $data['po_number'];
        
        // Check if a driver is assigned to this order and get the progress
        $stmt = $conn->prepare("SELECT driver_assigned, progress FROM orders WHERE po_number = ?");
        $stmt->bind_param("s", $po_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            exit;
        }
        
        $row = $result->fetch_assoc();
        $driver_assigned = (bool)$row['driver_assigned'];
        $progress = (int)$row['progress'];
        
        echo json_encode([
            'success' => true, 
            'driver_assigned' => $driver_assigned,
            'progress' => $progress
        ]);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'No PO number provided']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}
?>