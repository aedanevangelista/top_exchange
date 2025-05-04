<?php
session_start();
include "../../../backend/db_connection.php";

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

$stmt = $conn->prepare("SELECT product_id, category, product_name, item_description, packaging, price, stock_quantity, additional_description, product_image FROM $table WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Product not found']);
    exit;
}

$product = $result->fetch_assoc();
echo json_encode($product);
?>