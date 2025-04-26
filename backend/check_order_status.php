<?php
session_start();
include "db_connection.php";
include "check_role.php";

header('Content-Type: application/json');

// Check if the user is logged in
if (!isset($_SESSION['admin_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if (!isset($_GET['po_number'])) {
    echo json_encode(['success' => false, 'message' => 'Missing PO number']);
    exit;
}

$po_number = $_GET['po_number'];

// Check order progress and driver assignment status
$stmt = $conn->prepare("SELECT progress, driver_assigned FROM orders WHERE po_number = ?");
$stmt->bind_param("s", $po_number);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

$row = $result->fetch_assoc();
$progress = $row['progress'];
$driver_assigned = (bool)$row['driver_assigned'];

echo json_encode([
    'success' => true, 
    'progress' => $progress,
    'driver_assigned' => $driver_assigned,
    'can_complete' => $progress == 100 && $driver_assigned
]);
?>