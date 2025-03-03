<?php
include "db_connection.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $role_id = intval($_POST['role_id']);
    $role_name = trim($_POST['role_name']); // Sanitize input
    $page_ids = isset($_POST['page_ids']) ? $_POST['page_ids'] : []; // Ensure it's an array

    // ✅ Ensure we update the role instead of inserting a new one
    $stmt = $conn->prepare("UPDATE roles SET role_name = ? WHERE role_id = ?");
    $stmt->bind_param("si", $role_name, $role_id);
    $stmt->execute();
    $stmt->close();

    // ✅ Delete old permissions
    $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
    $stmt->bind_param("i", $role_id);
    $stmt->execute();
    $stmt->close();

    // ✅ Insert new permissions if pages are selected
    if (!empty($page_ids)) {
        $stmt = $conn->prepare("INSERT INTO role_permissions (role_id, page_id) VALUES (?, ?)");
        foreach ($page_ids as $page_id) {
            $stmt->bind_param("ii", $role_id, $page_id);
            $stmt->execute();
        }
        $stmt->close();
    }

    echo json_encode(['success' => true, 'message' => 'Role updated successfully.']);
}
?>
