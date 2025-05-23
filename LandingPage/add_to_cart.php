<?php
// Start the session if it hasn't been started already
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to add items to cart']);
    exit;
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Debug received data
$debug_data = [
    'post' => $_POST,
    'get' => $_GET,
    'session' => isset($_SESSION) ? array_keys($_SESSION) : 'No session'
];

// Validate input - with more detailed error message
$required_fields = ['product_id', 'product_name', 'price'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || $_POST[$field] === '') {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid product data: Missing ' . implode(', ', $missing_fields),
        'debug' => $debug_data
    ]);
    exit;
}

$productId = $_POST['product_id'];
$productName = $_POST['product_name'];
$productPrice = floatval($_POST['price']); // Changed from product_price to price to match the form data
$imagePath = isset($_POST['image_path']) && !empty($_POST['image_path']) ? $_POST['image_path'] : '/LandingPage/images/default-product.jpg';
$packaging = $_POST['packaging'] ?? '';
$category = $_POST['category'] ?? '';
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
$isPreOrder = isset($_POST['is_preorder']) ? filter_var($_POST['is_preorder'], FILTER_VALIDATE_BOOLEAN) : false;

// Log the data being added to the cart
error_log("Adding to cart: Product ID: $productId, Name: $productName, Price: $productPrice, Image: $imagePath, Quantity: $quantity");

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
        'packaging' => $packaging,
        'category' => $category,
        'is_preorder' => $isPreOrder
    ];
}

// Calculate total items in cart
$cartCount = array_sum(array_column($_SESSION['cart'], 'quantity'));

// Return success response
$response = [
    'success' => true,
    'cart_count' => $cartCount,
    'message' => $isPreOrder ? 'Pre-order added to cart' : 'Product added to cart',
    'product_id' => $productId,
    'quantity_added' => $quantity,
    'total_quantity' => $_SESSION['cart'][$productId]['quantity'],
    'is_preorder' => $isPreOrder
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