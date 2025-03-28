<?php
session_start();
header('Content-Type: application/json');

$count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
echo json_encode(['count' => $count]);
?>