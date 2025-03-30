<?php
// db_connection.php

$host = '151.106.122.5'; // Your database host
$dbname = 'u701062148_top_exchange'; // Your database name
$username = 'u701062148_top_exchange'; // Your database username
$password = 'Aedanpogi123'; // Updated with the correct password you provided

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>