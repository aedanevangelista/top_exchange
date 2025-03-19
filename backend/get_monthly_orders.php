<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    require_once "../db_connection.php";

    if (!isset($_GET['username']) || empty($_GET['username'])) {
        throw new Exception('Username is required');
    }

    $username = $_GET['username'];
    $year = $_GET['year'] ?? date('Y');

    // First, create table if it doesn't exist
    $createTableSQL = "CREATE TABLE IF NOT EXISTS `monthly_payments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(255) NOT NULL,
        `month` int(2) NOT NULL,
        `year` int(4) NOT NULL,
        `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
        `payment_status` enum('Paid','Unpaid') NOT NULL DEFAULT 'Unpaid',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_month_user` (`username`, `month`, `year`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    
    $conn->query($createTableSQL);

    // Check if records exist for all months
    $checkSql = "SELECT COUNT(*) as count 
                 FROM monthly_payments 
                 WHERE username = ? AND year = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("si", $username, $year);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $count = $checkResult->fetch_assoc()['count'];

    // If we don't have 12 months of records, create them
    if ($count < 12) {
        // Get total orders amount for each month
        $ordersSql = "SELECT 
                        MONTH(order_date) as month,
                        SUM(total_amount) as total
                     FROM orders 
                     WHERE username = ? 
                     AND YEAR(order_date) = ?
                     AND status = 'Completed'
                     GROUP BY MONTH(order_date)";
        
        $ordersStmt = $conn->prepare($ordersSql);
        $ordersStmt->bind_param("si", $username, $year);
        $ordersStmt->execute();
        $ordersResult = $ordersStmt->get_result();
        
        $monthlyTotals = array_fill(1, 12, 0); // Initialize all months with 0
        while ($row = $ordersResult->fetch_assoc()) {
            $monthlyTotals[$row['month']] = $row['total'];
        }

        // Insert records for all months
        $insertSql = "INSERT IGNORE INTO monthly_payments 
                      (username, month, year, total_amount, payment_status)
                      VALUES (?, ?, ?, ?, 'Unpaid')";
        $insertStmt = $conn->prepare($insertSql);
        
        for ($month = 1; $month <= 12; $month++) {
            $total = $monthlyTotals[$month];
            $insertStmt->bind_param("siid", $username, $month, $year, $total);
            $insertStmt->execute();
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
        'success' => false,
        'error' => true,
        'message' => 'Error loading payment history: ' . $e->getMessage()
    ]);
}
?>