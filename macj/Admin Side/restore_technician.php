<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

require_once '../db_connect.php';
require_once 'archive_functions.php';

header('Content-Type: application/json');

// Check if archive_id is provided
if (!isset($_GET['archive_id']) || empty($_GET['archive_id'])) {
    echo json_encode(['success' => false, 'error' => 'Archive ID is required']);
    exit;
}

$archiveId = intval($_GET['archive_id']);

try {
    $result = restoreTechnician($conn, $archiveId);
    
    if ($result['success']) {
        echo json_encode(['success' => true, 'message' => 'Technician restored successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
