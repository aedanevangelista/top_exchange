<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "db_connection.php";

// Create log file for debugging
$log_file = __DIR__ . "/payment_logs.txt";
file_put_contents($log_file, "External Payment Processing Started: " . date("Y-m-d H:i:s") . "\n", FILE_APPEND);

// Debug info
$debug_info = [];
$debug_info['session'] = isset($_SESSION['user_id']) ? 'Set' : 'Not set';
$debug_info['post_data'] = $_POST;
$debug_info['files'] = isset($_FILES['proof']) ? 'Set' : 'Not set';

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
    
    // Handle file upload
    if (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
        file_put_contents($log_file, "Error: Payment proof is required. Error code: " . ($_FILES['proof']['error'] ?? 'No file') . "\n", FILE_APPEND);
        throw new Exception('Payment proof is required');
    }

    // File details
    $file = $_FILES['proof'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    file_put_contents($log_file, "File details: name=$fileName, size=$fileSize, ext=$fileExt\n", FILE_APPEND);

    // Validate file
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    if (!in_array($fileExt, $allowed)) {
        file_put_contents($log_file, "Error: Invalid file type: $fileExt\n", FILE_APPEND);
        throw new Exception('Invalid file type. Allowed types: ' . implode(', ', $allowed));
    }

    if ($fileSize > 10485760) { // 10MB limit
        file_put_contents($log_file, "Error: File size exceeded: $fileSize\n", FILE_APPEND);
        throw new Exception('File size exceeded. Maximum size: 10MB');
    }

    // Get month name
    $monthName = date('F', mktime(0, 0, 0, $month, 10));
    
    // Check if directory exists and is writable
    $uploadDir = "../../payments/{$username}/{$monthName} - {$year}/";
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            file_put_contents($log_file, "Error: Failed to create directory: $uploadDir\n", FILE_APPEND);
            throw new Exception("Failed to create directory: $uploadDir");
        }
    }
    
    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        file_put_contents($log_file, "Error: Directory not writable: $uploadDir\n", FILE_APPEND);
        throw new Exception("Directory not writable: $uploadDir");
    }
    
    file_put_contents($log_file, "Upload directory ready: $uploadDir\n", FILE_APPEND);

    // Generate unique filename
    $newFileName = 'proof_' . uniqid() . '.' . $fileExt;
    $uploadPath = $uploadDir . $newFileName;

    // Move file
    if (!move_uploaded_file($fileTmpName, $uploadPath)) {
        file_put_contents($log_file, "Error: Failed to move uploaded file\n", FILE_APPEND);
        throw new Exception('Failed to upload file');
    }
    
    file_put_contents($log_file, "File uploaded successfully: $uploadPath\n", FILE_APPEND);

    // Begin transaction
    $conn->begin_transaction();
    file_put_contents($log_file, "Transaction started\n", FILE_APPEND);
    
    try {
        // Update payment record - First check if the record exists
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
                    payment_status = 'For Approval', 
                    payment_method = 'External', 
                    proof_image = ?,
                    notes = ?
                WHERE username = ? AND month = ? AND year = ?
            ");
            $stmt->bind_param("dsssii", $amount, $newFileName, $notes, $username, $month, $year);
        } else {
            // Insert new record
            $stmt = $conn->prepare("
                INSERT INTO monthly_payments 
                    (username, month, year, amount_paid, remaining_balance, payment_status, payment_method, proof_image, notes) 
                VALUES (?, ?, ?, ?, 0, 'For Approval', 'External', ?, ?)
            ");
            $stmt->bind_param("siidss", $username, $month, $year, $amount, $newFileName, $notes);
        }
        
        if (!$stmt->execute()) {
            file_put_contents($log_file, "Error updating payment record: " . $conn->error . "\n", FILE_APPEND);
            throw new Exception('Failed to update payment record: ' . $conn->error);
        }
        
        file_put_contents($log_file, "Payment record updated\n", FILE_APPEND);
        
        // Log payment in history table
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
            VALUES (?, ?, ?, 'External', ?)
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
            'message' => 'Payment submitted for approval',
            'file' => $newFileName
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