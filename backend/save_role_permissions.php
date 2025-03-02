<?php
include "db_connection.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $role_id = intval($_POST['role_id']);
    $page_ids = $_POST['page_ids']; // Array of page IDs

    // Delete existing permissions for the role
    $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
    $stmt->bind_param("i", $role_id);
    $stmt->execute();
    $stmt->close();

    // Insert new permissions
    $stmt = $conn->prepare("INSERT INTO role_permissions (role_id, page_id) VALUES (?, ?)");
    foreach ($page_ids as $page_id) {
        $stmt->bind_param("ii", $role_id, $page_id);
        $stmt->execute();
    }
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Permissions updated successfully.']);
}
?>