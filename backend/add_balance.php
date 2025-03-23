<?php
header('Content-Type: application/json');
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Include database connection
    require_once "db_connection.php";
    
    // Check if the user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Authentication required");
    }
    
    // Validate required fields
    if (!isset($_POST['username']) || !isset($_POST['amount'])) {
        throw new Exception("Missing required fields");
    }
    
    // Sanitize inputs
    $username = $_POST['username'];
    $amount = (float)$_POST['amount'];
    $currentBalance = isset($_POST['current_balance']) ? (float)$_POST['current_balance'] : 0;
    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
    
    // Validate amount
    if ($amount <= 0) {
        throw new Exception("Amount must be greater than 0");
    }
    
    // Calculate new balance - for adding balance, we increase the balance
    $newBalance = $currentBalance + $amount;
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update client account balance
        $sql = "UPDATE clients_accounts SET balance = ? WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ds", $newBalance, $username);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            throw new Exception("Failed to update balance or user not found");
        }
        
        // Record the balance addition in payment_history
        $sql = "INSERT INTO payment_history 
                (username, amount, payment_type, notes, created_at) 
                VALUES (?, ?, 'Balance Addition', ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sds", $username, $amount, $notes);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Balance added successfully',
            'balance' => $newBalance
        ]);
        
    } catch (Exception $e) {
        // Roll back the transaction on error
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    // Log error for server-side debugging
    error_log('Add balance error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>