<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

require_once '../db_connect.php';

header('Content-Type: application/json');

if (!isset($_GET['archive_id']) || empty($_GET['archive_id'])) {
    echo json_encode(['success' => false, 'error' => 'Archive ID is required']);
    exit;
}

$archiveId = (int)$_GET['archive_id'];

try {
    $stmt = $conn->prepare("SELECT * FROM archived_clients WHERE archive_id = ?");
    $stmt->bind_param("i", $archiveId);
    $stmt->execute();
    $result = $stmt->get_result();
    $client = $result->fetch_assoc();
    
    if ($client) {
        echo json_encode(['success' => true, 'data' => $client]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Archived client not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
