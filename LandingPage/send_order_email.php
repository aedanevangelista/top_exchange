<?php
/**
 * Send Order Email
 * 
 * This file contains functions to send a professional order confirmation email
 * to the customer after checkout.
 */

// Function to get user email from database
function getUserEmail($conn, $username) {
    $email = null;
    
    // Try to get email from clients_accounts table
    $stmt = $conn->prepare("SELECT email FROM clients_accounts WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $email = $user['email'];
    } else {
        // If not found in clients_accounts, try the admin accounts table
        $stmt = $conn->prepare("SELECT username as email FROM accounts WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $email = $user['email'] . "@topexchange.com"; // Default domain for admin accounts
        }
    }
    
    $stmt->close();
    return $email;
}

// Function to generate HTML email content
function generateOrderEmailHTML($order, $orderItems, $userEmail) {
    // Company information
    $companyName = "Top Exchange Food Corp";
    $companyLogo = "https://topexchangefoodcorp.com/LandingPage/images/resized_food_corp_logo.png";
    $companyAddress = "123 Main Street, Manila, Philippines";
    $companyPhone = "+63 (2) 8123 4567";
    $companyEmail = "orders@topexchangefoodcorp.com";
    
    // Format the order date and delivery date
    $orderDate = date('F j, Y', strtotime($order['order_date']));
    $deliveryDate = date('F j, Y', strtotime($order['delivery_date']));
    $deliveryDay = date('l', strtotime($order['delivery_date']));
    
    // Calculate order totals
    $subtotal = $order['subtotal'];
    $total = $order['total_amount'];
    
    // Start building the HTML email
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Order Confirmation - ' . htmlspecialchars($order['po_number']) . '</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
                background-color: #f9f9f9;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
                background-color: #ffffff;
                border: 1px solid #e0e0e0;
                border-radius: 5px;
            }
            .header {
                text-align: center;
                padding-bottom: 20px;
                border-bottom: 2px solid #9a7432;
                margin-bottom: 20px;
            }
            .logo {
                max-width: 200px;
                height: auto;
                margin-bottom: 15px;
            }
            .confirmation-message {
                text-align: center;
                margin-bottom: 30px;
                padding: 20px;
                background-color: #f8f9fa;
                border-radius: 5px;
            }
            .confirmation-message h2 {
                color: #28a745;
                margin-top: 0;
            }
            .order-details {
                margin-bottom: 30px;
            }
            .order-details h3 {
                color: #9a7432;
                border-bottom: 1px solid #eee;
                padding-bottom: 10px;
                margin-bottom: 15px;
            }
            .detail-row {
                margin-bottom: 10px;
            }
            .detail-label {
                font-weight: bold;
                width: 150px;
                display: inline-block;
            }
            .order-items {
                margin-bottom: 30px;
            }
            .order-items h3 {
                color: #9a7432;
                border-bottom: 1px solid #eee;
                padding-bottom: 10px;
                margin-bottom: 15px;
            }
            .item {
                padding: 10px 0;
                border-bottom: 1px solid #eee;
            }
            .item-name {
                font-weight: bold;
            }
            .item-details {
                color: #666;
                font-size: 0.9em;
                margin-top: 5px;
            }
            .item-price {
                text-align: right;
                font-weight: bold;
                color: #9a7432;
            }
            .order-totals {
                background-color: #f8f9fa;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 30px;
            }
            .total-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 5px;
            }
            .grand-total {
                font-weight: bold;
                font-size: 1.1em;
                color: #9a7432;
                border-top: 2px solid #ddd;
                padding-top: 10px;
                margin-top: 10px;
            }
            .footer {
                text-align: center;
                padding-top: 20px;
                border-top: 1px solid #eee;
                color: #777;
                font-size: 0.9em;
            }
            .contact-info {
                margin-top: 15px;
            }
            .note {
                background-color: #fff8e1;
                padding: 15px;
                border-left: 4px solid #ffc107;
                margin-bottom: 20px;
                font-size: 0.9em;
            }
            @media only screen and (max-width: 600px) {
                .container {
                    width: 100%;
                    padding: 10px;
                }
                .detail-label {
                    width: 120px;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="' . $companyLogo . '" alt="' . $companyName . '" class="logo">
                <h1>Order Confirmation</h1>
            </div>
            
            <div class="confirmation-message">
                <h2>Thank You for Your Order!</h2>
                <p>We\'ve received your order and will process it shortly. Your order will be delivered on the selected date.</p>
            </div>
            
            <div class="order-details">
                <h3>Order Information</h3>
                <div class="detail-row">
                    <span class="detail-label">Order Number:</span>
                    <span>' . htmlspecialchars($order['po_number']) . '</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Order Date:</span>
                    <span>' . $orderDate . '</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Delivery Date:</span>
                    <span>' . $deliveryDate . ' (' . $deliveryDay . ')</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Delivery Address:</span>
                    <span>' . htmlspecialchars($order['delivery_address']) . '</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Contact Number:</span>
                    <span>' . htmlspecialchars($order['contact_number']) . '</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Payment Method:</span>
                    <span>Check Payment</span>
                </div>';
    
    // Add special instructions if any
    if (!empty($order['special_instructions'])) {
        $html .= '
                <div class="detail-row">
                    <span class="detail-label">Special Instructions:</span>
                    <span>' . htmlspecialchars($order['special_instructions']) . '</span>
                </div>';
    }
    
    $html .= '
            </div>
            
            <div class="order-items">
                <h3>Order Items</h3>';
    
    // Add each order item
    foreach ($orderItems as $item) {
        $html .= '
                <div class="item">
                    <div class="item-name">' . htmlspecialchars($item['item_description']) . '</div>
                    <div class="item-details">
                        ' . $item['quantity'] . ' x ₱' . number_format($item['price'], 2);
        
        // Add category if available
        if (!empty($item['category'])) {
            $html .= ' • Category: ' . htmlspecialchars($item['category']);
        }
        
        // Add packaging if available
        if (!empty($item['packaging'])) {
            $html .= ' • ' . htmlspecialchars($item['packaging']);
        }
        
        $html .= '
                    </div>
                    <div class="item-price">₱' . number_format($item['price'] * $item['quantity'], 2) . '</div>
                </div>';
    }
    
    $html .= '
            </div>
            
            <div class="order-totals">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span>₱' . number_format($subtotal, 2) . '</span>
                </div>
                <div class="total-row grand-total">
                    <span>Total Amount:</span>
                    <span>₱' . number_format($total, 2) . '</span>
                </div>
            </div>
            
            <div class="note">
                <strong>Note:</strong> Please prepare a check payment for the total amount. Our delivery personnel will collect the payment upon delivery.
            </div>
            
            <div class="footer">
                <p>If you have any questions about your order, please contact our customer service.</p>
                <div class="contact-info">
                    <p>' . $companyName . '</p>
                    <p>' . $companyAddress . '</p>
                    <p>Phone: ' . $companyPhone . ' | Email: ' . $companyEmail . '</p>
                </div>
                <p>&copy; ' . date('Y') . ' ' . $companyName . '. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

// Function to send the order confirmation email
function sendOrderConfirmationEmail($conn, $orderId, $username) {
    // Get user email
    $userEmail = getUserEmail($conn, $username);
    
    if (!$userEmail) {
        error_log("Could not find email for user: $username");
        return false;
    }
    
    // Fetch order details
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND username = ?");
    $stmt->bind_param("is", $orderId, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        error_log("Order not found: ID=$orderId, Username=$username");
        return false;
    }
    
    $order = $result->fetch_assoc();
    
    // Safely decode JSON orders
    $orderItems = [];
    if (!empty($order['orders'])) {
        $decodedItems = json_decode($order['orders'], true);
        if ($decodedItems !== null) {
            $orderItems = $decodedItems;
        } else {
            error_log("JSON decode error for order ID $orderId: " . json_last_error_msg());
            error_log("Raw JSON: " . $order['orders']);
            return false;
        }
    }
    
    // Generate email content
    $emailHTML = generateOrderEmailHTML($order, $orderItems, $userEmail);
    
    // Email headers
    $subject = "Order Confirmation - " . $order['po_number'];
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Top Exchange Food Corp <orders@topexchangefoodcorp.com>" . "\r\n";
    $headers .= "Reply-To: orders@topexchangefoodcorp.com" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Send the email
    $mailSent = mail($userEmail, $subject, $emailHTML, $headers);
    
    if ($mailSent) {
        // Log success
        error_log("Order confirmation email sent to $userEmail for order ID $orderId");
        return true;
    } else {
        // Log failure
        error_log("Failed to send order confirmation email to $userEmail for order ID $orderId");
        return false;
    }
}
