<?php
// Add error reporting at the top
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include "db_connection.php";

// Create a log file for debugging
$log_file = "../logs/payment_debug.log";
if (!file_exists("../logs")) {
    mkdir("../logs", 0777, true);
}
file_put_contents($log_file, date("Y-m-d H:i:s") . " - Internal Payment Process Started\n", FILE_APPEND);

// Check if user is logged in with appropriate role
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Manager', 'Accountant'])) {
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - Unauthorized access: " . ($_SESSION['role'] ?? 'No role') . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Validate inputs
if (!isset($_POST['username']) || !isset($_POST['month']) || !isset($_POST['year']) || !isset($_POST['amount'])) {
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - Missing parameters: " . json_encode($_POST) . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$username = $_POST['username'];
$month = intval($_POST['month']);
$year = intval($_POST['year']);
$amount = floatval($_POST['amount']);
$notes = isset($_POST['notes']) ? $_POST['notes'] : '';
$payment_method = 'Internal'; // Internal payment method

if ($amount <= 0) {
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - Invalid amount: $amount\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Invalid payment amount']);
    exit;
}

// Log input data
file_put_contents($log_file, date("Y-m-d H:i:s") . " - Processing payment for: $username, Month: $month, Year: $year, Amount: $amount\n", FILE_APPEND);

try {
    // Get client's current balance
    $sql = "SELECT balance FROM clients_accounts WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        file_put_contents($log_file, date("Y-m-d H:i:s") . " - Client not found: $username\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Client not found']);
        exit;
    }

    $client = $result->fetch_assoc();
    $currentBalance = floatval($client['balance']);
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - Current balance: $currentBalance\n", FILE_APPEND);

    // Check if client has enough balance
    if ($amount > $currentBalance) {
        file_put_contents($log_file, date("Y-m-d H:i:s") . " - Insufficient balance: $currentBalance < $amount\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Insufficient balance']);
        exit;
    }

    // Start transaction
    $conn->begin_transaction();
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - Transaction started\n", FILE_APPEND);

    // Update client's balance
    $newBalance = $currentBalance - $amount;
    $sql = "UPDATE clients_accounts SET balance = ? WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ds", $newBalance, $username);
    if (!$stmt->execute()) {
        throw new Exception("Failed to update client balance: " . $stmt->error);
    }
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - Updated client balance to: $newBalance\n", FILE_APPEND);

    // Check if a payment record already exists for this month/year
    $sql = "SELECT id, remaining_balance, amount_paid FROM monthly_payments WHERE username = ? AND month = ? AND year = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $username, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Update existing payment record
        $payment = $result->fetch_assoc();
        $payment_id = $payment['id'];
        $remaining_balance = floatval($payment['remaining_balance']) - $amount;
        $amount_paid = floatval($payment['amount_paid']) + $amount;
        
        file_put_contents($log_file, date("Y-m-d H:i:s") . " - Updating existing payment record ID: $payment_id\n", FILE_APPEND);
        
        // Determine payment status based on remaining balance
        $payment_status = $remaining_balance <= 0 ? 'Paid' : 'For Approval';
        
        $sql = "UPDATE monthly_payments SET 
                amount_paid = ?, 
                remaining_balance = ?, 
                payment_status = ?, 
                payment_method = ?,
                notes = ?
                WHERE id = ?";
                
        file_put_contents($log_file, date("Y-m-d H:i:s") . " - SQL: $sql\n", FILE_APPEND);
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ddsssi", $amount_paid, $remaining_balance, $payment_status, $payment_method, $notes, $payment_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update payment record: " . $stmt->error);
        }
        
        file_put_contents($log_file, date("Y-m-d H:i:s") . " - Updated payment record successfully\n", FILE_APPEND);
    } else {
        // Insert new payment record
        file_put_contents($log_file, date("Y-m-d H:i:s") . " - Creating new payment record\n", FILE_APPEND);
        
        // First, get the total amount from orders for this month/year
        $sql = "SELECT SUM(total_amount) AS total FROM orders WHERE username = ? AND MONTH(delivery_date) = ? AND YEAR(delivery_date) = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $username, $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();
        $total_amount = floatval($order['total'] ?? 0);
        file_put_contents($log_file, date("Y-m-d H:i:s") . " - Total order amount: $total_amount\n", FILE_APPEND);
        
        $remaining = $total_amount - $amount;
        $status = $remaining <= 0 ? 'Paid' : 'For Approval';
        
        $sql = "INSERT INTO monthly_payments 
                (username, month, year, total_amount, amount_paid, remaining_balance, payment_method, payment_status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
        file_put_contents($log_file, date("Y-m-d H:i:s") . " - SQL: $sql\n", FILE_APPEND);
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("siidddss", $username, $month, $year, $total_amount, $amount, $remaining, $payment_method, $status, $notes);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert payment record: " . $stmt->error);
        }
        
        file_put_contents($log_file, date("Y-m-d H:i:s") . " - Inserted new payment record successfully\n", FILE_APPEND);
    }
    
    // Record transaction in payment history
    $sql = "INSERT INTO payment_history 
            (username, month, year, amount, transaction_type, notes, payment_method, created_by)
            VALUES (?, ?, ?, ?, 'Payment', ?, ?, ?)";
            
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - SQL for payment history: $sql\n", FILE_APPEND);
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed for payment history: " . $conn->error);
    }
    
    $transaction_note = "Payment for " . date("F", mktime(0, 0, 0, $month, 1)) . " $year";
    if ($notes) {
        $transaction_note .= " - $notes";
    }
    
    $created_by = $_SESSION['username'] ?? 'system';
    
    $stmt->bind_param("siidsss", $username, $month, $year, $amount, $transaction_note, $payment_method, $created_by);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert payment history: " . $stmt->error);
    }
    
    // Commit transaction
    $conn->commit();
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - Transaction committed successfully\n", FILE_APPEND);
    
    echo json_encode(['success' => true, 'message' => 'Payment processed successfully', 'new_balance' => $newBalance]);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Error processing payment: ' . $e->getMessage()]);
}

$conn->close();
file_put_contents($log_file, date("Y-m-d H:i:s") . " - Process completed\n", FILE_APPEND);
?>