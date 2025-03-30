<?php
session_start();
include "../db_connection.php";
include $_SERVER['DOCUMENT_ROOT'] . "/backend/db_connection.php";

// Get date parameter
$date = isset($_GET['date']) ? $_GET['date'] : null;

if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid date format']);
    exit;
}

// Fetch orders for the specified date with Active status
$orders = [];
$sql = "SELECT po_number, username, order_date, delivery_date, delivery_address, orders, total_amount 
        FROM orders 
        WHERE delivery_date = ? AND status = 'Active'
        ORDER BY order_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = [
            'po_number' => $row['po_number'],
            'username' => $row['username'],
            'order_date' => $row['order_date'],
            'delivery_date' => $row['delivery_date'],
            'delivery_address' => $row['delivery_address'],
            'orders' => $row['orders'],
            'total_amount' => $row['total_amount']
        ];
    }
}

$stmt->close();

// Return JSON response
header('Content-Type: application/json');
echo json_encode($orders);
exit;