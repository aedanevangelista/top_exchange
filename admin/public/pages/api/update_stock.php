<?php
session_start();
include "../../../admin/backend/db_connection.php";

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Get the input data from the POST request body
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($input['product_id'], $input['action'], $input['amount'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$product_id = $input['product_id'];
$action = $input['action'];
$amount = intval($input['amount']);
$product_type = isset($input['product_type']) && $input['product_type'] === 'walkin' ? 'walkin' : 'company';
$table = $product_type === 'walkin' ? 'walkin_products' : 'products';

// Validate amount
if ($amount <= 0) {
    echo json_encode(['error' => 'Invalid amount']);
    exit;
}

// Get current stock level
$stmt = $conn->prepare("SELECT stock_quantity FROM $table WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Product not found']);
    exit;
}

$product = $result->fetch_assoc();
$current_stock = $product['stock_quantity'];

// Calculate new stock level
if ($action === 'add') {
    $new_stock = $current_stock + $amount;
    $message = "Added $amount units to stock.";
} elseif ($action === 'remove') {
    if ($current_stock < $amount) {
        echo json_encode(['error' => 'Insufficient stock']);
        exit;
    }
    $new_stock = $current_stock - $amount;
    $message = "Removed $amount units from stock.";
} else {
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

// Update stock level
$update_stmt = $conn->prepare("UPDATE $table SET stock_quantity = ? WHERE product_id = ?");
$update_stmt->bind_param("ii", $new_stock, $product_id);
if ($update_stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => $message,
        'new_stock' => $new_stock
    ]);
} else {
    echo json_encode(['error' => 'Failed to update stock']);
}
?>