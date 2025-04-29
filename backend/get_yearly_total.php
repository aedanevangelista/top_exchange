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

// Get the yearly total of remaining payments for the user
$sql = "SELECT SUM(remaining_balance) as total_amount 
        FROM monthly_payments 
        WHERE username = ? AND year = ? AND payment_status != 'Fully Paid'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $username, $year);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $row = $result->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'total_amount' => $row['total_amount'] ?: 0,
        'username' => $username,
        'year' => $year
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch yearly total',
        'error' => $conn->error
    ]);
}

$stmt->close();
$conn->close();
?>