<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    require_once "../db_connection.php";

    $poNumber = $_GET['po_number'] ?? '';
    $username = $_GET['username'] ?? '';

    if (empty($poNumber) || empty($username)) {
        throw new Exception('PO Number and username are required');
    }

    $sql = "SELECT orders, total_amount 
            FROM orders 
            WHERE po_number = ? AND username = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $poNumber, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Order not found');
    }

    $row = $result->fetch_assoc();
    $orderItems = json_decode($row['orders'], true);

    echo json_encode([
        'success' => true,
        'data' => [
            'items' => $orderItems,
            'total_amount' => floatval($row['total_amount'])
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => true,
        'message' => 'Error loading order details: ' . $e->getMessage()
    ]);
}
?>