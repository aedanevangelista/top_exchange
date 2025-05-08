<?php
session_start();
include "db_connection.php";

header('Content-Type: application/json');

if (!isset($_SESSION['admin_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

if (!isset($_POST['username']) || !isset($_POST['po_number'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit;
}

$username = $_POST['username'];
$po_number = $_POST['po_number'];

try {
    // Get order details
    $stmt = $conn->prepare("SELECT order_date, delivery_date, total_amount FROM orders WHERE po_number = ?");
    $stmt->bind_param("s", $po_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }
    
    $order_date = $order['order_date'];
    $delivery_date = $order['delivery_date'];
    $total_amount = $order['total_amount'];
    
    // Get user email
    $stmt = $conn->prepare("SELECT email FROM clients_accounts WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user_data || empty($user_data['email'])) {
        echo json_encode(['success' => false, 'message' => 'User email not found.']);
        exit;
    }
    
    $user_email = $user_data['email'];
    
    // Prepare email content
    $subject = "Top Exchange Food Corp: New Order Confirmation";
    $message = "Dear $username,\n\n";
    $message .= "Thank you for your order. Your order has been successfully placed with the following details:\n\n";
    $message .= "Purchase Order Number: $po_number\n";
    $message .= "Order Date: $order_date\n";
    $message .= "Delivery Date: $delivery_date\n";
    $message .= "Total Amount: PHP " . number_format($total_amount, 2) . "\n\n";
    $message .= "Your order is pending review. We will notify you when its status changes.\n\n";
    $message .= "If you have any questions regarding your order, please contact our support team.\n\n";
    $message .= "Thank you for your business.\n\n";
    $message .= "Regards,\nTop Exchange Food Corp";
    
    // Set headers for email
    $headers = "From: no-reply@topexchange.com";
    
    // Send email
    if (mail($user_email, $subject, $message, $headers)) {
        // Log the notification in database
        $admin_username = $_SESSION['admin_username'] ?? 'admin';
        
        $stmt = $conn->prepare("INSERT INTO email_notifications (po_number, recipient, email_type, sent_by, sent_at) 
                               VALUES (?, ?, ?, ?, NOW())");
        $email_type = "new_order";
        $stmt->bind_param("ssss", $po_number, $user_email, $email_type, $admin_username);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['success' => true, 'message' => 'New order notification email sent successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send email notification.']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>