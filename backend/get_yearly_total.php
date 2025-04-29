<?php
session_start();
include "db_connection.php";

// Set content type to JSON
header('Content-Type: application/json');

// Check if username and year are provided
if (!isset($_GET['username']) || !isset($_GET['year'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Username and year are required'
    ]);
    exit;
}

$username = $_GET['username'];
$year = (int) $_GET['year'];

try {
    // Calculate the total amount from completed orders only
    $sql = "SELECT SUM(total_amount) as total_amount 
            FROM orders 
            WHERE username = ? 
            AND YEAR(order_date) = ? 
            AND status = 'Completed'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $username, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    // Get total amount from completed orders
    $totalAmount = $row['total_amount'] ?: 0;
    
    echo json_encode([
        'success' => true,
        'total_amount' => $totalAmount,
        'username' => $username,
        'year' => $year
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch yearly total: ' . $e->getMessage()
    ]);
}

$stmt->close();
$conn->close();
?>