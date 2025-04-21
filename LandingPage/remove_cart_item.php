<?php
session_start();
header('Content-Type: application/json');

$productId = $_POST['product_id'];

if (isset($_SESSION['cart'][$productId])) {
    unset($_SESSION['cart'][$productId]);
    echo json_encode([
        'success' => true,
        'cart_count' => array_sum(array_column($_SESSION['cart'], 'quantity'))
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Product not found in cart'
    ]);
}
?>