<?php
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'].'/backend/db_connection.php');

if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

$sql = "SELECT product_id, category, item_description, packaging, price, stock_quantity FROM products ORDER BY category, item_description";
$result = $conn->query($sql);

$inventory = [];

if ($result) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $inventory[] = $row;
        }
    }
    echo json_encode($inventory);
} else {
    echo json_encode(['error' => 'Error executing query: ' . $conn->error]);
}
?>