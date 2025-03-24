<?php
session_start();
include "db_connection.php";
include "check_role.php";
checkRole('Payment History');

header('Content-Type: application/json');

// Check if required data is received
if (!isset($_POST['username']) || !isset($_POST['month']) || !isset($_POST['year']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$username = $conn->real_escape_string($_POST['username']);
$month = (int)$_POST['month'];
$year = (int)$_POST['year'];
$status = $conn->real_escape_string($_POST['status']);

// Validate month
if ($month < 1 || $month > 12) {
    echo json_encode(['success' => false, 'message' => 'Invalid month']);
    exit;
}

// Validate status
$allowed_statuses = ['Unpaid', 'For Approval', 'Paid', 'Pending'];
if (!in_array($status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// Check if the month is in the future (using fixed date: March 24, 2025)
$current_date = new DateTime('2025-03-24');
$check_date = new DateTime("$year-$month-01");
if ($check_date > $current_date && $month != $current_date->format('n')) {
    echo json_encode(['success' => false, 'message' => 'Cannot update status for future months']);
    exit;
}

// Begin transaction
$conn->begin_transaction();

try {
    // Get the current payment status and amount
    $sql = "SELECT payment_status, total_amount, remaining_balance FROM monthly_payments 
            WHERE username = ? AND month = ? AND year = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $username, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $current_status = 'Unpaid';
    $amount = 0;
    $remaining_balance = 0;
    
    if ($row = $result->fetch_assoc()) {
        $current_status = $row['payment_status'];
        $amount = $row['total_amount'];
        $remaining_balance = $row['remaining_balance'];
        
        if ($remaining_balance === null) {
            $remaining_balance = $amount;
        }
    }
    
    // Update based on the new status
    if ($status !== $current_status) {
        if ($status === 'Paid') {
            // If changing to Paid, make sure remaining_balance is 0
            $sql = "UPDATE monthly_payments 
                    SET payment_status = ?, remaining_balance = 0
                    WHERE username = ? AND month = ? AND year = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssii", $status, $username, $month, $year);
            $stmt->execute();
        } else if ($status === 'Unpaid' && ($current_status === 'Paid' || $current_status === 'For Approval')) {
            // If changing from Paid/ForApproval to Unpaid, restore the original total_amount as remaining_balance
            $sql = "UPDATE monthly_payments 
                    SET payment_status = ?, remaining_balance = total_amount
                    WHERE username = ? AND month = ? AND year = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssii", $status, $username, $month, $year);
            $stmt->execute();
        } else {
            // For other status changes, just update the status
            $sql = "UPDATE monthly_payments 
                    SET payment_status = ? 
                    WHERE username = ? AND month = ? AND year = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssii", $status, $username, $month, $year);
            $stmt->execute();
        }
    }
    
    // If no rows updated, create a new monthly payment record with the given status
    if ($stmt->affected_rows === 0) {
        // First get total amount from completed orders for this month
        $orders_sql = "SELECT SUM(total_amount) as total_amount 
                      FROM orders 
                      WHERE username = ? AND MONTH(delivery_date) = ? 
                      AND YEAR(delivery_date) = ? AND status = 'Completed'";
        $orders_stmt = $conn->prepare($orders_sql);
        $orders_stmt->bind_param("sii", $username, $month, $year);
        $orders_stmt->execute();
        $orders_result = $orders_stmt->get_result();
        $total_amount = 0;
        
        if ($orders_row = $orders_result->fetch_assoc()) {
            $total_amount = $orders_row['total_amount'] ?: 0;
        }
        
        // Set remaining_balance based on status
        $remaining_balance = ($status === 'Paid') ? 0 : $total_amount;
        
        $sql = "INSERT INTO monthly_payments 
                (username, month, year, total_amount, payment_status, remaining_balance) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siissd", $username, $month, $year, $total_amount, $status, $remaining_balance);
        $stmt->execute();
    }
    
    // Log the status change
    $sql = "INSERT INTO payment_status_history 
            (username, month, year, old_status, new_status, changed_by) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $changed_by = $_SESSION['username'];
    $stmt->bind_param("siisss", $username, $month, $year, $current_status, $status, $changed_by);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Payment status updated successfully']);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Error updating payment status: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>