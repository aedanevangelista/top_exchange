<?php
// Check if session is already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include_once "db_connection.php"; // Include database connection

function checkRole($pageName) {
    global $conn;
    
    // Check if we're in admin context (used admin_ session variables)
    if (isset($_SESSION['admin_user_id'])) {
        $userRole = $_SESSION['admin_role'] ?? 'guest';
    } 
    // Check if we're in client context (used client_ session variables)
    else if (isset($_SESSION['client_user_id'])) {
        $userRole = $_SESSION['client_role'] ?? 'guest';
    } 
    // Fallback to traditional session for backwards compatibility
    else {
        $userRole = $_SESSION['role'] ?? 'guest';
    }

    // Fetch pages for the user role
    $stmt = $conn->prepare("SELECT pages FROM roles WHERE role_name = ? AND status = 'active'");
    $stmt->bind_param("s", $userRole);
    $stmt->execute();
    $stmt->bind_result($pages);
    $stmt->fetch();
    $stmt->close();

    // Check if the user has permission to access the page
    if (!$pages || !str_contains($pages, $pageName)) {
        header("Location: /public/pages/unauthorized.php");
        exit();
    }
}

function checkApiRole($requiredPage) {
    // Determine which session context we're in
    if (isset($_SESSION['admin_user_id'])) {
        $role = $_SESSION['admin_role'] ?? '';
    } else if (isset($_SESSION['client_user_id'])) {
        $role = $_SESSION['client_role'] ?? '';
    } else {
        $role = $_SESSION['role'] ?? '';
    }

    global $conn;
    
    // Check if the user has access to the required page
    $stmt = $conn->prepare("SELECT pages FROM roles WHERE role_name = ? AND status = 'active'");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $stmt->bind_result($pages);
    $stmt->fetch();
    $stmt->close();
    
    // Convert pages to an array and trim whitespace
    $allowedPages = array_map('trim', explode(',', $pages));
    
    if (!in_array($requiredPage, $allowedPages)) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }
    
    return true;
}

?>