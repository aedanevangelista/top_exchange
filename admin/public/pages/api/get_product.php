<?php
session_start();
include "../../../backend/db_connection.php"; // Adjusted path assuming get_product.php is in admin/pages/api/

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

$product_id = intval($_GET['id']); // Ensure it's an integer
$type = isset($_GET['type']) && $_GET['type'] === 'walkin' ? 'walkin' : 'company';
$table = $type === 'walkin' ? 'walkin_products' : 'products';

// Modify the SQL query to include 'status'
// If 'walkin_products' table also has a 'status' column, you can simplify this
// to a single query string. Otherwise, this conditional select handles it.
if ($table === 'products') {
    $sql = "SELECT product_id, category, product_name, item_description, packaging, price, stock_quantity, additional_description, product_image, expiration, status FROM $table WHERE product_id = ?";
} else { // For 'walkin_products', assuming it might not have 'status' or 'expiration'
    // Adjust this select if 'walkin_products' has these columns
    $sql = "SELECT product_id, category, product_name, item_description, packaging, price, stock_quantity, additional_description, product_image, NULL as expiration, 'Active' as status FROM $table WHERE product_id = ?";
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => 'Failed to prepare statement: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Product not found', 'id_searched' => $product_id, 'table' => $table]);
    $stmt->close();
    $conn->close();
    exit;
}

$product = $result->fetch_assoc();

// Make sure to also fetch 'expiration' if it's needed by the edit form and exists in the table
// The query for 'products' table now includes 'expiration'.
// If 'walkin_products' also has 'expiration', adjust its select query above.

$stmt->close();
$conn->close();

echo json_encode($product);
?>