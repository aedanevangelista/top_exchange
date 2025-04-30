<?php
include "db_connection.php";

// Get available years from orders table (only for completed orders)
$sql = "SELECT DISTINCT YEAR(order_date) as year 
        FROM orders 
        WHERE status = 'Completed'
        ORDER BY year DESC";

$result = $conn->query($sql);

$years = array();
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $years[] = $row['year'];
    }
}

// Debug log
error_log("Available years: " . json_encode($years));

header('Content-Type: application/json');
echo json_encode($years);
?>