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

// Validate status
$valid_statuses = ['Unpaid', 'For Approval', 'Fully Paid', 'Partially Paid'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// Validate month
if ($month < 1 || $month > 12) {
    echo json_encode(['success' => false, 'message' => 'Invalid month']);
    exit;
}

// Check if payment_status enum includes the new statuses
$check_status_values = "SHOW COLUMNS FROM monthly_payments LIKE 'payment_status'";
$result = $conn->query($check_status_values);
$update_enum = false;

if ($result && $row = $result->fetch_assoc()) {
    $type = $row['Type'];
    if (strpos($type, 'Fully Paid') === false || strpos($type, 'Partially Paid') === false) {
        $update_enum = true;
    }
}

// Begin transaction
$conn->begin_transaction();

try {
    // Update the payment_status enum if needed
    if ($update_enum) {
        $conn->query("ALTER TABLE monthly_payments 
                     MODIFY COLUMN payment_status ENUM('Fully Paid', 'Partially Paid', 'Unpaid', 'For Approval') 
                     NOT NULL DEFAULT 'Unpaid'");
    }
    
    // Get current payment status
    $sql = "SELECT payment_status FROM monthly_payments WHERE username = ? AND month = ? AND year = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $username, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $old_status = 'Unpaid';
    if ($row = $result->fetch_assoc()) {
        $old_status = $row['payment_status'];
    }
    
    // Update remaining balance to 0 if status is Fully Paid
    if ($status === 'Fully Paid') {
        $sql = "UPDATE monthly_payments SET payment_status = ?, remaining_balance = 0 WHERE username = ? AND month = ? AND year = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $status, $username, $month, $year);
    } else {
        $sql = "UPDATE monthly_payments SET payment_status = ? WHERE username = ? AND month = ? AND year = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $status, $username, $month, $year);
    }
    
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        // Get total from orders
        $orders_sql = "SELECT SUM(total_amount) as total_amount 
                      FROM orders 
                      WHERE username = ? AND MONTH(delivery_date) = ? AND YEAR(delivery_date) = ? AND status = 'Completed'";
        $orders_stmt = $conn->prepare($orders_sql);
        $orders_stmt->bind_param("sii", $username, $month, $year);
        $orders_stmt->execute();
        $orders_result = $orders_stmt->get_result();
        
        $total_amount = 0;
        if ($row = $orders_result->fetch_assoc()) {
            $total_amount = $row['total_amount'] ?: 0;
        }
        
        // Insert new record
        $remaining_balance = ($status === 'Fully Paid') ? 0 : $total_amount;
        
        $sql = "INSERT INTO monthly_payments (username, month, year, total_amount, payment_status, remaining_balance) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siidsd", $username, $month, $year, $total_amount, $status, $remaining_balance);
        $stmt->execute();
    }
    
    // Log the status change
    $sql = "INSERT INTO payment_status_history (username, month, year, old_status, new_status, changed_by) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $changed_by = $_SESSION['username'] ?? 'system';
    $stmt->bind_param("siisss", $username, $month, $year, $old_status, $status, $changed_by);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Payment status updated successfully',
        'old_status' => $old_status,
        'new_status' => $status
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Error updating payment status: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>