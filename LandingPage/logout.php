<?php
session_start();
// Clear only client session variables
unset($_SESSION['client_user_id']);
unset($_SESSION['client_username']);
unset($_SESSION['client_role']);
// Redirect to client login
header("Location: login.php");
exit();
?>