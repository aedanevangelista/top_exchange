<?php
session_start();
include "../backend/db_connection.php"; // Ensure correct path

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize input
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT id, username, password, role FROM accounts WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if ($password === $user['password']) {
            // Store session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Redirect to pages/dashboard.php
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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="/public_html/public/css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="form-content">
            <div class="formHeading">
                <span class="h3">WELCOME BACK 👋</span> <br/>
                <span class="excerptOne">Enter your username and password to continue.</span>
            </div>

            <!-- Display error messages -->
            <?php if (isset($_SESSION['error'])): ?>
                <p style="color: red; text-align: center; font-weight: bold;">
                    <?= htmlspecialchars($_SESSION['error']); ?>
                </p>
                <?php unset($_SESSION['error']); // Clear the error after displaying ?>
            <?php endif; ?>

            <!-- Login Form -->
            <form class="loginForm" action="/top_exchange/public/login.php" method="POST">
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
    <script src="/top_exchange/public/js/login.js"></script>
</body>
</html>
