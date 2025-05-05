<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

require_once '../db_config.php';
require_once 'archive_functions.php';

header('Content-Type: application/json');

if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'error' => 'Chemical ID is required']);
    exit;
}

$chemicalId = (int)$_POST['id'];

try {
    // Archive the chemical instead of deleting it
    $result = archiveChemical($pdo, $chemicalId);

    if ($result['success']) {
        echo json_encode(['success' => true, 'message' => 'Chemical archived successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}