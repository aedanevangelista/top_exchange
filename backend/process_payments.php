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

// Handle file upload
$proof = $_FILES['proof'];
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

if (!in_array($proof['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, and GIF are allowed.']);
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
    
    // Update the monthly payment
    $sql = "UPDATE monthly_payments SET 
            payment_status = 'For Approval', 
            proof_image = ?,
            remaining_balance = GREATEST(0, remaining_balance - ?)
            WHERE username = ? AND month = ? AND year = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sdsii", $file_name, $amount, $username, $month, $year);
    $stmt->execute();
    
    // If no rows updated, create a new monthly payment record
    if ($stmt->affected_rows === 0) {
        $sql = "INSERT INTO monthly_payments 
                (username, month, year, total_amount, payment_status, proof_image, remaining_balance) 
                VALUES (?, ?, ?, ?, 'For Approval', ?, 0)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siisd", $username, $month, $year, $amount, $file_name);
        $stmt->execute();
    }
    
    // Update client balance
    $sql = "UPDATE clients_accounts SET balance = balance - ? WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ds", $amount, $username);
    $stmt->execute();
    
    // Log the payment
    $sql = "INSERT INTO payment_history 
            (username, month, year, amount, notes, proof_image, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $created_by = $_SESSION['username'];
    $stmt->bind_param("siidsss", $username, $month, $year, $amount, $notes, $file_name, $created_by);
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