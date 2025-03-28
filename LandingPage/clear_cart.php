<?php
session_start();

unset($_SESSION['cart']);
$_SESSION['cart'] = [];

echo json_encode([
    'success' => true,
    'cart_count' => 0,
    'message' => 'Cart cleared successfully'
]);
?>