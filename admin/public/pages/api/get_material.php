<?php
session_start();
include "../../../admin/backend/db_connection.php";
include "../../../admin/backend/check_role.php";

header('Content-Type: application/json');

if (!isset($_SESSION['admin_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid material ID']);
    exit();
}

$material_id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT material_id, name, stock_quantity FROM raw_materials WHERE material_id = ?");
$stmt->bind_param("i", $material_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $material = $result->fetch_assoc();
    echo json_encode($material);
} else {
    echo json_encode(['success' => false, 'message' => 'Material not found']);
}

$stmt->close();
?>