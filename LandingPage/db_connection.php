<?php
// db_connection.php

$host = '151.106.122.5'; // Replace with your database host
$dbname = 'u701062148_top_exchange'; // Replace with your database name
$username = 'u701062148_top_exchange'; // Replace with your database username
$password = 'CreamLine123'; // Replace with your database password

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>