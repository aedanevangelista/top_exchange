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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['archive_id']) || empty($_POST['archive_id'])) {
    echo json_encode(['success' => false, 'error' => 'Archive ID is required']);
    exit;
}

$archiveId = (int)$_POST['archive_id'];

try {
    $result = restoreTool($conn, $archiveId);
    
    if ($result['success']) {
        echo json_encode(['success' => true, 'message' => 'Tool restored successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
