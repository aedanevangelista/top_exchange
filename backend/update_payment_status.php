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
$allowed_statuses = ['Unpaid', 'For Approval', 'Paid'];
if (!in_array($status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
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
    
    // Update balance if status changes to/from Paid
    if ($status !== $current_status) {
        // Only adjust balance if the status is changing to/from "Paid"
        if ($status === 'Paid' && $current_status !== 'Paid') {
            // If changing to Paid, make sure remaining_balance is 0
            $sql = "UPDATE monthly_payments 
                    SET payment_status = ?, remaining_balance = 0
                    WHERE username = ? AND month = ? AND year = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssii", $status, $username, $month, $year);
            $stmt->execute();
            
        } else if ($status !== 'Paid' && $current_status === 'Paid') {
            // If changing from Paid, restore the original total_amount as remaining_balance
            $sql = "UPDATE monthly_payments 
                    SET payment_status = ?, remaining_balance = total_amount
                    WHERE username = ? AND month = ? AND year = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssii", $status, $username, $month, $year);
            $stmt->execute();
            
        } else {
            // Just update the status without changing the remaining_balance
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
        $sql = "INSERT INTO monthly_payments 
                (username, month, year, total_amount, payment_status, remaining_balance) 
                VALUES (?, ?, ?, 0, ?, 0)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siis", $username, $month, $year, $status);
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