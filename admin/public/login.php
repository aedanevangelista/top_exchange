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
            // Use admin-specific session variables with prefix
            $_SESSION['admin_user_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_role'] = $user['role'];

            // Updated redirect path
            header("Location: /admin/public/pages/dashboard.php");
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
<style>
    .formHeading {
    margin-bottom: 40px;
    text-align: center;
    display: flex;
    flex-direction: column;
    
}

.h1{
font-size: 80px;
font-weight: 800;
}

.h2{
    font-size: 64px;
    font-weight: 800;
}

.h3{
    font-size: 24px;
    font-weight: 800;
}

.h4{
    font-size: 16px;
    font-weight: 800;
}



a:visited{ color: white }

body {
    font-family: 'Tahoma';
    background-color: #ffffff;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    margin: 0;
}

form {
    display: flex;
    flex-direction: column;
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
    width: 440px;
    text-align: start;
}

input, button {
    width: 100%;
    padding: 10px;
    margin: 10px 0;
    border: 1px solid #ccc;
    border-radius: 5px;
    box-sizing: border-box;
}

button {
    background: #000000;
    color: white;
    padding: 10px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

button:hover {
    background: #333333;
}

label {
    font-size: 12px;
    font-weight: 700;
}

.excerptOne {
    font-size: 16px;
    color: #646464;
}

.backHome{
    width: auto;
    padding: 8px;
    text-align: center;
    text-decoration: none;
    font-size: 10px;
}

.backHome a {
    color: #000000;
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
                <div class="backHome"><a href="/LandingPage/index.php">Back to Home</a></div>
            </form>
            

        </div>
    </div>
    <!-- Removed reference to non-existent JS file -->
</body>
</html>