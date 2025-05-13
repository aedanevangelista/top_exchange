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

    // Generate a PO number in format PO-XXXX-00#
    $username = $_SESSION['username'] ?? 'user';

    // Get first 4 letters of username and convert to uppercase
    $usernamePrefix = strtoupper(substr($username, 0, 4));

    // Get the next order number for this username
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE po_number LIKE ?");
    $poPattern = "PO-$usernamePrefix-%";
    $stmt->bind_param("s", $poPattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $nextOrderNum = ($row['count'] ?? 0) + 1;

    // Format the PO number
    $poNumber = sprintf("PO-%s-%03d", $usernamePrefix, $nextOrderNum);

    // Get the company name from clients_accounts
    $companyName = null;
    $companyStmt = $conn->prepare("SELECT company FROM clients_accounts WHERE username = ?");
    $companyStmt->bind_param("s", $username);
    $companyStmt->execute();
    $companyResult = $companyStmt->get_result();
    if ($companyResult->num_rows > 0) {
        $companyRow = $companyResult->fetch_assoc();
        $companyName = $companyRow['company'];
    }
    $companyStmt->close();

    // 1. Create order
    $stmt = $conn->prepare("INSERT INTO orders (
        user_id, po_number, username, company, order_date, status,
        delivery_address, contact_number, delivery_notes,
        payment_method, special_instructions, total_amount, order_type
    ) VALUES (?, ?, ?, ?, NOW(), 'pending', ?, ?, ?, ?, ?, ?, 'Online')");

    // Calculate total
    $subtotal = 0;
    foreach ($_SESSION['cart'] as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    $delivery = $subtotal > 500 ? 0 : 50;
    $total = $subtotal + $delivery;

    $stmt->bind_param(
        "isssississds",
        $_SESSION['user_id'],
        $poNumber,
        $username,
        $companyName,
        $_POST['address'],
        $_POST['contact'],
        $_POST['notes'],
        $_POST['payment'],
        $_POST['instructions'],
        $total,
        'Online'
    );

    $stmt->execute();
    $orderId = $conn->insert_id;

    // 2. Add order items
    foreach ($_SESSION['cart'] as $productId => $item) {
        $stmt = $conn->prepare("INSERT INTO order_items (
            order_id, po_number, product_id, quantity, price
        ) VALUES (?, ?, ?, ?, ?)");

        $stmt->bind_param(
            "isiid",
            $orderId,
            $poNumber,
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