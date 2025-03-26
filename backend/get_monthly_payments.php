<?php
session_start();
include "db_connection.php";
include "check_role.php";
checkRole('Payment History');

header('Content-Type: application/json');

// Check if required data is received
if (!isset($_GET['username']) || !isset($_GET['year'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$username = $conn->real_escape_string($_GET['username']);
$year = (int)$_GET['year'];

try {
    // First, get the total amount of completed orders for each month
    $orders_sql = "SELECT 
                MONTH(delivery_date) as order_month,
                YEAR(delivery_date) as order_year,
                SUM(total_amount) as total_amount
            FROM orders
            WHERE username = ? 
                AND YEAR(delivery_date) = ?
                AND status = 'Completed'
            GROUP BY MONTH(delivery_date), YEAR(delivery_date)";
    
    $orders_stmt = $conn->prepare($orders_sql);
    $orders_stmt->bind_param("si", $username, $year);
    $orders_stmt->execute();
    $orders_result = $orders_stmt->get_result();
    
    $monthly_order_totals = [];
    while ($row = $orders_result->fetch_assoc()) {
        $monthly_order_totals[$row['order_month']] = $row['total_amount'];
    }
    
    // Verify if monthly_payments table has notes column
    $check_notes_column = "SHOW COLUMNS FROM monthly_payments LIKE 'notes'";
    $notes_column_exists = $conn->query($check_notes_column)->num_rows > 0;
    
    // Verify if monthly_payments table has payment_type column
    $check_payment_type_column = "SHOW COLUMNS FROM monthly_payments LIKE 'payment_type'";
    $payment_type_column_exists = $conn->query($check_payment_type_column)->num_rows > 0;
    
    // Check if payment_status enum includes the new statuses
    $check_status_values = "SHOW COLUMNS FROM monthly_payments LIKE 'payment_status'";
    $result = $conn->query($check_status_values);
    $update_enum = false;

    if ($result && $row = $result->fetch_assoc()) {
        $type = $row['Type'];
        if (strpos($type, 'Fully Paid') === false || strpos($type, 'Partially Paid') === false) {
            $update_enum = true;
            
            // Update the payment_status enum
            $conn->query("ALTER TABLE monthly_payments 
                         MODIFY COLUMN payment_status ENUM('Fully Paid', 'Partially Paid', 'Unpaid', 'For Approval') 
                         NOT NULL DEFAULT 'Unpaid'");
        }
    }
    
    // Build the SQL query based on which columns exist
    $sql = "SELECT 
                mp.month, 
                mp.total_amount, 
                mp.payment_status,
                mp.remaining_balance,
                mp.proof_image";
    
    if ($notes_column_exists) {
        $sql .= ", mp.notes";
    }
    
    if ($payment_type_column_exists) {
        $sql .= ", mp.payment_type";
    }
    
    $sql .= " FROM monthly_payments mp
              WHERE mp.username = ? AND mp.year = ?
              ORDER BY mp.month";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $username, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    
    // Populate all months from 1-12
    for ($i = 1; $i <= 12; $i++) {
        $payment_data = [
            'month' => $i,
            'total_amount' => isset($monthly_order_totals[$i]) ? $monthly_order_totals[$i] : 0,
            'payment_status' => 'Unpaid',
            'remaining_balance' => isset($monthly_order_totals[$i]) ? $monthly_order_totals[$i] : 0,
            'proof_image' => null,
            'notes' => '',
            'payment_type' => null
        ];
        
        $payments[$i] = $payment_data;
    }
    
    // Update with actual payment data where available
    while ($row = $result->fetch_assoc()) {
        $month = $row['month'];
        
        // If we have actual payment data, use it
        if (isset($payments[$month])) {
            // If there's order data but payment record has no total, use the order total
            if ($row['total_amount'] == 0 && isset($monthly_order_totals[$month])) {
                $row['total_amount'] = $monthly_order_totals[$month];
                
                // Update the database record with this total amount
                $update_sql = "UPDATE monthly_payments 
                              SET total_amount = ? 
                              WHERE username = ? AND month = ? AND year = ? AND total_amount = 0";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("dsii", $monthly_order_totals[$month], $username, $month, $year);
                $update_stmt->execute();
            }
            
            // Convert old 'Paid' status to 'Fully Paid'
            if ($row['payment_status'] === 'Paid') {
                $row['payment_status'] = 'Fully Paid';
                
                // Update the database to use the new status
                $update_status_sql = "UPDATE monthly_payments 
                                     SET payment_status = 'Fully Paid' 
                                     WHERE username = ? AND month = ? AND year = ? AND payment_status = 'Paid'";
                $update_status_stmt = $conn->prepare($update_status_sql);
                $update_status_stmt->bind_param("sii", $username, $month, $year);
                $update_status_stmt->execute();
            }
            
            // Set remaining balance to total amount if it's null AND not paid
            if (($row['remaining_balance'] === null || $row['remaining_balance'] == 0) && 
                $row['payment_status'] != 'Fully Paid' && $row['payment_status'] != 'Partially Paid' && 
                $row['total_amount'] > 0) {
                $row['remaining_balance'] = $row['total_amount'];
                
                // Update the database record with this remaining balance
                $update_balance_sql = "UPDATE monthly_payments 
                                      SET remaining_balance = total_amount 
                                      WHERE username = ? AND month = ? AND year = ? AND 
                                      (remaining_balance IS NULL OR remaining_balance = 0) AND 
                                      payment_status NOT IN ('Fully Paid', 'Partially Paid') AND total_amount > 0";
                $update_balance_stmt = $conn->prepare($update_balance_sql);
                $update_balance_stmt->bind_param("sii", $username, $month, $year);
                $update_balance_stmt->execute();
            }
            
            // If status is Fully Paid, remaining balance should be 0
            if ($row['payment_status'] == 'Fully Paid') {
                $row['remaining_balance'] = 0;
                
                // Update the database to ensure consistency
                $update_paid_sql = "UPDATE monthly_payments 
                                   SET remaining_balance = 0 
                                   WHERE username = ? AND month = ? AND year = ? AND payment_status = 'Fully Paid'";
                $update_paid_stmt = $conn->prepare($update_paid_sql);
                $update_paid_stmt->bind_param("sii", $username, $month, $year);
                $update_paid_stmt->execute();
            }
            
            // Handle notes field if it doesn't exist in result
            if (!isset($row['notes'])) {
                $row['notes'] = '';
            }
            
            // Handle payment_type field if it doesn't exist in result
            if (!isset($row['payment_type'])) {
                $row['payment_type'] = null;
            }
            
            $payments[$month] = $row;
        }
    }
    
    // Convert to indexed array for JSON response
    $payments_array = array_values($payments);
    
    echo json_encode(['success' => true, 'data' => $payments_array]);
    
} catch (Exception $e) {
    error_log("Error getting monthly payments: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>