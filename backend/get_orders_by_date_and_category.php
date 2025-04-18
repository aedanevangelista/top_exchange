<?php
session_start();
include "db_connection.php";
include "check_role.php";
checkApiRole('Department Forecast');  // Changed from 'Forecast' to 'Department Forecast'

header('Content-Type: application/json');

if (!isset($_GET['date']) || !isset($_GET['category'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$date = $_GET['date'];
$category = $_GET['category'];

// Input validation
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['error' => 'Invalid date format']);
    exit;
}

try {
    $sql = "SELECT DISTINCT o.order_id, o.po_number, o.username, o.order_date, o.delivery_date, o.delivery_address, o.total_amount, o.orders
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            JOIN products p ON oi.product_id = p.product_id
            WHERE o.delivery_date = ? 
            AND o.status = 'Active'
            AND p.category = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $date, $category);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    
    echo json_encode($orders);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>