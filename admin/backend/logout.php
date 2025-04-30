<?php
session_start();
session_destroy(); // Clear session data
header("Location: /admin/public/login.php");
exit();
?>
