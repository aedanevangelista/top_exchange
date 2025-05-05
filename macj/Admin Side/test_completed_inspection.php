<?php
// This is a test script to verify that technicians cannot be assigned to completed inspections
require_once '../db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get a completed inspection ID
$query = "SELECT a.appointment_id 
          FROM appointments a 
          JOIN assessment_report ar ON a.appointment_id = ar.appointment_id 
          WHERE a.status = 'completed' 
          LIMIT 1";

$result = $conn->query($query);

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'No completed inspections found for testing'
    ]);
    exit;
}

$row = $result->fetch_assoc();
$appointmentId = $row['appointment_id'];

// Try to assign a technician to this completed inspection
$data = [
    'appointment_id' => $appointmentId,
    'technician_id' => 1, // Using technician ID 1 for testing
    'is_primary' => true
];

// Make a request to assign_technician_new.php
$ch = curl_init('http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/assign_technician_new.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Parse the response
$responseData = json_decode($response, true);

// Output the test results
echo json_encode([
    'test_name' => 'Assign technician to completed inspection',
    'expected_result' => 'Error: Cannot assign technicians to a completed inspection',
    'appointment_id' => $appointmentId,
    'http_code' => $httpCode,
    'response' => $responseData,
    'test_passed' => isset($responseData['success']) && $responseData['success'] === false && 
                    strpos($responseData['message'], 'Cannot assign technicians to a completed inspection') !== false
]);
?>
