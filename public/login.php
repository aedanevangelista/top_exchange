<?php
session_start();
include "../backend/db_connection.php";

// Add this at the top of your file to define the base path
define('BASE_PATH', '/top_exchange');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Rest of your PHP code remains the same
    // ... 
    
    if ($password === $user['password']) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        header("Location: " . BASE_PATH . "/public/pages/dashboard.php");
        exit();
    } else {
        $_SESSION['error'] = "Incorrect password. Please try again.";
        header("Location: " . BASE_PATH . "/public/login.php");
        exit();
    }
    // ...
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <!-- Update CSS path -->
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/public/css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="form-content">
            <div class="formHeading">
                <span class="h3">WELCOME BACK 👋</span> <br/>
                <span class="excerptOne">Enter your username and password to continue.</span>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <p style="color: red; text-align: center; font-weight: bold;">
                    <?= htmlspecialchars($_SESSION['error']); ?>
                </p>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Update form action -->
            <form class="loginForm" action="<?php echo BASE_PATH; ?>/public/login.php" method="POST">
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
    <!-- Update JavaScript path -->
    <script src="<?php echo BASE_PATH; ?>/public/js/login.js"></script>
</body>
</html>