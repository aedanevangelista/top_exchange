<?php
session_start();
// Clear only admin session variables
unset($_SESSION['admin_user_id']);
unset($_SESSION['admin_username']);
unset($_SESSION['admin_role']);
// Redirect to admin login
header("Location: login.php");
exit();
?>