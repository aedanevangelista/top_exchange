<?php
session_start();
include "db_connection.php";
include "check_role.php";
checkRole('Payment History');

// Check if notes column exists in monthly_payments table
$check_column_sql = "SHOW COLUMNS FROM monthly_payments LIKE 'notes'";
$column_result = $conn->query($check_column_sql);

if ($column_result->num_rows == 0) {
    // Add notes column to monthly_payments table
    $add_column_sql = "ALTER TABLE monthly_payments ADD COLUMN notes TEXT DEFAULT NULL AFTER proof_image";
    if ($conn->query($add_column_sql) === TRUE) {
        echo "Notes column added successfully to monthly_payments table.";
    } else {
        echo "Error adding notes column: " . $conn->error;
    }
} else {
    echo "Notes column already exists in monthly_payments table.";
}

$conn->close();
?>