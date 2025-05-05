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

// Check if technician_id is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'Technician ID is required']);
    exit;
}

$technicianId = intval($_GET['id']);

try {
    $result = archiveTechnician($conn, $technicianId);
    
    if ($result['success']) {
        echo json_encode(['success' => true, 'message' => 'Technician archived successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
