<?php
session_start();

if (!isset($_SESSION['cart']) || empty($_POST['product_id'])) {
    die(json_encode(['success' => false, 'message' => 'Invalid request']));
}

$product_id = $_POST['product_id'];
$change = intval($_POST['change']);

foreach ($_SESSION['cart'] as &$item) {
    if ($item['product_id'] == $product_id) {
        $newQuantity = $item['quantity'] + $change;
        
        // Ensure quantity doesn't go below 1
        if ($newQuantity < 1) {
            $newQuantity = 1;
        }
        
        $item['quantity'] = $newQuantity;
        
        echo json_encode([
            'success' => true,
            'cart_count' => count($_SESSION['cart']),
            'new_quantity' => $newQuantity
        ]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Product not found in cart']);
?>