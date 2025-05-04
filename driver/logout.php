<?php
session_start(); // Start the session to access session variables

// Unset all of the session variables specific to the driver login
unset($_SESSION['driver_id']);
unset($_SESSION['driver_username']);
unset($_SESSION['driver_name']);
unset($_SESSION['driver_logged_in']);

// Optional: If you want to destroy the *entire* session (including any potential admin session data if they share the same session), use session_destroy(). Be careful if admin and driver might log in in the same browser session.
// session_destroy();

// Redirect the user to the driver login page
header("Location: login.php");
exit(); // Ensure no further code is executed after redirection
?>