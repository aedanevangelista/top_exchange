<?php
session_start();
include "../backend/db_connection.php";

// For debugging
$fullUrl = "http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
echo "<script>console.log('Full URL: " . addslashes($fullUrl) . "');</script>";

// Define the base path for your assets
define('BASE_PATH', '');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT id, username, password, role FROM accounts WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if ($password === $user['password']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Updated redirect path
            header("Location: /pages/dashboard.php");
            exit();
        } else {
            $_SESSION['error'] = "Incorrect password. Please try again.";
            header("Location: /login.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "User not found.";
        header("Location: /login.php");
        exit();
    }

    $stmt->close();
}
$conn->close();
?>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <!-- Updated CSS path to use absolute path from domain root -->
    <link rel="stylesheet" href="https://topexchangefood.com/css/styles.css">
</head>
<body>
    <div class="login-container">
        <div class="form-content">
            <div class="formHeading">
                <span class="h3">WELCOME BACK test 👋</span> <br/>
                <span class="excerptOne">Enter your username and password to continue.</span>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <p style="color: red; text-align: center; font-weight: bold;">
                    <?= htmlspecialchars($_SESSION['error']); ?>
                </p>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Updated form action to use absolute path -->
            <form class="loginForm" action="/login.php" method="POST">
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter your username" required>
                <br/>

                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
                <br/>

                <button type="submit" class="button">Sign in</button>
            </form>
        </div>
    </div>
    <!-- Updated JavaScript path to use absolute path from domain root -->
    <script src="https://topexchangefood.com/js/script.js"></script>
</body>
</html>