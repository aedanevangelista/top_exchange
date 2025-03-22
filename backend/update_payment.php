<?php
header('Content-Type: application/json');
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Check user authentication
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Authentication required");
    }

    // Include the database connection
    require_once "db_connection.php";
    
    // Validate required fields
    $requiredFields = ['username', 'month', 'year', 'payment_status', 'payment_amount', 'total_amount'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || $_POST[$field] === '') {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Sanitize inputs
    $username = $_POST['username'];
    $month = (int)$_POST['month'];
    $year = (int)$_POST['year'];
    $paymentStatus = $_POST['payment_status'];
    $paymentAmount = (float)$_POST['payment_amount'];
    $totalAmount = (float)$_POST['total_amount'];
    $notes = isset($_POST['payment_notes']) ? $_POST['payment_notes'] : '';
    
    // Validate status
    if (!in_array($paymentStatus, ['Paid', 'Partial', 'Unpaid'])) {
        throw new Exception("Invalid payment status");
    }
    
    // Validate payment amount
    if ($paymentAmount < 0) {
        throw new Exception("Payment amount cannot be negative");
    }
    
    // Get current payment info
    $sql = "SELECT * FROM monthly_payments WHERE username = ? AND month = ? AND year = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $username, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $currentPayment = $result->fetch_assoc();
        $amountPaid = (float)$currentPayment['amount_paid'] + $paymentAmount;
    } else {
        $amountPaid = $paymentAmount;
    }
    
    // Calculate remaining balance
    $balance = $totalAmount - $amountPaid;
    if ($balance < 0) $balance = 0;
    
    // Handle proof of payment file upload if provided
    $proofFilename = null;
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadDir = "../uploads/payment_proofs/";
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception("Failed to create upload directory");
            }
        }
        
        $fileInfo = pathinfo($_FILES['payment_proof']['name']);
        $extension = strtolower($fileInfo['extension']);
        
        // Check file type
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception("Invalid file type. Only images and PDFs are allowed.");
        }
        
        // Generate unique filename
        $proofFilename = $username . '_' . $year . '_' . $month . '_' . time() . '.' . $extension;
        $targetFile = $uploadDir . $proofFilename;
        
        // Move the uploaded file
        if (!move_uploaded_file($_FILES['payment_proof']['tmp_name'], $targetFile)) {
            throw new Exception("Failed to upload proof of payment");
        }
    }
    
    // Update or insert payment record
    if ($result->num_rows > 0) {
        // Update existing record
        $sql = "UPDATE monthly_payments SET 
                payment_status = ?, 
                amount_paid = ?, 
                balance = ?, 
                payment_date = NOW(), 
                payment_notes = ?";
        
        // Add proof file if uploaded
        if ($proofFilename) {
            $sql .= ", proof_of_payment = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sddss", $paymentStatus, $amountPaid, $balance, $notes, $proofFilename);
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sdds", $paymentStatus, $amountPaid, $balance, $notes);
        }
        
        $sql .= " WHERE username = ? AND month = ? AND year = ?";
        $stmt->bind_param("sii", $username, $month, $year);
    } else {
        // Insert new record
        $sql = "INSERT INTO monthly_payments 
                (username, month, year, payment_status, total_amount, amount_paid, balance, payment_date, payment_notes";
        
        // Add proof file column if uploaded
        if ($proofFilename) {
            $sql .= ", proof_of_payment";
        }
        
        $sql .= ") VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?";
        
        // Add proof file value if uploaded
        if ($proofFilename) {
            $sql .= ", ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siiiddds", $username, $month, $year, $paymentStatus, $totalAmount, $amountPaid, $balance, $notes, $proofFilename);
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siiddds", $username, $month, $year, $paymentStatus, $totalAmount, $amountPaid, $balance, $notes);
        }
        
        $sql .= ")";
    }
    
    // Execute the query
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Payment updated successfully'
        ]);
    } else {
        throw new Exception("Database error: " . $stmt->error);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>