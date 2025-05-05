<?php
$dbname = 'u701062148_top_exchange';
$user = 'u701062148_top_exchange';
$pass = 'Aedanpogi123';
$servername = 'localhost';

// Connect to production database
try {
    // Connect to the production server
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $user, $pass);
} catch(PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);