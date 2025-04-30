<?php
include "../../admin/backend/db_connection.php";

$product_id = $_POST['product_id'];
$category = $_POST['category'];
$item_description = $_POST['item_description'];
$packaging = $_POST['packaging'];
$price = $_POST['price'];
$stock_quantity = $_POST['stock_quantity'];

$sql = "UPDATE products SET category = ?, item_description = ?, packaging = ?, price = ?, stock_quantity = ? WHERE product_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssdi", $category, $item_description, $packaging, $price, $stock_quantity, $product_id);

if ($stmt->execute()) {
    echo json_encode(["message" => "Product updated successfully"]);
} else {
    echo json_encode(["message" => "Error updating product"]);
}

$stmt->close();
$conn->close();
?>