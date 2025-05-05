<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: SignIn.php");
    exit;
}
require_once '../db_connect.php';
require_once '../notification_functions.php';

function getTechnicians() {
    global $conn;
    $technicians = array();
    $sql = "SELECT * FROM technicians";
    $result = $conn->query($sql);
    while($row = $result->fetch_assoc()) {
        $technicians[] = $row;
    }
    return $technicians;
}

function getTechniciansWithWorkload($filter = null, $specificDate = null) {
    global $conn;

    // Validate and sanitize specificDate
    if ($filter === 'date' && $specificDate) {
        $specificDate = $conn->real_escape_string($specificDate);
        if (!DateTime::createFromFormat('Y-m-d', $specificDate)) {
            $specificDate = null;
        }
    }

    // Build conditions for appointments
    $appointmentCondition = 'a.preferred_date >= CURDATE()'; // Default upcoming
    if ($filter === 'week') {
        $appointmentCondition = "a.preferred_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY";
    } elseif ($filter === 'month') {
        $appointmentCondition = "a.preferred_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 1 MONTH";
    } elseif ($filter === 'date' && $specificDate) {
        $appointmentCondition = "a.preferred_date = '$specificDate'";
    }

    // Build conditions for job orders
    $jobCondition = 'j.preferred_date >= CURDATE()'; // Default upcoming
    if ($filter === 'week') {
        $jobCondition = "j.preferred_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY";
    } elseif ($filter === 'month') {
        $jobCondition = "j.preferred_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 1 MONTH";
    } elseif ($filter === 'date' && $specificDate) {
        $jobCondition = "j.preferred_date = '$specificDate'";
    }

    $sql = "SELECT t.technician_id, t.username,
        COUNT(DISTINCT a.appointment_id) as appointment_count,
        COUNT(DISTINCT CASE WHEN a.status = 'completed' THEN a.appointment_id END) as completed_count,
        COUNT(DISTINCT j.job_order_id) as job_order_count,
        COUNT(DISTINCT CASE WHEN j.status = 'completed' THEN j.job_order_id END) as completed_job_count
        FROM technicians t
        LEFT JOIN appointments a
            ON t.technician_id = a.technician_id
            AND ($appointmentCondition)
        LEFT JOIN job_order_technicians jot
            ON t.technician_id = jot.technician_id
        LEFT JOIN job_order j
            ON jot.job_order_id = j.job_order_id
            AND ($jobCondition)
            AND (j.client_approval_status IN ('approved', 'one-time'))
        GROUP BY t.technician_id";

    $result = $conn->query($sql);
    $technicians = [];
    while($row = $result->fetch_assoc()) {
        $technicians[] = $row;
    }
    return $technicians;
}

function getCalendarEvents($eventType = null, $statusFilter = null, $timeFilter = null, $specificDate = null) {
    global $conn;
    $events = array();

    // Convert empty strings to null for consistency
    $eventType = ($eventType === '') ? null : $eventType;
    $statusFilter = ($statusFilter === '') ? null : $statusFilter;
    $timeFilter = ($timeFilter === '') ? null : $timeFilter;
    $specificDate = ($specificDate === '') ? null : $specificDate;

    // For debugging
    error_log("Event Type: " . ($eventType ?? 'null') . ", Status Filter: " . ($statusFilter ?? 'null') .
              ", Time Filter: " . ($timeFilter ?? 'null') . ", Specific Date: " . ($specificDate ?? 'null'));

    // Fetch Appointments (if no filter or filter is for appointments)
    if ($eventType === null || $eventType === 'appointments') {
        $sqlAppointments = "SELECT
            a.appointment_id,
            a.client_name,
            a.kind_of_place,
            a.location_address,
            a.preferred_date,
            TIME_FORMAT(a.preferred_time, '%H:%i') as preferred_time,
            a.email,
            a.contact_number,
            a.notes,
            a.pest_problems,
            a.technician_id,
            a.status,
            t.username as technician_name
        FROM appointments a
        LEFT JOIN technicians t ON a.technician_id = t.technician_id";

        // Build WHERE conditions
        $conditions = [];

        // Add status filter for appointments
        if ($statusFilter !== null) {
            if ($statusFilter === 'unassigned') {
                $conditions[] = "a.technician_id IS NULL";
            } else if ($statusFilter === 'assigned') {
                $conditions[] = "a.technician_id IS NOT NULL AND a.status != 'completed'";
            } else if ($statusFilter === 'completed') {
                $conditions[] = "a.status = 'completed'";
            }
        }

        // Add time filter for appointments
        if ($timeFilter !== null) {
            if ($timeFilter === 'week') {
                $conditions[] = "a.preferred_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
            } else if ($timeFilter === 'month') {
                $conditions[] = "a.preferred_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 MONTH)";
            } else if ($timeFilter === 'date' && $specificDate !== null) {
                $conditions[] = "a.preferred_date = '" . $conn->real_escape_string($specificDate) . "'";
            }
        }

        // Combine all conditions
        if (!empty($conditions)) {
            $sqlAppointments .= " WHERE " . implode(" AND ", $conditions);
        }

        // Log the query for debugging
        error_log("Appointments SQL: " . $sqlAppointments);

        $result = $conn->query($sqlAppointments);
        while($row = $result->fetch_assoc()) {
            $color = $row['technician_id'] ? '#3b82f6' : '#ef4444'; // Red for unassigned inspections
            $className = '';
            if ($row['status'] === 'completed') {
                $color = '#1e3a8a'; // Darker blue for completed
                $className = 'fc-event-completed';
            }
            $events[] = [
                'id' => 'appt_'.$row['appointment_id'],
                'title' => $row['client_name']." - ".$row['kind_of_place'],
                'start' => $row['preferred_date'].'T'.$row['preferred_time'],
                'color' => $color,
                'className' => $className,
                'type' => 'appointment',
                'extendedProps' => $row
            ];
        }
    }

    // Fetch Job Orders - only show approved ones (if no filter or filter is for job orders)
    if ($eventType === null || $eventType === 'job_orders') {
        $sqlJobOrders = "SELECT
            j.job_order_id,
            j.type_of_work,
            j.preferred_date,
            TIME_FORMAT(j.preferred_time, '%H:%i') as preferred_time,
            a.client_name,
            a.location_address,
            j.client_approval_status,
            j.status as job_status,
            COUNT(jot.technician_id) as technician_count,
            GROUP_CONCAT(t.username SEPARATOR ', ') as technicians
        FROM job_order j
        LEFT JOIN job_order_technicians jot ON j.job_order_id = jot.job_order_id
        LEFT JOIN technicians t ON jot.technician_id = t.technician_id
        LEFT JOIN assessment_report ar ON j.report_id = ar.report_id
        LEFT JOIN appointments a ON ar.appointment_id = a.appointment_id
        WHERE j.client_approval_status IN ('approved', 'one-time')";

        // Build conditions array
        $conditions = [];

        // Add status filter for job orders
        if ($statusFilter !== null) {
            if ($statusFilter === 'unassigned') {
                $conditions[] = "NOT EXISTS (SELECT 1 FROM job_order_technicians jt WHERE jt.job_order_id = j.job_order_id)";
            } else if ($statusFilter === 'assigned') {
                $conditions[] = "EXISTS (SELECT 1 FROM job_order_technicians jt WHERE jt.job_order_id = j.job_order_id) AND j.status != 'completed'";
            } else if ($statusFilter === 'completed') {
                $conditions[] = "j.status = 'completed'";
            }
        }

        // Add time filter for job orders
        if ($timeFilter !== null) {
            if ($timeFilter === 'week') {
                $conditions[] = "j.preferred_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
            } else if ($timeFilter === 'month') {
                $conditions[] = "j.preferred_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 MONTH)";
            } else if ($timeFilter === 'date' && $specificDate !== null) {
                $conditions[] = "j.preferred_date = '" . $conn->real_escape_string($specificDate) . "'";
            }
        }

        // Add all conditions to the query
        if (!empty($conditions)) {
            $sqlJobOrders .= " AND " . implode(" AND ", $conditions);
        }

        // Add GROUP BY clause
        $sqlJobOrders .= " GROUP BY j.job_order_id";

        // Log the query for debugging
        error_log("Job Orders SQL: " . $sqlJobOrders);

        $result = $conn->query($sqlJobOrders);
        while($row = $result->fetch_assoc()) {
            // Set color based on job status and technician assignment
            $color = '#8B5CF6'; // Purple for unassigned job orders (matching alert)
            $className = '';

            if ($row['job_status'] === 'completed') {
                $color = '#22c55e'; // Green for completed job orders
                $className = 'fc-event-completed';
            } else if ($row['technician_count'] > 0) {
                $color = '#FFFF00'; // Yellow for assigned job orders
            }

            $events[] = [
                'id' => 'job_'.$row['job_order_id'],
                'title' => $row['client_name']." - ".$row['type_of_work'],
                'start' => $row['preferred_date'].'T'.$row['preferred_time'],
                'color' => $color,
                'className' => $className,
                'type' => 'job_order',
                'extendedProps' => $row
            ];
        }
    }

    return $events;
}

$technicians = getTechnicians();
$filter = isset($_GET['filter']) ? $_GET['filter'] : null;
$specificDate = isset($_GET['specificDate']) ? $_GET['specificDate'] : null;
$eventType = isset($_GET['event_type']) ? $_GET['event_type'] : null;
$statusFilter = isset($_GET['status']) ? $_GET['status'] : null;

// Check if any filters are applied
$isFiltering = ($eventType !== '' || $statusFilter !== '' || $filter !== '');

// If no filters are applied, get all events without filtering
if (!$isFiltering) {
    $calendarEvents = getCalendarEvents(null, null, null, null);
} else {
    // Get calendar events with all filters
    $calendarEvents = getCalendarEvents($eventType, $statusFilter, $filter, $specificDate);
}

// Get technician workload
$techniciansWorkload = getTechniciansWithWorkload($filter, $specificDate);

// Debug info
error_log("Total events after filtering: " . count($calendarEvents));

