<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Include the database connection file
    require_once "db_connection.php";

    // Validate input
    if (!isset($_POST['username']) || empty($_POST['username'])) {
        throw new Exception('Username is required');
    }
    
    if (!isset($_POST['month']) || !is_numeric($_POST['month'])) {
        throw new Exception('Valid month is required');
    }
    
    if (!isset($_POST['year']) || !is_numeric($_POST['year'])) {
        throw new Exception('Valid year is required');
    }
    
    if (!isset($_POST['status']) || empty($_POST['status'])) {
        throw new Exception('Status is required');
    }
    
    // Sanitize inputs
    $username = $_POST['username'];
    $month = (int)$_POST['month'];
    $year = (int)$_POST['year'];
    $status = $_POST['status'];
    
    // Validate status value
    if (!in_array($status, ['Paid', 'Unpaid'])) {
        throw new Exception('Invalid status value');
    }
    
    // Update payment status
    $sql = "UPDATE monthly_payments 
            SET payment_status = ? 
            WHERE username = ? AND month = ? AND year = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $status, $username, $month, $year);
    $result = $stmt->execute();
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Payment status updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update payment status: ' . $conn->error);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>