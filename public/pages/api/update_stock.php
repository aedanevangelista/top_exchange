<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

$response = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['product_id'], $data['action'], $data['amount'])) {
        $response['error'] = "Invalid input data";
        echo json_encode($response);
        exit();
    }

    include $_SERVER['DOCUMENT_ROOT'] . "/top_exchange/backend/db_connection.php";

    if ($conn->connect_error) {
        $response['error'] = "Database connection failed: " . $conn->connect_error;
        echo json_encode($response);
        exit();
    }

    $productId = intval($data['product_id']);
    $action = $data['action'];
    $amount = intval($data['amount']);

    $stmt = $conn->prepare("SELECT stock_quantity FROM products WHERE product_id = ?");
    if (!$stmt) {
        $response['error'] = "Error preparing statement: " . $conn->error;
        echo json_encode($response);
        exit();
    }

    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $stmt->bind_result($currentStock);
    if (!$stmt->fetch()) {
        $response['error'] = "Product not found";
        echo json_encode($response);
        exit();
    }
    $stmt->close();

    if ($action === 'add') {
        $newStock = $currentStock + $amount;
    } elseif ($action === 'remove') {
        $newStock = max(0, $currentStock - $amount);
    } else {
        $response['error'] = "Invalid action";
        echo json_encode($response);
        exit();
    }

    $stmt = $conn->prepare("UPDATE products SET stock_quantity = ? WHERE product_id = ?");
    if (!$stmt) {
        $response['error'] = "Error preparing update: " . $conn->error;
        echo json_encode($response);
        exit();
    }

    $stmt->bind_param("ii", $newStock, $productId);
    if ($stmt->execute()) {
        $response['message'] = "Stock updated successfully";
    } else {
        $response['error'] = "Update failed: " . $stmt->error;
    }

    $stmt->close();
} else {
    $response['error'] = "Invalid request method";
}

echo json_encode($response);
?>
