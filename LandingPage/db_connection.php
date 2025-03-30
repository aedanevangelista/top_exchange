<?php
// db_connection.php

$host = 'localhost'; // Keep as localhost for Hostinger
$dbname = 'u701062148_top_exchange'; // Your Hostinger database name
$username = 'Food'; // Replace XXXXX with your Hostinger database username
$password = 'FoodniAedanRyanSheilaJeff1'; // Replace with your Hostinger database password

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>