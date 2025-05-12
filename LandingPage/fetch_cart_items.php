<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to view cart']);
    exit;
}

// Get cart items
$cartItems = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];

// Prepare response data
$response = [
    'success' => true,
    'cart_items' => [],
    'total_items' => 0,
    'subtotal' => 0
];

// Format cart items for response
foreach ($cartItems as $productId => $item) {
    // Ensure all required fields exist
    if (!isset($item['name']) || !isset($item['price']) || !isset($item['quantity'])) {
        continue; // Skip invalid items
    }

    // Ensure quantity is a valid number
    $quantity = max(1, intval($item['quantity']));

    // Ensure price is a valid number
    $price = floatval($item['price']);

    // Create a valid cart item
    $response['cart_items'][] = [
        'product_id' => $productId,
        'name' => $item['name'],
        'price' => $price,
        'quantity' => $quantity,
        'image_path' => isset($item['image_path']) ? $item['image_path'] : '/LandingPage/images/default-product.jpg',
        'packaging' => isset($item['packaging']) ? $item['packaging'] : '',
        'is_preorder' => isset($item['is_preorder']) ? (bool)$item['is_preorder'] : false
    ];

    $response['subtotal'] += $price * $quantity;
}

// Calculate total items
$response['total_items'] = 0;
if (!empty($cartItems)) {
    foreach ($cartItems as $item) {
        if (isset($item['quantity']) && is_numeric($item['quantity'])) {
            $response['total_items'] += (int)$item['quantity'];
        }
    }
}

// Add debug information
$response['debug'] = [
    'session_cart' => $cartItems,
    'formatted_cart' => $response['cart_items']
];

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>