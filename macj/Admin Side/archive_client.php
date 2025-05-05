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

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

// Check if client_id is provided
if (!isset($data['client_id']) || empty($data['client_id'])) {
    echo json_encode(['success' => false, 'error' => 'Client ID is required']);
    exit;
}

$clientId = intval($data['client_id']);

try {
    $result = archiveClient($conn, $clientId);
    
    if ($result['success']) {
        echo json_encode(['success' => true, 'message' => 'Client archived successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
