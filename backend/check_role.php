<?php
// Check if session is already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include "db_connection.php"; // Include database connection

function checkRole($page) {
    global $conn;
    $userRole = $_SESSION['role'] ?? 'guest'; // Default to 'guest' if no role is set

    // Fetch role_id for the user role
    $stmt = $conn->prepare("SELECT role_id FROM roles WHERE role_name = ?");
    $stmt->bind_param("s", $userRole);
    $stmt->execute();
    $stmt->bind_result($role_id);
    $stmt->fetch();
    $stmt->close();

    if (!$role_id) {
        // If role_id is not found, redirect to unauthorized page
        header("Location: /top_exchange/public/pages/unauthorized.php");
        exit();
    }

    // Check if the role has access to the page
    $stmt = $conn->prepare("SELECT COUNT(*) FROM role_permissions rp JOIN pages p ON rp.page_id = p.page_id WHERE rp.role_id = ? AND p.page_name = ?");
    $stmt->bind_param("is", $role_id, $page);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count == 0) {
        // If no access is found, redirect to unauthorized page
        header("Location: /top_exchange/public/pages/unauthorized.php");
        exit();
    }
}
?>