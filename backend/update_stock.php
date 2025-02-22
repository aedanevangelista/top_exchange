<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['product_id'], $data['action'], $data['amount'])) {
        include "../../backend/db_connection.php";

        $productId = $data['product_id'];
        $action = $data['action'];
        $amount = (int)$data['amount'];

        $sql = "SELECT stock_quantity FROM products WHERE product_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $stmt->bind_result($currentStock);
        $stmt->fetch();
        $stmt->close();

        if ($action === 'add') {
            $newStock = $currentStock + $amount;
        } elseif ($action === 'remove') {
            $newStock = max(0, $currentStock - $amount);
        } else {
            echo json_encode(['message' => 'Invalid action']);
            exit;
        }

        $sql = "UPDATE products SET stock_quantity = ? WHERE product_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $newStock, $productId);
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