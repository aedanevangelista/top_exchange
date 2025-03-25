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
$payment_method = 'External'; // External payment method

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment amount']);
    exit;
}

// Check if proof file was uploaded
if (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Payment proof is required']);
    exit;
}

// Process the uploaded proof file
$proof_file = $_FILES['proof'];
$file_extension = pathinfo($proof_file['name'], PATHINFO_EXTENSION);
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

if (!in_array(strtolower($file_extension), $allowed_extensions)) {
    echo json_encode(['success' => false, 'message' => 'Only image files (JPG, PNG, GIF) are allowed']);
    exit;
}

// Create directory for user's payment proofs if it doesn't exist
$month_name = date("F", mktime(0, 0, 0, $month, 1));
$upload_dir = "../payments/$username/$month_name - $year/";

if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate a unique filename
$filename = uniqid() . '_proof.' . $file_extension;
$upload_path = $upload_dir . $filename;

// Move the uploaded file to the target directory
if (!move_uploaded_file($proof_file['tmp_name'], $upload_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to upload proof file']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
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
        
        $sql = "UPDATE monthly_payments SET 
                amount_paid = amount_paid + ?, 
                remaining_balance = remaining_balance - ?,
                payment_status = 'For Approval',
                payment_method = ?,
                proof_image = ?,
                notes = CONCAT(notes, IF(notes = '', '', '\n'), ?)
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ddsssi", $amount, $amount, $payment_method, $filename, $notes, $payment_id);
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
        
        $sql = "INSERT INTO monthly_payments (username, month, year, total_amount, amount_paid, remaining_balance, payment_method, payment_status, proof_image, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'For Approval', ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siidddss", $username, $month, $year, $total_amount, $amount, $remaining, $payment_method, $filename, $notes);
    }
    
    $stmt->execute();
    
    // Record transaction in payment history
    $sql = "INSERT INTO payment_history (username, month, year, amount, transaction_type, notes, payment_method, proof_image)
            VALUES (?, ?, ?, ?, 'External Payment', ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $transaction_note = "External Payment for " . $month_name . " $year (awaiting approval)";
    if ($notes) {
        $transaction_note .= " - $notes";
    }
    $stmt->bind_param("siidsss", $username, $month, $year, $amount, $transaction_note, $payment_method, $filename);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Payment submitted successfully and pending approval']);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Delete the uploaded file if there was an error
    if (file_exists($upload_path)) {
        unlink($upload_path);
    }
    
    echo json_encode(['success' => false, 'message' => 'Error processing payment: ' . $e->getMessage()]);
}

$conn->close();
?>