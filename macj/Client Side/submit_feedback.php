<?php
session_start();
require_once '../db_connect.php';
require_once '../notification_functions.php';

// Check if user is logged in
if (!isset($_SESSION['client_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check required fields
if (empty($_POST['report_id']) || empty($_POST['rating']) || !isset($_POST['technician_arrived']) || !isset($_POST['job_completed'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$client_id = $_SESSION['client_id'];
$report_id = intval($_POST['report_id']);
$rating = intval($_POST['rating']);
$comments = isset($_POST['comments']) ? $_POST['comments'] : '';
$technician_arrived = isset($_POST['technician_arrived']) ? 1 : 0;
$job_completed = isset($_POST['job_completed']) ? 1 : 0;
$verification_notes = isset($_POST['verification_notes']) ? $_POST['verification_notes'] : '';

// Validate rating (0-5)
if ($rating < 0 || $rating > 5) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid rating value']);
    exit;
}

// Check if feedback already exists for this report
$check_stmt = $conn->prepare("SELECT feedback_id FROM technician_feedback WHERE report_id = ? AND client_id = ?");
$check_stmt->bind_param("ii", $report_id, $client_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Feedback already submitted for this report']);
    exit;
}

// Get technician_id from the report
$tech_stmt = $conn->prepare("
    SELECT a.technician_id
    FROM assessment_report ar
    JOIN appointments a ON ar.appointment_id = a.appointment_id
    WHERE ar.report_id = ?
");
$tech_stmt->bind_param("i", $report_id);
$tech_stmt->execute();
$tech_result = $tech_stmt->get_result();

if ($tech_result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Report not found']);
    exit;
}

$tech_data = $tech_result->fetch_assoc();
$technician_id = $tech_data['technician_id'];

try {
    // Insert feedback with verification fields
    $stmt = $conn->prepare("INSERT INTO technician_feedback (report_id, client_id, technician_id, rating, comments, technician_arrived, job_completed, verification_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiisiss", $report_id, $client_id, $technician_id, $rating, $comments, $technician_arrived, $job_completed, $verification_notes);
    $result = $stmt->execute();

    if ($result) {
        // Update the end_time in assessment_report if it's not already set
        $current_time = date('H:i:s');
        $update_stmt = $conn->prepare("UPDATE assessment_report SET end_time = ? WHERE report_id = ? AND (end_time IS NULL OR end_time = '00:00:00')");
        $update_stmt->bind_param("si", $current_time, $report_id);
        $update_stmt->execute();

        // Get appointment and client details for notification
        $app_stmt = $conn->prepare("
            SELECT
                a.appointment_id,
                a.client_name,
                ar.area,
                t.tech_fname,
                t.tech_lname
            FROM assessment_report ar
            JOIN appointments a ON ar.appointment_id = a.appointment_id
            LEFT JOIN technicians t ON a.technician_id = t.technician_id
            WHERE ar.report_id = ?
        ");
        $app_stmt->bind_param("i", $report_id);
        $app_stmt->execute();
        $app_result = $app_stmt->get_result();
        $app_data = $app_result->fetch_assoc();
        $appointment_id = $app_data ? $app_data['appointment_id'] : null;

        // Create notification for admin users when client validates technician's work
        if ($appointment_id && $technician_arrived == 1 && $job_completed == 1) {
            // Get client name and area from the appointment data
            $client_name = $app_data['client_name'];
            $area = $app_data['area'];
            $tech_name = $app_data['tech_fname'] . ' ' . $app_data['tech_lname'];

            // Create notification title and message
            $title = "Technician Work Verified";
            $message = "Client {$client_name} has verified technician {$tech_name}'s work for the inspection at {$area}.";

            // Get all admin users
            $admin_query = $conn->query("SELECT staff_id FROM office_staff");
            if ($admin_query && $admin_query->num_rows > 0) {
                while ($admin_row = $admin_query->fetch_assoc()) {
                    $admin_id = $admin_row['staff_id'];
                    // Create notification for each admin
                    createNotification(
                        $admin_id,
                        'admin',
                        $title,
                        $message,
                        $report_id,
                        'report',
                        $conn
                    );
                }
            }
        }

        // Get sort parameter if provided
        $sort = isset($_POST['sort']) ? $_POST['sort'] : 'date_desc';

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Feedback submitted successfully',
            'appointment_id' => $appointment_id,
            'sort' => $sort
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to submit feedback']);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
