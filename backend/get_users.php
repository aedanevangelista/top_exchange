<?php
include "db_connection.php";

$status = $_GET['status'];

$sql = "SELECT id, username, status FROM clients_accounts";
if ($status == 'active') {
    $sql .= " WHERE status = 'Active'";
} else if ($status == 'inactive') {
    $sql .= " WHERE status != 'Active'";
}
$sql .= " ORDER BY status DESC, username ASC";

$result = $conn->query($sql);

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

header('Content-Type: application/json');
echo json_encode($users);
?>