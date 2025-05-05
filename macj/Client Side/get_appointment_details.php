<?php
session_start();
require_once '../db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['client_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if appointment ID is provided
if (!isset($_GET['appointment_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Appointment ID is required']);
    exit;
}

$appointmentId = $_GET['appointment_id'];
$clientId = $_SESSION['client_id'];

// Fetch appointment details
$stmt = $conn->prepare("
    SELECT
        a.*,
        t.username as technician_name,
        t.tech_contact_number as technician_contact,
        t.tech_fname as technician_fname,
        t.tech_lname as technician_lname,
        t.technician_picture,
        ar.end_time,
        ar.area,
        ar.notes as report_notes,
        ar.recommendation,
        ar.attachments,
        ar.created_at as report_date,
        ar.report_id,
        ar.pest_types,
        ar.problem_area,
        tf.feedback_id,
        tf.rating,
        tf.comments as feedback_comments,
        tf.created_at as feedback_date,
        tf.technician_arrived,
        tf.job_completed,
        tf.verification_notes
    FROM appointments a
    LEFT JOIN technicians t ON a.technician_id = t.technician_id
    LEFT JOIN assessment_report ar ON a.appointment_id = ar.appointment_id
    LEFT JOIN technician_feedback tf ON ar.report_id = tf.report_id AND tf.client_id = ?
    WHERE a.appointment_id = ? AND a.client_id = ?
");

$stmt->bind_param("iii", $clientId, $appointmentId, $clientId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
    exit;
}

$appointment = $result->fetch_assoc();

// Return appointment details
header('Content-Type: application/json');
echo json_encode(['success' => true, 'appointment' => $appointment]);
?>
