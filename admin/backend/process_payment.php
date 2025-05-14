<?php
session_start();
include "db_connection.php";
include "check_role.php";
checkRole('Payment History');

header('Content-Type: application/json');

// Check if required data is received
if (!isset($_POST['username']) || !isset($_POST['month']) || !isset($_POST['year']) || !isset($_POST['amount']) || !isset($_POST['payment_type'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$username = $conn->real_escape_string($_POST['username']);
$month = (int)$_POST['month'];
$year = (int)$_POST['year'];
$amount = (float)$_POST['amount'];
$notes = isset($_POST['notes']) ? $conn->real_escape_string($_POST['notes']) : '';
$payment_type = $conn->real_escape_string($_POST['payment_type']);

// Validate payment type
if (!in_array($payment_type, ['Internal', 'External'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment type']);
    exit;
}

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

// Check if the month is in the future
$current_date = new DateTime(); // Use current server date and time
$check_date = new DateTime("$year-$month-01");
$last_day_of_month = clone $check_date;
$last_day_of_month->modify('last day of this month');

if ($check_date > $current_date) {
    echo json_encode(['success' => false, 'message' => 'Cannot make payments for future months']);
    exit;
}

// For External payment type, check if proof file is provided
$proof_file_name = null;
if ($payment_type === 'External') {
    if (!isset($_FILES['proof'])) {
        echo json_encode(['success' => false, 'message' => 'Payment proof is required for external payments']);
        exit;
    }
    
    // Handle file upload
    $proof = $_FILES['proof'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
    
    if (!in_array($proof['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, JPG, PNG, and GIF are allowed.']);
        exit;
    }
}

// Get month name
$month_names = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
$month_name = $month_names[$month];

// Create directory path for External payments
if ($payment_type === 'External') {
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
    $proof_file_name = "payment_" . time() . "." . $file_extension;
    $file_path = "$dir_path/$proof_file_name";
}

// Check if payment_type column exists
$check_payment_type_column = "SHOW COLUMNS FROM monthly_payments LIKE 'payment_type'";
$payment_type_column_exists = $conn->query($check_payment_type_column)->num_rows > 0;

// Check if notes column exists
$check_notes_column = "SHOW COLUMNS FROM monthly_payments LIKE 'notes'";
$notes_column_exists = $conn->query($check_notes_column)->num_rows > 0;

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
    
    // Upload file for External payments
    if ($payment_type === 'External' && $proof_file_name) {
        if (!move_uploaded_file($proof['tmp_name'], $file_path)) {
            throw new Exception("Failed to upload file");
        }
    }
    
    // For Internal payments, check if client has enough balance
    if ($payment_type === 'Internal') {
        $sql = "SELECT balance FROM clients_accounts WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $current_balance = $row['balance'];
            
            if ($current_balance < $amount) {
                // Remove the uploaded file if it exists and throw exception
                if ($payment_type === 'External' && $proof_file_name && file_exists($file_path)) {
                    unlink($file_path);
                }
                throw new Exception("Insufficient balance");
            }
        } else {
            // Remove the uploaded file if it exists and throw exception
            if ($payment_type === 'External' && $proof_file_name && file_exists($file_path)) {
                unlink($file_path);
            }
            throw new Exception("User not found");
        }
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
    // Determine payment status:
    // - Fully Paid if no remaining balance
    // - Partially Paid if there is still a remaining balance
    // - For Approval by default for external payments
    $payment_status = 'For Approval';
    
    if ($payment_type === 'Internal') {
        if ($new_remaining_balance <= 0) {
            $payment_status = 'Fully Paid';
        } else {
            $payment_status = 'Partially Paid';
        }
    }
    
    // If payment record exists, update it
    if ($payment_exists) {
        // Build the SQL query based on available columns
        $sql_parts = [
            "UPDATE monthly_payments SET payment_status = ?, remaining_balance = ?",
            ($payment_type_column_exists ? ", payment_type = ?" : ""),
            ($payment_type === 'External' && $proof_file_name ? ", proof_image = ?" : ""),
            ($notes_column_exists ? ", notes = ?" : ""),
            "WHERE username = ? AND month = ? AND year = ?"
        ];
        
        $sql = implode(" ", $sql_parts);
        
        // Prepare parameter types and values
        $param_types = "sd"; // payment_status, remaining_balance
        $param_values = [$payment_status, $new_remaining_balance];
        
        if ($payment_type_column_exists) {
            $param_types .= "s";
            $param_values[] = $payment_type;
        }
        
        if ($payment_type === 'External' && $proof_file_name) {
            $param_types .= "s";
            $param_values[] = $proof_file_name;
        }
        
        if ($notes_column_exists) {
            $param_types .= "s";
            $param_values[] = $notes;
        }
        
        // Add WHERE clause parameters
        $param_types .= "sii";
        $param_values[] = $username;
        $param_values[] = $month;
        $param_values[] = $year;
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($param_types, ...$param_values);
        $stmt->execute();
    } else {
        // If no payment record exists, create one
        // Build the SQL query based on available columns
        $columns = [
            "username", "month", "year", "total_amount", "payment_status", "remaining_balance"
        ];
        
        $placeholders = ["?", "?", "?", "?", "?", "?"];
        
        if ($payment_type_column_exists) {
            $columns[] = "payment_type";
            $placeholders[] = "?";
        }
        
        if ($payment_type === 'External' && $proof_file_name) {
            $columns[] = "proof_image";
            $placeholders[] = "?";
        }
        
        if ($notes_column_exists) {
            $columns[] = "notes";
            $placeholders[] = "?";
        }
        
        $sql = "INSERT INTO monthly_payments (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $placeholders) . ")";
        
        // Prepare parameter types and values
        $param_types = "siidsd"; // username, month, year, total_amount, payment_status, remaining_balance
        $param_values = [$username, $month, $year, $total_amount, $payment_status, $new_remaining_balance];
        
        if ($payment_type_column_exists) {
            $param_types .= "s";
            $param_values[] = $payment_type;
        }
        
        if ($payment_type === 'External' && $proof_file_name) {
            $param_types .= "s";
            $param_values[] = $proof_file_name;
        }
        
        if ($notes_column_exists) {
            $param_types .= "s";
            $param_values[] = $notes;
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($param_types, ...$param_values);
        $stmt->execute();
    }
    
    // Update client balance only for Internal payments
    if ($payment_type === 'Internal') {
        $sql = "UPDATE clients_accounts SET balance = balance - ? WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ds", $amount, $username);
        $stmt->execute();
    }
    
    // Log the payment
    // Check if payment_type column exists in payment_history
    $check_payment_type_history = "SHOW COLUMNS FROM payment_history LIKE 'payment_type'";
    $payment_type_history_exists = $conn->query($check_payment_type_history)->num_rows > 0;
    
    $columns = ["username", "month", "year", "amount"];
    $placeholders = ["?", "?", "?", "?"];
    $param_types = "siid"; // username, month, year, amount
    $param_values = [$username, $month, $year, $amount];
    
    if ($payment_type_history_exists) {
        $columns[] = "payment_type";
        $placeholders[] = "?";
        $param_types .= "s";
        $param_values[] = $payment_type;
    }
    
    if ($notes_column_exists) { // Assuming notes column in payment_history is the same as in monthly_payments
        $columns[] = "notes";
        $placeholders[] = "?";
        $param_types .= "s";
        $param_values[] = $notes;
    }
    
    if ($payment_type === 'External' && $proof_file_name) {
        $columns[] = "proof_image";
        $placeholders[] = "?";
        $param_types .= "s";
        $param_values[] = $proof_file_name;
    }
    
    $columns[] = "created_by";
    $placeholders[] = "?";
    $param_types .= "s";
    $created_by = $_SESSION['username'] ?? 'system';
    $param_values[] = $created_by;
    
    $sql = "INSERT INTO payment_history (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $placeholders) . ")";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$param_values);
    $stmt->execute();
    
    // Get the new balance (only affected by Internal payments)
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
        'new_balance' => $new_balance,
        'payment_type' => $payment_type
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Error processing payment: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>