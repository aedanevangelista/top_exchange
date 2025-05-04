<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to update your cart']);
    exit;
}

// Check if required parameters are present
if (!isset($_POST['action']) || ($_POST['action'] !== 'remove' && !isset($_POST['product_id']))) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$action = $_POST['action'];
$productId = $_POST['product_id'] ?? null;

// Initialize database connection
require_once 'db_connection.php';

if ($action === 'update') {
    $quantity = intval($_POST['quantity']);
    
    // Validate quantity
    if ($quantity < 1) {
        echo json_encode(['success' => false, 'message' => 'Quantity must be at least 1']);
        exit;
    }
    
    // Update quantity in cart
    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId]['quantity'] = $quantity;
    }
} elseif ($action === 'remove') {
    // Remove item from cart
    if (isset($_SESSION['cart'][$productId])) {
        unset($_SESSION['cart'][$productId]);
    }
}

// Calculate new totals
$subtotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$cartCount = array_sum(array_column($_SESSION['cart'], 'quantity'));

echo json_encode([
    'success' => true,
    'subtotal' => $subtotal,
    'cartCount' => $cartCount
]);

$conn->close();
?>