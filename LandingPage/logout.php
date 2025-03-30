<?php
session_start();
// Clear only client session variables
unset($_SESSION['user_id']);
unset($_SESSION['username']);
unset($_SESSION['role']);
// Redirect to client login
header("Location: login.php");
exit();
?>