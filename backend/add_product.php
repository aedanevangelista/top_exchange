<?php
include "db_connection.php";

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

// If JSON data is present, use it. Otherwise, use POST data
if (isset($data) && !empty($data)) {
    $category = $data['category'];
    $item_description = $data['item_description'];
    $packaging = $data['packaging'];
    $price = $data['price'];
    $stock_quantity = $data['stock_quantity'] ?? 0;
} else {
    $category = $_POST['category'];
    $item_description = $_POST['item_description'];
    $packaging = $_POST['packaging'];
    $price = $_POST['price'];
    $stock_quantity = $_POST['stock_quantity'] ?? 0;
}

// Check if product already exists
$check_sql = "SELECT COUNT(*) as count FROM products WHERE item_description = ? AND packaging = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ss", $item_description, $packaging);
$check_stmt->execute();
$result = $check_stmt->get_result();
$row = $result->fetch_assoc();

if ($row['count'] > 0) {
    echo json_encode(['success' => false, 'message' => 'Product with this description and packaging already exists.']);
    exit;
}

$sql = "INSERT INTO products (category, item_description, packaging, price, stock_quantity) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssdi", $category, $item_description, $packaging, $price, $stock_quantity);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Product added successfully"]);
} else {
    echo json_encode(["success" => false, "message" => "Error adding product: " . $conn->error]);
}

$stmt->close();
$conn->close();
?>