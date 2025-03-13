<?php
include "db_connection.php";

$sql = "SELECT DISTINCT category FROM products";
$result = $conn->query($sql);

$categories = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}

header('Content-Type: application/json');
echo json_encode($categories);
?>