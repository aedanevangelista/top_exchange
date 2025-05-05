<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../db_connect.php';
require_once '../notification_functions.php';

// Set the content type to JSON
header('Content-Type: application/json');

// Get the request data
$requestData = json_decode(file_get_contents('php://input'), true);

// Check if all required fields are present
if (!isset($requestData['appointment_id']) || !isset($requestData['new_date']) || !isset($requestData['new_time'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Extract the data
$appointmentId = intval($requestData['appointment_id']);
$newDate = $requestData['new_date'];
$newTime = $requestData['new_time'];

// Validate the date and time
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $newTime)) {
    echo json_encode(['success' => false, 'message' => 'Invalid time format']);
    exit;
}

// Get current date and time in Asia/Manila timezone
$currentDateTime = new DateTime('now', new DateTimeZone('Asia/Manila'));
$selectedDateTime = new DateTime($newDate . ' ' . $newTime, new DateTimeZone('Asia/Manila'));
$todayDate = new DateTime('today', new DateTimeZone('Asia/Manila'));

// Check if the selected date is in the past
if ($newDate < $todayDate->format('Y-m-d')) {
    echo json_encode(['success' => false, 'message' => 'Cannot reschedule for a past date']);
    exit;
}

// If rescheduling for today, check if the time is in the future with proper interval
if ($newDate == $todayDate->format('Y-m-d')) {
    // Current time plus 30 minutes, rounded up to the next 30-minute interval
    $minAllowedTime = clone $currentDateTime;
    $minutes = (int)$minAllowedTime->format('i');
    $roundedMinutes = $minutes < 30 ? 30 : 60;
    $minAllowedTime->setTime(
        (int)$minAllowedTime->format('H'),
        $roundedMinutes - $minutes,
        0
    );

    // Check if selected time is at least at the next 30-minute interval
    if ($selectedDateTime < $minAllowedTime) {
        $minTimeStr = $minAllowedTime->format('g:i A');
        echo json_encode(['success' => false, 'message' => "When rescheduling for today, the time must be at least 30 minutes after current time, rounded to the next 30-minute interval ($minTimeStr onwards)"]);
        exit;
    }
}

try {
    // Start a transaction
    $conn->begin_transaction();

    // Get the current appointment details for notification purposes
    $stmt = $conn->prepare("SELECT client_id, client_name, preferred_date, preferred_time, technician_id, status FROM appointments WHERE appointment_id = ?");
    $stmt->bind_param("i", $appointmentId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit;
    }

    $appointment = $result->fetch_assoc();
    $clientId = $appointment['client_id'];
    $clientName = $appointment['client_name'];
    $oldDate = $appointment['preferred_date'];
    $oldTime = $appointment['preferred_time'];
    $technicianId = $appointment['technician_id'];
    $currentStatus = $appointment['status'];

    // Check if the appointment is already completed
    if ($currentStatus === 'completed') {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Completed appointments cannot be rescheduled']);
        exit;
    }

    // Update the appointment with the new date and time
    $stmt = $conn->prepare("UPDATE appointments SET preferred_date = ?, preferred_time = ?, status = 'rescheduled' WHERE appointment_id = ?");
    $stmt->bind_param("ssi", $newDate, $newTime, $appointmentId);
    $result = $stmt->execute();

    if (!$result) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to update appointment: ' . $conn->error]);
        exit;
    }

    // Format dates for notifications
    $oldDateFormatted = date('F j, Y', strtotime($oldDate));
    $oldTimeFormatted = date('g:i A', strtotime($oldTime));
    $newDateFormatted = date('F j, Y', strtotime($newDate));
    $newTimeFormatted = date('g:i A', strtotime($newTime));

    // Create notification for the client
    $clientTitle = "Appointment Rescheduled";
    $clientMessage = "Your appointment on {$oldDateFormatted} at {$oldTimeFormatted} has been rescheduled to {$newDateFormatted} at {$newTimeFormatted}.";
    $clientNotificationSent = createNotification(
        $clientId,
        'client',
        $clientTitle,
        $clientMessage,
        $appointmentId,
        'appointment_rescheduled'
    );

    // Create notification for the technician if assigned
    $technicianNotificationSent = false;
    if ($technicianId) {
        $techTitle = "Appointment Rescheduled";
        $techMessage = "The appointment for {$clientName} on {$oldDateFormatted} at {$oldTimeFormatted} has been rescheduled to {$newDateFormatted} at {$newTimeFormatted}.";
        $technicianNotificationSent = createNotification(
            $technicianId,
            'technician',
            $techTitle,
            $techMessage,
            $appointmentId,
            'appointment_rescheduled'
        );
    }

    // Commit the transaction
    $conn->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Appointment rescheduled successfully',
        'appointment_id' => $appointmentId,
        'new_date' => $newDate,
        'new_time' => $newTime,
        'client_notification_sent' => $clientNotificationSent,
        'technician_notification_sent' => $technicianNotificationSent
    ]);

} catch (Exception $e) {
    // Rollback the transaction in case of error
    $conn->rollback();

    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
