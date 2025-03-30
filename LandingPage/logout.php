<?php
session_start();

// Only unset client session variables
unset($_SESSION['client_user_id']);
unset($_SESSION['client_username']);
unset($_SESSION['client_role']);
unset($_SESSION['client_email']);
unset($_SESSION['cart']); // If you're using a cart for clients

// Redirect to login page
header("Location: login.php");
exit();
?>