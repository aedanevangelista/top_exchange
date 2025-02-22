<?php
include "../../backend/db_connection.php";

$category = $_POST['category'];
$item_description = $_POST['item_description'];
$packaging = $_POST['packaging'];
$price = $_POST['price'];
$stock_quantity = $_POST['stock_quantity'];

$sql = "INSERT INTO products (category, item_description, packaging, price, stock_quantity) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssdi", $category, $item_description, $packaging, $price, $stock_quantity);

if ($stmt->execute()) {
    echo json_encode(["message" => "Product added successfully"]);
} else {
    echo json_encode(["message" => "Error adding product"]);
}

$stmt->close();
$conn->close();
?>