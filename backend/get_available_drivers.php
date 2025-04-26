<?php
session_start();
include "db_connection.php";
include "check_role.php";

header('Content-Type: application/json');

// Check if the user is logged in
if (!isset($_SESSION['admin_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Fetch available drivers
$drivers = [];
$sql = "SELECT id, name, area, current_deliveries FROM drivers 
        WHERE availability = 'Available' AND current_deliveries < 20
        ORDER BY name ASC";

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $drivers[] = $row;
    }
    echo json_encode(['success' => true, 'drivers' => $drivers]);
} else {
    echo json_encode(['success' => true, 'drivers' => []]);
}
?>