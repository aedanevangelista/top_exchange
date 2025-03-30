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
    <title>Admin Login</title>
    <!-- Fixed CSS path to use relative path -->
    <link rel="stylesheet" href="css/login.css">
    <style>
    /* Including basic styling in case CSS file still has issues */
    .formHeading {
        margin-bottom: 40px;
        text-align: center;
        display: flex;
        flex-direction: column;
    }
    
    .h3{
        font-size: 24px;
        font-weight: 800;
    }
    
    body {
        font-family: 'Tahoma', Arial, sans-serif;
        background-color: #ffffff;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        margin: 0;
    }
    
    .login-container {
        max-width: 500px;
        width: 100%;
        padding: 20px;
    }
    
    .form-content {
        background: white;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .loginForm {
        display: flex;
        flex-direction: column;
    }
    
    input, button {
        width: 100%;
        padding: 12px;
        margin: 10px 0;
        border: 1px solid #ddd;
        border-radius: 5px;
        box-sizing: border-box;
    }
    
    button {
        background: #000000;
        color: white;
        padding: 12px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-weight: bold;
        margin-top: 20px;
    }
    
    button:hover {
        background: #333333;
    }
    
    label {
        font-size: 14px;
        font-weight: 700;
    }
    
    .excerptOne {
        font-size: 16px;
        color: #646464;
    }
    
    a {
        display: block;
        text-align: center;
        margin-top: 20px;
        color: #646464;
        text-decoration: none;
    }
    
    a:hover {
        color: #000;
    }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="form-content">
            <div class="formHeading">
                <span class="h3">ADMIN LOGIN 👋</span> <br/>
                <span class="excerptOne">Enter your username and password to continue.</span>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <p style="color: red; text-align: center; font-weight: bold;">
                    <?= htmlspecialchars($_SESSION['error']); ?>
                </p>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Updated form action to use relative path -->
            <form class="loginForm" action="login.php" method="POST">
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter your username" required>
                <br/>

                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
                <br/>

                <button type="submit" class="button">Sign in</button>
            </form>
            
            <a href="../LandingPage/index.php">Back to Home</a>
        </div>
    </div>
    <!-- Removed reference to non-existent JS file -->
</body>
</html>