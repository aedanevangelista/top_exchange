<?php
session_start();
include "../backend/db_connection.php";
include "../backend/check_role.php";
checkRole('Orders'); // Ensure the user has access to the Orders page

header('Content-Type: application/json');

if (!isset($_GET['po_number'])) {
    echo json_encode(['success' => false, 'message' => 'PO number is required']);
    exit;
}

$po_number = $_GET['po_number'];

// Fetch the order data with orders and completed_items
$stmt = $conn->prepare("SELECT orders, completed_items FROM orders WHERE po_number = ?");
$stmt->bind_param("s", $po_number);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

$orderItems = json_decode($row['orders'], true);
$completedItems = [];

if (!empty($row['completed_items'])) {
    $completedItems = json_decode($row['completed_items'], true);
}

echo json_encode([
    'success' => true,
    'orderItems' => $orderItems,
    'completedItems' => $completedItems
]);
exit;
?>