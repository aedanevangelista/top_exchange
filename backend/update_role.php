<?php
session_start();
include "db_connection.php";

$response = array();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $role_id = $_POST['role_id'] ?? null;
    $role_name = $_POST['role_name'] ?? '';
    $page_ids = $_POST['page_ids'] ?? [];

    if ($role_id && $role_name) {
        // Update existing role
        $stmt = $conn->prepare("UPDATE roles SET role_name = ? WHERE role_id = ?");
        $stmt->bind_param("si", $role_name, $role_id);
        if ($stmt->execute()) {
            // Delete existing permissions for the role
            $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $stmt->bind_param("i", $role_id);
            $stmt->execute();

            // Insert new permissions
            $stmt = $conn->prepare("INSERT INTO role_permissions (role_id, page_id) VALUES (?, ?)");
            foreach ($page_ids as $page_id) {
                $stmt->bind_param("ii", $role_id, $page_id);
                $stmt->execute();
            }
            $response['success'] = true;
        } else {
            $response['success'] = false;
            $response['message'] = "Failed to update role.";
        }
        $stmt->close();
    } else {
        $response['success'] = false;
        $response['message'] = "Role ID and Role Name are required.";
    }
} else {
    $response['success'] = false;
    $response['message'] = "Invalid request method.";
}

echo json_encode($response);
?>