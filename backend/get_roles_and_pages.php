<?php
include "db_connection.php";

// Fetch roles
$roles = [];
$roleQuery = "SELECT role_id, role_name FROM roles";
$result = $conn->query($roleQuery);
while ($row = $result->fetch_assoc()) {
    $roles[] = $row;
}

// Fetch pages
$pages = [];
$pageQuery = "SELECT page_id, page_name FROM pages";
$result = $conn->query($pageQuery);
while ($row = $result->fetch_assoc()) {
    $pages[] = $row;
}

echo json_encode(['roles' => $roles, 'pages' => $pages]);
?>