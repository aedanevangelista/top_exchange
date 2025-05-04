<?php
session_start();
include "../../../backend/db_connection.php";
include "../../../backend/check_role.php";

header('Content-Type: application/json');

if (!isset($_SESSION['admin_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get the JSON data from the request body
$data = json_decode(file_get_contents('php://input'), true);

if (
    !isset($data['material_id']) || !is_numeric($data['material_id']) ||
    !isset($data['action']) || 
    !isset($data['amount']) || !is_numeric($data['amount'])
) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

$material_id = intval($data['material_id']);
$action = $data['action'];
$amount = floatval($data['amount']);

// Validate action
if ($action !== 'add' && $action !== 'remove') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Get current stock
$stmt = $conn->prepare("SELECT stock_quantity FROM raw_materials WHERE material_id = ?");
$stmt->bind_param("i", $material_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Material not found']);
    exit();
}

$current_stock = $result->fetch_assoc()['stock_quantity'];
$stmt->close();

// Calculate new stock
$new_stock = ($action === 'add') ? $current_stock + $amount : $current_stock - $amount;

// Ensure stock doesn't go negative
if ($new_stock < 0) {
    echo json_encode(['success' => false, 'message' => 'Not enough stock to remove']);
    exit();
}

// Update stock
$stmt = $conn->prepare("UPDATE raw_materials SET stock_quantity = ?, updated_at = NOW() WHERE material_id = ?");
$stmt->bind_param("di", $new_stock, $material_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode([
        'success' => true, 
        'message' => 'Stock updated successfully', 
        'new_stock' => $new_stock
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update stock']);
}

$stmt->close();
?>