<?php
include 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Log all incoming POST data
    error_log("Received POST data: " . print_r($_POST, true));

    $username = $_POST['username'] ?? '';
    $order_date = $_POST['order_date'] ?? '';
    $delivery_date = $_POST['delivery_date'] ?? '';
    $po_number = $_POST['po_number'] ?? '';
    $orders_raw = $_POST['orders'] ?? '';
    $total_amount = $_POST['total_amount'] ?? '';

    // Log raw JSON data
    error_log("Raw JSON data: " . $orders_raw);

    // Decode JSON data
    $orders = json_decode($orders_raw, true);

    // Log JSON error if any
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON error: " . json_last_error_msg());
        die('Invalid JSON data: ' . json_last_error_msg());
    }

    // Log parsed JSON data
    error_log("Parsed JSON data: " . print_r($orders, true));

    // Insert order into database
    $products_ordered = json_encode($orders);
    $stmt = $conn->prepare("INSERT INTO orders (po_number, username, order_date, delivery_date, products_ordered, total_amount, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }
    $stmt->bind_param("sssssd", $po_number, $username, $order_date, $delivery_date, $products_ordered, $total_amount);
    $stmt->execute();
    $stmt->close();

    echo "Order successfully added!";
}
?>