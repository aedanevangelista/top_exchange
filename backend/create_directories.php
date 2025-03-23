<?php
// Script to create necessary directories for payment uploads

// Define the path to the uploads directory
$baseDir = "../uploads";
$paymentsDir = "$baseDir/payments";
$logsDir = "../logs";

// Create uploads directory if it doesn't exist
if (!is_dir($baseDir)) {
    if (!mkdir($baseDir, 0755, true)) {
        die("Failed to create uploads directory");
    }
    echo "Created uploads directory<br>";
}

// Create payments directory if it doesn't exist
if (!is_dir($paymentsDir)) {
    if (!mkdir($paymentsDir, 0755, true)) {
        die("Failed to create payments directory");
    }
    echo "Created payments directory<br>";
}

// Create logs directory if it doesn't exist
if (!is_dir($logsDir)) {
    if (!mkdir($logsDir, 0755, true)) {
        die("Failed to create logs directory");
    }
    echo "Created logs directory<br>";
}

echo "Directory structure created successfully!";
?>