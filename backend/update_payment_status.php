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
    if (!isset($_POST['username']) || !isset($_POST['month']) || !isset($_POST['year']) || !isset($_POST['status'])) {
        throw new Exception("Missing required fields");
    }
    
    // Sanitize inputs
    $username = $_POST['username'];
    $month = (int)$_POST['month'];
    $year = (int)$_POST['year'];
    $status = $_POST['status'];
    
    // Validate status
    if (!in_array($status, ['Paid', 'Pending', 'Unpaid'])) {
        throw new Exception("Invalid status value");
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Check if monthly payment record exists
        $sql = "SELECT * FROM monthly_payments WHERE username = ? AND month = ? AND year = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $username, $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing record
            $sql = "UPDATE monthly_payments SET payment_status = ?, updated_at = NOW() WHERE username = ? AND month = ? AND year = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssii", $status, $username, $month, $year);
        } else {
            // Get total amount for orders in this month/year
            $sql = "SELECT IFNULL(SUM(total_amount), 0) as total_amount FROM orders 
                    WHERE username = ? AND MONTH(delivery_date) = ? AND YEAR(delivery_date) = ? AND status = 'Active'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sii", $username, $month, $year);
            $stmt->execute();
            $orderResult = $stmt->get_result();
            $orderRow = $orderResult->fetch_assoc();
            $totalAmount = (float)$orderRow['total_amount'];
            
            // Insert new record
            $sql = "INSERT INTO monthly_payments (username, month, year, payment_status, total_amount, remaining_balance, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siisdd", $username, $month, $year, $status, $totalAmount, $totalAmount);
        }
        
        if ($stmt->execute()) {
            $conn->commit();
            echo json_encode([
                'success' => true,
                'message' => "Payment status updated to $status"
            ]);
        } else {
            throw new Exception("Failed to update payment status: " . $conn->error);
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    // Log error for server-side debugging
    error_log('Update payment status error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>