<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Check if cart is empty
if (empty($_SESSION['cart'])) {
    header("Location: ordering.php");
    exit();
}

// Connect to database
require_once('db_connection.php');
// Use the $conn variable from db_connection.php

// Process checkout form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $deliveryAddress = trim($_POST['delivery_address']);
    $deliveryDate = $_POST['delivery_date'];
    $paymentMethod = $_POST['payment_method'];
    $specialInstructions = trim($_POST['special_instructions']);
    $contactNumber = trim($_POST['contact_number']);
    
    // Basic validation
    if (empty($deliveryAddress) || empty($deliveryDate) || empty($paymentMethod) || empty($contactNumber)) {
        die("Please fill in all required fields");
    }
    
    // Calculate order totals
    $subtotal = 0;
    $orderItems = [];
    foreach ($_SESSION['cart'] as $productId => $item) {
        $itemSubtotal = $item['price'] * $item['quantity'];
        $subtotal += $itemSubtotal;
        $orderItems[] = [
            'product_id' => $productId,
            'category' => $item['category'] ?? '',
            'item_description' => $item['name'],
            'packaging' => $item['packaging'] ?? '',
            'price' => $item['price'],
            'quantity' => $item['quantity']
        ];
    }
    
    $deliveryFee = ($subtotal > 500) ? 0 : 50;
    $total = $subtotal + $deliveryFee;
    
    // Generate PO number (username + incrementing number)
    $username = $_SESSION['username'];
    $poNumber = $username . '-' . (getNextOrderNumber($conn, $username) + 1);
    
    // Insert order into database
    $stmt = $conn->prepare("INSERT INTO orders (
        po_number, 
        username, 
        order_date, 
        delivery_date, 
        delivery_address, 
        contact_number,
        orders, 
        total_amount, 
        status, 
        special_instructions,
        payment_method,
        subtotal,
        delivery_fee
    ) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?)");
    
    $jsonOrders = json_encode($orderItems);
    
    $stmt->bind_param(
        "ssssssdssdd", 
        $poNumber,
        $username,
        $deliveryDate,
        $deliveryAddress,
        $contactNumber,
        $jsonOrders,
        $total,
        $specialInstructions,
        $paymentMethod,
        $subtotal,
        $deliveryFee
    );
    
    if ($stmt->execute()) {
        // Clear the cart
        $_SESSION['cart'] = [];
        
        // Redirect to confirmation page with the new order ID
        header("Location: order_confirmation.php?id=" . $stmt->insert_id);
        exit();
    } else {
        die("Error processing order: " . $conn->error);
    }
}

// Helper function to get the next order number for a user
function getNextOrderNumber($conn, $username) {
    $result = $conn->query("SELECT MAX(SUBSTRING_INDEX(po_number, '-', -1)) as max_num FROM orders WHERE username = '$username'");
    if ($result && $row = $result->fetch_assoc()) {
        return (int)$row['max_num'];
    }
    return 0;
}

// Fetch user details if available
$userDetails = [];
$stmt = $conn->prepare("SELECT company_address, phone FROM clients_accounts WHERE username = ?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $userDetails = $result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Checkout</title>
    <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css">
    <style>
        .delivery-date-picker {
            margin-bottom: 20px;
        }
        .order-summary-card {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .payment-methods {
            margin-bottom: 20px;
        }
        .payment-method {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            cursor: pointer;
        }
        .payment-method.selected {
            border-color: #4CAF50;
            background-color: #f8f9fa;
        }
        .payment-method input[type="radio"] {
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row">
            <div class="col-md-8">
                <h2>Checkout</h2>
                
                <form id="checkoutForm" method="POST">
                    <div class="form-group">
                        <h4>Delivery Information</h4>
                        <label for="delivery_address">Delivery Address*</label>
                        <textarea class="form-control" id="delivery_address" name="delivery_address" rows="3" required><?php echo htmlspecialchars($userDetails['company_address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_number">Contact Number*</label>
                        <input type="tel" class="form-control" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($userDetails['phone'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="delivery_date">Delivery Date*</label>
                        <input type="date" class="form-control" id="delivery_date" name="delivery_date" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <h4>Payment Method</h4>
                        <div class="payment-methods">
                            <div class="payment-method">
                                <label>
                                    <input type="radio" name="payment_method" value="Cash on Delivery" checked required> 
                                    Cash on Delivery (COD)
                                </label>
                                <p class="text-muted mt-2">Pay with cash upon delivery</p>
                            </div>
                            <div class="payment-method">
                                <label>
                                    <input type="radio" name="payment_method" value="GCash" required> 
                                    GCash
                                </label>
                                <p class="text-muted mt-2">Pay via GCash mobile payment</p>
                                <div id="gcashDetails" style="display: none; margin-top: 10px;">
                                    <p>Please send payment to GCash number: 0917-123-4567</p>
                                    <p>Include Order ID as reference</p>
                                </div>
                            </div>
                            <div class="payment-method">
                                <label>
                                    <input type="radio" name="payment_method" value="Bank Transfer" required> 
                                    Bank Transfer
                                </label>
                                <p class="text-muted mt-2">Pay via bank deposit/transfer</p>
                                <div id="bankDetails" style="display: none; margin-top: 10px;">
                                    <p>Bank: BDO</p>
                                    <p>Account Name: Top Exchange</p>
                                    <p>Account Number: 123-456-7890</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="special_instructions">Special Instructions</label>
                        <textarea class="form-control" id="special_instructions" name="special_instructions" rows="3" placeholder="Any special requests or notes for your order..."></textarea>
                    </div>
                </form>
            </div>
            
            <div class="col-md-4">
                <div class="order-summary-card">
                    <h4>Order Summary</h4>
                    
                    <div id="orderItems">
                        <?php 
                        $subtotal = 0;
                        foreach ($_SESSION['cart'] as $productId => $item): 
                            $itemSubtotal = $item['price'] * $item['quantity'];
                            $subtotal += $itemSubtotal;
                        ?>
                            <div class="d-flex justify-content-between mb-2">
                                <div>
                                    <span><?php echo htmlspecialchars($item['name']); ?></span>
                                    <small class="text-muted d-block">x<?php echo $item['quantity']; ?></small>
                                    <?php if (!empty($item['packaging'])): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($item['packaging']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <span>₱<?php echo number_format($itemSubtotal, 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <span id="summarySubtotal">₱<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Delivery Fee:</span>
                        <span id="summaryDeliveryFee">₱<?php echo number_format(($subtotal > 500) ? 0 : 50, 2); ?></span>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between font-weight-bold">
                        <span>Total:</span>
                        <span id="summaryTotal">₱<?php echo number_format($subtotal + (($subtotal > 500) ? 0 : 50), 2); ?></span>
                    </div>
                    
                    <button type="submit" form="checkoutForm" class="btn btn-primary btn-block mt-4">Place Order</button>
                </div>
            </div>
        </div>
    </div>

    <script src="js/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            // Set minimum delivery date (tomorrow)
            var today = new Date();
            var tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);
            var minDate = tomorrow.toISOString().split('T')[0];
            $('#delivery_date').attr('min', minDate);
            
            // Set default delivery date to tomorrow
            $('#delivery_date').val(minDate);
            
            // Show/hide payment method details
            $('input[name="payment_method"]').change(function() {
                $('#gcashDetails, #bankDetails').hide();
                if ($(this).val() === 'GCash') {
                    $('#gcashDetails').show();
                } else if ($(this).val() === 'Bank Transfer') {
                    $('#bankDetails').show();
                }
            });
        });
    </script>
</body>
</html>