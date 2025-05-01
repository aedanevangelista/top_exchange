<?php
header('Content-Type: application/json');
include "db_connection.php";

// Get the year from the request, default to current year
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

try {
    // Count orders for the selected year with status = 'Completed'
    $query = "SELECT COUNT(*) as count FROM orders WHERE YEAR(order_date) = ? AND status = 'Completed'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(intval($row['count']));
    } else {
        echo json_encode(0);
    }
} catch (Exception $e) {
    // Log the error and return 0
    error_log('Error in get_order_counts.php: ' . $e->getMessage());
    echo json_encode(0);
}
?>