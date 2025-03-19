<?php
include "db_connection.php";

$user_id = $_GET['user_id'];

$sql = "SELECT YEAR(order_date) as year, MONTH(order_date) as month, SUM(total_amount) as total_amount, status FROM orders WHERE username = (SELECT username FROM clients_accounts WHERE id = ?) GROUP BY year, month, status ORDER BY year DESC, month DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$years = [];
while ($row = $result->fetch_assoc()) {
    $year = $row['year'];
    if (!isset($years[$year])) {
        $years[$year] = ['year' => $year, 'transactions' => []];
    }
    $years[$year]['transactions'][] = [
        'id' => $row['id'],
        'month' => $row['month'],
        'total_amount' => $row['total_amount'],
        'status' => $row['status']
    ];
}

header('Content-Type: application/json');
echo json_encode(['years' => array_values($years)]);
?>