<?php
session_start();
include "db_connection.php";
include "check_role.php";
checkRole('Payment History');

header('Content-Type: application/json');

// Check if required data is received
if (!isset($_POST['username']) || !isset($_POST['month']) || !isset($_POST['year']) || !isset($_POST['amount']) || !isset($_FILES['proof'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$username = $conn->real_escape_string($_POST['username']);
$month = (int)$_POST['month'];
$year = (int)$_POST['year'];
$amount = (float)$_POST['amount'];
$notes = isset($_POST['notes']) ? $conn->real_escape_string($_POST['notes']) : '';

// Validate month
if ($month < 1 || $month > 12) {
    echo json_encode(['success' => false, 'message' => 'Invalid month']);
    exit;
}

// Validate amount
if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Amount must be greater than 0']);
    exit;
}

// Check if the month is in the future (using fixed date: March 24, 2025)
$current_date = new DateTime('2025-03-24');
$check_date = new DateTime("$year-$month-01");
$last_day_of_month = clone $check_date;
$last_day_of_month->modify('last day of this month');

if ($check_date > $current_date) {
    echo json_encode(['success' => false, 'message' => 'Cannot make payments for future months']);
    exit;
}

// Handle file upload
$proof = $_FILES['proof'];
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];

if (!in_array($proof['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, JPG, PNG, and GIF are allowed.']);
    exit;
}

// Get month name
$month_names = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
$month_name = $month_names[$month];

// Create directory path
$dir_path = "../payments/$username/$month_name - $year";

// Create directories if they don't exist
if (!file_exists("../payments")) {
    mkdir("../payments", 0777, true);
}
if (!file_exists("../payments/$username")) {
    mkdir("../payments/$username", 0777, true);
}
if (!file_exists($dir_path)) {
    mkdir($dir_path, 0777, true);
}

// Generate unique filename
$file_extension = pathinfo($proof['name'], PATHINFO_EXTENSION);
$file_name = "payment_" . time() . "." . $file_extension;
$file_path = "$dir_path/$file_name";

// Check if notes column exists
$check_notes_column = "SHOW COLUMNS FROM monthly_payments LIKE 'notes'";
$notes_column_exists = $conn->query($check_notes_column)->num_rows > 0;

// Begin transaction
$conn->begin_transaction();

try {
    // Upload file
    if (!move_uploaded_file($proof['tmp_name'], $file_path)) {
        throw new Exception("Failed to upload file");
    }
    
    // Check if client has enough balance
    $sql = "SELECT balance FROM clients_accounts WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $current_balance = $row['balance'];
        
        if ($current_balance < $amount) {
            // Remove the uploaded file and throw exception
            unlink($file_path);
            throw new Exception("Insufficient balance");
        }
    } else {
        // Remove the uploaded file and throw exception
        unlink($file_path);
        throw new Exception("User not found");
    }
    
    // Get existing payment data
    $sql = "SELECT payment_status, total_amount, remaining_balance FROM monthly_payments 
            WHERE username = ? AND month = ? AND year = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $username, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $total_amount = 0;
    $remaining_balance = 0;
    $payment_exists = false;
    
    if ($row = $result->fetch_assoc()) {
        $payment_exists = true;
        $total_amount = $row['total_amount'];
        $remaining_balance = $row['remaining_balance'] !== null ? $row['remaining_balance'] : $row['total_amount'];
    } else {
        // Get total from orders if no payment record exists
        $sql = "SELECT SUM(total_amount) as total_amount 
               FROM orders 
               WHERE username = ? AND MONTH(delivery_date) = ? 
               AND YEAR(delivery_date) = ? AND status = 'Completed'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $username, $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $total_amount = $row['total_amount'] ?: 0;
            $remaining_balance = $total_amount;
        }
    }
    
    // Calculate new remaining balance
    $new_remaining_balance = max(0, $remaining_balance - $amount);
    
    // Set payment status based on remaining balance
    $payment_status = 'For Approval';
    if ($new_remaining_balance <= 0) {
        $payment_status = 'For Approval'; // Still need admin approval even if fully paid
    }
    
    // If payment record exists, update it
    if ($payment_exists) {
        // Check if notes column exists and include it in the update
        if ($notes_column_exists) {
            $sql = "UPDATE monthly_payments SET 
                    payment_status = ?, 
                    proof_image = ?,
                    remaining_balance = ?,
                    notes = ?
                    WHERE username = ? AND month = ? AND year = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdssis", $payment_status, $file_name, $new_remaining_balance, $notes, $username, $month, $year);
        } else {
            $sql = "UPDATE monthly_payments SET 
                    payment_status = ?, 
                    proof_image = ?,
                    remaining_balance = ?
                    WHERE username = ? AND month = ? AND year = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdsii", $payment_status, $file_name, $new_remaining_balance, $username, $month, $year);
        }
        $stmt->execute();
    } else {
        // If no payment record exists, create one
        if ($notes_column_exists) {
            $sql = "INSERT INTO monthly_payments 
                    (username, month, year, total_amount, payment_status, proof_image, remaining_balance, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siidssds", $username, $month, $year, $total_amount, $payment_status, $file_name, $new_remaining_balance, $notes);
        } else {
            $sql = "INSERT INTO monthly_payments 
                    (username, month, year, total_amount, payment_status, proof_image, remaining_balance) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siidssd", $username, $month, $year, $total_amount, $payment_status, $file_name, $new_remaining_balance);
        }
        $stmt->execute();
    }
    
    // Update client balance
    $sql = "UPDATE clients_accounts SET balance = balance - ? WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ds", $amount, $username);
    $stmt->execute();
    
    // Log the payment
    if ($notes_column_exists) {
        $sql = "INSERT INTO payment_history 
                (username, month, year, amount, notes, proof_image, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $created_by = $_SESSION['username'] ?? 'system';
        $stmt->bind_param("siidsss", $username, $month, $year, $amount, $notes, $file_name, $created_by);
    } else {
        $sql = "INSERT INTO payment_history 
                (username, month, year, amount, proof_image, created_by) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $created_by = $_SESSION['username'] ?? 'system';
        $stmt->bind_param("siidss", $username, $month, $year, $amount, $file_name, $created_by);
    }
    $stmt->execute();
    
    // Get the new balance
    $sql = "SELECT balance FROM clients_accounts WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $new_balance = 0;
    
    if ($row = $result->fetch_assoc()) {
        $new_balance = $row['balance'];
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment processed successfully',
        'new_balance' => $new_balance
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Error processing payment: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>