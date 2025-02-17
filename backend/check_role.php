<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Function to check if the user's role is allowed to access the page.
 * 
 * @param array $allowedRoles Array of roles that are allowed to access the page.
 */
function checkRole($allowedRoles) {
    // Check if the user's role is set and if it is in the allowed roles array
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
        // Redirect to login page if the user is not authorized
        header("Location: ../login.php");
        exit();
    }
}
