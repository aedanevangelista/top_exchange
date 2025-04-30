<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to modify cart']);
    exit;
}

// Validate input
if (!isset($_POST['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

$productId = $_POST['product_id'];

// Remove item from cart if exists
if (isset($_SESSION['cart'][$productId])) {
    unset($_SESSION['cart'][$productId]);
}

// Calculate total items in cart
$cartCount = array_sum(array_column($_SESSION['cart'], 'quantity'));

// Return success response
echo json_encode([
    'success' => true,
    'cart_count' => $cartCount,
    'message' => 'Item removed from cart'
]);
?>