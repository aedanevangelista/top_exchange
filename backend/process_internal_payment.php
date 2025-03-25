<?php
session_start();
include "db_connection.php";

// Check if user is logged in with appropriate role
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Manager', 'Accountant'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Validate inputs
if (!isset($_POST['username']) || !isset($_POST['month']) || !isset($_POST['year']) || !isset($_POST['amount'])) {
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
    echo json_encode(['success' => false, 'message' => 'Invalid payment amount']);
    exit;
}

// Get client's current balance
$sql = "SELECT balance FROM clients_accounts WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Client not found']);
    exit;
}

$client = $result->fetch_assoc();
$currentBalance = floatval($client['balance']);

// Check if client has enough balance
if ($amount > $currentBalance) {
    echo json_encode(['success' => false, 'message' => 'Insufficient balance']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Update client's balance
    $newBalance = $currentBalance - $amount;
    $sql = "UPDATE clients_accounts SET balance = ? WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ds", $newBalance, $username);
    $stmt->execute();

    // Check if a payment record already exists for this month/year
    $sql = "SELECT id FROM monthly_payments WHERE username = ? AND month = ? AND year = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $username, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Update existing payment record
        $payment = $result->fetch_assoc();
        $payment_id = $payment['id'];
        
        $sql = "UPDATE monthly_payments SET amount_paid = amount_paid + ?, remaining_balance = remaining_balance - ?, 
                payment_status = IF(remaining_balance - ? <= 0, 'Paid', payment_status), 
                payment_method = ?, notes = CONCAT(notes, IF(notes = '', '', '\n'), ?)
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("dddssi", $amount, $amount, $amount, $payment_method, $notes, $payment_id);
    } else {
        // Insert new payment record
        // First, get the total amount from orders for this month/year
        $sql = "SELECT SUM(total_amount) AS total FROM orders WHERE username = ? AND MONTH(delivery_date) = ? AND YEAR(delivery_date) = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $username, $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();
        $total_amount = floatval($order['total'] ?? 0);
        
        $remaining = $total_amount - $amount;
        $status = $remaining <= 0 ? 'Paid' : 'For Approval';
        
        $sql = "INSERT INTO monthly_payments (username, month, year, total_amount, amount_paid, remaining_balance, payment_method, payment_status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siidddss", $username, $month, $year, $total_amount, $amount, $remaining, $payment_method, $status, $notes);
    }
    
    $stmt->execute();
    
    // Record transaction in payment history
    $sql = "INSERT INTO payment_history (username, month, year, amount, transaction_type, notes, payment_method)
            VALUES (?, ?, ?, ?, 'Payment', ?, ?)";
    $stmt = $conn->prepare($sql);
    $transaction_note = "Payment for " . date("F", mktime(0, 0, 0, $month, 1)) . " $year";
    if ($notes) {
        $transaction_note .= " - $notes";
    }
    $stmt->bind_param("siidss", $username, $month, $year, $amount, $transaction_note, $payment_method);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Payment processed successfully', 'new_balance' => $newBalance]);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error processing payment: ' . $e->getMessage()]);
}

$conn->close();
?>