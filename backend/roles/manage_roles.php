<?php
session_start();
include "../db_connection.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $roleId = $_POST['role_id'] ?? '';
    $roleName = trim($_POST['role_name'] ?? '');
    $pageIds = isset($_POST['page_ids']) ? implode(", ", $_POST['page_ids']) : '';

    // Prevent modifying the 'admin' role
    if ($roleName === 'admin') {
        header("Location: ../../public/pages/user_roles.php?error=restricted");
        exit;
    }

    // Check if role name already exists (except for the current role being edited)
    $stmt = $conn->prepare("SELECT role_id FROM roles WHERE role_name = ? AND role_id != ?");
    $stmt->bind_param("si", $roleName, $roleId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        header("Location: ../../public/pages/user_roles.php?error=duplicate");
        exit;
    }
    $stmt->close();

    if ($action === 'add') {
        // Add new role
        $stmt = $conn->prepare("INSERT INTO roles (role_name, pages, status) VALUES (?, ?, 'active')");
        $stmt->bind_param("ss", $roleName, $pageIds);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'edit' && !empty($roleId)) {
        // Edit existing role
        $stmt = $conn->prepare("UPDATE roles SET role_name = ?, pages = ? WHERE role_id = ?");
        $stmt->bind_param("ssi", $roleName, $pageIds, $roleId);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'archive' && !empty($roleId)) {
        // Archive role
        $stmt = $conn->prepare("UPDATE roles SET status = 'inactive' WHERE role_id = ?");
        $stmt->bind_param("i", $roleId);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'activate' && !empty($roleId)) {
        // Activate role
        $stmt = $conn->prepare("UPDATE roles SET status = 'active' WHERE role_id = ?");
        $stmt->bind_param("i", $roleId);
        $stmt->execute();
        $stmt->close();
    }

    // Redirect back
    header("Location: ../../public/pages/user_roles.php");
    exit;
}
?>