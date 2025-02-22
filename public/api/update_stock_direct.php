<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['product_id'], $data['stock_quantity'])) {
        include "../../backend/db_connection.php";

        $productId = $data['product_id'];
        $stockQuantity = (int)$data['stock_quantity'];

        $sql = "UPDATE products SET stock_quantity = ? WHERE product_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $stockQuantity, $productId);
        if ($stmt->execute()) {
            echo json_encode(['message' => 'Stock updated successfully']);
        } else {
            echo json_encode(['message' => 'Error updating stock']);
        }
        $stmt->close();
    } else {
        echo json_encode(['message' => 'Invalid input']);
    }
} else {
    echo json_encode(['message' => 'Invalid request method']);
}
?>