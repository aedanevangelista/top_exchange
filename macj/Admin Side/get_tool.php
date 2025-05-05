<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

require_once '../db_connect.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'Tool ID is required']);
    exit;
}

$id = (int)$_GET['id'];

try {
    $stmt = $conn->prepare("SELECT * FROM tools_equipment WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();
    $tool = $result->fetch_assoc();

    if ($tool) {
        echo json_encode(['success' => true, 'data' => $tool]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Tool not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
