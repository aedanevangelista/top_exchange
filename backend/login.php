<?php
session_start();
include "db_connection.php"; // Ensure correct file name

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Prepare SQL statement
    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // **Direct password comparison**
        if ($password == $user['password']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header("Location: http://localhost/top_exchange/public/pages/dashboard.php");
            exit();
        } else {
            $_SESSION['error'] = "Incorrect password. Please try again.";
            header("Location: http://localhost/top_exchange/public/login.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "User not found.";
        header("Location: http://localhost/top_exchange/public/login.php");
        exit();
    }

    $stmt->close();
}

$conn->close();
?>
