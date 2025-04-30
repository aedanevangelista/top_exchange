<?php
session_start();
include "../../../admin/backend/db_connection.php";

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get the input data from the POST request body
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($input['product_id'], $input['ingredients'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$product_id = $input['product_id'];
$ingredients = $input['ingredients'];
$product_type = isset($input['product_type']) && $input['product_type'] === 'walkin' ? 'walkin' : 'company';
$table = $product_type === 'walkin' ? 'walkin_products' : 'products';

// Convert the ingredients array to JSON
$ingredients_json = json_encode($ingredients);

// Update the product's ingredients
$stmt = $conn->prepare("UPDATE $table SET ingredients = ? WHERE product_id = ?");
$stmt->bind_param("si", $ingredients_json, $product_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Ingredients updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update ingredients']);
}
?>