<?php
$dbname = 'u701062148_macj';
$user = 'u701062148_macj';
$pass = 'Macjpestcontrol123';
$servername = '151.106.122.5';

// Connect to production database
try {
    // Connect to the production server
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $user, $pass);
} catch(PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);