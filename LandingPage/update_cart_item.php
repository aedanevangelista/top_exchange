<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to update cart']);
    exit;
}

// Validate input
if (!isset($_POST['product_id']) || !isset($_POST['quantity_change'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$productId = $_POST['product_id'];
$quantityChange = intval($_POST['quantity_change']);

// Check if product exists in cart
if (!isset($_SESSION['cart'][$productId])) {
    echo json_encode(['success' => false, 'message' => 'Product not found in cart']);
    exit;
}

// Update quantity
$_SESSION['cart'][$productId]['quantity'] += $quantityChange;

// Remove item if quantity reaches zero
if ($_SESSION['cart'][$productId]['quantity'] <= 0) {
    unset($_SESSION['cart'][$productId]);
}

// Calculate total items in cart
$cartCount = array_sum(array_column($_SESSION['cart'], 'quantity'));

// Return success response
echo json_encode([
    'success' => true,
    'cart_count' => $cartCount,
    'message' => 'Cart updated'
]);
?>