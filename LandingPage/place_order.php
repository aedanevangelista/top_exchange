<?php
session_start();
header('Content-Type: application/json');

// Validate cart
if (empty($_SESSION['cart'])) {
    echo json_encode(['success' => false, 'message' => 'Your cart is empty']);
    exit;
}

// Validate input
if (empty($_POST['address']) || empty($_POST['contact'])) {
    echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
    exit;
}

// Connect to database
$conn = new mysqli("localhost", "root", "", "top_exchange");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // 1. Create order
    $stmt = $conn->prepare("INSERT INTO orders (
        user_id, order_date, status,
        delivery_address, contact_number, delivery_notes,
        payment_method, special_instructions, total_amount
    ) VALUES (?, NOW(), 'pending', ?, ?, ?, ?, ?, ?)");
    
    // Calculate total
    $subtotal = 0;
    foreach ($_SESSION['cart'] as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    $delivery = $subtotal > 500 ? 0 : 50;
    $total = $subtotal + $delivery;
    
    $stmt->bind_param(
        "isssssd",
        $_SESSION['user_id'],
        $_POST['address'],
        $_POST['contact'],
        $_POST['notes'],
        $_POST['payment'],
        $_POST['instructions'],
        $total
    );
    
    $stmt->execute();
    $orderId = $conn->insert_id;
    
    // 2. Add order items
    foreach ($_SESSION['cart'] as $productId => $item) {
        $stmt = $conn->prepare("INSERT INTO order_items (
            order_id, product_id, quantity, price
        ) VALUES (?, ?, ?, ?)");
        
        $stmt->bind_param(
            "iiid",
            $orderId,
            $productId,
            $item['quantity'],
            $item['price']
        );
        
        $stmt->execute();
    }
    
    // 3. Clear cart
    $_SESSION['cart'] = [];
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'message' => 'Order placed successfully!'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>