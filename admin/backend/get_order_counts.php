<?php
include "db_connection.php";

$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

$sql = "SELECT COUNT(*) as order_count 
        FROM orders 
        WHERE YEAR(order_date) = ? 
        AND status = 'Completed'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $year);
$stmt->execute();
$result = $stmt->get_result();

$count = 0;
if ($row = $result->fetch_assoc()) {
    $count = $row['order_count'];
}

header('Content-Type: application/json');
echo json_encode($count);
?>