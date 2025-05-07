<?php
session_start();
require_once '../db_connect.php';
require_once '../notification_functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = $_POST['appointment_id'];
    // Automatically set the current time as the end_time
    $end_time = date('H:i:s');
    $area = $_POST['area'];
    $notes = $_POST['notes'];
    $recommendation = $_POST['recommendation'] ?? '';
    $problem_area = $_POST['problem_area'] ?? '';
    // Process pest types, including the "Other" field if specified
    $pest_types = [];
    if (isset($_POST['pest_types'])) {
        $pest_types = $_POST['pest_types'];

        // If "Others" is selected and other_pest_type is provided, replace "Others" with the specific value
        if (in_array('Others', $pest_types) && !empty($_POST['other_pest_type'])) {
            $otherIndex = array_search('Others', $pest_types);
            $pest_types[$otherIndex] = 'Others: ' . $_POST['other_pest_type'];
        }
    }
    $pest_types = implode(', ', $pest_types);
    $attachments = [];

    // Handle file uploads
    if (!empty($_FILES['attachments'])) {
        $uploadDir = '../uploads/';
        foreach ($_FILES['attachments']['tmp_name'] as $key => $tmpName) {
            $fileName = uniqid() . '_' . basename($_FILES['attachments']['name'][$key]);
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($tmpName, $targetPath)) {
                $attachments[] = $fileName;
            }
        }
    }

    // Insert report
    $stmt = $conn->prepare("
        INSERT INTO assessment_report
        (appointment_id, end_time, area, notes, recommendation, attachments, pest_types, problem_area)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $attachmentsStr = implode(',', $attachments);
    $stmt->bind_param("isssssss", $appointment_id, $end_time, $area, $notes, $recommendation, $attachmentsStr, $pest_types, $problem_area);

    if ($stmt->execute()) {
        // Get the report ID
        $report_id = $conn->insert_id;

        // Get client and technician information
        $infoQuery = $conn->prepare("SELECT a.client_id, t.username, t.technician_id
                                  FROM appointments a
                                  JOIN technicians t ON a.technician_id = t.technician_id
                                  WHERE a.appointment_id = ?");
        $infoQuery->bind_param("i", $appointment_id);
        $infoQuery->execute();
        $result = $infoQuery->get_result();
        $info = $result->fetch_assoc();

        // Update appointment status to completed
        $conn->query("UPDATE appointments SET status = 'completed' WHERE appointment_id = $appointment_id");

        // Create notification for client about the report
        if (isset($info['client_id'])) {
            notifyClientAboutReport(
                $info['client_id'],
                $appointment_id,
                $report_id
            );
        }

        // Create notification for admin about the report
        // Get all admin IDs (office staff)
        $adminQuery = $conn->query("SELECT staff_id FROM office_staff");
        while ($admin = $adminQuery->fetch_assoc()) {
            notifyAdminAboutNewReport(
                $admin['staff_id'],
                $report_id,
                $info['username'] ?? 'Technician'
            );
        }

        echo json_encode(['success' => true, 'report_id' => $report_id]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
}
?>