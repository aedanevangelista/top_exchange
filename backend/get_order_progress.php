<?php
session_start();
include "../backend/db_connection.php";
include "../backend/check_role.php";
checkApiRole('Orders');

header('Content-Type: application/json');

if (!isset($_GET['po_number'])) {
    echo json_encode(['success' => false, 'message' => 'PO number is required']);
    exit;
}

$po_number = $_GET['po_number'];

// Fetch the order progress data
$stmt = $conn->prepare("SELECT completed_items FROM orders WHERE po_number = ?");
$stmt->bind_param("s", $po_number);
$stmt->execute();
$stmt->bind_result($completed_items_json);
$found = $stmt->fetch();
$stmt->close();

if (!$found) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

// Parse the completed items JSON
$completed_items = [];
if (!empty($completed_items_json)) {
    $completed_items = json_decode($completed_items_json, true);
}

echo json_encode([
    'success' => true, 
    'completed_items' => $completed_items
]);
exit;
?>