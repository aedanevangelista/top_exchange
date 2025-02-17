<?php
include 'db_connection.php';

$data = json_decode(file_get_contents("php://input"), true);
$product_id = $data['product_id'];
$action = $data['action'];
$amount = intval($data['amount']);

if ($action === "add") {
    $sql = "UPDATE products SET stock_quantity = stock_quantity + $amount WHERE product_id = $product_id";
} elseif ($action === "remove") {
    $sql = "UPDATE products SET stock_quantity = GREATEST(stock_quantity - $amount, 0) WHERE product_id = $product_id";
}

if ($conn->query($sql) === TRUE) {
    echo json_encode(["message" => "Stock updated successfully"]);
} else {
    echo json_encode(["message" => "Error updating stock: " . $conn->error]);
}
?>
