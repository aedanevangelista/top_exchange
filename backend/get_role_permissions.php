<?php
session_start();
include "db_connection.php";

// Check if role_id is provided
if (!isset($_GET['role_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Role ID is required"]);
    exit();
}

$role_id = $_GET['role_id'];

// Fetch role details
$stmt = $conn->prepare("SELECT role_name FROM roles WHERE role_id = ?");
$stmt->bind_param("i", $role_id);
$stmt->execute();
$stmt->bind_result($role_name);
$stmt->fetch();
$stmt->close();

// Fetch associated pages
$stmt = $conn->prepare("SELECT page_id FROM role_permissions WHERE role_id = ?");
$stmt->bind_param("i", $role_id);
$stmt->execute();
$result = $stmt->get_result();
$pages = [];
while ($row = $result->fetch_assoc()) {
    $pages[] = $row['page_id'];
}
$stmt->close();

echo json_encode([
    "role_name" => $role_name,
    "pages" => $pages
]);
?>