<?php
// Check if session is already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function checkRole($allowedRoles) {
    $userRole = $_SESSION['role'] ?? 'guest'; // Default to 'guest' if no role is set
    if (!in_array($userRole, $allowedRoles)) {
        header("Location: /top_exchange/public/pages/unauthorized.php");
        exit();
    }
}
?>