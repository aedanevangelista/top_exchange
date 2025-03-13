<?php
include "db_connection.php";

function generatePONumber($username, $conn) {
    // Fetch the last PO number for the user
    $stmt = $conn->prepare("SELECT po_number FROM orders WHERE username = ? ORDER BY po_number DESC LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($lastPONumber);
    $stmt->fetch();
    $stmt->close();

    // Generate new PO number
    if ($lastPONumber) {
        $number = (int)substr($lastPONumber, strrpos($lastPONumber, '-') + 1) + 1;
    } else {
        $number = 1;
    }
    return $username . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
}

// For AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $username = $_POST['username'];
    echo generatePONumber($username, $conn);
}
?>