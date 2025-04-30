<?php
// This code assumes that session has been already started

// Function to check if the user has access to a specific page
function checkRole($pageName) {
    if (!isset($_SESSION['admin_user_id']) || !isset($_SESSION['admin_role'])) {
        // Redirect to login page
        header("Location: ../login.php");
        exit();
    }

    $role = $_SESSION['admin_role'];
    
    // Check if the role has access to the specified page
    global $conn;
    $stmt = $conn->prepare("SELECT pages FROM roles WHERE role_name = ?");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $pages = explode(', ', $row['pages']);
        
        if (!in_array($pageName, $pages)) {
            // Redirect to access denied page
            header("Location: ../access_denied.php");
            exit();
        }
    } else {
        // Role not found or has no permissions, redirect to login
        header("Location: ../login.php");
        exit();
    }
}

// Helper function to check if user has access to a page without redirecting
function hasAccess($pageName) {
    if (!isset($_SESSION['admin_user_id']) || !isset($_SESSION['admin_role'])) {
        return false;
    }

    $role = $_SESSION['admin_role'];
    
    // Check if the role has access to the specified page
    global $conn;
    $stmt = $conn->prepare("SELECT pages FROM roles WHERE role_name = ?");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $pages = explode(', ', $row['pages']);
        
        return in_array($pageName, $pages);
    }
    
    return false;
}
?>