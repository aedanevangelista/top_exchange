<?php
session_start();
include "../backend/db_connection.php";
include "../backend/check_role.php";
checkApiRole('Orders');

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

// Update the order progress
$stmt = $conn->prepare("UPDATE orders SET completed_items = ?, progress = ? WHERE po_number = ?");
$stmt->bind_param("sis", $completed_items, $progress, $po_number);
$result = $stmt->execute();
$stmt->close();

if ($result) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update order progress']);
}
exit;
?>