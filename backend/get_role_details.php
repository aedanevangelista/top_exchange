<?php
include "../../backend/db_connection.php";

if (isset($_GET['role_id'])) {
    $role_id = $_GET['role_id'];

    // Get role details
    $stmt = $conn->prepare("SELECT role_name FROM roles WHERE role_id = ?");
    $stmt->bind_param("i", $role_id);
    $stmt->execute();
    $stmt->bind_result($role_name);
    $stmt->fetch();
    $stmt->close();

    // Get page permissions
    $stmt = $conn->prepare("SELECT page_id FROM role_permissions WHERE role_id = ?");
    $stmt->bind_param("i", $role_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $page_ids = [];
    while ($row = $result->fetch_assoc()) {
        $page_ids[] = $row['page_id'];
    }
    $stmt->close();

    // Return JSON response
    echo json_encode([
        'success' => true,
        'role_id' => $role_id,
        'role_name' => $role_name,
        'page_ids' => $page_ids
    ]);
} else {
    echo json_encode(['success' => false]);
}
?>
