<?php
header('Content-Type: application/json');
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Check user authentication
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Authentication required");
    }

    // Include database connection
    require_once "db_connection.php";
    
    // Get the monthly payment data
    $sql = "SELECT mp.*, 
            (SELECT SUM(o.total_amount) FROM orders o 
             WHERE o.username = mp.username 
             AND MONTH(o.delivery_date) = mp.month 
             AND YEAR(o.delivery_date) = mp.year 
             AND o.status = 'Active') as total_orders
            FROM monthly_payments mp
            WHERE mp.username = ? AND mp.month = ? AND mp.year = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $_POST['username'], $_POST['month'], $_POST['year']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $payment = $result->fetch_assoc();
        
        // Update payment status, amount_paid, and balance
        $sql = "UPDATE monthly_payments 
                SET payment_status = ?, amount_paid = ?, balance = ?, 
                    payment_notes = CONCAT(IFNULL(payment_notes, ''), '\n', ?)
                WHERE username = ? AND month = ? AND year = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sddsii", $_POST['status'], $_POST['amount_paid'], $_POST['balance'], $_POST['notes'], $_POST['username'], $_POST['month'], $_POST['year']);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Payment updated successfully'
            ]);
        } else {
            throw new Exception("Failed to update payment: " . $conn->error);
        }
    } else {
        // Insert new payment record
        $sql = "INSERT INTO monthly_payments (username, month, year, payment_status, total_amount, amount_paid, balance, payment_notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $totalAmount = $_POST['total_amount'];
        $stmt->bind_param("siisdds", $_POST['username'], $_POST['month'], $_POST['year'], $_POST['status'], $totalAmount, $_POST['amount_paid'], $_POST['balance'], $_POST['notes']);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Payment record created successfully'
            ]);
        } else {
            throw new Exception("Failed to create payment record: " . $conn->error);
        }
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>