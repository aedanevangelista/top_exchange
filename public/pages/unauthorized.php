<?php
session_start(); // Start the session to check for logged-in status
include "../../backend/db_connection.php"; // Include database connection

// Function to check if user has access to dashboard
function hasAccessToDashboard() {
    global $conn;
    
    // If not logged in, definitely no access
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return false;
    }
    
    $userRole = $_SESSION['role'];
    
    // Fetch pages for the user role
    $stmt = $conn->prepare("SELECT pages FROM roles WHERE role_name = ? AND status = 'active'");
    $stmt->bind_param("s", $userRole);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $pages = $row['pages'];
        // Check if Dashboard is in the list of allowed pages
        return strpos($pages, 'Dashboard') !== false;
    }
    
    return false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access</title>
    <link rel="stylesheet" href="../css/unauthorized.css">
</head>
<body>
    <div class="unauthorized-container">
        <div class="error-section">
            <h1 class="error-code">ERROR 401</h1>
            <h2>Unauthorized Access</h2>
            <p>You do not have permission to access this page.</p>
        </div>
        <?php if(hasAccessToDashboard()): ?>
            <!-- Show dashboard link if user has dashboard access -->
            <a href="/top_exchange/public/pages/dashboard.php" class="back-to-dashboard">Go to Dashboard</a>
        <?php else: ?>
            <!-- Show login link if user has no dashboard access -->
            <a href="/top_exchange/public/login.php" class="back-to-dashboard">Go to Login</a>
        <?php endif; ?>
    </div>
</body>
</html>