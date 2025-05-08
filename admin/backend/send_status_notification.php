<?php
session_start();
include "db_connection.php";

header('Content-Type: application/json');

if (!isset($_SESSION['admin_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

if (!isset($_POST['po_number']) || !isset($_POST['new_status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit;
}

$po_number = $_POST['po_number'];
$new_status = $_POST['new_status'];

try {
    // Get order information
    $stmt = $conn->prepare("SELECT username, order_date, delivery_date FROM orders WHERE po_number = ?");
    $stmt->bind_param("s", $po_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }
    
    $username = $order['username'];
    $delivery_date = $order['delivery_date'];
    
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
    
    // Prepare email content based on status change
    $subject = "Top Exchange Food Corp: Order Status Update";
    $message = "Dear $username,\n\n";
    $message .= "Your order (PO: $po_number) status has been updated to: $new_status.\n\n";
    
    // Add additional information based on status
    if ($new_status == "Active") {
        $message .= "We are now processing your order. We'll notify you when it's ready for delivery.\n\n";
    } elseif ($new_status == "For Delivery") {
        $message .= "Your order is now ready for delivery. It will be delivered on $delivery_date.\n\n";
    } elseif ($new_status == "Rejected") {
        $message .= "Unfortunately, your order has been rejected. If you have questions, please contact our support team.\n\n";
    }
    
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
        $email_type = "status_update";
        $stmt->bind_param("ssss", $po_number, $user_email, $email_type, $admin_username);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['success' => true, 'message' => 'Status notification email sent successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send email notification.']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>