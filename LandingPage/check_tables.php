<?php
// Database connection
$servername = "127.0.0.1:3306";
$username = "u701062148_top_exchange";
$password = "Aedanpogi123";
$dbname = "u701062148_top_exchange";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if orders table exists
$result = $conn->query("SHOW TABLES LIKE 'orders'");
echo "Orders table exists: " . ($result->num_rows > 0 ? "Yes" : "No") . "<br>";

if ($result->num_rows > 0) {
    // Get columns in orders table
    $result = $conn->query("DESCRIBE orders");
    echo "<h3>Orders Table Columns:</h3>";
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>" . $row['Field'] . " - " . $row['Type'] . "</li>";
    }
    echo "</ul>";
    
    // Get sample data
    $result = $conn->query("SELECT * FROM orders LIMIT 1");
    if ($result->num_rows > 0) {
        echo "<h3>Sample Order Data:</h3>";
        echo "<pre>";
        print_r($result->fetch_assoc());
        echo "</pre>";
    } else {
        echo "No orders found in the database.<br>";
    }
}

// Check if order_items table exists
$result = $conn->query("SHOW TABLES LIKE 'order_items'");
echo "Order items table exists: " . ($result->num_rows > 0 ? "Yes" : "No") . "<br>";

if ($result->num_rows > 0) {
    // Get columns in order_items table
    $result = $conn->query("DESCRIBE order_items");
    echo "<h3>Order Items Table Columns:</h3>";
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>" . $row['Field'] . " - " . $row['Type'] . "</li>";
    }
    echo "</ul>";
    
    // Get sample data
    $result = $conn->query("SELECT * FROM order_items LIMIT 1");
    if ($result->num_rows > 0) {
        echo "<h3>Sample Order Item Data:</h3>";
        echo "<pre>";
        print_r($result->fetch_assoc());
        echo "</pre>";
    } else {
        echo "No order items found in the database.<br>";
    }
}

$conn->close();
?>
