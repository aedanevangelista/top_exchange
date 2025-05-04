<?php
session_start();
session_destroy(); // Clear session data
header("Location: /public/login.php");
exit();
?>
