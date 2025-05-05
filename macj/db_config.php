<?php
$dbname = 'u701062148_top_exchange';
$user = 'u701062148_top_exchange';
$pass = 'Aedanpogi123';

// Connect to database
try {
    // Connect to the server
    $pdo = new PDO("mysql:host=localhost;dbname=$dbname", $user, $pass);
} catch(PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);