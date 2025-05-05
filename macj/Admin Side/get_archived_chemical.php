<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

require_once '../db_config.php';

header('Content-Type: application/json');

if (!isset($_GET['archive_id']) || empty($_GET['archive_id'])) {
    echo json_encode(['success' => false, 'error' => 'Archive ID is required']);
    exit;
}

$archiveId = (int)$_GET['archive_id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM archived_chemical_inventory WHERE archive_id = ?");
    $stmt->execute([$archiveId]);
    $chemical = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($chemical) {
        echo json_encode(['success' => true, 'data' => $chemical]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Archived chemical not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
