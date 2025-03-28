<?php
session_start();
header('Content-Type: application/json');

$cartItems = [];
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $productId => $item) {
        $cartItems[] = [
            'product_id' => $productId,
            'name' => $item['name'],
            'price' => $item['price'],
            'quantity' => $item['quantity'],
            'image_path' => $item['image_path'],
            'packaging' => $item['packaging']
        ];
    }
}

echo json_encode(['cart_items' => $cartItems]);
?>