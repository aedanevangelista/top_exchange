<?php
// Turn on all error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set content type header
header('Content-Type: application/json');

try {
    // Include database connection
    require_once "db_connection.php";
    
    if (!isset($_GET['username']) || empty($_GET['username'])) {
        throw new Exception('Username is required');
    }

    $username = $_GET['username'];
    
    // Check for distinct years in orders table
    $years_sql = "SELECT DISTINCT YEAR(order_date) as year 
                  FROM orders 
                  WHERE username = ? 
                  AND status = 'Completed'
                  ORDER BY year DESC";
    
    $stmt = $conn->prepare($years_sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $years = [];
    while ($row = $result->fetch_assoc()) {
        $years[] = intval($row['year']);
    }
    
    // If no years found in orders, check monthly_payments table
    if (empty($years)) {
        $payments_sql = "SELECT DISTINCT year FROM monthly_payments WHERE username = ? ORDER BY year DESC";
        $stmt = $conn->prepare($payments_sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $years[] = intval($row['year']);
        }
    }
    
    // If still no years, add the current year
    if (empty($years)) {
        $years[] = intval(date('Y'));
    }
    
    echo json_encode([
        'success' => true,
        'data' => $years
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>