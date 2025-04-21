<?php
session_start();
include "../../../backend/db_connection.php";

header('Content-Type: application/json');

if (!isset($_SESSION['admin_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['product_id']) || !is_numeric($data['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

if (!isset($data['ingredients']) || !is_array($data['ingredients'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid ingredients data']);
    exit;
}

$product_id = intval($data['product_id']);
$ingredients = json_encode($data['ingredients']);

$stmt = $conn->prepare("UPDATE products SET ingredients = ? WHERE product_id = ?");
$stmt->bind_param("si", $ingredients, $product_id);
$result = $stmt->execute();

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Ingredients updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update ingredients: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>