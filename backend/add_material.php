<?php
session_start();
include "db_connection.php";
include "check_role.php";

header('Content-Type: application/json');

if (!isset($_SESSION['admin_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if user has permission for Inventory
try {
    checkRole('Inventory');
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to manage inventory']);
    exit();
}

if (!isset($_POST['name']) || !isset($_POST['stock_quantity'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$name = trim($_POST['name']);
$stock_quantity = floatval($_POST['stock_quantity']);

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Material name cannot be empty']);
    exit();
}

// Check if material name already exists
$stmt = $conn->prepare("SELECT material_id FROM raw_materials WHERE name = ?");
$stmt->bind_param("s", $name);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'A material with this name already exists']);
    $stmt->close();
    exit();
}

$stmt->close();

// Insert new material
$stmt = $conn->prepare("INSERT INTO raw_materials (name, stock_quantity) VALUES (?, ?)");
$stmt->bind_param("sd", $name, $stock_quantity);
$success = $stmt->execute();

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Material added successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add material: ' . $conn->error]);
}

$stmt->close();
?>