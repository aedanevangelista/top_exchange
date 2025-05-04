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

if (!isset($_POST['material_id']) || !isset($_POST['name']) || !isset($_POST['stock_quantity'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$material_id = intval($_POST['material_id']);
$name = trim($_POST['name']);
$stock_quantity = floatval($_POST['stock_quantity']);

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Material name cannot be empty']);
    exit();
}

// Check if material name already exists for another material
$stmt = $conn->prepare("SELECT material_id FROM raw_materials WHERE name = ? AND material_id != ?");
$stmt->bind_param("si", $name, $material_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'A material with this name already exists']);
    $stmt->close();
    exit();
}

$stmt->close();

// Update material
$stmt = $conn->prepare("UPDATE raw_materials SET name = ?, stock_quantity = ?, updated_at = NOW() WHERE material_id = ?");
$stmt->bind_param("sdi", $name, $stock_quantity, $material_id);
$success = $stmt->execute();

if ($success) {
    // Check if any rows were affected
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Material updated successfully']);
    } else {
        // The query was successful but no rows changed (data might be the same)
        $check = $conn->prepare("SELECT material_id FROM raw_materials WHERE material_id = ?");
        $check->bind_param("i", $material_id);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Material not found']);
        } else {
            echo json_encode(['success' => true, 'message' => 'No changes were made']);
        }
        $check->close();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update material: ' . $conn->error]);
}

$stmt->close();
?>