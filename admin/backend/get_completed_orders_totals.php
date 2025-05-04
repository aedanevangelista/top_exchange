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
    // Calculate the total amount from completed orders by month
    $sql = "SELECT 
                MONTH(order_date) as month, 
                SUM(total_amount) as total_amount 
            FROM orders 
            WHERE username = ? 
            AND YEAR(order_date) = ? 
            AND status = 'Completed'
            GROUP BY MONTH(order_date)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $username, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Create an associative array with month as the key
    $monthlyTotals = array();
    while ($row = $result->fetch_assoc()) {
        $monthlyTotals[$row['month']] = (float)$row['total_amount'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $monthlyTotals,
        'username' => $username,
        'year' => $year
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch monthly totals',
        'error' => $e->getMessage()
    ]);
}

$stmt->close();
$conn->close();
?>