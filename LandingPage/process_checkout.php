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
    $stmt = $conn->prepare("INSERT INTO orders (user_id, po_number, username, company, total_amount, status, special_instructions, order_date, order_type)
                           VALUES (?, ?, ?, ?, ?, 'Pending', ?, NOW(), 'Online')");
    $stmt->bind_param("isssds", $user_id, $poNumber, $username, $companyName, $total_amount, $special_instructions);
    $stmt->execute();
    $order_id = $conn->insert_id;

    // 2. Add order items
    $stmt = $conn->prepare("INSERT INTO order_items (order_id, po_number, product_id, quantity, price)
                           VALUES (?, ?, ?, ?, ?)");

    foreach ($_SESSION['cart'] as $item) {
        $stmt->bind_param("isiid", $order_id, $poNumber, $item['product_id'], $item['quantity'], $item['price']);
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