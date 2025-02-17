<?php
session_start();
session_destroy(); // Clear session data
header("Location: http://localhost/top_exchange/public/login.php");
exit();
?>
