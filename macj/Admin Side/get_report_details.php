<?php
session_start();
require_once '../db_connect.php';

// Check if the user is logged in as admin
if (!isset($_SESSION['staff_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get the report ID from the query string
$report_id = isset($_GET['report_id']) ? intval($_GET['report_id']) : 0;

if ($report_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid report ID']);
    exit;
}

// Fetch the report details
$query = "SELECT ar.report_id, ar.appointment_id, ar.pest_types, ar.area, ar.problem_area, ar.notes, ar.recommendation
          FROM assessment_report ar
          WHERE ar.report_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Report not found']);
    exit;
}

$report = $result->fetch_assoc();

// Get the pest types and area directly from the assessment report
$pest_types = $report['pest_types'];
$area = $report['area'];

// Return the report details as JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'report_id' => $report['report_id'],
    'appointment_id' => $report['appointment_id'],
    'pest_types' => $pest_types,
    'area' => $area,
    'problem_area' => $report['problem_area'],
    'notes' => $report['notes'],
    'recommendation' => $report['recommendation'],
    'source' => 'assessment'
]);
?>
