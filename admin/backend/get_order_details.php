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

// Fetch the order data including all progress tracking fields
$stmt = $conn->prepare("SELECT orders, completed_items, quantity_progress_data, item_progress_percentages FROM orders WHERE po_number = ?");
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
$quantityProgressData = [];
$itemProgressPercentages = [];

if (!empty($row['completed_items'])) {
    $completedItems = json_decode($row['completed_items'], true);
}

if (!empty($row['quantity_progress_data'])) {
    $quantityProgressData = json_decode($row['quantity_progress_data'], true);
}

if (!empty($row['item_progress_percentages'])) {
    $itemProgressPercentages = json_decode($row['item_progress_percentages'], true);
}

echo json_encode([
    'success' => true,
    'orderItems' => $orderItems,
    'completedItems' => $completedItems,
    'quantityProgressData' => $quantityProgressData,
    'itemProgressPercentages' => $itemProgressPercentages
]);
exit;
?>