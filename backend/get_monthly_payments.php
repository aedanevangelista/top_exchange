<?php
// Turn on all error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set content type header
header('Content-Type: application/json');

try {
    // Correct path to db_connection.php based on project structure
    // Since it's in the same directory as this file
    require_once "db_connection.php";
    
    if (!isset($_GET['username']) || empty($_GET['username'])) {
        throw new Exception('Username is required');
    }

    $username = $_GET['username'];
    $year = $_GET['year'] ?? date('Y');

    // Check if the monthly_payments table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'monthly_payments'");
    if ($table_check->num_rows == 0) {
        // Create the table if it doesn't exist
        $create_table_sql = "
            CREATE TABLE IF NOT EXISTS monthly_payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(255) NOT NULL,
                month INT NOT NULL,
                year INT NOT NULL,
                total_amount DECIMAL(10, 2) NOT NULL DEFAULT 0,
                payment_status ENUM('Paid', 'Unpaid') NOT NULL DEFAULT 'Unpaid',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_month_year (username, month, year)
            )
        ";
        $conn->query($create_table_sql);
    }

    // Check if we have any records for this user
    $check_sql = "SELECT COUNT(*) as count FROM monthly_payments WHERE username = ? AND year = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("si", $username, $year);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $count = $check_result->fetch_assoc()['count'];

    // If no records exist, initialize records for all months
    if ($count == 0) {
        // First, get all completed orders for the user in the specified year
        $orders_sql = "SELECT 
                          MONTH(order_date) as month,
                          SUM(total_amount) as total_amount
                       FROM orders 
                       WHERE username = ? 
                       AND YEAR(order_date) = ?
                       AND status = 'Completed'
                       GROUP BY MONTH(order_date)";
        
        $orders_stmt = $conn->prepare($orders_sql);
        $orders_stmt->bind_param("si", $username, $year);
        $orders_stmt->execute();
        $orders_result = $orders_stmt->get_result();
        
        $monthly_totals = [];
        while ($row = $orders_result->fetch_assoc()) {
            $monthly_totals[$row['month']] = $row['total_amount'];
        }

        // Initialize all months
        for ($month = 1; $month <= 12; $month++) {
            $total = $monthly_totals[$month] ?? 0;
            $insert_sql = "INSERT IGNORE INTO monthly_payments 
                          (username, month, year, total_amount, payment_status)
                          VALUES (?, ?, ?, ?, 'Unpaid')";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("siid", $username, $month, $year, $total);
            $insert_stmt->execute();
        }
    }

    // Fetch all months
    $sql = "SELECT month, total_amount, payment_status 
            FROM monthly_payments 
            WHERE username = ? AND year = ?
            ORDER BY month";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $username, $year);
    $stmt->execute();
    $result = $stmt->get_result();

    $payments = [];
    while ($row = $result->fetch_assoc()) {
        $payments[] = [
            'month' => intval($row['month']),
            'total_amount' => floatval($row['total_amount']),
            'payment_status' => $row['payment_status']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $payments
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>