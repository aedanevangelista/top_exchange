<?php
session_start();
include "db_connection.php";

// Current date/time from your system
$current_datetime = "2025-03-25 17:13:01";
$current_user = "aedanevangelista";

// Set error logging
error_log("Payment processing started at $current_datetime by $current_user");

header('Content-Type: application/json');

// Check if required data is received
if (!isset($_POST['username']) || !isset($_POST['month']) || !isset($_POST['year']) || !isset($_POST['amount'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$username = $_POST['username'];
$month = (int)$_POST['month'];
$year = (int)$_POST['year'];
$amount = (float)$_POST['amount'];
$notes = isset($_POST['notes']) ? $_POST['notes'] : '';
$payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'External';

// Log the payment attempt
error_log("Payment processing: User=$username, Month=$month, Year=$year, Amount=$amount, Method=$payment_method");

// Validate amount
if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Amount must be greater than 0']);
    exit;
}

// Get client's current balance
$sql = "SELECT balance FROM clients_accounts WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$row = $result->fetch_assoc();
$current_balance = (float)$row['balance'];

// For Internal payments, check if user has enough balance
if ($payment_method === 'Internal' && $current_balance < $amount) {
    echo json_encode(['success' => false, 'message' => 'Insufficient balance for internal payment']);
    exit;
}

// Get month name
$month_names = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
$month_name = $month_names[$month];

// Handle file upload for External payments
$file_name = null;
if ($payment_method === 'External') {
    if (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Payment proof is required for external payments']);
        exit;
    }
    
    // Create directory structure
    $dir_path = "../payments/$username/$month_name - $year";
    if (!is_dir("../payments")) mkdir("../payments", 0777, true);
    if (!is_dir("../payments/$username")) mkdir("../payments/$username", 0777, true);
    if (!is_dir($dir_path)) mkdir($dir_path, 0777, true);
    
    // Save the file
    $proof = $_FILES['proof'];
    $file_ext = pathinfo($proof['name'], PATHINFO_EXTENSION);
    $file_name = "payment_" . time() . "." . $file_ext;
    $file_path = "$dir_path/$file_name";
    
    if (!move_uploaded_file($proof['tmp_name'], $file_path)) {
        error_log("Failed to upload file to: $file_path");
        echo json_encode(['success' => false, 'message' => 'Failed to upload proof file']);
        exit;
    }
}

// Begin transaction
$conn->begin_transaction();

try {
    // Check if a payment record exists for this month/year
    $sql = "SELECT * FROM monthly_payments WHERE username = ? AND month = ? AND year = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $username, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment_exists = $result->num_rows > 0;
    
    if ($payment_exists) {
        $payment_data = $result->fetch_assoc();
        $total_amount = $payment_data['total_amount'];
        $remaining = max(0, $payment_data['remaining_balance'] - $amount);
        
        // Update existing payment record
        if ($payment_method === 'External') {
            $sql = "UPDATE monthly_payments SET 
                    payment_status = 'For Approval',
                    proof_image = ?,
                    payment_method = ?,
                    notes = CONCAT_WS('', notes, '\n', ?),
                    remaining_balance = ?
                    WHERE username = ? AND month = ? AND year = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssdiii", $file_name, $payment_method, $notes, $remaining, $username, $month, $year);
        } else {
            $sql = "UPDATE monthly_payments SET 
                    payment_status = 'Paid',
                    payment_method = ?,
                    notes = CONCAT_WS('', notes, '\n', ?),
                    remaining_balance = ?
                    WHERE username = ? AND month = ? AND year = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdiii", $payment_method, $notes, $remaining, $username, $month, $year);
        }
        $stmt->execute();
    } else {
        // Get total amount from orders
        $sql = "SELECT SUM(total_amount) AS total FROM orders 
                WHERE username = ? AND MONTH(delivery_date) = ? AND YEAR(delivery_date) = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $username, $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_amount = (float)($row['total'] ?? 0);
        $remaining = max(0, $total_amount - $amount);
        
        // Create new payment record
        if ($payment_method === 'External') {
            $sql = "INSERT INTO monthly_payments 
                    (username, month, year, total_amount, payment_status, proof_image, payment_method, notes, remaining_balance) 
                    VALUES (?, ?, ?, ?, 'For Approval', ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siidssd", $username, $month, $year, $total_amount, $file_name, $payment_method, $notes, $remaining);
        } else {
            $sql = "INSERT INTO monthly_payments 
                    (username, month, year, total_amount, payment_status, payment_method, notes, remaining_balance) 
                    VALUES (?, ?, ?, ?, 'Paid', ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siidsd", $username, $month, $year, $total_amount, $payment_method, $notes, $remaining);
        }
        $stmt->execute();
    }
    
    // For Internal payments, update client balance
    if ($payment_method === 'Internal') {
        $sql = "UPDATE clients_accounts SET balance = balance - ? WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ds", $amount, $username);
        $stmt->execute();
        
        // Get updated balance
        $sql = "SELECT balance FROM clients_accounts WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $new_balance = (float)$row['balance'];
    } else {
        $new_balance = $current_balance;
    }
    
    // Log payment in payment_history
    $created_by = $current_user;
    
    if ($payment_method === 'External') {
        $sql = "INSERT INTO payment_history 
                (username, month, year, amount, proof_image, payment_method, notes, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siidsss", $username, $month, $year, $amount, $file_name, $payment_method, $notes, $created_by);
    } else {
        $sql = "INSERT INTO payment_history 
                (username, month, year, amount, payment_method, notes, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siidsss", $username, $month, $year, $amount, $payment_method, $notes, $created_by);
    }
    $stmt->execute();
    
    // Commit the transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => $payment_method === 'Internal' ? 'Payment processed successfully' : 'Payment submitted for approval',
        'new_balance' => $new_balance,
        'payment_method' => $payment_method
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Log the error
    error_log("Payment error: " . $e->getMessage());
    
    // Return error response
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>