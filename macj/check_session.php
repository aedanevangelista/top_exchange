<?php
session_start();
header('Content-Type: application/json');

// Return session data for debugging
$session_data = [
    'session_exists' => isset($_SESSION) && !empty($_SESSION),
    'role' => $_SESSION['role'] ?? null,
    'user_id' => $_SESSION['user_id'] ?? null,
    'username' => $_SESSION['username'] ?? null,
    'is_logged_in' => isset($_SESSION['role']),
    'session_id' => session_id(),
    'notification_user_type' => null
];

// Determine notification user type
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'client') {
        $session_data['notification_user_type'] = 'client';
    } elseif ($_SESSION['role'] === 'technician') {
        $session_data['notification_user_type'] = 'technician';
    } elseif ($_SESSION['role'] === 'office_staff') {
        $session_data['notification_user_type'] = 'admin';
    }
}

echo json_encode($session_data);
?>
