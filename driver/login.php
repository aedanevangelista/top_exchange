<?php
session_start();
// Adjust the path based on the final location of db_connection.php relative to driver/login.php
// Assuming 'backend' is two levels up from 'driver'
require_once __DIR__ . '/../../backend/db_connection.php';

// If driver is already logged in, redirect to their dashboard
if (isset($_SESSION['driver_id'])) {
    header("Location: dashboard.php"); // Assuming dashboard.php will be in the same 'driver' folder
    exit();
}

$error_message = '';
$username_attempt = ''; // To repopulate username field on error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $username_attempt = $username; // Store for repopulation

    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
    } else {
        // Prepare statement to fetch driver by username AND check if account is active
        $stmt = $conn->prepare("SELECT id, password, name FROM drivers WHERE username = ? AND status = 'Active'");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $driver = $result->fetch_assoc();

                // Verify the password
                if (password_verify($password, $driver['password'])) {
                    // Password is correct, start session
                    session_regenerate_id(true); // Prevent session fixation

                    $_SESSION['driver_id'] = $driver['id'];
                    $_SESSION['driver_username'] = $username;
                    $_SESSION['driver_name'] = $driver['name'];
                    $_SESSION['driver_logged_in'] = true; // Simple flag

                    // Redirect to the driver dashboard
                    header("Location: dashboard.php");
                    exit();
                } else {
                    // Invalid password
                    $error_message = 'Invalid username or password.';
                }
            } else {
                // No user found with that username or account is not active
                $error_message = 'Invalid username or password.';
            }
            $stmt->close();
        } else {
            // Database error
            error_log("Driver Login Prepare Error: " . $conn->error);
            $error_message = 'An internal error occurred. Please try again later.';
        }
    }
    $conn->close(); // Close connection after processing
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Login - Top Exchange</title>
    <link rel="stylesheet" href="css/driver_login.css">
    <!-- Optional: Add link to Font Awesome if you want icons -->
    <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> -->
</head>
<body>
    <div class="login-container">
        <h1>Driver Portal Login</h1>
        <p class="tagline">Access your delivery schedule.</p>

        <?php if (!empty($error_message)): ?>
            <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST" novalidate>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($username_attempt) ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="login-button">Login</button>
        </form>
         <div class="footer-note">
            Top Exchange &copy; <?= date("Y") ?>
        </div>
    </div>
</body>
</html>