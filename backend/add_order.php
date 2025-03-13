<?php
include 'db_connection.php'; // Ensure this includes your database connection logic

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST['username'];
    $order_date = $_POST['order_date'];
    $delivery_date = $_POST['delivery_date'];
    $po_number = $_POST['po_number'];
    $orders = json_decode($_POST['orders'], true); // Decoding the order details

    // Insert order details into `orders` table
    $total_amount = 0;

    foreach ($orders as $item) {
        $product_id = $item['product_id'];
        $quantity = (int)$item['quantity'];
        
        // Fetch product details for calculating total price
        $stmt = $conn->prepare("SELECT price, stock_quantity FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();

        if (!$product || $product['stock_quantity'] < $quantity) {
            echo "Error: Insufficient stock for product ID " . $product_id;
            exit;
        }

        $total_amount += $product['price'] * $quantity;

        // Update stock in `products` table
        $new_stock = $product['stock_quantity'] - $quantity;
        $updateStockStmt = $conn->prepare("UPDATE products SET stock_quantity = ? WHERE product_id = ?");
        $updateStockStmt->bind_param("ii", $new_stock, $product_id);
        $updateStockStmt->execute();
    }

    // Insert into orders table
    $insertOrder = $conn->prepare("
        INSERT INTO orders (username, order_date, delivery_date, po_number, total_amount) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $insertOrder->bind_param("ssssd", $username, $order_date, $delivery_date, $po_number, $total_amount);

    if ($insertOrder->execute()) {
        echo "Order successfully added!";
    } else {
        echo "Error: " . $insertOrder->error;
    }
}
?>
