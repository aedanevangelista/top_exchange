<?php
session_start();
include "db_connection.php";
include "check_role.php";
checkRole('Payment History');

header('Content-Type: application/json');

// Check if required data is received
if (!isset($_POST['username']) || !isset($_POST['amount'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$username = $conn->real_escape_string($_POST['username']);
$amount = (float)$_POST['amount'];
$notes = isset($_POST['notes']) ? $conn->real_escape_string($_POST['notes']) : '';

// Validate amount
if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Amount must be greater than 0']);
    exit;
}

// Begin transaction
$conn->begin_transaction();

try {
    // Update client balance
    $sql = "UPDATE clients_accounts SET balance = balance + ? WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ds", $amount, $username);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("User not found or no changes made");
    }
    
    // Log the balance addition in a separate table if needed
    $sql = "INSERT INTO balance_history (username, amount, notes, created_by) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $created_by = $_SESSION['username'];
    $stmt->bind_param("sdss", $username, $amount, $notes, $created_by);
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
        'message' => 'Balance updated successfully',
        'new_balance' => $new_balance
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Error updating client balance: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>