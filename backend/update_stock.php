<?php
include "../../backend/db_connection.php";

$data = json_decode(file_get_contents('php://input'), true);
$product_id = $data['product_id'];
$action = $data['action'];
$amount = $data['amount'];

$sql = "UPDATE products SET stock_quantity = stock_quantity + ? WHERE product_id = ?";
if ($action === 'remove') {
    $sql = "UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $amount, $product_id);

if ($stmt->execute()) {
    echo json_encode(["message" => "Stock updated successfully"]);
} else {
    echo json_encode(["message" => "Error updating stock"]);
}

$stmt->close();
$conn->close();
?>