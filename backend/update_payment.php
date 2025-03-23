<?php
// Basic error handling and debugging
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start a buffer to capture any errors
ob_start();

try {
    // Log the request to help with debugging
    $logFile = __DIR__ . '/payment_debug.log';
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - Request received' . PHP_EOL, FILE_APPEND);
    file_put_contents($logFile, json_encode($_POST) . PHP_EOL, FILE_APPEND);
    file_put_contents($logFile, json_encode($_FILES) . PHP_EOL, FILE_APPEND);
    
    // Include database connection
    require_once "db_connection.php";
    
    // Session management - optional for this simplified version
    session_start();
    
    // Get basic parameters
    $username = $_POST['username'] ?? '';
    $month = isset($_POST['month']) ? (int)$_POST['month'] : 0;
    $year = isset($_POST['year']) ? (int)$_POST['year'] : 0;
    $paymentAmount = isset($_POST['payment_amount']) ? (float)$_POST['payment_amount'] : 0;
    $totalAmount = isset($_POST['total_amount']) ? (float)$_POST['total_amount'] : 0;
    $currentBalance = isset($_POST['current_balance']) ? (float)$_POST['current_balance'] : 0;
    $remainingBalance = isset($_POST['remaining_balance']) ? (float)$_POST['remaining_balance'] : 0;
    
    // Validate basic requirements
    if (empty($username) || $month <= 0 || $year <= 0 || $paymentAmount <= 0) {
        throw new Exception("Missing or invalid required fields");
    }
    
    // Calculate new values
    $newRemainingBalance = max(0, $remainingBalance - $paymentAmount);
    $newBalance = $currentBalance - $paymentAmount;
    
    // Handle file upload 
    $proofFilename = null;
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK && $_FILES['payment_proof']['size'] > 0) {
        $uploadDir = dirname(__DIR__) . "/uploads/payments/";
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate a unique filename
        $extension = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
        $proofFilename = 'payment_' . time() . '_' . mt_rand(1000, 9999) . '.' . $extension;
        $uploadPath = $uploadDir . $proofFilename;
        
        // Move the uploaded file
        if (!move_uploaded_file($_FILES['payment_proof']['tmp_name'], $uploadPath)) {
            $uploadError = error_get_last();
            file_put_contents($logFile, "Upload failed: " . json_encode($uploadError) . PHP_EOL, FILE_APPEND);
            throw new Exception("Failed to upload file");
        }
    }
    
    // Simplified database operations - no transactions for now
    // First, check if the record exists
    $checkSql = "SELECT COUNT(*) FROM monthly_payments WHERE username = ? AND month = ? AND year = ?";
    $checkStmt = $conn->prepare($checkSql);
    
    if (!$checkStmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $checkStmt->bind_param("sii", $username, $month, $year);
    $checkStmt->execute();
    $checkStmt->bind_result($count);
    $checkStmt->fetch();
    $checkStmt->close();
    
    if ($count > 0) {
        // Update existing record
        $updateSql = "UPDATE monthly_payments SET 
                     payment_status = 'Paid', 
                     remaining_balance = ?, 
                     updated_at = NOW()";
        
        // Only update proof if we have a new one
        if ($proofFilename) {
            $updateSql .= ", proof_of_payment = ?";
        }
        
        $updateSql .= " WHERE username = ? AND month = ? AND year = ?";
        
        $updateStmt = $conn->prepare($updateSql);
        
        if (!$updateStmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        if ($proofFilename) {
            $updateStmt->bind_param("dssii", $newRemainingBalance, $proofFilename, $username, $month, $year);
        } else {
            $updateStmt->bind_param("dsii", $newRemainingBalance, $username, $month, $year);
        }
        
        if (!$updateStmt->execute()) {
            throw new Exception("Failed to update payment: " . $updateStmt->error);
        }
    } else {
        // Create new record
        $insertSql = "INSERT INTO monthly_payments 
                      (username, month, year, payment_status, total_amount, remaining_balance, proof_of_payment, created_at, updated_at) 
                      VALUES (?, ?, ?, 'Paid', ?, ?, ?, NOW(), NOW())";
        
        $insertStmt = $conn->prepare($insertSql);
        
        if (!$insertStmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        $insertStmt->bind_param("siidds", $username, $month, $year, $totalAmount, $newRemainingBalance, $proofFilename);
        
        if (!$insertStmt->execute()) {
            throw new Exception("Failed to insert payment: " . $insertStmt->error);
        }
    }
    
    // Update client account balance
    $balanceSql = "UPDATE clients_accounts SET balance = ? WHERE username = ?";
    $balanceStmt = $conn->prepare($balanceSql);
    
    if (!$balanceStmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $balanceStmt->bind_param("ds", $newBalance, $username);
    
    if (!$balanceStmt->execute()) {
        throw new Exception("Failed to update balance: " . $balanceStmt->error);
    }
    
    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Payment updated successfully',
        'balance' => $newBalance,
        'remaining' => $newRemainingBalance
    ]);
    
} catch (Exception $e) {
    // Log the error
    $errorMsg = date('Y-m-d H:i:s') . ' - Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() . PHP_EOL;
    file_put_contents(__DIR__ . '/payment_error.log', $errorMsg, FILE_APPEND);
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug_info' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
    
    // If we uploaded a file but encountered an error, clean it up
    if (isset($proofFilename) && isset($uploadPath) && file_exists($uploadPath)) {
        unlink($uploadPath);
    }
}

// Capture any errors that may have occurred
$errors = ob_get_clean();
if (!empty($errors)) {
    file_put_contents(__DIR__ . '/php_errors.log', date('Y-m-d H:i:s') . ' - ' . $errors . PHP_EOL, FILE_APPEND);
}
?>