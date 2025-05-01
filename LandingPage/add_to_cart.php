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
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

// Validate quantity
if ($quantity < 1) {
    $quantity = 1;
} elseif ($quantity > 100) {
    $quantity = 100;
}

// Add or update item in cart
if (isset($_SESSION['cart'][$productId])) {
    // Update quantity if product already in cart
    $_SESSION['cart'][$productId]['quantity'] += $quantity;

    // Make sure we don't exceed the maximum quantity
    if ($_SESSION['cart'][$productId]['quantity'] > 100) {
        $_SESSION['cart'][$productId]['quantity'] = 100;
    }
} else {
    // Add new product to cart
    $_SESSION['cart'][$productId] = [
        'name' => $productName,
        'price' => $productPrice,
        'quantity' => $quantity,
        'image_path' => $imagePath,
        'packaging' => $packaging
    ];
}

// Calculate total items in cart
$cartCount = array_sum(array_column($_SESSION['cart'], 'quantity'));

// Return success response
$response = [
    'success' => true,
    'cart_count' => $cartCount,
    'message' => 'Product added to cart',
    'product_id' => $productId,
    'quantity_added' => $quantity,
    'total_quantity' => $_SESSION['cart'][$productId]['quantity']
];

// Debug information
if (isset($_GET['debug'])) {
    $response['debug'] = [
        'session_id' => session_id(),
        'cart' => $_SESSION['cart'],
        'post_data' => $_POST
    ];
}

echo json_encode($response);
?>