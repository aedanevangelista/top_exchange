<?php
include "../db_connection.php";

$username = $_POST['username'] ?? '';
$month = $_POST['month'] ?? '';
$year = $_POST['year'] ?? date('Y');
$status = $_POST['status'] ?? '';

// First, check if record exists
$checkSql = "SELECT id FROM monthly_payments 
             WHERE username = ? AND month = ? AND year = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("sii", $username, $month, $year);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows > 0) {
    // Update existing record
    $sql = "UPDATE monthly_payments 
            SET payment_status = ? 
            WHERE username = ? AND month = ? AND year = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $status, $username, $month, $year);
} else {
    // Calculate total amount for the month
    $amountSql = "SELECT COALESCE(SUM(total_amount), 0) as total 
                  FROM orders 
                  WHERE username = ? 
                  AND MONTH(order_date) = ? 
                  AND YEAR(order_date) = ? 
                  AND status = 'Completed'";
    $amountStmt = $conn->prepare($amountSql);
    $amountStmt->bind_param("sii", $username, $month, $year);
    $amountStmt->execute();
    $amountResult = $amountStmt->get_result();
    $totalAmount = $amountResult->fetch_assoc()['total'];

    // Insert new record
    $sql = "INSERT INTO monthly_payments (username, month, year, total_amount, payment_status) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siids", $username, $month, $year, $totalAmount, $status);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}