// Dump the first few events for debugging
if (count($calendarEvents) > 0) {
    error_log("First event: " . json_encode(array_slice($calendarEvents, 0, 1)));
} else {
    error_log("WARNING: No events found with the current filters!");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - MacJ Pest Control</title>
    <link rel="stylesheet" href="css/calendar-page.css">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/notifications.css">
    <style>
        /* Additional notification styles for Admin Side */
        .notification-container {
            position: relative;
            margin-right: 20px;
            cursor: pointer;
        }

        .notification-icon {
            font-size: 1.5rem;
            color: var(--primary-color);
            transition: color 0.3s ease;
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #e74c3c;
            color: white;
            font-size: 0.75rem;
            font-weight: bold;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 350px;
            max-height: 400px;
            overflow-y: auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            display: none;
        }

        .notification-dropdown.show {
            display: block;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-name {
            font-weight: 600;
            color: var(--text-color);
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--text-light);
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        /* Make sure the modal is visible when displayed */
        .modal-overlay[style*="display: block"] {
            display: block !important;
        }
        .modal-content {
            background: white;
            width: 600px;
            padding: 20px;
            border-radius: 8px;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-height: 85vh;
            overflow-y: auto;
            z-index: 1001; /* Make sure it's above the overlay */
        }
        .modal-header {
            padding-bottom: 15px;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 {
            margin: 0;
            font-size: 1.2rem;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .close-modal {
            font-size: 1.5rem;
            cursor: pointer;
            color: #9ca3af;
            transition: color 0.2s;
        }
        .close-modal:hover {
            color: #4b5563;
        }
        .detail-section {
            margin-bottom: 15px;
        }
        .detail-section h4 {
            margin: 0 0 10px 0;
            font-size: 0.95rem;
            color: #4b5563;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .detail-row {
            display: flex;
            margin-bottom: 8px;
        }
        .detail-label {
            flex: 0 0 120px;
            font-size: 0.85rem;
            color: #6b7280;
        }
        .detail-value {
            flex: 1;
            font-size: 0.9rem;
            color: #1f2937;
            word-break: break-word;
        }
        .notes-box {
            background: #f9fafb;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            margin-top: 5px;
        }
        #assignSection {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            background-color: #f9fafb;
            padding: 15px;
            border-radius: 8px;
        }

        .assign-form {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }

        #technicianSelect {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .assign-help {
            color: #6b7280;
            font-size: 0.8rem;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-primary {
            background-color: #3b82f6;
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background-color: #2563eb;
        }

        .btn-primary:disabled {
            background-color: #93c5fd;
            cursor: not-allowed;
        }

        .btn-secondary {
            background-color: #f3f4f6;
            color: #4b5563;
            border: 1px solid #d1d5db;
        }

        /* Assigned technician badges */
        .assigned-tech-badge {
            display: inline-flex;
            align-items: center;
            background-color: #e5e7eb;
            color: #4b5563;
            padding: 6px 10px;
            border-radius: 4px;
            margin-right: 8px;
            margin-bottom: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .assigned-tech-badge.primary {
            background-color: #3b82f6;
            color: white;
        }

        .assigned-tech-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .remove-tech {
            margin-left: 8px;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.2s;
        }

        .remove-tech:hover {
            opacity: 1;
        }

        .btn-secondary:hover {
            background-color: #e5e7eb;
            color: #1f2937;
        }
        .modal-actions {
            text-align: right;
            margin-top: 15px;
        }

        /* Assigned technician styles */
        .assigned-tech-info {
            background-color: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 10px;
        }

        .assigned-tech-badge {
            display: inline-flex;
            align-items: center;
            background-color: #0ea5e9;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
            margin-bottom: 8px;
            margin-right: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .assigned-tech-badge i {
            margin-right: 6px;
        }

        .assigned-tech-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .assigned-tech-badge.primary {
            background-color: #2563eb;
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px #2563eb;
        }

        .assigned-tech-badge.primary::after {
            content: "Primary";
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 10px;
            background-color: #2563eb;
            padding: 2px 6px;
            border-radius: 10px;
            white-space: nowrap;
        }

        .assigned-tech-badge .remove-tech {
            margin-left: 8px;
            font-size: 12px;
            opacity: 0.7;
            transition: opacity 0.2s ease;
        }

        .assigned-tech-badge .remove-tech:hover {
            opacity: 1;
        }
        .workload-summary {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        padding: 20px;
        margin-bottom: 30px;
    }

    .workload-summary h3 {
        margin: 0 0 20px 0;
        color: #2d3748;
        font-size: 1.4rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .workload-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 10px;
    }

    .workload-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px;
        transition: transform 0.2s;
    }

    .workload-card:hover {
        transform: translateY(-2px);
    }

    .tech-name {
        font-weight: 600;
        color: #1a365d;
        margin-bottom: 8px;
        font-size: 0.95rem;
        padding-bottom: 6px;
        border-bottom: 1px solid #edf2f7;
    }

    .workload-stats {
        display: flex;
        justify-content: space-around;
        gap: 10px;
    }

    .stat {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 6px;
        font-weight: 500;
    }

    .appointment-stat {
        background: #3b82f6; /* Blue for assigned appointments */
        color: white;
    }

    .completed-stat {
        background: #1e3a8a; /* Dark blue for completed appointments */
        color: white;
    }

    .job-stat {
        background: #FFFF00; /* Yellow for assigned job orders */
        color: #333;
    }

    .unassigned-job-stat {
        background: #FF69B4; /* Pink for unassigned job orders */
        color: white;
    }

    .completed-job-stat {
        background: #22c55e; /* Green for completed job orders */
        color: white;
    }

    .stat i {
        font-size: 0.9em;
    }

    /* Compact Stats Layout */
    .compact-stats {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-top: 10px;
    }

    .stat-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 5px 0;
    }

    .stat-icon {
        font-size: 0.85rem;
        color: #4b5563;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .stat-icon i {
        color: #1e3a8a;
        font-size: 0.9rem;
    }

    .stat-badges {
        display: flex;
        gap: 8px;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .appointment-badge {
        background-color: #3b82f6;
        color: white;
    }

    .completed-badge {
        background-color: #1e3a8a;
        color: white;
    }

    .job-badge {
        background-color: #FFFF00;
        color: #333;
    }

    .completed-job-badge {
        background-color: #22c55e;
        color: white;
    }

    .alerts-container {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-bottom: 15px;
        width: 100%;
        max-width: 800px;
    }

    .unassigned-inspection-alert {
        background: linear-gradient(135deg, #3b82f6, #1e40af); /* Blue gradient matching primary colors */
        color: white;
        padding: 8px 12px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
        box-shadow: 0 3px 6px rgba(0, 0, 0, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: all 0.2s ease;
    }

    .unassigned-inspection-alert i {
        font-size: 1.1rem;
        color: rgba(255, 255, 255, 0.9);
    }

    .unassigned-job-alert {
        background: linear-gradient(135deg, #8B5CF6, #7C3AED); /* Purple gradient matching accent colors */
        color: white;
        padding: 8px 12px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
        box-shadow: 0 3px 6px rgba(0, 0, 0, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: all 0.2s ease;
    }

    .unassigned-job-alert i {
        font-size: 1.1rem;
        color: rgba(255, 255, 255, 0.9);
    }

    .unassigned-inspection-alert:hover,
    .unassigned-job-alert:hover {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.18);
        transform: translateY(-1px);
    }

    .alert-action-link {
        margin-left: auto;
        background-color: rgba(255, 255, 255, 0.15);
        color: white;
        padding: 5px 10px;
        border-radius: 6px;
        text-decoration: none;
        font-size: 0.8rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 5px;
        transition: all 0.2s ease;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .alert-action-link:hover {
        background-color: rgba(255, 255, 255, 0.25);
        color: white;
        transform: translateY(-1px);
    }
    .workload-filter {
        margin-bottom: 15px;
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
        background-color: #f8fafc;
        padding: 12px 15px;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
    }

    .filter-section {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .filter-group label {
        font-size: 0.75rem;
        color: #4b5563;
        font-weight: 500;
    }

    .workload-filter select,
    .workload-filter input[type="date"] {
        padding: 6px 10px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        font-size: 13px;
        min-width: 140px;
    }

    .filter-button {
        padding: 6px 12px;
        background-color: #3b82f6;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 5px;
        margin-left: auto;
        align-self: flex-end;
    }



    .reset-button {
        padding: 6px 12px;
        background-color: #f3f4f6;
        color: #4b5563;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 5px;
        align-self: flex-end;
    }

    .reset-button:hover {
        background-color: #e5e7eb;
        color: #1f2937;
    }

    .no-events-message {
        background-color: #f0f9ff;
        border: 1px solid #bae6fd;
        color: #0369a1;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 10px;
        width: 100%;
    }

    .calendar-empty-message {
        background-color: #f9fafb;
        border: 1px dashed #d1d5db;
        color: #4b5563;
        padding: 40px 20px;
        border-radius: 8px;
        font-size: 1rem;
        text-align: center;
        margin: 20px 0;
    }

    .workload-filter button:hover {
        background-color: #2563eb;
    }
    .fc-event-completed {
        position: relative;
        padding-left: 24px !important;
    }
    .fc-event-completed::before {
        content: '✓';
        position: absolute;
        left: 6px;
        top: 50%;
        transform: translateY(-50%);
        font-weight: bold;
    }
    .completed-stat {
        background: #1e3a8a;
        color: white;
    }

    /* Color Legend Styles */
    .workload-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .workload-header h3 {
        margin: 0;
    }

    .color-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        background-color: #f8fafc;
        padding: 8px 12px;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        align-items: center;
    }

    .legend-title {
        font-weight: 600;
        color: #1f2937;
        margin-right: 5px;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.85rem;
    }

    .color-box {
        width: 16px;
        height: 16px;
        border-radius: 4px;
        display: inline-block;
        border: 1px solid rgba(0, 0, 0, 0.1);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        cursor: help;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .legend-item:hover .color-box {
        transform: scale(1.2);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .legend-text {
        color: #4b5563;
        font-weight: 500;
    }

    /* Compact Legend */
    .compact-legend {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .legend-row {
        display: flex;
        gap: 20px;
        align-items: center;
        flex-wrap: wrap;
    }

    .legend-group {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .legend-label {
        font-weight: 600;
        color: #4b5563;
        font-size: 0.85rem;
    }

    .mini-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 700;
        color: white;
        cursor: help;
    }

    /* Responsive styles for the color legend */
    @media (max-width: 768px) {
        .workload-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .color-legend {
            width: 100%;
            margin-top: 10px;
            justify-content: flex-start;
        }
    }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-title">
            <h1>Admin Dashboard</h1>
        </div>
        <div class="user-menu">
            <!-- Notification Icon -->
            <div class="notification-container">
                <i class="fas fa-bell notification-icon"></i>
                <span class="notification-badge" style="display: none;">0</span>

                <!-- Notification Dropdown -->
                <div class="notification-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <span class="mark-all-read">Mark all as read</span>
                    </div>
                    <ul class="notification-list">
                        <!-- Notifications will be loaded here -->
                    </ul>
                </div>
            </div>

            <div class="user-info">
                <?php
                // Check if profile picture exists
                $staff_id = $_SESSION['user_id'];
                $profile_picture = '';

                // Check if the office_staff table has profile_picture column
                $result = $conn->query("SHOW COLUMNS FROM office_staff LIKE 'profile_picture'");
                if ($result->num_rows > 0) {
                    $stmt = $conn->prepare("SELECT profile_picture FROM office_staff WHERE staff_id = ?");
                    $stmt->bind_param("i", $staff_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        $profile_picture = $row['profile_picture'];
                    }
                }

                $profile_picture_url = !empty($profile_picture)
                    ? "../uploads/admin/" . $profile_picture
                    : "../assets/default-profile.jpg";
                ?>
                <img src="<?php echo $profile_picture_url; ?>" alt="Profile" class="user-avatar" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px;">
                <div>
                    <div class="user-name"><?= $_SESSION['username'] ?? 'Admin' ?></div>
                    <div class="user-role">Administrator</div>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>MacJ Pest Control</h2>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                    <li><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
                    <li class="active"><a href="calendar.php"><i class="fas fa-calendar-alt"></i> Calendar</a></li>
                    <li><a href="assessment_report.php"><i class="fas fa-clipboard-check"></i> Assessment Report</a></li>
                    <li><a href="joborder_report.php"><i class="fas fa-tasks"></i> Job Order Report</a></li>
                    <li><a href="chemical_inventory.php"><i class="fas fa-flask"></i> Chemical Inventory</a></li>
                    <li><a href="tools_equipment.php"><i class="fas fa-tools"></i> Tools and Equipment</a></li>
                    <li><a href="technicians.php"><i class="fas fa-user-md"></i> Technicians</a></li>
                    <li><a href="clients.php"><i class="fas fa-users"></i> Clients</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="../SignOut.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">

            <div class="chemicals-content">
        <div class="workload-summary">
            <div class="workload-header">
                <h3><i class="fas fa-users-cog"></i> Technician Workload Summary</h3>

                <div class="alerts-container">
                    <!-- Unassigned Inspections Alert -->
                    <?php
                    // Get count of unassigned inspections
                    $unassigned_inspections_query = "SELECT COUNT(*) as count FROM appointments
                                                   WHERE technician_id IS NULL
                                                   AND status != 'completed'";
                    $unassigned_inspections_result = $conn->query($unassigned_inspections_query);
                    $unassigned_inspections_row = $unassigned_inspections_result->fetch_assoc();
                    $unassigned_inspections_count = $unassigned_inspections_row['count'];

                    if ($unassigned_inspections_count > 0) {
                        echo "<div class='unassigned-inspection-alert'>";
                        echo "<i class='fas fa-clipboard-list'></i> ";
                        echo "<span>There are <strong>$unassigned_inspections_count</strong> unassigned inspections that need technicians.</span>";
                        echo "<a href='calendar.php?event_type=appointments&status=unassigned' class='alert-action-link'>";
                        echo "<i class='fas fa-filter'></i> Filter Calendar";
                        echo "</a>";
                        echo "</div>";
                    }
                    ?>

                    <!-- Unassigned Job Orders Alert -->
                    <?php
                    // Get count of unassigned job orders
                    $unassigned_query = "SELECT COUNT(*) as count FROM job_order j
                                        LEFT JOIN job_order_technicians jt ON j.job_order_id = jt.job_order_id
                                        WHERE jt.technician_id IS NULL
                                        AND j.client_approval_status IN ('approved', 'one-time')
                                        AND j.status != 'completed'";
                    $unassigned_result = $conn->query($unassigned_query);
                    $unassigned_row = $unassigned_result->fetch_assoc();
                    $unassigned_count = $unassigned_row['count'];

                    if ($unassigned_count > 0) {
                        echo "<div class='unassigned-job-alert'>";
                        echo "<i class='fas fa-tools'></i> ";
                        echo "<span>There are <strong>$unassigned_count</strong> unassigned job orders that need technicians.</span>";
                        echo "<a href='calendar.php?event_type=job_orders&status=unassigned' class='alert-action-link'>";
                        echo "<i class='fas fa-filter'></i> Filter Calendar";
                        echo "</a>";
                        echo "</div>";
                    }
                    ?>
                </div>

                <div class="color-legend">
                    <div class="legend-title">Calendar Color Guide:</div>
                    <div class="compact-legend">
                        <div class="legend-row">
                            <div class="legend-group">
                                <span class="legend-label">Inspections:</span>
                                <span class="mini-badge" style="background-color: #ef4444;" title="Unassigned Inspection">U</span>
                                <span class="mini-badge" style="background-color: #3b82f6;" title="Assigned Inspection">A</span>
                                <span class="mini-badge" style="background-color: #1e3a8a;" title="Completed Inspection">C</span>
                            </div>
                            <div class="legend-group">
                                <span class="legend-label">Job Orders:</span>
                                <span class="mini-badge" style="background: linear-gradient(135deg, #8B5CF6, #7C3AED);" title="Unassigned Job Order">U</span>
                                <span class="mini-badge" style="background-color: #FFFF00; color: #333;" title="Assigned Job Order">A</span>
                                <span class="mini-badge" style="background-color: #22c55e;" title="Completed Job Order">C</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <form method="GET" action="calendar.php" id="workloadFilter">
        <div class="workload-filter">
            <div class="filter-section">
                <div class="filter-group">
                    <label for="filterSelect">Time Period:</label>
                    <select name="filter" id="filterSelect" onchange="updateDatePicker()">
                        <option value="">All Time</option>
                        <option value="week" <?= isset($_GET['filter']) && $_GET['filter'] == 'week' ? 'selected' : '' ?>>This Week</option>
                        <option value="month" <?= isset($_GET['filter']) && $_GET['filter'] == 'month' ? 'selected' : '' ?>>This Month</option>
                        <option value="date" <?= isset($_GET['filter']) && $_GET['filter'] == 'date' ? 'selected' : '' ?>>Specific Date</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="eventTypeSelect">Event Type:</label>
                    <select name="event_type" id="eventTypeSelect">
                        <option value="">All Events</option>
                        <option value="appointments" <?= isset($_GET['event_type']) && $_GET['event_type'] == 'appointments' ? 'selected' : '' ?>>Inspections Only</option>
                        <option value="job_orders" <?= isset($_GET['event_type']) && $_GET['event_type'] == 'job_orders' ? 'selected' : '' ?>>Job Orders Only</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="statusSelect">Status:</label>
                    <select name="status" id="statusSelect">
                        <option value="">All Statuses</option>
                        <option value="unassigned" <?= isset($_GET['status']) && $_GET['status'] == 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
                        <option value="assigned" <?= isset($_GET['status']) && $_GET['status'] == 'assigned' ? 'selected' : '' ?>>Assigned</option>
                        <option value="completed" <?= isset($_GET['status']) && $_GET['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>
            </div>

            <input type="date" name="specificDate" id="specificDate"
                value="<?= isset($_GET['specificDate']) ? htmlspecialchars($_GET['specificDate']) : '' ?>"
                style="display: none;">

            <button type="submit" class="filter-button">
                <i class="fas fa-filter"></i> Apply Filters
            </button>

            <?php if ($isFiltering): ?>
            <a href="calendar.php" class="reset-button">
                <i class="fas fa-times"></i> Reset All
            </a>
            <?php endif; ?>

            <?php if (count($calendarEvents) === 0): ?>
            <div class="no-events-message">
                <i class="fas fa-info-circle"></i> No events match your current filter criteria.
            </div>
            <?php endif; ?>
        </div>
    </form>
            <div class="workload-grid">
                <?php foreach ($techniciansWorkload as $tech): ?>
                <div class="workload-card">
                    <div class="tech-name"><?= $tech['username'] ?></div>
                    <div class="compact-stats">
                        <!-- Inspections Row -->
                        <div class="stat-row">
                            <div class="stat-icon"><i class="fas fa-clipboard-list"></i> Inspections:</div>
                            <div class="stat-badges">
                                <span class="badge appointment-badge" title="Assigned Inspections">
                                    <i class="fas fa-calendar-check"></i> <?= $tech['appointment_count'] ?>
                                </span>
                                <span class="badge completed-badge" title="Completed Inspections">
                                    <i class="fas fa-check-circle"></i> <?= $tech['completed_count'] ?>
                                </span>
                            </div>
                        </div>

                        <!-- Job Orders Row -->
                        <div class="stat-row">
                            <div class="stat-icon"><i class="fas fa-tools"></i> Job Orders:</div>
                            <div class="stat-badges">
                                <span class="badge job-badge" title="Assigned Job Orders">
                                    <i class="fas fa-briefcase"></i> <?= $tech['job_order_count'] - ($tech['completed_job_count'] ?? 0) ?>
                                </span>
                                <span class="badge completed-job-badge" title="Completed Job Orders">
                                    <i class="fas fa-check-double"></i> <?= $tech['completed_job_count'] ?? 0 ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div id="calendar"></div>
            </div>
        </main>
    </div>

    <div class="modal-overlay" id="eventModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-calendar-alt"></i> <span id="modalTitle"></span></h3>
                <span class="close-modal" id="closeModalBtn">&times;</span>
            </div>

            <!-- Appointment Details -->
            <div id="appointmentDetails" style="display: none;">
                <div class="detail-section">
                    <h4><i class="fas fa-user"></i> Client Information</h4>
                    <div class="detail-row">
                        <div class="detail-label">Name:</div>
                        <div class="detail-value" id="apptClientName"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Contact:</div>
                        <div class="detail-value" id="apptContactNumber"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Email:</div>
                        <div class="detail-value" id="apptEmail"></div>
                    </div>
                </div>

                <div class="detail-section">
                    <h4><i class="fas fa-calendar-check"></i> Appointment Details</h4>
                    <div class="detail-row">
                        <div class="detail-label">Property Type:</div>
                        <div class="detail-value" id="apptPropertyType"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Date/Time:</div>
                        <div class="detail-value" id="apptDateTime"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Technician:</div>
                        <div class="detail-value" id="apptTechnician"></div>
                    </div>
                </div>

                <div class="detail-section">
                    <h4><i class="fas fa-map-marker-alt"></i> Location</h4>
                    <div class="detail-value" id="apptLocation"></div>
                </div>

                <div class="detail-section">
                    <h4><i class="fas fa-bug"></i> Pest Problems Client Encountered</h4>
                    <div class="notes-box" id="apptPestProblems">No pest problems specified</div>
                </div>

                <div class="detail-section">
                    <h4><i class="fas fa-sticky-note"></i> Client Notes</h4>
                    <div class="notes-box" id="apptNotes"></div>
                </div>

                <div id="assignSection" style="display: none;" class="detail-section">
                    <h4><i class="fas fa-user-plus"></i> <span id="assignSectionTitle">Assign Technicians</span></h4>

                    <!-- Completed inspection message (shown when inspection is completed) -->
                    <div id="completedInspectionMessage" style="display: none;" class="alert alert-info">
                        <i class="fas fa-info-circle"></i> This inspection has been completed. Technician assignments are disabled.
                    </div>

                    <!-- Technician assignment form (shown when no technician is assigned and inspection is not completed) -->
                    <div id="assignForm" class="assign-form">
                        <select id="technicianSelect" class="form-control">
                            <option value="">Select technician...</option>
                            <?php foreach ($technicians as $tech): ?>
                            <option value="<?= $tech['technician_id'] ?>"><?= htmlspecialchars($tech['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-primary" onclick="assignTechnician()">
                            <i class="fas fa-user-check"></i> Assign
                        </button>
                    </div>

                    <!-- Assigned technicians info (shown when technicians are assigned and inspection is not completed) -->
                    <div id="assignedInfo" style="display: none;" class="assigned-tech-info">
                        <div id="assignedTechList">
                            <!-- Technician badges will be added here dynamically -->
                        </div>
                        <div class="assign-form" style="margin-top: 10px;">
                            <select id="additionalTechnicianSelect" class="form-control">
                                <option value="">Add another technician...</option>
                                <!-- Technician options will be populated dynamically -->
                            </select>
                            <button type="button" class="btn btn-primary" onclick="assignAdditionalTechnician()">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                        <div class="assign-help" style="margin-top: 10px;">
                            <small><i class="fas fa-info-circle"></i> The technician marked as "Primary" will be responsible for submitting reports. Click on a technician's badge to set them as primary.</small>
                        </div>
                    </div>

                    <!-- Help text for assignment form -->
                    <div id="assignHelp" class="assign-help">
                        <small><i class="fas fa-info-circle"></i> Assigning technicians will update the appointment status and send notifications to the technicians. The first assigned technician will be set as primary by default.</small>
                    </div>
                </div>
            </div>

            <!-- Job Order Details -->
            <div id="jobDetails" style="display: none;">
                <div class="detail-section">
                    <h4><i class="fas fa-tools"></i> Job Details</h4>
                    <div class="detail-row">
                        <div class="detail-label">Type:</div>
                        <div class="detail-value" id="jobType"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Date/Time:</div>
                        <div class="detail-value" id="jobDateTime"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Client:</div>
                        <div class="detail-value" id="jobClient"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Location:</div>
                        <div class="detail-value" id="jobLocation"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Technicians:</div>
                        <div class="detail-value" id="jobTechnicians"></div>
                    </div>
                </div>

                <div id="jobAssignSection" style="display: none;" class="detail-section">
                    <h4><i class="fas fa-user-plus"></i> <span id="jobAssignSectionTitle">Assign Technicians to Job Order</span></h4>

                    <!-- Technician assignment form (shown when no technician is assigned) -->
                    <div id="jobAssignForm" class="assign-form">
                        <select id="jobTechnicianSelect" class="form-control">
                            <option value="">Select technician...</option>
                            <?php foreach ($technicians as $tech): ?>
                            <option value="<?= $tech['technician_id'] ?>"><?= htmlspecialchars($tech['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-primary" onclick="assignJobTechnician()">
                            <i class="fas fa-user-check"></i> Assign
                        </button>
                    </div>

                    <!-- Assigned technicians info (shown when technicians are assigned) -->
                    <div id="jobAssignedInfo" style="display: none;" class="assigned-tech-info">
                        <div id="jobAssignedTechList">
                            <!-- Technician badges will be added here dynamically -->
                        </div>
                        <div class="assign-form" style="margin-top: 10px;">
                            <select id="jobAdditionalTechnicianSelect" class="form-control">
                                <option value="">Add another technician...</option>
                                <!-- Technician options will be populated dynamically -->
                            </select>
                            <button type="button" class="btn btn-primary" onclick="assignAdditionalJobTechnician()">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                        <div class="assign-help" style="margin-top: 10px;">
                            <small><i class="fas fa-info-circle"></i> The technician marked as "Primary" will be responsible for submitting reports. Click on a technician's badge to set them as primary.</small>
                        </div>
                    </div>

                    <!-- Help text for assignment form -->
                    <div id="jobAssignHelp" class="assign-help">
                        <small><i class="fas fa-info-circle"></i> Assigning technicians will update the job order status and send notifications to the technicians. The first assigned technician will be set as primary by default.</small>
                    </div>
                </div>
            </div>

            <div class="modal-actions" id="modalActions">
                <!-- Reschedule button will only show for appointments that are not completed -->
                <button class="btn btn-primary" id="rescheduleBtn" style="display: none; margin-right: 10px;">
                    <i class="fas fa-calendar-alt"></i> Reschedule
                </button>
                <button class="btn btn-secondary" id="closeModalFooterBtn">Close</button>
            </div>

            <!-- Reschedule Form (Hidden by default) -->
            <div id="rescheduleForm" style="display: none; margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb;">
                <h4><i class="fas fa-calendar-plus"></i> Reschedule Appointment</h4>
                <div style="margin-bottom: 15px; padding: 10px; background-color: #f8f9fa; border-radius: 4px; border-left: 4px solid #3b82f6;">
                    <p style="margin: 0; font-size: 0.85rem; color: #4b5563;">
                        <i class="fas fa-info-circle" style="color: #3b82f6;"></i> <strong>Rescheduling Rules:</strong>
                        <br>• You cannot reschedule for a past date
                        <br>• When rescheduling for today, the time must be at least 30 minutes after the current time
                        <br>• Times are rounded up to the next 30-minute interval (e.g., 9:00 AM, 9:30 AM, 10:00 AM)
                    </p>
                </div>
                <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <div style="flex: 1;">
                        <label for="newDate" style="display: block; margin-bottom: 5px; font-size: 0.9rem; color: #4b5563;">New Date:</label>
                        <input type="date" id="newDate" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div style="flex: 1;">
                        <label for="newTime" style="display: block; margin-bottom: 5px; font-size: 0.9rem; color: #4b5563;">New Time:</label>
                        <input type="time" id="newTime" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;" step="1800">
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-secondary" id="cancelRescheduleBtn">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="confirmRescheduleBtn">
                        <i class="fas fa-check"></i> Confirm Reschedule
                    </button>
                </div>
            </div>


        </div>
    </div>

    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script>
        let currentAppointmentId = null;
        let currentJobOrderId = null;

        document.addEventListener('DOMContentLoaded', () => {
            // Add click event listener to the modal overlay
            const modalOverlay = document.getElementById('eventModal');
            modalOverlay.addEventListener('click', function(event) {
                // Close the modal when clicking outside the modal content
                if (event.target === modalOverlay) {
                    closeModal();
                }
            });

            // Add click event listener to the close button in header
            const closeBtn = document.getElementById('closeModalBtn');
            closeBtn.addEventListener('click', function() {
                closeModal();
            });

            // Add click event listener to the close button in footer
            const closeFooterBtn = document.getElementById('closeModalFooterBtn');
            closeFooterBtn.addEventListener('click', function() {
                closeModal();
            });

            // Add click event listener to the reschedule button
            const rescheduleBtn = document.getElementById('rescheduleBtn');
            rescheduleBtn.addEventListener('click', function() {
                // Hide the modal actions and show the reschedule form
                document.getElementById('modalActions').style.display = 'none';
                document.getElementById('rescheduleForm').style.display = 'block';

                // Set minimum date to today
                const today = new Date().toISOString().split('T')[0];
                document.getElementById('newDate').min = today;

                // If the current value is before today, update it to today
                const currentDateValue = document.getElementById('newDate').value;
                if (currentDateValue < today) {
                    document.getElementById('newDate').value = today;
                }

                // Set up time validation for today
                const dateInput = document.getElementById('newDate');
                const timeInput = document.getElementById('newTime');

                // Initial setup of min time if date is today
                updateMinTime();

                // Add event listener to update min time when date changes
                dateInput.addEventListener('change', updateMinTime);
            });

            // Add click event listener to the cancel reschedule button
            const cancelRescheduleBtn = document.getElementById('cancelRescheduleBtn');
            cancelRescheduleBtn.addEventListener('click', function() {
                // Hide the reschedule form and show the modal actions
                document.getElementById('rescheduleForm').style.display = 'none';
                document.getElementById('modalActions').style.display = 'flex';
            });

            // Add click event listener to the confirm reschedule button
            const confirmRescheduleBtn = document.getElementById('confirmRescheduleBtn');
            confirmRescheduleBtn.addEventListener('click', function() {
                rescheduleAppointment();
            });

            // Debug the events data
            const eventsData = <?php echo json_encode($calendarEvents); ?>;
            console.log('Calendar events data:', eventsData);
            console.log('Number of events:', eventsData.length);

            if (eventsData.length === 0) {
                console.warn('WARNING: No events data available for the calendar!');
                // Show a message in the calendar area
                document.getElementById('calendar').innerHTML = '<div class="calendar-empty-message">No events to display. Please check your filter settings or try resetting filters.</div>';
            }

            const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
                initialView: 'dayGridMonth',
                events: eventsData,
                eventDidMount: (info) => {
                    if(info.event.extendedProps.type === 'appointment') {
                        info.el.style.setProperty('--event-color', info.event.backgroundColor);
                    }
                    console.log('Event mounted:', info.event.title);
                },
                eventClassNames: (info) => {
                    return info.event.extendedProps.type === 'appointment'
                        ? 'fc-event-appointment'
                        : 'fc-event-job';
                },
                eventClick: (info) => {
                    const event = info.event;
                    const isAppointment = event.extendedProps.type === 'appointment';

                    console.log('Event clicked:', event);
                    console.log('Event ID:', event.id);
                    console.log('Event type:', event.extendedProps.type);

                    document.getElementById('modalTitle').textContent =
                        isAppointment ? 'Appointment Details' : 'Job Order Details';

                    document.getElementById('appointmentDetails').style.display =
                        isAppointment ? 'block' : 'none';
                    document.getElementById('jobDetails').style.display =
                        isAppointment ? 'none' : 'block';

                    if(isAppointment) {
                        const props = event.extendedProps;
                        currentAppointmentId = event.id;

                        document.getElementById('apptClientName').textContent = props.client_name || 'N/A';
                        document.getElementById('apptContactNumber').textContent = props.contact_number || 'N/A';
                        document.getElementById('apptEmail').textContent = props.email || 'N/A';
                        document.getElementById('apptPropertyType').textContent = props.kind_of_place || 'N/A';
                        document.getElementById('apptLocation').textContent = props.location_address || 'N/A';
                        document.getElementById('apptDateTime').textContent =
                            event.start ? `${event.start.toLocaleDateString()} at ${event.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}` : 'N/A';
                        document.getElementById('apptTechnician').textContent =
                            props.technician_name || 'Not assigned';
                        document.getElementById('apptPestProblems').textContent = props.pest_problems || 'No pest problems specified';
                        document.getElementById('apptNotes').textContent = props.notes || 'No notes';

                        // Show reschedule button only if appointment is not completed
                        const rescheduleBtn = document.getElementById('rescheduleBtn');
                        if (props.status !== 'completed') {
                            rescheduleBtn.style.display = 'inline-block';

                            // Set default values for reschedule form
                            if (event.start) {
                                const date = event.start.toISOString().split('T')[0];
                                const time = props.preferred_time;
                                document.getElementById('newDate').value = date;
                                document.getElementById('newTime').value = time;
                            }
                        } else {
                            rescheduleBtn.style.display = 'none';
                        }

                        // Show the assign section regardless of whether a technician is assigned
                        document.getElementById('assignSection').style.display = 'block';

                        // Check if the inspection is completed
                        const isCompleted = props.status === 'completed';

                        if (isCompleted) {
                            // If inspection is completed, show the completed message and hide all assignment UI
                            document.getElementById('assignSectionTitle').textContent = 'Technician Assignment';
                            document.getElementById('completedInspectionMessage').style.display = 'block';
                            document.getElementById('assignForm').style.display = 'none';
                            document.getElementById('assignHelp').style.display = 'none';
                            document.getElementById('assignedInfo').style.display = 'none';

                            // Still fetch technicians to display them, but don't allow modifications
                            if (props.technician_id) {
                                fetch(`get_appointment_technicians.php?appointment_id=${currentAppointmentId.replace('appt_', '')}`)
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            // Store the technicians in our array
                                            appointmentTechnicians = data.technicians;

                                            // Just update the technician display, but don't show the edit UI
                                            const technicianNames = appointmentTechnicians.map(tech => tech.name).join(', ');
                                            document.getElementById('apptTechnician').textContent = technicianNames;
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error fetching technicians:', error);
                                    });
                            }
                        } else {
                            // If inspection is not completed, show normal assignment UI
                            document.getElementById('completedInspectionMessage').style.display = 'none';

                            // If a technician is assigned, show the assigned info and hide the form
                            if (props.technician_id) {
                                document.getElementById('assignSectionTitle').textContent = 'Assigned Technicians';
                                document.getElementById('assignForm').style.display = 'none';
                                document.getElementById('assignHelp').style.display = 'none';
                                document.getElementById('assignedInfo').style.display = 'block';

                                // Fetch all assigned technicians for this appointment
                                fetch(`get_appointment_technicians.php?appointment_id=${currentAppointmentId.replace('appt_', '')}`)
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            // Store the technicians in our array
                                            appointmentTechnicians = data.technicians.map(tech => ({
                                                id: parseInt(tech.id), // Ensure IDs are integers
                                                name: tech.name,
                                                isPrimary: tech.isPrimary
                                            }));

                                            // Update the technician list
                                            updateAppointmentTechniciansList();

                                            // Update the additional technician dropdown
                                            updateAdditionalTechnicianDropdown();

                                            // Log the assigned technicians for debugging
                                            console.log('Assigned technicians:', appointmentTechnicians);
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error fetching technicians:', error);
                                    });
                            } else {
                                // If no technician is assigned, show the form and hide the assigned info
                                document.getElementById('assignSectionTitle').textContent = 'Assign Technicians';
                                document.getElementById('assignForm').style.display = 'flex';
                                document.getElementById('assignHelp').style.display = 'block';
                                document.getElementById('assignedInfo').style.display = 'none';
                            }
                        }
                    } else {
                        const props = event.extendedProps;
                        currentJobOrderId = event.id;

                        document.getElementById('jobType').textContent = props.type_of_work || 'N/A';
                        document.getElementById('jobDateTime').textContent =
                            event.start ? `${event.start.toLocaleDateString()} at ${event.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}` : 'N/A';
                        document.getElementById('jobClient').textContent = props.client_name || 'N/A';
                        document.getElementById('jobLocation').textContent = props.location_address || 'N/A';
                        document.getElementById('jobTechnicians').textContent = props.technicians || 'No technicians assigned';

                        // Get job status and technician info
                        const isCompleted = props.job_status === 'completed';
                        const hasTechnicians = props.technician_count > 0;

                        // Show reschedule button only if job order is not completed
                        const rescheduleBtn = document.getElementById('rescheduleBtn');
                        if (!isCompleted) {
                            rescheduleBtn.style.display = 'inline-block';

                            // Set default values for reschedule form
                            if (event.start) {
                                const date = event.start.toISOString().split('T')[0];
                                const time = props.preferred_time;
                                document.getElementById('newDate').value = date;
                                document.getElementById('newTime').value = time;
                            }
                        } else {
                            rescheduleBtn.style.display = 'none';
                        }

                        // Show job assignment section if job is not completed
                        document.getElementById('jobAssignSection').style.display = isCompleted ? 'none' : 'block';

                        // If job has technicians, show the assigned info and hide the form
                        if (!isCompleted) {
                            if (hasTechnicians) {
                                document.getElementById('jobAssignSectionTitle').textContent = 'Assigned Technicians';
                                document.getElementById('jobAssignForm').style.display = 'none';
                                document.getElementById('jobAssignHelp').style.display = 'none';
                                document.getElementById('jobAssignedInfo').style.display = 'block';

                                // Fetch all assigned technicians for this job order
                                fetch(`get_job_order_technicians.php?job_order_id=${currentJobOrderId.replace('job_', '')}`)
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            // Store the technicians in our array
                                            jobOrderTechnicians = data.technicians.map(tech => ({
                                                id: parseInt(tech.id), // Ensure IDs are integers
                                                name: tech.name,
                                                isPrimary: tech.isPrimary
                                            }));

                                            // Update the technician list
                                            updateJobOrderTechniciansList();

                                            // Update the additional technician dropdown
                                            updateJobAdditionalTechnicianDropdown();

                                            // Log the assigned technicians for debugging
                                            console.log('Job Order Technicians:', jobOrderTechnicians);
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error fetching technicians:', error);
                                    });
                            } else {
                                // If no technician is assigned, show the form and hide the assigned info
                                document.getElementById('jobAssignSectionTitle').textContent = 'Assign Technicians to Job Order';
                                document.getElementById('jobAssignForm').style.display = 'flex';
                                document.getElementById('jobAssignHelp').style.display = 'block';
                                document.getElementById('jobAssignedInfo').style.display = 'none';
                            }
                        }
                    }

                    console.log('Showing modal');
                    const modal = document.getElementById('eventModal');
                    modal.style.display = 'block';
                    console.log('Modal display style:', modal.style.display);
                }
            });
            calendar.render();
        });

        // Global variables to store assigned technicians
        let appointmentTechnicians = [];
        let jobOrderTechnicians = [];

        function assignTechnician() {
            const selectedTechId = document.getElementById('technicianSelect').value;
            if (!selectedTechId) {
                alert('Please select a technician');
                return;
            }

            // Check if any technicians are already assigned
            if (appointmentTechnicians.length > 0) {
                // Check if this specific technician is already assigned
                const assignedIds = appointmentTechnicians.map(tech => tech.id);
                if (assignedIds.includes(parseInt(selectedTechId))) {
                    alert('This technician is already assigned to this inspection.');
                    return;
                }
            }

            // Show loading state
            const assignButton = document.querySelector('#assignForm button');
            const originalButtonText = assignButton.innerHTML;
            assignButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Assigning...';
            assignButton.disabled = true;

            const appointmentId = currentAppointmentId.replace('appt_', '');
            console.log('Assigning technician ID', selectedTechId, 'to appointment ID', appointmentId);
            showDebugInfo(`Attempting to assign technician ID ${selectedTechId} to appointment ID ${appointmentId}...`);

            // Set as primary since this is the first technician
            const isPrimary = true;

            // Show debug info for the request
            const requestData = {
                appointment_id: appointmentId,
                technician_id: selectedTechId,
                is_primary: isPrimary
            };
            console.log('Request data:', requestData);

            // Add debug info to help diagnose issues
            showDebugInfo(`Request data: ${JSON.stringify(requestData)}`);

            // Try the new version first
            tryAssignTechnician('assign_technician_new.php', requestData, assignButton, originalButtonText)
                .catch(error => {
                    console.error('First attempt failed:', error);
                    showDebugInfo(`First attempt failed: ${error.message}. Trying fallback...`);

                    // If that fails, try the simple version
                    return tryAssignTechnician('assign_technician_simple.php', requestData, assignButton, originalButtonText);
                })
                .catch(error => {
                    console.error('Both attempts failed:', error);
                    showDebugInfo(`Both attempts failed: ${error.message}`);

                    // Reset button state
                    assignButton.innerHTML = originalButtonText;
                    assignButton.disabled = false;

                    alert('Failed to assign technician: ' + error.message);
                });
        }

        function tryAssignTechnician(url, requestData, assignButton, originalButtonText) {
            return new Promise((resolve, reject) => {
                // Check if the technician is already assigned
                const techId = parseInt(requestData.technician_id);
                const assignedIds = appointmentTechnicians.map(tech => tech.id);

                if (assignedIds.includes(techId)) {
                    // Reset button state
                    assignButton.innerHTML = originalButtonText;
                    assignButton.disabled = false;

                    // Reject with an error message
                    reject(new Error('This technician is already assigned to this inspection.'));
                    return;
                }

                // Make sure isPrimary is explicitly set to true for the first technician
                if (!requestData.hasOwnProperty('is_primary')) {
                    requestData.is_primary = true;
                }

                // Log the request data for debugging
                console.log('Sending request data:', requestData);
                showDebugInfo(`Sending to ${url}: ${JSON.stringify(requestData)}`);

                fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(requestData)
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', [...response.headers.entries()]);

                    // Check if the response is JSON
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        // If not JSON, get the text and throw an error with it
                        return response.text().then(text => {
                            throw new Error('Expected JSON response but got: ' + text);
                        });
                    }

                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.status);
                    }

                    return response.json();
                })
                .then(data => {
                    console.log('Assignment response:', data);
                    showDebugInfo(data);

                    // Always reset button state
                    assignButton.innerHTML = originalButtonText;
                    assignButton.disabled = false;

                    if(data.success) {
                        console.log('Technician assignment successful');

                        // Update the UI to show the assigned technician
                        document.getElementById('apptTechnician').textContent = data.technician_name;
                        document.getElementById('assignSectionTitle').textContent = 'Assigned Technicians';
                        document.getElementById('assignForm').style.display = 'none';

                        // Check if assignHelp exists before trying to hide it
                        const assignHelp = document.getElementById('assignHelp');
                        if (assignHelp) {
                            assignHelp.style.display = 'none';
                        }

                        document.getElementById('assignedInfo').style.display = 'block';

                        // Add the technician to our array
                        appointmentTechnicians = [{
                            id: parseInt(data.technician_id),
                            name: data.technician_name,
                            isPrimary: true
                        }];

                        // Update the technician list
                        updateAppointmentTechniciansList();

                        // Show success message
                        alert('Technician assigned successfully!');

                        // Log notification status
                        if (data.notification_sent) {
                            console.log(`Notification sent for appointment ${data.appointment_id}`);
                        }

                        // Remove the assigned technician from the additional technician dropdown
                        updateAdditionalTechnicianDropdown();

                        // Resolve the promise
                        resolve(data);
                    } else {
                        // Show error message
                        const errorMsg = data.message || 'Failed to assign technician';
                        console.error('Assignment error:', errorMsg);
                        showDebugInfo(`Error: ${errorMsg}`);

                        // Reject the promise
                        reject(new Error(errorMsg));
                    }
                })
                .catch(error => {
                    // Reject the promise
                    reject(error);
                });
            });
        }

        function assignAdditionalTechnician() {
            const selectedTechId = document.getElementById('additionalTechnicianSelect').value;
            if (!selectedTechId) {
                alert('Please select a technician');
                return;
            }

            // Check if the technician is already assigned
            const assignedIds = appointmentTechnicians.map(tech => tech.id);
            if (assignedIds.includes(parseInt(selectedTechId))) {
                alert('This technician is already assigned to this inspection.');
                return;
            }

            // Show loading state
            const assignButton = document.querySelector('#assignedInfo .assign-form button');
            const originalButtonText = assignButton.innerHTML;
            assignButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            assignButton.disabled = true;

            const appointmentId = currentAppointmentId.replace('appt_', '');
            console.log('Assigning additional technician ID', selectedTechId, 'to appointment ID', appointmentId);

            // Not primary by default
            const isPrimary = false;

            // Create request data object
            const requestData = {
                appointment_id: appointmentId,
                technician_id: selectedTechId,
                is_primary: isPrimary
            };
            console.log('Additional technician request data:', requestData);

            fetch('assign_technician_new.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(requestData)
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', [...response.headers.entries()]);

                // Check if the response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    // If not JSON, get the text and throw an error with it
                    return response.text().then(text => {
                        throw new Error('Expected JSON response but got: ' + text);
                    });
                }

                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }

                return response.json();
            })
            .then(data => {
                console.log('Additional assignment response:', data);
                showDebugInfo(data);

                if(data.success) {
                    // Add the technician to our array
                    appointmentTechnicians.push({
                        id: parseInt(data.technician_id),
                        name: data.technician_name,
                        isPrimary: false
                    });

                    // Update the technician list
                    updateAppointmentTechniciansList();

                    // Update the main technician display
                    const technicianNames = appointmentTechnicians.map(tech => tech.name).join(', ');
                    document.getElementById('apptTechnician').textContent = technicianNames;

                    // Reset the dropdown
                    document.getElementById('additionalTechnicianSelect').value = '';

                    // Reset button state
                    assignButton.innerHTML = originalButtonText;
                    assignButton.disabled = false;

                    // Show success message
                    alert('Additional technician assigned successfully!');

                    // Remove the assigned technician from the dropdown
                    updateAdditionalTechnicianDropdown();
                } else {
                    // Reset button state
                    assignButton.innerHTML = originalButtonText;
                    assignButton.disabled = false;

                    // Show error message
                    alert('Error: ' + (data.message || 'Failed to assign additional technician'));
                }
            })
            .catch(error => {
                console.error('Error:', error);

                // Show detailed error in debug section
                const debugInfo = {
                    error: error.message,
                    stack: error.stack || 'No stack trace available',
                    appointmentId: appointmentId,
                    technicianId: selectedTechId,
                    requestData: requestData
                };

                console.error('Debug info:', debugInfo);
                showDebugInfo(debugInfo);

                // Reset button state
                assignButton.innerHTML = originalButtonText;
                assignButton.disabled = false;

                // Show a more user-friendly error message
                let errorMsg = error.message;
                if (errorMsg.includes('Expected JSON response but got:')) {
                    errorMsg = 'Server returned an invalid response. Please check the debug information for details.';
                }

                alert('Failed to assign additional technician: ' + errorMsg);
            });
        }

        function updateAppointmentTechniciansList() {
            const techListContainer = document.getElementById('assignedTechList');
            techListContainer.innerHTML = '';

            appointmentTechnicians.forEach(tech => {
                const badge = document.createElement('div');
                badge.className = `assigned-tech-badge ${tech.isPrimary ? 'primary' : ''}`;
                badge.dataset.techId = tech.id;
                badge.innerHTML = `
                    <i class="fas fa-user-check"></i> ${tech.name}
                    <span class="remove-tech" onclick="removeAppointmentTechnician(event, ${tech.id})"><i class="fas fa-times"></i></span>
                `;

                // Add click event to set as primary
                badge.addEventListener('click', function(e) {
                    // Ignore clicks on the remove button
                    if (e.target.closest('.remove-tech')) return;

                    setAppointmentPrimaryTechnician(tech.id);
                });

                techListContainer.appendChild(badge);
            });
        }

        function updateAdditionalTechnicianDropdown() {
            const dropdown = document.getElementById('additionalTechnicianSelect');
            if (!dropdown) return; // Safety check

            // Get array of assigned technician IDs
            const assignedIds = appointmentTechnicians.map(tech => tech.id);
            console.log('Filtering dropdown. Assigned IDs:', assignedIds);

            // Clear existing options except the first one
            while (dropdown.options.length > 1) {
                dropdown.remove(1);
            }

            // Add technicians that aren't already assigned
            <?php foreach ($technicians as $tech): ?>
            {
                const techId = <?= $tech['technician_id'] ?>;
                if (!assignedIds.includes(techId)) {
                    const option = document.createElement('option');
                    option.value = techId;
                    option.textContent = '<?= htmlspecialchars($tech['username']) ?>';
                    dropdown.appendChild(option);
                } else {
                    console.log('Excluding already assigned technician:', techId, '<?= htmlspecialchars($tech['username']) ?>');
                }
            }
            <?php endforeach; ?>

            console.log('Dropdown updated with filtered technicians. Options count:', dropdown.options.length);
        }

        function setAppointmentPrimaryTechnician(techId) {
            const appointmentId = currentAppointmentId.replace('appt_', '');

            fetch('set_primary_technician.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    appointment_id: appointmentId,
                    technician_id: techId,
                    type: 'appointment'
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Update our local array
                    appointmentTechnicians.forEach(tech => {
                        tech.isPrimary = (tech.id == techId);
                    });

                    // Update the UI
                    updateAppointmentTechniciansList();

                    // Show success message
                    alert('Primary technician updated successfully!');
                } else {
                    alert('Error: ' + (data.message || 'Failed to update primary technician'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to update primary technician: ' + error.message);
            });
        }

        function removeAppointmentTechnician(event, techId) {
            event.stopPropagation(); // Prevent the badge click event from firing

            // Confirm removal
            if (!confirm('Are you sure you want to remove this technician from the appointment?')) {
                return;
            }

            const appointmentId = currentAppointmentId.replace('appt_', '');

            fetch('remove_technician.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    appointment_id: appointmentId,
                    technician_id: techId,
                    type: 'appointment'
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Remove from our local array
                    appointmentTechnicians = appointmentTechnicians.filter(tech => tech.id != techId);

                    // If we removed the primary technician and there are still technicians left,
                    // set the first one as primary
                    if (appointmentTechnicians.length > 0 && !appointmentTechnicians.some(tech => tech.isPrimary)) {
                        appointmentTechnicians[0].isPrimary = true;

                        // Update the primary technician in the database
                        setAppointmentPrimaryTechnician(appointmentTechnicians[0].id);
                    }

                    // Update the UI
                    updateAppointmentTechniciansList();
                    updateAdditionalTechnicianDropdown();

                    // Update the main technician display
                    const technicianNames = appointmentTechnicians.length > 0
                        ? appointmentTechnicians.map(tech => tech.name).join(', ')
                        : 'Not assigned';
                    document.getElementById('apptTechnician').textContent = technicianNames;

                    // If no technicians left, show the assignment form again
                    if (appointmentTechnicians.length === 0) {
                        document.getElementById('assignSectionTitle').textContent = 'Assign Technicians';
                        document.getElementById('assignForm').style.display = 'flex';
                        document.getElementById('assignHelp').style.display = 'block';
                        document.getElementById('assignedInfo').style.display = 'none';
                    }

                    // Show success message
                    alert('Technician removed successfully!');
                } else {
                    alert('Error: ' + (data.message || 'Failed to remove technician'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to remove technician: ' + error.message);
            });
        }

        function assignJobTechnician() {
            const selectedTechId = document.getElementById('jobTechnicianSelect').value;
            if (!selectedTechId) {
                alert('Please select a technician');
                return;
            }

            // Check if any technicians are already assigned
            if (jobOrderTechnicians.length > 0) {
                // Check if this specific technician is already assigned
                const assignedIds = jobOrderTechnicians.map(tech => tech.id);
                if (assignedIds.includes(parseInt(selectedTechId))) {
                    alert('This technician is already assigned to this job order.');
                    return;
                }
            }

            // Show loading state
            const assignButton = document.querySelector('#jobAssignSection button');
            const originalButtonText = assignButton.innerHTML;
            assignButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Assigning...';
            assignButton.disabled = true;

            const jobOrderId = currentJobOrderId.replace('job_', '');
            console.log('Assigning technician ID', selectedTechId, 'to job order ID', jobOrderId);
            showDebugInfo(`Attempting to assign technician ID ${selectedTechId} to job order ID ${jobOrderId}...`);

            // Set as primary since this is the first technician
            const isPrimary = true;

            fetch('assign_job_technician.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    job_order_id: jobOrderId,
                    technician_id: selectedTechId,
                    is_primary: isPrimary
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Assignment response:', data);
                showDebugInfo(data);

                if(data.success) {
                    // Update the UI to show the assigned technician
                    document.getElementById('jobTechnicians').textContent = data.technician_name;
                    document.getElementById('jobAssignSectionTitle').textContent = 'Assigned Technicians';
                    document.getElementById('jobAssignForm').style.display = 'none';
                    document.getElementById('jobAssignHelp').style.display = 'none';
                    document.getElementById('jobAssignedInfo').style.display = 'block';

                    // Add the technician to our array
                    jobOrderTechnicians = [{
                        id: parseInt(data.technician_id),
                        name: data.technician_name,
                        isPrimary: true
                    }];

                    // Update the technician list
                    updateJobOrderTechniciansList();

                    // Show success message
                    alert('Technician assigned successfully!');

                    // Log notification status
                    if (data.notification_sent) {
                        console.log(`Notification sent for job order ${data.job_order_id}`);
                    }

                    // Remove the assigned technician from the additional technician dropdown
                    updateJobAdditionalTechnicianDropdown();
                } else {
                    // Reset button state
                    assignButton.innerHTML = originalButtonText;
                    assignButton.disabled = false;

                    // Show error message
                    alert('Error: ' + (data.message || 'Failed to assign technician'));
                }
            })
            .catch(error => {
                console.error('Error:', error);

                // Show error in debug section
                showDebugInfo(`Error: ${error.message}\n\nStack: ${error.stack || 'No stack trace available'}`);

                // Reset button state
                assignButton.innerHTML = originalButtonText;
                assignButton.disabled = false;

                alert('Failed to assign technician: ' + error.message);
            });
        }

        function assignAdditionalJobTechnician() {
            const selectedTechId = document.getElementById('jobAdditionalTechnicianSelect').value;
            if (!selectedTechId) {
                alert('Please select a technician');
                return;
            }

            // Check if the technician is already assigned
            const assignedIds = jobOrderTechnicians.map(tech => tech.id);
            if (assignedIds.includes(parseInt(selectedTechId))) {
                alert('This technician is already assigned to this job order.');
                return;
            }

            // Show loading state
            const assignButton = document.querySelector('#jobAssignedInfo .assign-form button');
            const originalButtonText = assignButton.innerHTML;
            assignButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            assignButton.disabled = true;

            const jobOrderId = currentJobOrderId.replace('job_', '');
            console.log('Assigning additional technician ID', selectedTechId, 'to job order ID', jobOrderId);

            // Not primary by default
            const isPrimary = false;

            fetch('assign_job_technician.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    job_order_id: jobOrderId,
                    technician_id: selectedTechId,
                    is_primary: isPrimary
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Additional assignment response:', data);
                showDebugInfo(data);

                if(data.success) {
                    // Add the technician to our array
                    jobOrderTechnicians.push({
                        id: parseInt(data.technician_id),
                        name: data.technician_name,
                        isPrimary: false
                    });

                    // Update the technician list
                    updateJobOrderTechniciansList();

                    // Update the main technician display
                    const technicianNames = jobOrderTechnicians.map(tech => tech.name).join(', ');
                    document.getElementById('jobTechnicians').textContent = technicianNames;

                    // Reset the dropdown
                    document.getElementById('jobAdditionalTechnicianSelect').value = '';

                    // Reset button state
                    assignButton.innerHTML = originalButtonText;
                    assignButton.disabled = false;

                    // Show success message
                    alert('Additional technician assigned successfully!');

                    // Remove the assigned technician from the dropdown
                    updateJobAdditionalTechnicianDropdown();
                } else {
                    // Reset button state
                    assignButton.innerHTML = originalButtonText;
                    assignButton.disabled = false;

                    // Show error message
                    alert('Error: ' + (data.message || 'Failed to assign additional technician'));
                }
            })
            .catch(error => {
                console.error('Error:', error);

                // Show error in debug section
                showDebugInfo(`Error: ${error.message}\n\nStack: ${error.stack || 'No stack trace available'}`);

                // Reset button state
                assignButton.innerHTML = originalButtonText;
                assignButton.disabled = false;

                alert('Failed to assign additional technician: ' + error.message);
            });
        }

        function updateJobOrderTechniciansList() {
            const techListContainer = document.getElementById('jobAssignedTechList');
            techListContainer.innerHTML = '';

            jobOrderTechnicians.forEach(tech => {
                const badge = document.createElement('div');
                badge.className = `assigned-tech-badge ${tech.isPrimary ? 'primary' : ''}`;
                badge.dataset.techId = tech.id;
                badge.innerHTML = `
                    <i class="fas fa-user-check"></i> ${tech.name}
                    <span class="remove-tech" onclick="removeJobOrderTechnician(event, ${tech.id})"><i class="fas fa-times"></i></span>
                `;

                // Add click event to set as primary
                badge.addEventListener('click', function(e) {
                    // Ignore clicks on the remove button
                    if (e.target.closest('.remove-tech')) return;

                    setJobOrderPrimaryTechnician(tech.id);
                });

                techListContainer.appendChild(badge);
            });
        }

        function updateJobAdditionalTechnicianDropdown() {
            const dropdown = document.getElementById('jobAdditionalTechnicianSelect');
            if (!dropdown) return; // Safety check

            // Get array of assigned technician IDs
            const assignedIds = jobOrderTechnicians.map(tech => tech.id);
            console.log('Filtering job dropdown. Assigned IDs:', assignedIds);

            // Clear existing options except the first one
            while (dropdown.options.length > 1) {
                dropdown.remove(1);
            }

            // Add technicians that aren't already assigned
            <?php foreach ($technicians as $tech): ?>
            {
                const techId = <?= $tech['technician_id'] ?>;
                if (!assignedIds.includes(techId)) {
                    const option = document.createElement('option');
                    option.value = techId;
                    option.textContent = '<?= htmlspecialchars($tech['username']) ?>';
                    dropdown.appendChild(option);
                } else {
                    console.log('Excluding already assigned technician from job dropdown:', techId, '<?= htmlspecialchars($tech['username']) ?>');
                }
            }
            <?php endforeach; ?>

            console.log('Job dropdown updated with filtered technicians. Options count:', dropdown.options.length);
        }

        function setJobOrderPrimaryTechnician(techId) {
            const jobOrderId = currentJobOrderId.replace('job_', '');

            fetch('set_primary_technician.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    job_order_id: jobOrderId,
                    technician_id: techId,
                    type: 'job_order'
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Update our local array
                    jobOrderTechnicians.forEach(tech => {
                        tech.isPrimary = (tech.id == techId);
                    });

                    // Update the UI
                    updateJobOrderTechniciansList();

                    // Show success message
                    alert('Primary technician updated successfully!');
                } else {
                    alert('Error: ' + (data.message || 'Failed to update primary technician'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to update primary technician: ' + error.message);
            });
        }

        function removeJobOrderTechnician(event, techId) {
            event.stopPropagation(); // Prevent the badge click event from firing

            // Confirm removal
            if (!confirm('Are you sure you want to remove this technician from the job order?')) {
                return;
            }

            const jobOrderId = currentJobOrderId.replace('job_', '');

            fetch('remove_technician.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    job_order_id: jobOrderId,
                    technician_id: techId,
                    type: 'job_order'
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Remove from our local array
                    jobOrderTechnicians = jobOrderTechnicians.filter(tech => tech.id != techId);

                    // If we removed the primary technician and there are still technicians left,
                    // set the first one as primary
                    if (jobOrderTechnicians.length > 0 && !jobOrderTechnicians.some(tech => tech.isPrimary)) {
                        jobOrderTechnicians[0].isPrimary = true;

                        // Update the primary technician in the database
                        setJobOrderPrimaryTechnician(jobOrderTechnicians[0].id);
                    }

                    // Update the UI
                    updateJobOrderTechniciansList();
                    updateJobAdditionalTechnicianDropdown();

                    // Update the main technician display
                    const technicianNames = jobOrderTechnicians.length > 0
                        ? jobOrderTechnicians.map(tech => tech.name).join(', ')
                        : 'No technicians assigned';
                    document.getElementById('jobTechnicians').textContent = technicianNames;

                    // If no technicians left, show the assignment form again
                    if (jobOrderTechnicians.length === 0) {
                        document.getElementById('jobAssignSectionTitle').textContent = 'Assign Technicians to Job Order';
                        document.getElementById('jobAssignForm').style.display = 'flex';
                        document.getElementById('jobAssignHelp').style.display = 'block';
                        document.getElementById('jobAssignedInfo').style.display = 'none';
                    }

                    // Show success message
                    alert('Technician removed successfully!');
                } else {
                    alert('Error: ' + (data.message || 'Failed to remove technician'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to remove technician: ' + error.message);
            });
        }

        function rescheduleAppointment() {
            const newDate = document.getElementById('newDate').value;
            const newTime = document.getElementById('newTime').value;

            if (!newDate || !newTime) {
                alert('Please select both a date and time for rescheduling.');
                return;
            }

            // Client-side validation for past dates
            const today = new Date();
            today.setHours(0, 0, 0, 0); // Set to beginning of day for date comparison

            // Format today's date as YYYY-MM-DD for direct string comparison
            const todayFormatted = today.toISOString().split('T')[0];

            if (newDate < todayFormatted) {
                alert('Cannot reschedule for a past date.');
                return;
            }

            // Client-side validation for same-day scheduling (30-minute interval rule)
            if (newDate === todayFormatted) {
                const currentTime = new Date();
                const selectedDateTime = new Date(newDate + 'T' + newTime);

                // Calculate minimum allowed time (current time rounded up to next 30-minute interval)
                const minAllowedTime = new Date();
                const currentMinutes = minAllowedTime.getMinutes();
                const roundedMinutes = currentMinutes < 30 ? 30 : 60;

                // Set to the next 30-minute interval
                minAllowedTime.setMinutes(roundedMinutes);
                minAllowedTime.setSeconds(0);
                minAllowedTime.setMilliseconds(0);

                if (selectedDateTime < minAllowedTime) {
                    const minTimeStr = minAllowedTime.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    alert(`When rescheduling for today, the time must be at least 30 minutes after current time, rounded to the next 30-minute interval (${minTimeStr} onwards).`);
                    return;
                }
            }

            // Show loading state
            const confirmBtn = document.getElementById('confirmRescheduleBtn');
            const originalBtnText = confirmBtn.innerHTML;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            confirmBtn.disabled = true;

            // Determine if we're rescheduling an appointment or a job order
            let requestData, endpoint, elementToUpdate;

            if (currentAppointmentId) {
                const appointmentId = currentAppointmentId.replace('appt_', '');
                requestData = {
                    appointment_id: appointmentId,
                    new_date: newDate,
                    new_time: newTime
                };
                endpoint = 'reschedule_appointment.php';
                elementToUpdate = 'apptDateTime';
            } else if (currentJobOrderId) {
                const jobOrderId = currentJobOrderId.replace('job_', '');
                requestData = {
                    job_order_id: jobOrderId,
                    new_date: newDate,
                    new_time: newTime
                };
                endpoint = 'reschedule_job_order.php';
                elementToUpdate = 'jobDateTime';
            } else {
                // This shouldn't happen, but just in case
                alert('Error: Could not determine what to reschedule.');
                confirmBtn.innerHTML = originalBtnText;
                confirmBtn.disabled = false;
                return;
            }

            // Send the request to the server
            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(requestData)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                // Reset button state
                confirmBtn.innerHTML = originalBtnText;
                confirmBtn.disabled = false;

                if (data.success) {
                    // Update the UI to show the new date and time
                    const formattedDate = new Date(newDate + 'T' + newTime).toLocaleDateString();
                    const formattedTime = new Date(newDate + 'T' + newTime).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    document.getElementById(elementToUpdate).textContent = `${formattedDate} at ${formattedTime}`;

                    // Hide the reschedule form and show the modal actions
                    document.getElementById('rescheduleForm').style.display = 'none';
                    document.getElementById('modalActions').style.display = 'flex';

                    // Show success message
                    const itemType = currentAppointmentId ? 'Appointment' : 'Job order';
                    alert(`${itemType} rescheduled successfully! Notifications have been sent to the client and technician(s).`);

                    // Refresh the calendar to show the updated item
                    window.location.reload();
                } else {
                    // Show error message
                    const itemType = currentAppointmentId ? 'appointment' : 'job order';
                    alert('Error: ' + (data.message || `Failed to reschedule ${itemType}`));
                }
            })
            .catch(error => {
                console.error('Error:', error);

                // Reset button state
                confirmBtn.innerHTML = originalBtnText;
                confirmBtn.disabled = false;

                const itemType = currentAppointmentId ? 'appointment' : 'job order';
                alert(`Failed to reschedule ${itemType}: ` + error.message);
            });
        }

        function closeModal() {
            console.log('Closing modal');
            document.getElementById('eventModal').style.display = 'none';
            // Reset the current IDs
            currentAppointmentId = null;
            currentJobOrderId = null;

            // Reset technician arrays
            appointmentTechnicians = [];
            jobOrderTechnicians = [];

            console.log('Modal closed. Technician arrays reset.');

            // Reset reschedule form
            document.getElementById('rescheduleForm').style.display = 'none';
            document.getElementById('modalActions').style.display = 'flex';

            // Remove event listener for date change to prevent memory leaks
            const dateInput = document.getElementById('newDate');
            if (dateInput) {
                dateInput.removeEventListener('change', updateMinTime);
            }

            // Reset appointment form elements
            const technicianSelect = document.getElementById('technicianSelect');
            if (technicianSelect) {
                technicianSelect.value = '';
            }

            // Reset appointment UI elements
            if (document.getElementById('assignForm')) {
                document.getElementById('assignForm').style.display = 'flex';
            }
            if (document.getElementById('assignHelp')) {
                document.getElementById('assignHelp').style.display = 'block';
            }
            if (document.getElementById('assignedInfo')) {
                document.getElementById('assignedInfo').style.display = 'none';
            }
            if (document.getElementById('assignSectionTitle')) {
                document.getElementById('assignSectionTitle').textContent = 'Assign Technicians';
            }
            if (document.getElementById('assignedTechList')) {
                document.getElementById('assignedTechList').innerHTML = '';
            }

            // Reset job order form elements
            const jobTechnicianSelect = document.getElementById('jobTechnicianSelect');
            if (jobTechnicianSelect) {
                jobTechnicianSelect.value = '';
            }

            // Reset job order UI elements
            if (document.getElementById('jobAssignForm')) {
                document.getElementById('jobAssignForm').style.display = 'flex';
            }
            if (document.getElementById('jobAssignHelp')) {
                document.getElementById('jobAssignHelp').style.display = 'block';
            }
            if (document.getElementById('jobAssignedInfo')) {
                document.getElementById('jobAssignedInfo').style.display = 'none';
            }
            if (document.getElementById('jobAssignSectionTitle')) {
                document.getElementById('jobAssignSectionTitle').textContent = 'Assign Technicians to Job Order';
            }
            if (document.getElementById('jobAssignedTechList')) {
                document.getElementById('jobAssignedTechList').innerHTML = '';
            }

            // Reset any loading states
            const assignButton = document.querySelector('#assignSection button');
            if (assignButton) {
                assignButton.innerHTML = '<i class="fas fa-user-check"></i> Assign';
                assignButton.disabled = false;
            }

            const jobAssignButton = document.querySelector('#jobAssignSection button');
            if (jobAssignButton) {
                jobAssignButton.innerHTML = '<i class="fas fa-user-check"></i> Assign';
                jobAssignButton.disabled = false;
            }

            // Hide debug section
            const debugSection = document.getElementById('debugSection');
            if (debugSection) {
                debugSection.style.display = 'none';
            }
        }
        function updateDatePicker() {
            const filterSelect = document.getElementById('filterSelect');
            const dateInput = document.getElementById('specificDate');
            dateInput.style.display = filterSelect.value === 'date' ? 'inline-block' : 'none';
        }

        // Function to update minimum time when date is today
        function updateMinTime() {
            const dateInput = document.getElementById('newDate');
            const timeInput = document.getElementById('newTime');
            const today = new Date().toISOString().split('T')[0];

            // Only apply min time restriction if the selected date is today
            if (dateInput.value === today) {
                const now = new Date();
                const currentMinutes = now.getMinutes();
                const roundedMinutes = currentMinutes < 30 ? 30 : 60;

                // Create a new date with rounded minutes
                const minTime = new Date();
                minTime.setMinutes(roundedMinutes);
                minTime.setSeconds(0);
                minTime.setMilliseconds(0);

                // Format as HH:MM for the min attribute
                const hours = minTime.getHours().toString().padStart(2, '0');
                const minutes = minTime.getMinutes().toString().padStart(2, '0');
                timeInput.min = `${hours}:${minutes}`;

                // If current value is less than min, update it
                if (timeInput.value && timeInput.value < timeInput.min) {
                    timeInput.value = timeInput.min;
                }

                // Show a message about the time restriction
                console.log(`Time restricted to ${hours}:${minutes} or later for today`);
            } else {
                // No restriction for future dates
                timeInput.min = '';
            }
        }

        // Debug helper function - only logs to console now
        function showDebugInfo(info) {
            // Only log to console for debugging
            console.log('Debug Info:', info);
        }
        // Initialize on load
        updateDatePicker();

        // Add hover effect for color legend items
        document.addEventListener('DOMContentLoaded', function() {
            const legendItems = document.querySelectorAll('.legend-item');

            legendItems.forEach(item => {
                const colorBox = item.querySelector('.color-box');
                const color = colorBox.style.backgroundColor;

                item.addEventListener('mouseenter', function() {
                    // Add a subtle highlight to all calendar events with this color
                    const calendarEvents = document.querySelectorAll('.fc-event');
                    calendarEvents.forEach(event => {
                        if (window.getComputedStyle(event).backgroundColor === color) {
                            event.style.boxShadow = '0 0 0 2px #fff, 0 0 0 4px ' + color;
                            event.style.zIndex = '10';
                        } else {
                            event.style.opacity = '0.6';
                        }
                    });
                });

                item.addEventListener('mouseleave', function() {
                    // Reset all events
                    const calendarEvents = document.querySelectorAll('.fc-event');
                    calendarEvents.forEach(event => {
                        event.style.boxShadow = '';
                        event.style.zIndex = '';
                        event.style.opacity = '';
                    });
                });
            });
        });
    </script>
    <!-- Notification Scripts -->
    <script src="js/notifications.js"></script>
    <script src="js/chemical-notifications.js"></script>
    <script>
        // Initialize mobile menu and notifications when the page loads
        $(document).ready(function() {
            // Mobile menu toggle
            $('#menuToggle').on('click', function() {
                $('.sidebar').toggleClass('active');
            });

            // Fetch notifications immediately
            if (typeof fetchNotifications === 'function') {
                fetchNotifications();

                // Set up periodic notification checks for real-time updates
                setInterval(fetchNotifications, 5000); // Check every 5 seconds
            } else {
                console.error("fetchNotifications function not found");
            }
        });
    </script>
</body>
</html>