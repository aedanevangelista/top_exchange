<?php
include "db_connection.php";

$transaction_id = $_GET['transaction_id'];

// Get current status
$sql = "SELECT status, username FROM orders WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

$new_status = $row['status'] === 'Paid' ? 'Unpaid' : 'Paid';
$username = $row['username'];

// Update status
$sql = "UPDATE orders SET status = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $new_status, $transaction_id);
$stmt->execute();

header('Content-Type: application/json');
echo json_encode(['success' => true, 'userId' => $user_id]);
?>