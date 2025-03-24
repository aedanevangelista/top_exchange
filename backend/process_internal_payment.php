<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "db_connection.php";

// Create log file for debugging
$log_file = __DIR__ . "/payment_logs.txt";
file_put_contents($log_file, "Payment Processing Started: " . date("Y-m-d H:i:s") . "\n", FILE_APPEND);

// Debug info
$debug_info = [];
$debug_info['session'] = isset($_SESSION['user_id']) ? 'Set' : 'Not set';
$debug_info['post_data'] = $_POST;

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        file_put_contents($log_file, "Error: Not authenticated\n", FILE_APPEND);
        echo json_encode([
            'success' => false,
            'message' => 'Not authenticated'
        ]);
        exit;
    }

    // Get POST data
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $month = isset($_POST['month']) ? $_POST['month'] : '';
    $year = isset($_POST['year']) ? $_POST['year'] : '';
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';

    file_put_contents($log_file, "Data received: username=$username, month=$month, year=$year, amount=$amount\n", FILE_APPEND);

    if (!$username || !$month || !$year || $amount <= 0) {
        file_put_contents($log_file, "Error: Missing required parameters\n", FILE_APPEND);
        throw new Exception('Missing required parameters');
    }

    // Simple validation
    $month = (int)$month;
    $year = (int)$year;
    
    // Validate input
    if ($month < 1 || $month > 12) {
        file_put_contents($log_file, "Error: Invalid month: $month\n", FILE_APPEND);
        throw new Exception('Invalid month');
    }
    
    // Get current user balance
    $stmt = $conn->prepare("SELECT balance FROM clients_accounts WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        file_put_contents($log_file, "Error: User '$username' not found\n", FILE_APPEND);
        throw new Exception('User not found');
    }

    $row = $result->fetch_assoc();
    $currentBalance = $row['balance'];
    
    file_put_contents($log_file, "Current balance: $currentBalance\n", FILE_APPEND);

    // Check if user has enough balance
    if ($currentBalance < $amount) {
        file_put_contents($log_file, "Error: Insufficient balance. Required: $amount, Available: $currentBalance\n", FILE_APPEND);
        throw new Exception("Insufficient balance. Required: $amount, Available: $currentBalance");
    }

    // Begin transaction
    $conn->begin_transaction();
    file_put_contents($log_file, "Transaction started\n", FILE_APPEND);
    
    try {
        // 1. Update user balance
        $newBalance = $currentBalance - $amount;
        $stmt = $conn->prepare("UPDATE clients_accounts SET balance = ? WHERE username = ?");
        $stmt->bind_param("ds", $newBalance, $username);
        
        if (!$stmt->execute()) {
            file_put_contents($log_file, "Error updating balance: " . $conn->error . "\n", FILE_APPEND);
            throw new Exception('Failed to update balance: ' . $conn->error);
        }
        
        file_put_contents($log_file, "Balance updated. New balance: $newBalance\n", FILE_APPEND);
        
        // 2. Update payment record - First check if the record exists
        $stmt = $conn->prepare("SELECT * FROM monthly_payments WHERE username = ? AND month = ? AND year = ?");
        $stmt->bind_param("sii", $username, $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing record
            $stmt = $conn->prepare("
                UPDATE monthly_payments 
                SET amount_paid = ?, 
                    remaining_balance = 0, 
                    payment_status = 'Paid', 
                    payment_method = 'Internal', 
                    notes = ?
                WHERE username = ? AND month = ? AND year = ?
            ");
            $stmt->bind_param("dssis", $amount, $notes, $username, $month, $year);
        } else {
            // Insert new record
            $stmt = $conn->prepare("
                INSERT INTO monthly_payments 
                    (username, month, year, amount_paid, remaining_balance, payment_status, payment_method, notes) 
                VALUES (?, ?, ?, ?, 0, 'Paid', 'Internal', ?)
            ");
            $stmt->bind_param("siids", $username, $month, $year, $amount, $notes);
        }
        
        if (!$stmt->execute()) {
            file_put_contents($log_file, "Error updating payment record: " . $conn->error . "\n", FILE_APPEND);
            throw new Exception('Failed to update payment record: ' . $conn->error);
        }
        
        file_put_contents($log_file, "Payment record updated\n", FILE_APPEND);
        
        // 3. Log payment in history table
        // First check if payment_history table exists
        $result = $conn->query("SHOW TABLES LIKE 'payment_history'");
        if ($result->num_rows === 0) {
            // Create the table
            $sql = "CREATE TABLE payment_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(255) NOT NULL,
                payment_date DATETIME NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                payment_method VARCHAR(20) NOT NULL,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $conn->query($sql);
            file_put_contents($log_file, "Created payment_history table\n", FILE_APPEND);
        }
        
        // Insert payment record
        $paymentDate = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("
            INSERT INTO payment_history 
                (username, payment_date, amount, payment_method, notes) 
            VALUES (?, ?, ?, 'Internal', ?)
        ");
        $stmt->bind_param("ssds", $username, $paymentDate, $amount, $notes);
        
        if (!$stmt->execute()) {
            file_put_contents($log_file, "Error logging payment: " . $conn->error . "\n", FILE_APPEND);
            throw new Exception('Failed to log payment history: ' . $conn->error);
        }
        
        file_put_contents($log_file, "Payment logged in history\n", FILE_APPEND);
        
        // Commit transaction
        $conn->commit();
        file_put_contents($log_file, "Transaction committed successfully\n", FILE_APPEND);
        
        // Return success
        echo json_encode([
            'success' => true,
            'message' => 'Payment processed successfully',
            'new_balance' => $newBalance
        ]);
        
    } catch (Exception $e) {
        // Rollback the transaction
        $conn->rollback();
        file_put_contents($log_file, "Transaction rolled back: " . $e->getMessage() . "\n", FILE_APPEND);
        throw $e;
    }
} catch (Exception $e) {
    file_put_contents($log_file, "Exception: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => $debug_info
    ]);
}

file_put_contents($log_file, "Processing completed\n\n", FILE_APPEND);
?>