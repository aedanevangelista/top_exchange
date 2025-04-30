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
    $response['cart_items'][] = [
        'product_id' => $productId,
        'name' => $item['name'],
        'price' => $item['price'],
        'quantity' => $item['quantity'],
        'image_path' => $item['image_path'],
        'packaging' => $item['packaging']
    ];
    
    $response['subtotal'] += $item['price'] * $item['quantity'];
}

$response['total_items'] = array_sum(array_column($cartItems, 'quantity'));

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>