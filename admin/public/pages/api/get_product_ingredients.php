<?php
session_start();
include "../../../admin/backend/db_connection.php";

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'No product ID provided']);
    exit;
}

$product_id = $_GET['id'];
$type = isset($_GET['type']) && $_GET['type'] === 'walkin' ? 'walkin' : 'company';
$table = $type === 'walkin' ? 'walkin_products' : 'products';

$stmt = $conn->prepare("SELECT product_id, item_description, ingredients FROM $table WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Product not found']);
    exit;
}

$product = $result->fetch_assoc();

// Parse ingredients JSON if exists
if (!empty($product['ingredients'])) {
    $ingredients = json_decode($product['ingredients']);
    if (json_last_error() === JSON_ERROR_NONE) {
        $product['ingredients'] = $ingredients;
    } else {
        $product['ingredients'] = [];
    }
} else {
    $product['ingredients'] = [];
}

echo json_encode($product);
?>