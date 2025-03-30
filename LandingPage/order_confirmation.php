<?php
session_start();

// Check if order ID is provided
if (!isset($_GET['id'])) {
    header("Location: ordering.php");
    exit();
}

$orderId = intval($_GET['id']);

// Connect to database
$conn = new mysqli("151.106.122.5", "u701062148_top_exchange", "CreamLine123", "u701062148_top_exchange");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch order details
$stmt = $conn->prepare("SELECT o.*, c.email FROM orders o 
                       JOIN clients_accounts c ON o.username = c.username 
                       WHERE o.id = ?");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Order not found");
}

$order = $result->fetch_assoc();
$orderItems = json_decode($order['orders'], true);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Order Confirmation</title>
    <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css">
    <style>
        .confirmation-card {
            border: 1px solid #4CAF50;
            border-radius: 5px;
            padding: 30px;
            margin: 30px auto;
            max-width: 800px;
        }
        .confirmation-header {
            color: #4CAF50;
            margin-bottom: 20px;
        }
        .order-details {
            margin-top: 20px;
        }
        .thank-you {
            font-size: 1.2em;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="confirmation-card">
            <div class="text-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="#4CAF50" viewBox="0 0 16 16">
                    <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
                </svg>
                <h2 class="confirmation-header">Order Confirmed!</h2>
                <p class="thank-you">Thank you for your order, <?php echo htmlspecialchars($order['username']); ?>!</p>
                <p>Your order #<?php echo $order['po_number']; ?> has been received and is being processed.</p>
                <p>A confirmation email has been sent to <?php echo htmlspecialchars($order['email']); ?></p>
            </div>
            
            <div class="order-details">
                <h4>Order Details</h4>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Order Number:</strong> #<?php echo $order['po_number']; ?></p>
                        <p><strong>Order Date:</strong> <?php echo date('F j, Y', strtotime($order['order_date'])); ?></p>
                        <p><strong>Delivery Date:</strong> <?php echo date('F j, Y', strtotime($order['delivery_date'])); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($order['payment_method'] ?? 'Cash on Delivery'); ?></p>
                        <p><strong>Delivery Address:</strong> <?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></p>
                        <p><strong>Contact Number:</strong> <?php echo htmlspecialchars($order['contact_number']); ?></p>
                    </div>
                </div>
                
                <h5 class="mt-4">Order Items</h5>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderItems as $item): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($item['item_description']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($item['packaging'] ?? ''); ?></small>
                                </td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                <td>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="text-right">
                    <p><strong>Subtotal:</strong> ₱<?php echo number_format($order['subtotal'], 2); ?></p>
                    <p><strong>Delivery Fee:</strong> ₱<?php echo number_format($order['delivery_fee'], 2); ?></p>
                    <p><strong>Total:</strong> ₱<?php echo number_format($order['total_amount'], 2); ?></p>
                </div>
                
                <?php if (!empty($order['special_instructions'])): ?>
                    <div class="mt-3">
                        <h5>Special Instructions</h5>
                        <p><?php echo nl2br(htmlspecialchars($order['special_instructions'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="text-center mt-4">
                <a href="ordering.php" class="btn btn-primary">Continue Shopping</a>
                <a href="order_history.php" class="btn btn-outline-secondary ml-2">View Order History</a>
            </div>
        </div>
    </div>
</body>
</html>