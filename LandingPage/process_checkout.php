<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "top_exchange");

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Check if cart exists and has items
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    die(json_encode(['success' => false, 'message' => 'Your cart is empty']));
}

// Get user ID from session
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'User not logged in']));
}

$user_id = $_SESSION['user_id'];
$total_amount = floatval($_POST['total_amount']);
$special_instructions = isset($_POST['special_instructions']) ? $_POST['special_instructions'] : '';

// Start transaction
$conn->begin_transaction();

try {
    // 1. Create order
    $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, status, special_instructions, order_date) 
                           VALUES (?, ?, 'Pending', ?, NOW())");
    $stmt->bind_param("ids", $user_id, $total_amount, $special_instructions);
    $stmt->execute();
    $order_id = $conn->insert_id;
    
    // 2. Add order items
    $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) 
                           VALUES (?, ?, ?, ?)");
    
    foreach ($_SESSION['cart'] as $item) {
        $stmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
        $stmt->execute();
    }
    
    // 3. Clear cart
    unset($_SESSION['cart']);
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'message' => 'Order placed successfully'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Error processing order: ' . $e->getMessage()
    ]);
}

$conn->close();
?>