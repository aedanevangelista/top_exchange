<?php
session_start();
include "../../../backend/db_connection.php";

header('Content-Type: application/json');

if (!isset($_SESSION['admin_user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['error' => 'Invalid product ID']);
    exit;
}

$product_id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT product_id, item_description, ingredients FROM products WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Product not found']);
    exit;
}

$product = $result->fetch_assoc();

// Parse ingredients JSON if not null
if ($product['ingredients'] !== null) {
    $product['ingredients'] = json_decode($product['ingredients'], true);
} else {
    $product['ingredients'] = [];
}

echo json_encode($product);

$stmt->close();
$conn->close();
?>