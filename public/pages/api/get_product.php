<?php
session_start();
include "../../../backend/db_connection.php";

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid product ID']);
    exit;
}

$product_id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT product_id, category, product_name, item_description, packaging, price, stock_quantity, additional_description, product_image FROM products WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Product not found']);
    exit;
}

$product = $result->fetch_assoc();
header('Content-Type: application/json');
echo json_encode($product);

$stmt->close();
$conn->close();
?>