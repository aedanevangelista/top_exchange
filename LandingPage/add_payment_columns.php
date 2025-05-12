<?php
// This script adds payment_method and payment_status columns to the orders table if they don't exist

// Connect to database
include_once('db_connection.php');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if payment_method column exists
$checkMethodQuery = "SHOW COLUMNS FROM `orders` LIKE 'payment_method'";
$methodResult = $conn->query($checkMethodQuery);
$hasMethodColumn = $methodResult && $methodResult->num_rows > 0;

// Check if payment_status column exists
$checkStatusQuery = "SHOW COLUMNS FROM `orders` LIKE 'payment_status'";
$statusResult = $conn->query($checkStatusQuery);
$hasStatusColumn = $statusResult && $statusResult->num_rows > 0;

// Add payment_method column if it doesn't exist
if (!$hasMethodColumn) {
    $addMethodQuery = "ALTER TABLE `orders` ADD COLUMN `payment_method` VARCHAR(50) DEFAULT 'check_payment'";
    if ($conn->query($addMethodQuery) === TRUE) {
        echo "payment_method column added successfully<br>";
    } else {
        echo "Error adding payment_method column: " . $conn->error . "<br>";
    }
} else {
    echo "payment_method column already exists<br>";
}

// Add payment_status column if it doesn't exist
if (!$hasStatusColumn) {
    $addStatusQuery = "ALTER TABLE `orders` ADD COLUMN `payment_status` VARCHAR(20) DEFAULT 'Pending'";
    if ($conn->query($addStatusQuery) === TRUE) {
        echo "payment_status column added successfully<br>";
    } else {
        echo "Error adding payment_status column: " . $conn->error . "<br>";
    }
} else {
    echo "payment_status column already exists<br>";
}

// Close connection
$conn->close();

echo "<p>Database update completed. <a href='checkout.php'>Go to checkout page</a></p>";
?>
