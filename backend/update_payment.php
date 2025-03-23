<?php
// Set headers to prevent caching and indicate JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Enable error reporting for debugging but don't display
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Initialize log file
$logFile = __DIR__ . '/payment_error.log';
file_put_contents($logFile, "\n\n" . date('Y-m-d H:i:s') . " - PAYMENT UPDATE REQUEST\n", FILE_APPEND);
file_put_contents($logFile, "POST DATA: " . json_encode($_POST, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
file_put_contents($logFile, "FILES: " . json_encode($_FILES, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

try {
    // Include database connection
    require_once "db_connection.php";
    
    // Get and validate inputs
    if (!isset($_POST['username']) || !isset($_POST['month']) || !isset($_POST['year']) || !isset($_POST['payment_amount'])) {
        throw new Exception("Missing required fields");
    }
    
    $username = $_POST['username'];
    $month = (int)$_POST['month'];
    $year = (int)$_POST['year'];
    $paymentAmount = (float)$_POST['payment_amount'];
    $currentBalance = isset($_POST['current_balance']) ? (float)$_POST['current_balance'] : 0;
    $totalAmount = isset($_POST['total_amount']) ? (float)$_POST['total_amount'] : 0;
    $remainingBalance = isset($_POST['remaining_balance']) ? (float)$_POST['remaining_balance'] : $totalAmount;
    $notes = isset($_POST['payment_notes']) ? $_POST['payment_notes'] : '';
    
    // Calculate new account balance
    $newBalance = $currentBalance - $paymentAmount;
    
    // Calculate remaining balance for this month
    $newRemainingBalance = max(0, $remainingBalance - $paymentAmount);
    
    // Handle file upload if present
    $dbFilePath = null;
    $uploadSuccess = false;
    
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK && $_FILES['payment_proof']['size'] > 0) {
        file_put_contents($logFile, "Processing file upload\n", FILE_APPEND);
        
        // Base upload directory
        $uploadBaseDir = dirname(dirname(__FILE__)) . '/uploads/payments/';
        file_put_contents($logFile, "Upload base directory: $uploadBaseDir\n", FILE_APPEND);
        
        // Create directory structure if it doesn't exist
        if (!is_dir($uploadBaseDir)) {
            if (!mkdir($uploadBaseDir, 0755, true)) {
                throw new Exception("Failed to create uploads directory");
            }
        }
        
        // Month names for folder structure
        $monthNames = array(1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 
                           7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December');
        
        // Generate a unique filename
        $ext = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
        $baseFileName = $username . '_' . $month . '_' . $year;
        
        // Create a simple file name without complex directory structure
        $fileName = $baseFileName . '_' . uniqid() . '.' . $ext;
        $uploadPath = $uploadBaseDir . $fileName;
        
        file_put_contents($logFile, "Attempting to upload to: $uploadPath\n", FILE_APPEND);
        
        // Move the uploaded file
        if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $uploadPath)) {
            $dbFilePath = $fileName;
            $uploadSuccess = true;
            file_put_contents($logFile, "File uploaded successfully\n", FILE_APPEND);
        } else {
            $uploadError = error_get_last();
            file_put_contents($logFile, "Upload failed: " . json_encode($uploadError) . "\n", FILE_APPEND);
            throw new Exception("Failed to upload file: " . ($uploadError['message'] ?? 'Unknown error'));
        }
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Check if record exists
        $checkSql = "SELECT * FROM monthly_payments WHERE username = ? AND month = ? AND year = ?";
        $checkStmt = $conn->prepare($checkSql);
        if (!$checkStmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }
        
        $checkStmt->bind_param("sii", $username, $month, $year);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        $now = date('Y-m-d H:i:s');
        
        if ($result->num_rows > 0) {
            // Update existing record
            $payment = $result->fetch_assoc();
            
            if ($uploadSuccess) {
                // Update with new file
                $updateSql = "UPDATE monthly_payments SET 
                              payment_status = 'Paid',
                              balance = ?,
                              amount_paid = amount_paid + ?,
                              payment_date = ?,
                              payment_notes = ?,
                              proof_of_payment = ?,
                              updated_at = ?
                              WHERE username = ? AND month = ? AND year = ?";
                
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("ddssssii", 
                    $newRemainingBalance,
                    $paymentAmount,
                    $now,
                    $notes,
                    $dbFilePath,
                    $now,
                    $username,
                    $month,
                    $year
                );
            } else {
                // Update without file
                $updateSql = "UPDATE monthly_payments SET 
                              payment_status = 'Paid',
                              balance = ?,
                              amount_paid = amount_paid + ?,
                              payment_date = ?,
                              payment_notes = ?,
                              updated_at = ?
                              WHERE username = ? AND month = ? AND year = ?";
                
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("ddsssii", 
                    $newRemainingBalance,
                    $paymentAmount,
                    $now,
                    $notes,
                    $now,
                    $username,
                    $month,
                    $year
                );
            }
            
            if (!$updateStmt->execute()) {
                throw new Exception("Failed to update payment: " . $updateStmt->error);
            }
            
        } else {
            // Insert new record
            $insertSql = "INSERT INTO monthly_payments 
                         (username, month, year, total_amount, payment_status, 
                          balance, amount_paid, proof_of_payment, payment_date, 
                          payment_notes, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, 'Paid', ?, ?, ?, ?, ?, ?, ?)";
            
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param("siidddssss", 
                $username,
                $month,
                $year,
                $totalAmount,
                $newRemainingBalance,
                $paymentAmount,
                $dbFilePath,
                $now,
                $notes,
                $now,
                $now
            );
            
            if (!$insertStmt->execute()) {
                throw new Exception("Failed to insert payment: " . $insertStmt->error);
            }
        }
        
        // Update client balance in clients_accounts table
        $balanceSql = "UPDATE clients_accounts SET balance = ? WHERE username = ?";
        $balanceStmt = $conn->prepare($balanceSql);
        $balanceStmt->bind_param("ds", $newBalance, $username);
        
        if (!$balanceStmt->execute()) {
            throw new Exception("Failed to update client balance: " . $balanceStmt->error);
        }
        
        // Record in balance_history
        $balanceHistorySql = "INSERT INTO balance_history 
                              (username, amount, previous_balance, new_balance, notes, created_at) 
                              VALUES (?, ?, ?, ?, ?, NOW())";
        
        $balanceHistoryStmt = $conn->prepare($balanceHistorySql);
        $paymentNote = "Payment for " . $monthNames[$month] . " " . $year;
        if (!empty($notes)) {
            $paymentNote .= ": " . $notes;
        }
        
        $balanceHistoryStmt->bind_param("sddds", 
            $username,
            $paymentAmount,
            $currentBalance,
            $newBalance,
            $paymentNote
        );
        
        if (!$balanceHistoryStmt->execute()) {
            throw new Exception("Failed to record balance history: " . $balanceHistoryStmt->error);
        }
        
        // Commit transaction
        $conn->commit();
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Payment processed successfully',
            'balance' => $newBalance,
            'remaining' => $newRemainingBalance,
            'file_uploaded' => $uploadSuccess,
            'filename' => $dbFilePath
        ]);
        
    } catch (Exception $e) {
        // Roll back transaction
        $conn->rollback();
        
        // If we uploaded a file but the transaction failed, delete it
        if ($uploadSuccess && file_exists($uploadPath)) {
            unlink($uploadPath);
        }
        
        throw $e;
    }
    
} catch (Exception $e) {
    // Log error
    $errorMessage = "ERROR: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
    file_put_contents($logFile, $errorMessage, FILE_APPEND);
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>