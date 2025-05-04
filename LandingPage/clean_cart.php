<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to clean cart']);
    exit;
}

// Initialize response
$response = [
    'success' => true,
    'message' => 'Cart cleaned successfully',
    'removed_items' => 0,
    'cart_count_before' => 0,
    'cart_count_after' => 0
];

// Check if cart exists
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
    echo json_encode($response);
    exit;
}

// Calculate cart count before cleaning
$cartCountBefore = 0;
foreach ($_SESSION['cart'] as $item) {
    if (isset($item['quantity']) && is_numeric($item['quantity'])) {
        $cartCountBefore += (int)$item['quantity'];
    }
}
$response['cart_count_before'] = $cartCountBefore;

// Clean the cart by removing invalid items
$removedItems = 0;
foreach ($_SESSION['cart'] as $productId => $item) {
    // Check if item has all required fields
    if (!isset($item['name']) || !isset($item['price']) || !isset($item['quantity'])) {
        unset($_SESSION['cart'][$productId]);
        $removedItems++;
        continue;
    }
    
    // Ensure price is a valid number
    if (!is_numeric($item['price'])) {
        $_SESSION['cart'][$productId]['price'] = 0;
    }
    
    // Ensure quantity is a valid number
    if (!is_numeric($item['quantity']) || $item['quantity'] <= 0) {
        $_SESSION['cart'][$productId]['quantity'] = 1;
    }
}

// Calculate cart count after cleaning
$cartCountAfter = 0;
foreach ($_SESSION['cart'] as $item) {
    if (isset($item['quantity']) && is_numeric($item['quantity'])) {
        $cartCountAfter += (int)$item['quantity'];
    }
}
$response['cart_count_after'] = $cartCountAfter;
$response['removed_items'] = $removedItems;

// Return response
echo json_encode($response);
?>
