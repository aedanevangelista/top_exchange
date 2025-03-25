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
file_put_contents($log_file, date("Y-m-d H:i:s") . " - External Payment Process Started\n", FILE_APPEND);
file_put_contents($log_file, date("Y-m-d H:i:s") . " - POST data: " . json_encode($_POST) . "\n", FILE_APPEND);
file_put_contents($log_file, date("Y-m-d H:i:s") . " - FILES data: " . json_encode($_FILES) . "\n", FILE_APPEND);

// Check if user is logged in with appropriate role
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Manager', 'Accountant'])) {
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - Unauthorized access: " . ($_SESSION['role'] ?? 'No role') . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Validate inputs
if (!isset($_POST['username']) || !isset($_POST['month']) || !isset($_POST['year']) || !isset($_POST['amount'])) {
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - Missing parameters\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$username = $_POST['username'];
$month = intval($_POST['month']);
$year = intval($_POST['year']);
$amount = floatval($_POST['amount']);
$notes = isset($_POST['notes']) ? $_POST['notes'] : '';
$payment_method = 'External'; // External payment method

file_put_contents($log_file, date("Y-m-d H:i:s") . " - Processing external payment for: $username, Month: $month, Year: $year, Amount: $amount\n", FILE_APPEND);

if ($amount <= 0) {
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - Invalid amount: $amount\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Invalid payment amount']);
    exit;
}

// Check if proof file was uploaded
if (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - Missing or invalid proof file. Error code: " . ($_FILES['proof']['error'] ?? 'Not set') . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Payment proof is required']);
    exit;
}

// Process the uploaded proof file
$proof_file = $_FILES['proof'];
$file_extension = pathinfo($proof_file['name'], PATHINFO_EXTENSION);
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

if (!in_array(strtolower($file_extension), $allowed_extensions)) {
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - Invalid file extension: $file_extension\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Only image files (JPG, PNG, GIF) are allowed']);
    exit;
}

// Create directory for user's payment proofs if it doesn't exist
$month_name = date("F", mktime(0, 0, 0, $month, 1));
$payments_dir = "../payments/";
$user_dir = $payments_dir . $username . "/";
$upload_dir = $user_dir . $month_name . " - " . $year . "/";

// Create parent directories if they don't exist
if (!file_exists($payments_dir)) {
    if (!mkdir($payments_dir, 0777)) {
        file_put_contents($log_file, date("Y-m-d H:i:s") . " - Failed to create payments directory\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Failed to create payments directory']);
        exit;
    }
}

if (!file_exists($user_dir)) {
    if (!mkdir($user_dir, 0777)) {
        file_put_contents($log_file, date("Y-m-d H:i:s") . " - Failed to create user directory: $user_dir\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Failed to create user directory']);
        exit;
    }
}

if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0777)) {
        file_put_contents($log_file, date("Y-m-d H:i:s") . " - Failed to create upload directory: $upload_dir\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
        exit;
    }
}

file_put_contents($log_file, date("Y-m-d H:i:s") . " - Created upload directory: $upload_dir\n", FILE_APPEND);

// Generate a unique filename
$filename = uniqid() . '_proof.' . $file_extension;
$upload_path = $upload_dir . $filename;

// Move the uploaded file to the target directory
if (!move_uploaded_file($proof_file['tmp_name'], $upload_path)) {
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - Failed to upload file to: $upload_path\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Failed to upload proof file']);
    exit;
}

file_put_contents($log_file, date("Y-m-d H:i:s") . " - File uploaded successfully to: $upload_path\n", FILE_APPEND);

// Start transaction
$conn->begin_transaction();
file_put_contents($log_file, date("Y-m-d H:i:s") . " - Transaction started\n", FILE_APPEND);

try {
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
        
        $sql = "UPDATE monthly_payments SET 
                amount_paid = ?,
                remaining_balance = ?,
                payment_status = 'For Approval',
                payment_method = ?,
                proof_image = ?,
                notes = ?
                WHERE id = ?";
                
        file_put_contents($log_file, date("Y-m-d H:i:s") . " - SQL: $sql\n", FILE_APPEND);
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ddsssi", $amount_paid, $remaining_balance, $payment_method, $filename, $notes, $payment_id);
        
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
        
        $sql = "INSERT INTO monthly_payments 
                (username, month, year, total_amount, amount_paid, remaining_balance, payment_method, payment_status, proof_image, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'For Approval', ?, ?)";
                
        file_put_contents($log_file, date("Y-m-d H:i:s") . " - SQL: $sql\n", FILE_APPEND);
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("siidddss", $username, $month, $year, $total_amount, $amount, $remaining, $payment_method, $filename, $notes);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert payment record: " . $stmt->error);
        }
        
        file_put_contents($log_file, date("Y-m-d H:i:s") . " - Inserted new payment record successfully\n", FILE_APPEND);
    }
    
    // Record transaction in payment history
    $sql = "INSERT INTO payment_history 
            (username, month, year, amount, transaction_type, notes, payment_method, proof_image, created_by)
            VALUES (?, ?, ?, ?, 'External Payment', ?, ?, ?, ?)";
            
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - SQL for payment history: $sql\n", FILE_APPEND);
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed for payment history: " . $conn->error);
    }
    
    $transaction_note = "External Payment for " . $month_name . " $year (awaiting approval)";
    if ($notes) {
        $transaction_note .= " - $notes";
    }
    
    $created_by = $_SESSION['username'] ?? 'system';
    
    $stmt->bind_param("siidssss", $username, $month, $year, $amount, $transaction_note, $payment_method, $filename, $created_by);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert payment history: " . $stmt->error);
    }
    
    // Commit transaction
    $conn->commit();
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - Transaction committed successfully\n", FILE_APPEND);
    
    echo json_encode(['success' => true, 'message' => 'Payment submitted successfully and pending approval']);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    
    // Delete the uploaded file if there was an error
    if (file_exists($upload_path)) {
        unlink($upload_path);
        file_put_contents($log_file, date("Y-m-d H:i:s") . " - Deleted uploaded file due to error\n", FILE_APPEND);
    }
    
    echo json_encode(['success' => false, 'message' => 'Error processing payment: ' . $e->getMessage()]);
}

$conn->close();
file_put_contents($log_file, date("Y-m-d H:i:s") . " - Process completed\n", FILE_APPEND);
?>