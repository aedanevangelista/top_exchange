<?php
session_start();

// Clear admin session variables if they exist
if (isset($_SESSION['admin_user_id'])) {
    unset($_SESSION['admin_user_id']);
    unset($_SESSION['admin_username']);
    unset($_SESSION['admin_role']);
    
    // Redirect to admin login
    header("Location: /public/login.php");
    exit();
}

// Clear client session variables if they exist
if (isset($_SESSION['client_user_id'])) {
    unset($_SESSION['client_user_id']);
    unset($_SESSION['client_username']);
    unset($_SESSION['client_role']);
    
    // Redirect to client login/landing page
    header("Location: /LandingPage/index.php");
    exit();
}

// Fallback to clear traditional session variables (for backward compatibility)
unset($_SESSION['user_id']);
unset($_SESSION['username']);
unset($_SESSION['role']);

// Determine where to redirect based on referrer
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (strpos($referer, 'public') !== false) {
    header("Location: /public/login.php");
} else {
    header("Location: /LandingPage/index.php");
}
exit();
?>