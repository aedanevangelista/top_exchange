<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$productId = $_POST['product_id'];
$productName = $_POST['product_name'];
$productPrice = $_POST['product_price'];
$imagePath = $_POST['image_path'];
$packaging = $_POST['packaging'];

// Initialize item if not exists
if (!isset($_SESSION['cart'][$productId])) {
    $_SESSION['cart'][$productId] = [
        'name' => $productName,
        'price' => $productPrice,
        'quantity' => 0,
        'image_path' => $imagePath,
        'packaging' => $packaging
    ];
}

// Increase quantity
$_SESSION['cart'][$productId]['quantity']++;

echo json_encode([
    'success' => true,
    'cart_count' => array_sum(array_column($_SESSION['cart'], 'quantity'))
]);
?>