<?php
session_start();
require_once 'db_connect.php';
require_once 'db_config.php'; // Add PDO connection
require_once 'notification_functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['role'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Determine user type and ID
$user_type = $_SESSION['role'];
$user_id = null;

if ($user_type === 'client') {
    $user_id = $_SESSION['client_id'];
} elseif ($user_type === 'technician') {
    $user_id = $_SESSION['user_id'];
    $user_type = 'technician'; // Explicitly set user_type to technician
} elseif ($user_type === 'office_staff') {
    $user_type = 'admin'; // Map office_staff to admin for notifications
    $user_id = $_SESSION['user_id'];
} else {
    echo json_encode(['error' => 'Invalid user type']);
    exit;
}

// Log the user type and ID for debugging
error_log("get_notifications.php: user_type=$user_type, user_id=$user_id");

// Get notifications
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';

// Try to use PDO connection first, fall back to mysqli if needed
$db_connection = isset($pdo) ? $pdo : $conn;

$notifications = getNotifications($user_id, $user_type, $limit, $unread_only, $db_connection);
$unread_count = getUnreadNotificationsCount($user_id, $user_type, $db_connection);

echo json_encode([
    'notifications' => $notifications,
    'unread_count' => $unread_count
]);
?>
