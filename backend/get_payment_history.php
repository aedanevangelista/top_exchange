<?php
session_start();
include "db_connection.php";
include "check_role.php";
checkRole('Payment History');

header('Content-Type: application/json');

// Check if required data is received
if (!isset($_GET['username'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameter: username']);
    exit;
}

$username = $conn->real_escape_string($_GET['username']);

try {
    // Check if payment_type column exists in payment_history
    $check_payment_type_column = "SHOW COLUMNS FROM payment_history LIKE 'payment_type'";
    $payment_type_column_exists = $conn->query($check_payment_type_column)->num_rows > 0;
    
    // Check if payment_type column exists in monthly_payments
    $check_monthly_payment_type = "SHOW COLUMNS FROM monthly_payments LIKE 'payment_type'";
    $monthly_payment_type_exists = $conn->query($check_monthly_payment_type)->num_rows > 0;
    
    // Build the SQL query based on which columns exist
    if ($monthly_payment_type_exists) {
        // Use a JOIN to get payment_type from monthly_payments
        $sql = "SELECT 
                    ph.id, 
                    ph.username, 
                    ph.month, 
                    ph.year, 
                    ph.amount, 
                    ph.notes, 
                    ph.proof_image, 
                    ph.created_by, 
                    ph.created_at";
        
        // Add payment_type from payment_history if it exists (for backward compatibility)
        if ($payment_type_column_exists) {
            $sql .= ", COALESCE(ph.payment_type, mp.payment_type) as payment_type";
        } else {
            $sql .= ", mp.payment_type";
        }
        
        $sql .= " FROM payment_history ph
                  LEFT JOIN monthly_payments mp ON 
                      ph.username = mp.username AND 
                      ph.month = mp.month AND 
                      ph.year = mp.year
                  WHERE ph.username = ?
                  ORDER BY ph.created_at DESC";
    } else {
        // Fallback to original query if monthly_payments doesn't have payment_type
        $sql = "SELECT 
                    id, 
                    username, 
                    month, 
                    year, 
                    amount, 
                    notes, 
                    proof_image, 
                    created_by, 
                    created_at";
        
        if ($payment_type_column_exists) {
            $sql .= ", payment_type";
        }
        
        $sql .= " FROM payment_history
                  WHERE username = ?
                  ORDER BY created_at DESC";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    while ($row = $result->fetch_assoc()) {
        // If payment_type doesn't exist in the result set, set it to null
        if (!isset($row['payment_type']) || $row['payment_type'] === null) {
            // Default to Internal as a fallback
            $row['payment_type'] = 'Internal';
        }
        
        $payments[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $payments]);
    
} catch (Exception $e) {
    error_log("Error getting payment history: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>