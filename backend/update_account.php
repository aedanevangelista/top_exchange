<?php
session_start();
include "db_connection.php";
include "check_role.php";
checkRole('update_account.php'); // Check access for current page

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    $account_id = $_POST['account_id'];
    $username = trim($_POST['username']);
    $password = $_POST['password']; // No hashing (as requested)
    $role = $_POST['role'];

    // Check if username is already taken (excluding current account)
    $checkStmt = $conn->prepare("SELECT id FROM accounts WHERE username = ? AND id != ?");
    $checkStmt->bind_param("si", $username, $account_id);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        $checkStmt->close();
        exit;
    }
    $checkStmt->close();

    // Update account
    $stmt = $conn->prepare("UPDATE accounts SET username = ?, password = ?, role = ? WHERE id = ?");
    $stmt->bind_param("sssi", $username, $password, $role, $account_id);    

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Account updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating account']);
    }

    $stmt->close();
    exit;
}
?>