<?php
// Add error reporting at the top
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include "db_connection.php";

// Create a log file for debugging
$log_file = "../logs/payment_debug.log";
if (!file_exists("../logs")) {
    mkdir("../logs", 0777, true);
}
file_put_contents($log_file, date("Y-m-d H:i:s") . " - Get Monthly Payments Started\n", FILE_APPEND);
file_put_contents($log_file, date("Y-m-d H:i:s") . " - Get Monthly Payments - Examining database schema\n", FILE_APPEND);

// Check if required parameters are provided
if (!isset($_GET['username']) || !isset($_GET['year'])) {
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - Missing parameters in get_monthly_payments\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$username = $_GET['username'];
$year = intval($_GET['year']);

file_put_contents($log_file, date("Y-m-d H:i:s") . " - Fetching payments for: $username, Year: $year\n", FILE_APPEND);

try {
    // Let's first check the actual schema of the monthly_payments table
    $schema_check = $conn->query("DESCRIBE monthly_payments");
    $columns = [];
    while ($col = $schema_check->fetch_assoc()) {
        $columns[] = $col['Field'];
    }
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - Available columns: " . implode(", ", $columns) . "\n", FILE_APPEND);
    
    // Build a query that only uses the columns that exist
    $select_columns = "month, total_amount, payment_status";
    
    if (in_array("remaining_balance", $columns)) {
        $select_columns .= ", remaining_balance";
    }
    
    if (in_array("payment_method", $columns)) {
        $select_columns .= ", payment_method";
    }
    
    if (in_array("proof_image", $columns)) {
        $select_columns .= ", proof_image";
    }
    
    if (in_array("notes", $columns)) {
        $select_columns .= ", notes";
    }
    
    // Get monthly payment records for this user and year
    $sql = "SELECT $select_columns FROM monthly_payments WHERE username = ? AND year = ?";
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - SQL Query: $sql\n", FILE_APPEND);
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("si", $username, $year);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $payments = [];
    
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
    
    // If no payments found, check if the user exists
    if (empty($payments)) {
        $sql = "SELECT username FROM clients_accounts WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            file_put_contents($log_file, date("Y-m-d H:i:s") . " - User not found: $username\n", FILE_APPEND);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        // User exists but no payments for this year, return empty array
        file_put_contents($log_file, date("Y-m-d H:i:s") . " - No payments found for user: $username in year: $year\n", FILE_APPEND);
    }
    
    // Get orders for the year to ensure we have data for all months
    $sql = "SELECT 
                MONTH(delivery_date) as month, 
                SUM(total_amount) as total 
            FROM orders 
            WHERE username = ? AND YEAR(delivery_date) = ? 
            GROUP BY MONTH(delivery_date)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed for orders query: " . $conn->error);
    }
    
    $stmt->bind_param("si", $username, $year);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for orders query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $orders = [];
    
    while ($row = $result->fetch_assoc()) {
        $month = intval($row['month']);
        $order_data = [
            'month' => $month,
            'total_amount' => floatval($row['total']),
            'payment_status' => 'Unpaid',
            'remaining_balance' => floatval($row['total'])
        ];
        
        // Only add fields that exist in our schema
        if (in_array("payment_method", $columns)) {
            $order_data['payment_method'] = '';
        }
        
        if (in_array("proof_image", $columns)) {
            $order_data['proof_image'] = null;
        }
        
        if (in_array("notes", $columns)) {
            $order_data['notes'] = '';
        }
        
        $orders[$month] = $order_data;
    }
    
    // Merge payments data with orders data
    foreach ($payments as $payment) {
        $month = intval($payment['month']);
        if (isset($orders[$month])) {
            // Update existing order data with payment info
            foreach ($payment as $key => $value) {
                if ($key != 'month') {
                    $orders[$month][$key] = $value;
                }
            }
            
            // Ensure remaining_balance is set properly
            if (isset($payment['remaining_balance'])) {
                $orders[$month]['remaining_balance'] = floatval($payment['remaining_balance']);
            }
        } else {
            // Payment exists but no order found (could happen if payment was created manually)
            $orders[$month] = [];
            $orders[$month]['month'] = $month;
            $orders[$month]['total_amount'] = isset($payment['total_amount']) ? floatval($payment['total_amount']) : 0;
            $orders[$month]['payment_status'] = isset($payment['payment_status']) ? $payment['payment_status'] : 'Unpaid';
            
            // Only add fields that exist in our schema
            if (isset($payment['remaining_balance'])) {
                $orders[$month]['remaining_balance'] = floatval($payment['remaining_balance']);
            } else {
                $orders[$month]['remaining_balance'] = floatval($payment['total_amount']);
            }
            
            if (isset($payment['payment_method'])) {
                $orders[$month]['payment_method'] = $payment['payment_method'];
            }
            
            if (isset($payment['proof_image'])) {
                $orders[$month]['proof_image'] = $payment['proof_image'];
            }
            
            if (isset($payment['notes'])) {
                $orders[$month]['notes'] = $payment['notes'];
            }
        }
    }
    
    // Convert to numerically indexed array
    $result_data = array_values($orders);
    
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - Successfully fetched " . count($result_data) . " records\n", FILE_APPEND);
    echo json_encode(['success' => true, 'data' => $result_data]);

} catch (Exception $e) {
    file_put_contents($log_file, date("Y-m-d H:i:s") . " - ERROR in get_monthly_payments: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Error fetching payment data: ' . $e->getMessage()]);
}

$conn->close();
file_put_contents($log_file, date("Y-m-d H:i:s") . " - Get Monthly Payments completed\n", FILE_APPEND);
?>