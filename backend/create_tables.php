<?php
header('Content-Type: text/html');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Tables Setup</h1>";

try {
    require_once "db_connection.php";
    
    $tables = [
        'monthly_payments' => "CREATE TABLE monthly_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL,
            month INT NOT NULL,
            year INT NOT NULL,
            payment_status VARCHAR(50) DEFAULT 'Unpaid',
            total_amount DECIMAL(15,2) DEFAULT 0,
            remaining_balance DECIMAL(15,2) DEFAULT 0,
            proof_of_payment TEXT,
            notes TEXT,
            created_at DATETIME,
            updated_at DATETIME,
            UNIQUE KEY unique_month_year (username, month, year)
        )",
        
        'payment_history' => "CREATE TABLE payment_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            payment_type VARCHAR(50) NOT NULL,
            reference_month INT,
            reference_year INT,
            proof VARCHAR(255),
            notes TEXT,
            created_at DATETIME,
            updated_at DATETIME
        )"
    ];
    
    // Check clients_accounts table for balance column
    $result = $conn->query("SHOW TABLES LIKE 'clients_accounts'");
    if ($result->num_rows > 0) {
        $columnResult = $conn->query("SHOW COLUMNS FROM clients_accounts LIKE 'balance'");
        if ($columnResult->num_rows == 0) {
            $conn->query("ALTER TABLE clients_accounts ADD COLUMN balance DECIMAL(15,2) DEFAULT 0");
            echo "<p>Added balance column to clients_accounts table.</p>";
        } else {
            echo "<p>Balance column already exists in clients_accounts table.</p>";
        }
    } else {
        echo "<p>Warning: clients_accounts table does not exist. Please create it first.</p>";
    }
    
    // Check and create other tables
    foreach ($tables as $tableName => $createStatement) {
        $result = $conn->query("SHOW TABLES LIKE '$tableName'");
        if ($result->num_rows == 0) {
            if ($conn->query($createStatement)) {
                echo "<p>Table '$tableName' created successfully.</p>";
            } else {
                echo "<p>Error creating table '$tableName': " . $conn->error . "</p>";
            }
        } else {
            echo "<p>Table '$tableName' already exists.</p>";
        }
    }
    
    echo "<p>Database setup completed.</p>";
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>