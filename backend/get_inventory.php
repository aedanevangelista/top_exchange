<?php
include(__DIR__ . '/db_connection.php');

$sql = "SELECT product_id, item_description AS product_name, category, packaging, price, stock_quantity FROM products";
$result = $conn->query($sql);

// Check if query execution was successful
if (!$result) {
    die("Query failed: " . $conn->error);
}

$inventory = [];
while ($row = $result->fetch_assoc()) {
    $inventory[] = $row;
}

echo json_encode($inventory);
?>
