<?php
session_start();

if (!isset($_SESSION['cart']) || empty($_POST['product_id'])) {
    die(json_encode(['success' => false, 'message' => 'Invalid request']));
}

$product_id = $_POST['product_id'];

foreach ($_SESSION['cart'] as $key => $item) {
    if ($item['product_id'] == $product_id) {
        unset($_SESSION['cart'][$key]);
        $_SESSION['cart'] = array_values($_SESSION['cart']); // Re-index array
        
        echo json_encode([
            'success' => true,
            'cart_count' => count($_SESSION['cart']),
            'message' => 'Product removed from cart'
        ]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Product not found in cart']);
?>