<?php
session_start();
header('Content-Type: application/json');

$productId = $_POST['product_id'];
$change = (int)$_POST['quantity_change'];

if (isset($_SESSION['cart'][$productId])) {
    $_SESSION['cart'][$productId]['quantity'] += $change;
    
    // Remove if quantity is zero or less
    if ($_SESSION['cart'][$productId]['quantity'] <= 0) {
        unset($_SESSION['cart'][$productId]);
    }
    
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