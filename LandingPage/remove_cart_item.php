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

// Debug information
$debug = [
    'request' => $_POST,
    'cart_before' => $_SESSION['cart'],
    'product_id' => $productId
];

// Remove item from cart if exists
if (isset($_SESSION['cart'][$productId])) {
    unset($_SESSION['cart'][$productId]);
    $debug['action'] = 'Removed item with exact key match';
} else {
    // Try to find the product by iterating through the cart
    $found = false;
    foreach ($_SESSION['cart'] as $key => $item) {
        if ($key == $productId || (isset($item['product_id']) && $item['product_id'] == $productId)) {
            unset($_SESSION['cart'][$key]);
            $found = true;
            $debug['action'] = 'Removed item by iterating through cart';
            break;
        }
    }

    if (!$found) {
        $debug['action'] = 'Item not found in cart';
    }
}

// Calculate total items in cart
$cartCount = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        if (isset($item['quantity']) && is_numeric($item['quantity'])) {
            $cartCount += (int)$item['quantity'];
        }
    }
}

$debug['cart_after'] = $_SESSION['cart'];
$debug['cart_count'] = $cartCount;

// Return success response
echo json_encode([
    'success' => true,
    'cart_count' => $cartCount,
    'message' => 'Item removed from cart',
    'debug' => $debug
]);
?>