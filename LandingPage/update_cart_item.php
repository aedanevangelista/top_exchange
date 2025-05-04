<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to update cart']);
    exit;
}

// Validate input
if (!isset($_POST['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request: Missing product ID']);
    exit;
}

$productId = $_POST['product_id'];

// Check if product exists in cart
if (!isset($_SESSION['cart'][$productId])) {
    echo json_encode(['success' => false, 'message' => 'Product not found in cart']);
    exit;
}

// Handle manual quantity update
if (isset($_POST['quantity']) && isset($_POST['manual_update'])) {
    $newQuantity = intval($_POST['quantity']);

    // Validate quantity
    if ($newQuantity < 1) {
        echo json_encode(['success' => false, 'message' => 'Quantity must be at least 1']);
        exit;
    }

    // Set the new quantity directly
    $_SESSION['cart'][$productId]['quantity'] = $newQuantity;
}
// Handle increment/decrement
else if (isset($_POST['quantity_change'])) {
    $quantityChange = intval($_POST['quantity_change']);
    // Update quantity
    $_SESSION['cart'][$productId]['quantity'] += $quantityChange;
}
else {
    echo json_encode(['success' => false, 'message' => 'Invalid request: Missing quantity parameters']);
    exit;
}

// Remove item if quantity reaches zero
if ($_SESSION['cart'][$productId]['quantity'] <= 0) {
    unset($_SESSION['cart'][$productId]);
}

// Calculate total items in cart
$cartCount = 0;
foreach ($_SESSION['cart'] as $item) {
    if (isset($item['quantity']) && is_numeric($item['quantity'])) {
        $cartCount += (int)$item['quantity'];
    }
}

// Return success response
echo json_encode([
    'success' => true,
    'cart_count' => $cartCount,
    'message' => 'Cart updated'
]);
?>