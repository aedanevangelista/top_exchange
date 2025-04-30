<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to add items to cart']);
    exit;
}

// Validate input
if (!isset($_POST['product_id']) || !isset($_POST['product_name']) || !isset($_POST['product_price'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid product data']);
    exit;
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$productId = $_POST['product_id'];
$productName = $_POST['product_name'];
$productPrice = floatval($_POST['product_price']);
$imagePath = $_POST['image_path'] ?? 'images/default-product.jpg';
$packaging = $_POST['packaging'] ?? '';

// Add or update item in cart
if (isset($_SESSION['cart'][$productId])) {
    // Increment quantity if product already in cart
    $_SESSION['cart'][$productId]['quantity'] += 1;
} else {
    // Add new product to cart
    $_SESSION['cart'][$productId] = [
        'name' => $productName,
        'price' => $productPrice,
        'quantity' => 1,
        'image_path' => $imagePath,
        'packaging' => $packaging
    ];
}

// Calculate total items in cart
$cartCount = array_sum(array_column($_SESSION['cart'], 'quantity'));

// Return success response
echo json_encode([
    'success' => true,
    'cart_count' => $cartCount,
    'message' => 'Product added to cart'
]);
?>