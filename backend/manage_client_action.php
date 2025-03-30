<?php
session_start();
include "db_connection.php";
include "check_role.php";
checkRole(['admin']); // Only admins can access

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $action = $_POST['action'];

    if ($action == 'accept') {
        $stmt = $conn->prepare("UPDATE clients SET status = 'accepted' WHERE id = ?");
    } elseif ($action == 'decline') {
        $stmt = $conn->prepare("UPDATE clients SET status = 'declined' WHERE id = ?");
    }

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Client registration has been " . ($action == 'accept' ? "accepted." : "declined.");
    } else {
        $_SESSION['error'] = "Failed to update client registration status. Please try again.";
    }

    $stmt->close();
    $conn->close();

    header("Location: /public/pages/manage_clients.php");
}
?>