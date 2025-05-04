<?php
session_start();
include "db_connection.php";

// Set content type to JSON
header('Content-Type: application/json');

// Check if username, month, and year are provided
if (!isset($_GET['username']) || !isset($_GET['month']) || !isset($_GET['year'])) {
    echo json_encode(['success' => false, 'message' => 'Username, month and year are required']);
    exit;
}

$username = $_GET['username'];
$month = (int) $_GET['month'];
$year = (int) $_GET['year'];

// Calculate date range for the month
$firstDayOfMonth = sprintf("%04d-%02d-01", $year, $month);
$lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));

try {
    // Get completed orders for this month
    $sql = "SELECT po_number, order_date, delivery_date, delivery_address, orders, total_amount 
            FROM orders 
            WHERE username = ? 
            AND order_date BETWEEN ? AND ? 
            AND status = 'Completed'
            ORDER BY order_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $username, $firstDayOfMonth, $lastDayOfMonth);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        // Parse orders JSON data if it's stored as a string
        if (isset($row['orders']) && is_string($row['orders'])) {
            $row['orders'] = json_decode($row['orders'], true);
        }
        $orders[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $orders,
        'month' => $month,
        'year' => $year
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching orders: ' . $e->getMessage()
    ]);
}

$stmt->close();
$conn->close();
?>