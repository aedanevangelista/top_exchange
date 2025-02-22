<?php
header('Content-Type: application/json');

include "../../backend/db_connection.php";

$sql = "SELECT product_id, category, item_description, packaging, price, stock_quantity FROM products ORDER BY category, item_description";
$result = $conn->query($sql);

$inventory = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $inventory[] = $row;
    }
}

echo json_encode($inventory);
?>