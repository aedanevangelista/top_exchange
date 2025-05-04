<?php
include "db_connection.php";

$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Debug log
error_log("Fetching orders for year: " . $year);

$sql = "SELECT username, COUNT(*) as order_count 
        FROM orders 
        WHERE YEAR(order_date) = ? 
        AND status = 'Completed'
        GROUP BY username
        ORDER BY order_count DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $year);
$stmt->execute();
$result = $stmt->get_result();

$data = array();
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $data[] = array(
            'username' => $row['username'],
            'count' => intval($row['order_count'])
        );
    }
}

// Debug log
error_log("Data found: " . json_encode($data));

header('Content-Type: application/json');
echo json_encode($data);
?>