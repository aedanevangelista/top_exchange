<?php
header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "db_connection.php";

echo "Starting database structure check...\n\n";

// Check if payment_method column exists in monthly_payments
echo "Checking monthly_payments table...\n";
$result = $conn->query("SHOW COLUMNS FROM monthly_payments LIKE 'payment_method'");
if ($result && $result->num_rows == 0) {
    echo "Adding payment_method column to monthly_payments table...\n";
    if ($conn->query("ALTER TABLE monthly_payments ADD COLUMN payment_method VARCHAR(20) DEFAULT NULL")) {
        echo "Successfully added payment_method column.\n";
    } else {
        echo "Error adding payment_method column: " . $conn->error . "\n";
    }
} else {
    echo "Payment method column already exists in monthly_payments table.\n";
}

// Check if payment_history table exists
echo "\nChecking payment_history table...\n";
$result = $conn->query("SHOW TABLES LIKE 'payment_history'");
if ($result && $result->num_rows == 0) {
    echo "Creating payment_history table...\n";
    $sql = "CREATE TABLE payment_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) NOT NULL,
        payment_date DATETIME NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(20) NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql)) {
        echo "Successfully created payment_history table.\n";
    } else {
        echo "Error creating payment_history table: " . $conn->error . "\n";
    }
} else {
    echo "Payment history table already exists.\n";
}

echo "\nDatabase structure check completed.";
?>