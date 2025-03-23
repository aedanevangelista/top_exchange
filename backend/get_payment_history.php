<?php
header('Content-Type: application/json');
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Include database connection
    require_once "db_connection.php";
    
    // Validate required parameters
    if (!isset($_GET['username']) || !isset($_GET['month']) || !isset($_GET['year'])) {
        throw new Exception("Missing required parameters");
    }
    
    // Sanitize inputs
    $username = $_GET['username'];
    $month = (int)$_GET['month'];
    $year = (int)$_GET['year'];
    
    // Query to get payment history
    $sql = "SELECT payment_date, amount_paid, payment_status, proof_of_payment, payment_notes 
            FROM monthly_payments 
            WHERE username = ? AND month = ? AND year = ?
            ORDER BY payment_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $username, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Format payment date
            $row['payment_date'] = date('Y-m-d H:i:s', strtotime($row['payment_date']));
            $payments[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $payments
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>