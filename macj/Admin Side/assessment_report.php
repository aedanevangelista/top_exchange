<?php
session_start();
require_once '../db_connect.php';
require_once '../notification_functions.php';

// Get Dashboard Metrics
try {
    // Total Assessment Reports
    $stmt = $conn->query("SELECT COUNT(*) AS total FROM assessment_report");
    $total_reports = $stmt->fetch_assoc()['total'];

    // Reports with Job Orders
    $stmt = $conn->query("SELECT COUNT(DISTINCT ar.report_id) AS with_job_orders
                         FROM assessment_report ar
                         JOIN job_order jo ON ar.report_id = jo.report_id");
    $with_job_orders = $stmt->fetch_assoc()['with_job_orders'];

    // Reports without Job Orders
    $without_job_orders = $total_reports - $with_job_orders;

    // Reports with Feedback
    $stmt = $conn->query("SELECT COUNT(*) AS with_feedback
                         FROM assessment_report ar
                         JOIN technician_feedback tf ON ar.report_id = tf.report_id");
    $with_feedback = $stmt->fetch_assoc()['with_feedback'];

    // Average Feedback Rating
    $stmt = $conn->query("SELECT AVG(rating) AS avg_rating FROM technician_feedback");
    $avg_rating = $stmt->fetch_assoc()['avg_rating'];
    $avg_rating = $avg_rating ? round($avg_rating, 1) : 0;

} catch (Exception $e) {
    // Handle any errors
    $total_reports = 0;
    $with_job_orders = 0;
    $without_job_orders = 0;
    $with_feedback = 0;
    $avg_rating = 0;
}

// Handle Job Order Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_job_order'])) {
    $report_id = $conn->real_escape_string($_POST['report_id']);
    $preferred_date = $conn->real_escape_string($_POST['preferred_date']);
    $preferred_time = $conn->real_escape_string($_POST['preferred_time']);
    $frequency = $conn->real_escape_string($_POST['frequency']);
    // Technicians are no longer assigned during quotation generation

    // Handle multiple work types
    $work_types = isset($_POST['type_of_work']) && is_array($_POST['type_of_work']) ? $_POST['type_of_work'] : [];
    $type_of_work = !empty($work_types) ? implode(', ', array_map(function($type) use ($conn) {
        return $conn->real_escape_string($type);
    }, $work_types)) : '';

    // Handle selected chemicals
    $selected_chemicals = isset($_POST['selected_chemicals']) ? $_POST['selected_chemicals'] : '';

    // Check if job order already exists for this report
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_order WHERE report_id = ?");
    $check_stmt->bind_param("i", $report_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_row = $check_result->fetch_assoc();
    $check_stmt->close();

    if ($check_row['count'] > 0) {
        // Job order already exists, redirect with error message
        header("Location: assessment_report.php?error=job_order_exists");
        exit;
    }

    // Check if client has verified the technician's work
    $verify_stmt = $conn->prepare("SELECT technician_arrived, job_completed FROM technician_feedback WHERE report_id = ?");
    $verify_stmt->bind_param("i", $report_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();

    if ($verify_result->num_rows === 0) {
        // No feedback/verification exists, redirect with error message
        header("Location: assessment_report.php?error=verification_required");
        exit;
    }

    $verification = $verify_result->fetch_assoc();
    if ($verification['technician_arrived'] != 1 || $verification['job_completed'] != 1) {
        // Verification failed, redirect with error message
        header("Location: assessment_report.php?error=verification_failed");
        exit;
    }
    $verify_stmt->close();

    // Calculate end date (1 year from start date)
    $end_date = date('Y-m-d', strtotime($preferred_date . ' + 1 year'));

    // First, add the main job order record
    // Set client_approval_status to 'pending' for non-one-time frequencies
    $approval_status = ($frequency === 'one-time') ? 'approved' : 'pending';

    // Get cost from form
    $cost = isset($_POST['cost']) ? floatval($_POST['cost']) : 0;

    $stmt = $conn->prepare("INSERT INTO job_order (report_id, type_of_work, preferred_date, preferred_time, frequency, client_approval_status, chemical_recommendations, cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssssd", $report_id, $type_of_work, $preferred_date, $preferred_time, $frequency, $approval_status, $selected_chemicals, $cost);
    $stmt->execute();
    $job_order_id = $conn->insert_id;
    $stmt->close();

    // Technicians are no longer assigned during quotation generation
    // They will be assigned later in the calendar after client approval

    // Create recurring job orders if frequency is not one-time
    $recurring_job_ids = [];
    if ($frequency !== 'one-time') {
        $current_date = $preferred_date;
        $interval = '';

        // Set the appropriate interval based on frequency
        switch ($frequency) {
            case 'weekly':
                $interval = '1 week';
                break;
            case 'monthly':
                $interval = '1 month';
                break;
            case 'quarterly':
                $interval = '3 months';
                break;
        }

        // Create recurring job orders
        $stmt = $conn->prepare("INSERT INTO job_order (report_id, type_of_work, preferred_date, preferred_time, frequency, client_approval_status, chemical_recommendations, cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        // Start from the next occurrence (skip the first one as it's already created)
        $current_date = date('Y-m-d', strtotime($current_date . ' + ' . $interval));

        while (strtotime($current_date) <= strtotime($end_date)) {
            $stmt->bind_param("issssssd", $report_id, $type_of_work, $current_date, $preferred_time, $frequency, $approval_status, $selected_chemicals, $cost);
            $stmt->execute();
            $recurring_job_id = $conn->insert_id;
            $recurring_job_ids[] = $recurring_job_id;

            // Note: We don't assign technicians to recurring job orders until after client approval
            // This will be handled when the client approves the treatment plan

            // Move to the next date
            $current_date = date('Y-m-d', strtotime($current_date . ' + ' . $interval));
        }

        $stmt->close();
    }

    // Technicians are no longer assigned during quotation generation
    $tech_names = [];

    // Store success data in session
    $_SESSION['job_order_success'] = [
        'job_order_id' => $job_order_id,
        'type_of_work' => $type_of_work,
        'preferred_date' => $preferred_date,
        'preferred_time' => $preferred_time,
        'frequency' => $frequency,
        'technicians' => $tech_names,
        'recurring_count' => count($recurring_job_ids)
    ];

    // Get client ID from the report ID to send notification
    $client_query = $conn->prepare("
        SELECT a.client_id
        FROM assessment_report ar
        JOIN appointments a ON ar.appointment_id = a.appointment_id
        WHERE ar.report_id = ?
    ");
    $client_query->bind_param("i", $report_id);
    $client_query->execute();
    $client_result = $client_query->get_result();

    if ($client_result->num_rows > 0) {
        $client_data = $client_result->fetch_assoc();
        $client_id = $client_data['client_id'];

        // Send notification to client about new quotation
        notifyClientAboutQuotation($client_id, $job_order_id, $report_id, $type_of_work, $frequency);
    }

    header("Location: assessment_report.php?success=job_order_created");
    exit;
}

// Get filter parameters
$client_filter = isset($_GET['client_name']) ? $conn->real_escape_string($_GET['client_name']) : '';
$technician_filter = isset($_GET['technician_name']) ? $conn->real_escape_string($_GET['technician_name']) : '';

// Fetch assessment reports with filters
$report_query = "SELECT ar.report_id, ar.area, ar.notes AS assessment_notes, ar.recommendation, ar.attachments, ar.created_at, ar.end_time,
                        ar.pest_types, ar.problem_area,
                        a.appointment_id, a.client_name, a.location_address, a.kind_of_place,
                        a.preferred_date, a.preferred_time, a.contact_number, a.email,
                        a.notes AS client_notes, a.pest_problems,
                        t.username AS technician_name,
                        COUNT(jo.job_order_id) AS job_order_count,
                        jo.job_order_id,
                        jo.type_of_work,
                        jo.preferred_date AS jo_preferred_date,
                        jo.preferred_time AS jo_preferred_time,
                        jo.frequency,
                        jo.client_approval_status,
                        jo.client_approval_date,
                        jo.chemical_recommendations,
                        jo.cost,
                        tf.rating as feedback_rating,
                        tf.comments as feedback_comments,
                        tf.created_at as feedback_date,
                        tf.technician_arrived,
                        tf.job_completed,
                        tf.verification_notes
                 FROM assessment_report ar
                 JOIN appointments a ON ar.appointment_id = a.appointment_id
                 LEFT JOIN technician_feedback tf ON ar.report_id = tf.report_id
                 JOIN technicians t ON a.technician_id = t.technician_id
                 LEFT JOIN job_order jo ON ar.report_id = jo.report_id
                 WHERE 1=1";

// Add filters if provided
if (!empty($client_filter)) {
    $report_query .= " AND a.client_name LIKE '%$client_filter%'";
}

if (!empty($technician_filter)) {
    $report_query .= " AND t.username LIKE '%$technician_filter%'";
}

$report_query .= " GROUP BY ar.report_id
                 ORDER BY ar.created_at DESC";
$report_result = $conn->query($report_query);

// Prepare to fetch technicians for each job order
$job_order_technicians = [];
if ($report_result->num_rows > 0) {
    $temp_result = $report_result->fetch_all(MYSQLI_ASSOC);

    // Get all job order IDs that exist
    $job_order_ids = [];
    foreach ($temp_result as $row) {
        if ($row['job_order_count'] > 0 && !empty($row['job_order_id'])) {
            $job_order_ids[] = $row['job_order_id'];
        }
    }

    // If we have job orders, fetch their technicians
    if (!empty($job_order_ids)) {
        $ids_str = implode(',', $job_order_ids);
        $tech_query = "SELECT jot.job_order_id, t.username
                      FROM job_order_technicians jot
                      JOIN technicians t ON jot.technician_id = t.technician_id
                      WHERE jot.job_order_id IN ($ids_str)";
        $tech_result = $conn->query($tech_query);

        while ($tech = $tech_result->fetch_assoc()) {
            if (!isset($job_order_technicians[$tech['job_order_id']])) {
                $job_order_technicians[$tech['job_order_id']] = [];
            }
            $job_order_technicians[$tech['job_order_id']][] = $tech['username'];
        }
    }

    // Reset the result pointer
    $report_result = $conn->query($report_query);
}

// Fetch technicians for dropdown
$tech_query = "SELECT technician_id, username FROM technicians";
$tech_result = $conn->query($tech_query);
$technicians = [];
while ($tech = $tech_result->fetch_assoc()) {
    $technicians[] = $tech;
}

// Fetch unique client names for filter dropdown
$client_query = "SELECT DISTINCT client_name FROM appointments ORDER BY client_name ASC";
$client_result = $conn->query($client_query);
$clients = [];
while ($client = $client_result->fetch_assoc()) {
    $clients[] = $client;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Reports - MacJ Pest Control</title>
    <link rel="stylesheet" href="css/assessment-table.css">
    <link rel="stylesheet" href="css/tools-equipment-page.css">
    <link rel="stylesheet" href="css/modern-modal.css">
    <link rel="stylesheet" href="css/notification-override.css">
    <link rel="stylesheet" href="css/notification-viewed.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/notifications.css">
    <!-- jQuery and jsPDF libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
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

        /* Chemical notification styles */
        .notification-icon-wrapper {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: rgba(59, 130, 246, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            flex-shrink: 0;
        }

        .notification-icon-wrapper.chemical-expiring {
            background-color: #ffe6e6;
        }

        .notification-icon-wrapper.chemical-expiring i {
            color: #cc0000;
        }

        .notification-item {
            display: flex;
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }

        .notification-item:hover {
            background-color: #f9f9f9;
        }

        .notification-item.unread {
            background-color: #f0f7ff;
        }

        .notification-item.unread:hover {
            background-color: #e6f0ff;
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

        .report-card { background: white; border-radius: 8px; box-shadow: var(--shadow); margin-bottom: 20px; transition: var(--transition); cursor: pointer; overflow: hidden; display: flex; flex-direction: column; }
        .report-header {
            padding: 20px;
            display: flex;
            justify-content: flex-start;
            align-items: flex-start;
            background: #f8f9fa;
            gap: 20px;
        }
        .report-header > div:first-child {
            flex: 1;
        }
        .report-info h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1.2rem;
            color: var(--accent-color);
        }
        .report-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .report-location {
            font-weight: 500;
            color: #555;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .report-location i {
            color: #e74c3c;
        }
        .report-time {
            display: flex;
            flex-direction: column;
            gap: 5px;
            font-size: 0.9rem;
            color: #666;
        }
        .report-time i {
            width: 16px;
            color: var(--accent-color);
        }
        .report-details { padding: 0 20px; max-height: 0; overflow: hidden; transition: max-height 0.5s ease-out; flex: 1; }
        .report-details.active { padding: 20px; max-height: 1000px; flex: 1; }
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.9em; }
        .status-completed { background: rgba(46, 204, 113, 0.1); color: var(--success-color); }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
        }
        .modal-content {
            background: white;
            margin: 3% auto;
            padding: 0;
            width: 95%;
            max-width: 900px;
            border-radius: 8px;
            position: relative;
            box-shadow: 0 15px 40px rgba(0,0,0,0.25);
            overflow: hidden;
            animation: modalFadeIn 0.3s ease-out;
            display: flex;
            flex-direction: column;
            height: 90vh;
        }

        .modal-header {
            background: #2962ff;
            color: white;
            padding: 15px 25px;
            position: relative;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 500;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: none;
            padding-bottom: 0;
        }

        .modal-header .close {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: white;
            opacity: 0.8;
            font-size: 1.5rem;
        }

        .modal-header .close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 25px;
            overflow-y: auto;
            flex: 1;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        /* Removed modal-content::before */
        /* Modal content h2 styling moved to modal-header */
        .modal-content h2 i {
            background: var(--accent-color);
            color: white;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 1.1rem;
        }
        /* Close button styling moved to modal-header */
        .create-job-btn {
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .create-job-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        }
        .create-job-btn:active {
            transform: translateY(0);
        }
        .create-job-btn::before {
            content: '\f067'; /* fa-plus */
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
        }

        .view-feedback-btn {
            background: #f8f9fa;
            color: var(--accent-color);
            border: 1px solid #ddd;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            width: fit-content;
        }
        .view-feedback-btn:hover {
            background: #e9ecef;
            color: var(--secondary-color);
        }

        /* Job Order Status Styles */
        .job-order-container {
            display: flex;
            align-items: flex-start;
            justify-content: flex-end;
            min-width: 300px;
        }
        .job-order-status {
            display: flex;
            flex-direction: column;
            gap: 8px;
            width: 100%;
        }
        .job-order-badge {
            background: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
            width: fit-content;
        }
        .job-order-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px 15px;
            background: #f8f9fa;
            border: 1px solid #eee;
            border-radius: 6px;
            padding: 12px;
            font-size: 0.85rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
            width: 100%;
        }
        .job-order-detail {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .job-order-detail .detail-label {
            color: #666;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .job-order-detail .detail-label i {
            color: var(--accent-color);
            width: 16px;
        }
        .job-order-detail .detail-value {
            font-weight: 600;
            color: #333;
        }
        .detail-row { display: flex; flex-wrap: wrap; gap: 20px; }
        .detail-item { flex: 1; min-width: 250px; }

        /* Detail Styles */
        .detail-section {
            margin-bottom: 30px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            overflow: hidden;
            border: 1px solid #eaeaea;
        }
        .detail-section h3 {
            margin: 0;
            padding: 15px 20px;
            color: #2962ff;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #eaeaea;
        }
        .detail-section h3 i {
            color: #2962ff;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
            padding: 20px;
        }
        .detail-label {
            font-weight: 500;
            color: #666;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }
        .detail-label i {
            color: #2962ff;
            width: 16px;
        }
        .detail-value {
            font-weight: 600;
            color: #333;
            background-color: #f8f9fa;
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #eaeaea;
        }

        /* Feedback Styles */
        .feedback-section {
            background-color: white;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #eaeaea;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .feedback-section h3 {
            margin: 0;
            padding: 15px 20px;
            color: #2962ff;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #eaeaea;
        }
        .feedback-section:hover {
            box-shadow: 0 5px 12px rgba(0,0,0,0.08);
            border-color: #d0d0d0;
        }
        .feedback-section h3 {
            color: var(--accent-color);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .feedback-section h3 i {
            color: var(--accent-color);
        }
        .feedback-section strong {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            color: var(--accent-color);
            font-size: 1rem;
        }
        .feedback-display {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .rating-stars {
            font-size: 1.6rem;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 20px;
            background-color: #fff;
        }
        .feedback-comments {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #eaeaea;
            margin: 20px;
            position: relative;
        }
        .feedback-comments p {
            margin-bottom: 12px;
            line-height: 1.6;
            font-size: 1.05rem;
        }

        /* Chemical Recommendations Styles */
        #chemicalRecommendationsDetailSection table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 0.95rem;
        }

        #chemicalRecommendationsDetailSection th {
            background-color: #f8f9fa;
            font-weight: bold;
            text-align: left;
            padding: 10px;
            border-bottom: 2px solid #dee2e6;
        }

        #chemicalRecommendationsDetailSection td {
            padding: 8px 10px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
            color: #333;
        }
        .feedback-comments small {
            display: block;
            text-align: right;
            font-style: italic;
            color: #777;
            margin-top: 12px;
            border-top: 1px dashed #e0e0e0;
            padding-top: 10px;
            font-size: 0.9rem;
        }
        .feedback-comments::before {
            content: '"';
            position: absolute;
            top: 5px;
            left: 10px;
            font-size: 2rem;
            color: #f0f0f0;
            font-family: Georgia, serif;
            line-height: 1;
        }
        .feedback-comments p {
            padding-left: 15px;
            position: relative;
        }

        /* Client Approval Styles */
        .client-approval-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }

        .approval-approved {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .approval-declined {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        .approval-one-time {
            background-color: #e3f2fd;
            color: #1565c0;
            border: 1px solid #bbdefb;
        }

        .approval-pending {
            background-color: #fff8e1;
            color: #f57c00;
            border: 1px solid #ffecb3;
        }

        .approval-date {
            font-size: 0.8rem;
            color: #666;
            margin-top: 3px;
        }

        /* Attachment Gallery Styles */
        .attachments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            padding: 20px;
        }

        .attachment-item {
            border: 1px solid #eaeaea;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            height: 150px;
        }

        .attachment-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
            border-color: #2962ff;
        }

        .attachment-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .text-warning {
            color: #ffb400 !important;
            text-shadow: 0 1px 2px rgba(0,0,0,0.15);
        }
        .text-secondary {
            color: #d8d8d8 !important;
            text-shadow: 0 1px 1px rgba(0,0,0,0.05);
        }
        .alert-info {
            background-color: rgba(13, 202, 240, 0.1);
            color: #0dcaf0;
            padding: 15px 20px;
            border-radius: 8px;
            border-left: 4px solid #0dcaf0;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.05rem;
            box-shadow: 0 3px 6px rgba(0,0,0,0.05);
            margin: 10px 0;
        }
        .alert-info i {
            font-size: 1.3rem;
        }
        /* Filter Styles */
        .filter-section {
            background: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 20px;
        }
        .filter-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .filter-buttons {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        .filter-btn {
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .filter-btn:hover {
            background: var(--secondary-color);
        }
        .reset-btn {
            background: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .reset-btn:hover {
            background: #e9ecef;
        }
        .filter-status {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        .filter-status p {
            margin: 0;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .filter-tag {
            background: var(--accent-color);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.9em;
            display: inline-flex;
            align-items: center;
            margin-left: 5px;
        }
        .no-reports {
            background: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 40px 20px;
            text-align: center;
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }
        .no-reports i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 10px;
        }
        .no-reports p {
            font-size: 1.1rem;
            color: #666;
            margin: 0;
        }
        .existing-job-message {
            padding: 25px;
            background: rgba(46, 204, 113, 0.1);
            border-radius: 12px;
            margin: 0 0 30px;
            display: flex;
            align-items: flex-start;
            gap: 18px;
            border-left: 4px solid var(--success-color);
            box-shadow: 0 3px 10px rgba(46, 204, 113, 0.1);
            animation: fadeInUp 0.5s ease-out;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .message-icon {
            background: linear-gradient(135deg, var(--success-color), #27ae60);
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 8px rgba(46, 204, 113, 0.3);
        }
        .message-icon i {
            font-size: 1.3rem;
            color: white;
        }
        .message-content {
            flex: 1;
        }
        .message-content h3 {
            margin: 0 0 8px;
            font-size: 1.2rem;
            color: var(--success-color);
            font-weight: 600;
        }
        .message-content p {
            margin: 0;
            color: #444;
            line-height: 1.5;
            font-size: 1.05rem;
        }

        /* Modal Form Styles */
        .modal-form {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        .form-section {
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 25px;
            background: #fafafa;
            background-image: radial-gradient(#f0f0f0 1px, transparent 1px);
            background-size: 20px 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .form-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: linear-gradient(to bottom, var(--accent-color), var(--secondary-color));
            opacity: 0.7;
        }
        .form-section:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-color: #ddd;
        }
        .form-section h3 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.15rem;
            color: var(--accent-color);
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 1px dashed #e0e0e0;
            position: relative;
        }
        .form-section h3 i {
            background: linear-gradient(135deg, var(--accent-color), var(--secondary-color));
            color: white;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 0.9rem;
            box-shadow: 0 3px 6px rgba(0,0,0,0.1);
        }
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 25px;
            margin-bottom: 5px;
        }
        .form-row .form-group {
            flex: 1;
            min-width: 200px;
        }
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        .form-group:last-child {
            margin-bottom: 0;
        }
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #444;
        }
        .form-group label i {
            color: var(--accent-color);
            width: 20px;
            text-align: center;
        }
        .form-help {
            margin: 5px 0 8px;
            font-size: 0.85rem;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .form-help i {
            color: #888;
            font-size: 0.8rem;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s ease;
            background-color: white;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
        }
        .form-control:focus {
            border-color: var(--accent-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(var(--accent-color-rgb), 0.15), inset 0 1px 3px rgba(0,0,0,0.05);
        }
        .form-control:hover:not(:focus) {
            border-color: #bbb;
        }
        select.form-control {
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="12" height="6"><path d="M0 0l6 6 6-6z" fill="%23666"/></svg>');
            background-repeat: no-repeat;
            background-position: right 15px center;
            padding-right: 35px;
        }
        select[multiple].form-control {
            height: 160px;
            padding: 10px;
            background-image: none;
        }
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 15px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .cancel-btn {
            background: #f8f9fa;
            color: #555;
            border: 1px solid #ddd;
            padding: 14px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .cancel-btn:hover {
            background: #e9ecef;
            border-color: #ccc;
        }
        .submit-btn {
            background: linear-gradient(135deg, var(--accent-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.05rem;
            font-weight: 500;
            box-shadow: 0 4px 10px rgba(var(--accent-color-rgb), 0.3);
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .submit-btn:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(var(--accent-color-rgb), 0.4);
        }
        .submit-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 5px rgba(var(--accent-color-rgb), 0.3);
        }

        /* Success and Error Messages */
        .success-message {
            background: rgba(46, 204, 113, 0.1);
            border-left: 4px solid var(--success-color);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: flex-start;
            position: relative;
        }
        .success-icon {
            font-size: 2rem;
            color: var(--success-color);
            margin-right: 20px;
            flex-shrink: 0;
        }
        .success-content {
            flex: 1;
        }
        .success-content h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: var(--success-color);
        }
        .success-details {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        .success-detail {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .detail-label {
            font-weight: 500;
            color: #555;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .detail-label i {
            color: var(--success-color);
        }
        .detail-value {
            font-size: 1.1rem;
        }
        .close-success, .close-message {
            background: transparent;
            border: none;
            color: #aaa;
            font-size: 1.2rem;
            cursor: pointer;
            position: absolute;
            top: 15px;
            right: 15px;
            transition: color 0.2s;
        }
        .close-success:hover, .close-message:hover {
            color: #555;
        }
        .error-message {
            background: rgba(231, 76, 60, 0.1);
            border-left: 4px solid #e74c3c;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
        }
        .error-message i {
            font-size: 1.5rem;
            color: #e74c3c;
        }
        .error-message p {
            margin: 0;
            font-weight: 500;
        }

        /* Work Type Container Styles */
        .work-type-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .work-types-checkbox-container {
            margin-bottom: 15px;
        }

        .work-types-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            background-color: #f9f9f9;
        }

        .work-type-checkbox-item {
            display: flex;
            align-items: center;
            padding: 5px;
        }

        .work-type-checkbox-item label {
            display: flex;
            align-items: center;
            margin-bottom: 0;
            cursor: pointer;
            font-weight: normal;
        }

        .work-type-checkbox-item input[type="checkbox"] {
            margin-right: 8px;
        }

        .work-type-actions {
            display: flex;
            gap: 10px;
        }

        .work-type-selection {
            position: relative;
        }

        .input-group {
            display: flex;
            flex-wrap: nowrap;
        }

        .input-group-append {
            display: flex;
        }

        .input-group .form-control {
            flex: 1;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }

        .input-group-append .btn {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .input-group-append .btn:not(:last-child) {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }

        #new_work_type_container {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        #new_work_type_container:focus-within {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(var(--accent-color-rgb), 0.15);
        }

        #add_work_type_btn {
            background-color: var(--accent-color);
            color: white;
            border: none;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            line-height: 1.5;
            transition: all 0.2s ease;
        }

        #add_work_type_btn:hover {
            background-color: var(--secondary-color);
        }

        #manage_work_types_btn {
            background-color: #6c757d;
            color: white;
            border: none;
        }

        #manage_work_types_btn:hover {
            background-color: #5a6268;
        }

        /* Work Types List Styles */
        .work-types-list {
            margin-top: 20px;
        }

        .work-type-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.2s ease;
        }

        .work-type-item:hover {
            background-color: #e9ecef;
            border-color: #dee2e6;
        }

        .work-type-name {
            font-weight: 500;
            color: #333;
            flex: 1;
        }

        .work-type-actions {
            display: flex;
            gap: 10px;
        }

        .delete-work-type-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .delete-work-type-btn:hover {
            background-color: #c82333;
        }

        .default-type {
            position: relative;
        }

        .default-type::after {
            content: 'Default';
            position: absolute;
            top: 0;
            right: 0;
            background-color: #6c757d;
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 3px;
            transform: translate(0, -50%);
        }

        .loading-spinner {
            text-align: center;
            padding: 20px;
            color: #6c757d;
        }

        /* Print styles for assessment report */
        @media print {
            body * {
                visibility: hidden;
            }

            #detailsModal,
            #detailsModal * {
                visibility: visible;
            }

            #detailsModal {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                overflow: visible;
            }

            #detailsModal .modal-content {
                width: 100%;
                max-width: 100%;
                margin: 0;
                padding: 0;
                border: none;
                box-shadow: none;
                height: auto;
            }

            #detailsModal .modal-header,
            #detailsModal .modal-footer,
            #saveAsPdfBtn,
            #printReportBtn,
            #closeDetailsBtn,
            #createJobFromDetailsBtn,
            .close {
                display: none !important;
            }

            .detail-section {
                break-inside: avoid;
                page-break-inside: avoid;
                margin-bottom: 1.5rem;
                box-shadow: none;
                border: 1px solid #dee2e6;
            }

            /* Add a title to the printed page */
            #detailsModal .modal-body::before {
                content: "Assessment Report";
                display: block;
                font-size: 24px;
                font-weight: bold;
                color: #2962ff;
                text-align: center;
                margin-bottom: 10px;
                border-bottom: 2px solid #2962ff;
                padding-bottom: 10px;
            }

            /* Hide any buttons that might be inside the modal content */
            button, .btn, input[type="button"], input[type="submit"] {
                display: none !important;
            }

            /* Ensure proper spacing and borders for print */
            .modal-body {
                padding: 20px !important;
            }

            /* Add page numbers */
            @page {
                margin: 0.5cm;
            }
        }

        /* Chemical Recommendations Styles */
        .recommendations-container {
            margin-top: 15px;
        }

        .recommendation-category {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #e9ecef;
        }

        .recommendation-category h4 {
            color: var(--accent-color);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.1rem;
        }

        .recommendations-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .recommendations-table th,
        .recommendations-table td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .recommendations-table th {
            background-color: #f1f3f5;
            font-weight: 600;
            color: #495057;
        }

        .recommendations-table tr:hover {
            background-color: #f1f3f5;
        }

        .chemical-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
    </style>
</head>
<body>
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
                    <li><a href="calendar.php"><i class="fas fa-calendar-alt"></i> Calendar</a></li>
                    <li class="active"><a href="assessment_report.php"><i class="fas fa-clipboard-check"></i> Assessment Report</a></li>
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

        <!-- Mobile menu toggle -->
        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>

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

        <!-- Main Content -->
        <main class="main-content">
            <div class="tools-content">
                <div class="tools-header">
                    <h1>Assessment Reports</h1>
                </div>

                <!-- Assessment Summary -->
                <div class="inventory-summary">
                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--primary-color);">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Total Reports</h3>
                            <p><?= $total_reports ?></p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--success-color);">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="summary-info">
                            <h3>With Job Orders</h3>
                            <p><?= $with_job_orders ?></p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--warning-color);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Pending Job Orders</h3>
                            <p><?= $without_job_orders ?></p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--info-color);">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Average Rating</h3>
                            <p><?= $avg_rating ?> / 5</p>
                        </div>
                    </div>
                </div>

        <?php if (isset($_GET['success']) && $_GET['success'] === 'job_order_created' && isset($_SESSION['job_order_success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <div>
                <strong>Job Order Created Successfully!</strong>
                <p>Type: <?= htmlspecialchars($_SESSION['job_order_success']['type_of_work']) ?> |
                   Date: <?= date('F j, Y', strtotime($_SESSION['job_order_success']['preferred_date'])) ?> |
                   Time: <?= date('g:i A', strtotime($_SESSION['job_order_success']['preferred_time'])) ?> |
                   Frequency: <?= ucfirst(htmlspecialchars($_SESSION['job_order_success']['frequency'])) ?> |
                   Technicians: <?= !empty($_SESSION['job_order_success']['technicians']) ? htmlspecialchars(implode(', ', $_SESSION['job_order_success']['technicians'])) : 'None' ?></p>
                <?php if (isset($_SESSION['job_order_success']['recurring_count']) && $_SESSION['job_order_success']['recurring_count'] > 0): ?>
                <p><i class="fas fa-calendar-alt"></i> <strong><?= $_SESSION['job_order_success']['recurring_count'] ?></strong> additional recurring appointments have been scheduled for the next year.</p>
                <?php if ($_SESSION['job_order_success']['frequency'] !== 'one-time'): ?>
                <p><i class="fas fa-exclamation-circle"></i> <strong>Note:</strong> Client approval is required for the recurring schedule. The client will be prompted to approve, decline, or choose a one-time treatment only.</p>
                <p><i class="fas fa-calendar-alt"></i> <strong>Important:</strong> Job orders will only appear in the calendar and technician assignments will only be applied after client approval. One-time treatments are automatically approved.</p>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php unset($_SESSION['job_order_success']); ?>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'job_order_exists'): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <p>A job order already exists for this assessment report.</p>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'verification_required'): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Verification Required:</strong>
                <p>The client must verify the technician's work before a job order can be created. Please ask the client to provide feedback and verification through their client portal.</p>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'verification_failed'): ?>
        <div class="alert alert-danger">
            <i class="fas fa-times-circle"></i>
            <div>
                <strong>Verification Failed:</strong>
                <p>The client has indicated issues with the technician's work. Please review the feedback and address any concerns before creating a job order.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filter Controls -->
        <div class="filter-container">
            <form id="filterForm" method="GET" style="display: flex; flex-wrap: wrap; gap: 15px; width: 100%;">
                <div class="filter-group">
                    <label for="client_name">Client:</label>
                    <select name="client_name" id="client_name" onchange="this.form.submit()">
                        <option value="">All Clients</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= htmlspecialchars($client['client_name']) ?>" <?= ($client_filter === $client['client_name']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($client['client_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="technician_name">Technician:</label>
                    <select name="technician_name" id="technician_name" onchange="this.form.submit()">
                        <option value="">All Technicians</option>
                        <?php foreach ($technicians as $tech): ?>
                            <option value="<?= htmlspecialchars($tech['username']) ?>" <?= ($technician_filter === $tech['username']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tech['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <a href="assessment_report.php" class="btn btn-secondary" style="margin-top: 24px;">
                        <i class="fas fa-sync-alt"></i> Reset Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Filter Status Message -->
        <?php if (!empty($client_filter) || !empty($technician_filter)): ?>
        <div class="alert alert-info">
            <i class="fas fa-filter"></i>
            <div>
                <strong>Filtered Results:</strong>
                <?php if (!empty($client_filter)): ?>
                    Client: <strong><?= htmlspecialchars($client_filter) ?></strong>
                <?php endif; ?>
                <?php if (!empty($technician_filter)): ?>
                    <?php if (!empty($client_filter)): ?> | <?php endif; ?>
                    Technician: <strong><?= htmlspecialchars($technician_filter) ?></strong>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="assessment-table-container">
            <?php if ($report_result->num_rows > 0): ?>
                <table class="assessment-table">
                    <thead>
                        <tr>
                            <th class="col-id">ID</th>
                            <th class="col-client">Client</th>
                            <th class="col-location">Location</th>
                            <th class="col-date">Date</th>
                            <th class="col-technician">Technician</th>
                            <th class="col-status">Job Order</th>
                            <th class="col-actions text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($report = $report_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($report['report_id']) ?></td>
                            <td><?= htmlspecialchars($report['client_name']) ?></td>
                            <td class="truncate" title="<?= htmlspecialchars($report['location_address']) ?>">
                                <?= htmlspecialchars($report['location_address']) ?>
                            </td>
                            <td><?= date('M j, Y', strtotime($report['preferred_date'])) ?></td>
                            <td><?= htmlspecialchars($report['technician_name']) ?></td>
                            <td>
                                <?php if ($report['job_order_count'] > 0): ?>
                                    <span class="job-order-badge">
                                        <i class="fas fa-check-circle"></i> Created
                                    </span>
                                <?php else: ?>
                                    <span class="no-job-order">None</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="action-buttons">
                                    <button class="btn-sm btn-info view-details-btn"
                                            data-report-id="<?= htmlspecialchars($report['report_id']) ?>"
                                            data-client="<?= htmlspecialchars($report['client_name']) ?>"
                                            data-email="<?= htmlspecialchars($report['email']) ?>"
                                            data-phone="<?= htmlspecialchars($report['contact_number']) ?>"
                                            data-location="<?= htmlspecialchars($report['location_address']) ?>"
                                            data-property="<?= htmlspecialchars($report['kind_of_place']) ?>"
                                            data-area="<?= htmlspecialchars($report['area']) ?>"
                                            data-date="<?= date('F j, Y', strtotime($report['preferred_date'])) ?>"
                                            data-time="<?= date('g:i A', strtotime($report['preferred_time'])) ?>"
                                            data-technician="<?= htmlspecialchars($report['technician_name']) ?>"
                                            data-notes="<?= htmlspecialchars($report['assessment_notes']) ?>"
                                            data-recommendation="<?= htmlspecialchars($report['recommendation']) ?>"
                                            data-client-notes="<?= htmlspecialchars($report['client_notes']) ?>"
                                            data-attachments="<?= htmlspecialchars($report['attachments']) ?>"
                                            data-pest-types="<?= htmlspecialchars($report['pest_types'] ?? '') ?>"
                                            data-pest-problems="<?= htmlspecialchars($report['pest_problems'] ?? '') ?>"
                                            data-problem-area="<?= htmlspecialchars($report['problem_area'] ?? '') ?>"
                                            data-feedback-rating="<?= htmlspecialchars($report['feedback_rating'] ?? '') ?>"
                                            data-feedback-comments="<?= htmlspecialchars($report['feedback_comments'] ?? '') ?>"
                                            data-feedback-date="<?= htmlspecialchars($report['feedback_date'] ?? '') ?>"
                                            data-technician-arrived="<?= htmlspecialchars($report['technician_arrived'] ?? '') ?>"
                                            data-job-completed="<?= htmlspecialchars($report['job_completed'] ?? '') ?>"
                                            data-verification-notes="<?= htmlspecialchars($report['verification_notes'] ?? '') ?>"
                                            data-job-order-id="<?= htmlspecialchars($report['job_order_id'] ?? '') ?>"
                                            data-job-order-type="<?= htmlspecialchars($report['type_of_work'] ?? '') ?>"
                                            data-job-order-date="<?= !empty($report['jo_preferred_date']) ? date('M j, Y', strtotime($report['jo_preferred_date'])) : '' ?>"
                                            data-job-order-time="<?= !empty($report['jo_preferred_time']) ? date('g:i A', strtotime($report['jo_preferred_time'])) : '' ?>"
                                            data-job-order-techs="<?= isset($job_order_technicians[$report['job_order_id']]) ? htmlspecialchars(implode(', ', $job_order_technicians[$report['job_order_id']])) : '' ?>"
                                            data-job-order-frequency="<?= htmlspecialchars($report['frequency'] ?? '') ?>"
                                            data-job-order-approval-status="<?= htmlspecialchars($report['client_approval_status'] ?? '') ?>"
                                            data-job-order-approval-date="<?= !empty($report['client_approval_date']) ? date('M j, Y', strtotime($report['client_approval_date'])) : '' ?>"
                                            data-chemical-recommendations="<?= htmlspecialchars($report['chemical_recommendations'] ?? '') ?>"
                                            data-job-order-cost="<?= !empty($report['cost']) ? number_format($report['cost'], 2) : '' ?>"
                                    >
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
        <?php else: ?>
            <div class="alert alert-info" style="text-align: center; padding: 30px;">
                <i class="fas fa-clipboard-check" style="font-size: 2rem; margin-bottom: 15px;"></i>
                <h3>No Assessment Reports Found</h3>
                <?php if (!empty($client_filter) || !empty($technician_filter)): ?>
                    <p>No assessment reports found matching your filter criteria.</p>
                    <a href="assessment_report.php" class="btn btn-secondary"><i class="fas fa-sync-alt"></i> Clear Filters</a>
                <?php else: ?>
                    <p>No assessment reports found in the system.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        </div>
            </div>
        </main>
    </div>

    <!-- Job Order Modal -->
    <div id="jobOrderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title"><i class="fas fa-clipboard-list"></i> Generate Quotation</h2>
                <button class="close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-success" style="display: none;" id="existingJobMessage">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>Job Order Already Exists</strong>
                        <p>A Job Order has already been assigned to this assessment report.</p>
                    </div>
                </div>
                <form method="POST" id="jobOrderForm">
                    <input type="hidden" name="report_id" id="modalReportId">

                    <div class="detail-section">
                        <h3><i class="fas fa-tasks"></i> Service Details</h3>
                        <div class="form-group">
                            <label><i class="fas fa-briefcase"></i> Type of Work:</label>
                            <div class="work-type-container">
                                <div class="work-type-selection">
                                    <div class="work-types-checkbox-container">
                                        <div class="work-types-grid">
                                            <?php
                                            // Define default work types
                                            $default_work_types = [
                                                'General Pest Control',
                                                'Rodent Control Only',
                                                'Termite Baiting',
                                                'Weed Control',
                                                'Disinfection'
                                            ];

                                            // Output default work types as checkboxes
                                            foreach ($default_work_types as $type) {
                                                echo '<div class="work-type-checkbox-item">';
                                                echo '<label>';
                                                echo '<input type="checkbox" name="type_of_work[]" value="' . htmlspecialchars($type) . '"> ';
                                                echo htmlspecialchars($type);
                                                echo '</label>';
                                                echo '</div>';
                                            }

                                            // Fetch custom work types from database if they exist
                                            try {
                                                // Get custom work types from job_order table
                                                $custom_types_query1 = "SELECT DISTINCT type_of_work FROM job_order
                                                                      WHERE type_of_work NOT IN ('" . implode("','", $default_work_types) . "')
                                                                      ORDER BY type_of_work";
                                                $custom_types_result1 = $conn->query($custom_types_query1);

                                                $custom_types = [];

                                                if ($custom_types_result1 && $custom_types_result1->num_rows > 0) {
                                                    while ($custom_type = $custom_types_result1->fetch_assoc()) {
                                                        $custom_types[] = $custom_type['type_of_work'];
                                                    }
                                                }

                                                // Check if work_types table exists
                                                $table_exists = false;
                                                $tables_result = $conn->query("SHOW TABLES LIKE 'work_types'");
                                                if ($tables_result && $tables_result->num_rows > 0) {
                                                    $table_exists = true;
                                                }

                                                // Create the work_types table if it doesn't exist
                                                if (!$table_exists) {
                                                    $create_table_sql = "CREATE TABLE IF NOT EXISTS work_types (
                                                        id INT(11) NOT NULL AUTO_INCREMENT,
                                                        type_name VARCHAR(255) NOT NULL,
                                                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                                        PRIMARY KEY (id),
                                                        UNIQUE KEY (type_name)
                                                    )";

                                                    if ($conn->query($create_table_sql)) {
                                                        $table_exists = true;
                                                    } else {
                                                        // If table creation fails, just continue without it
                                                        error_log("Failed to create work_types table: " . $conn->error);
                                                    }
                                                }

                                                // Get custom work types from work_types table if it exists
                                                if ($table_exists) {
                                                    $custom_types_query2 = "SELECT type_name FROM work_types
                                                                          WHERE type_name NOT IN ('" . implode("','", $default_work_types) . "')
                                                                          ORDER BY type_name";

                                                    $custom_types_result2 = $conn->query($custom_types_query2);

                                                    if ($custom_types_result2 && $custom_types_result2->num_rows > 0) {
                                                        while ($custom_type = $custom_types_result2->fetch_assoc()) {
                                                            if (!in_array($custom_type['type_name'], $custom_types)) {
                                                                $custom_types[] = $custom_type['type_name'];
                                                            }
                                                        }
                                                    }
                                                }

                                                // Sort custom types alphabetically
                                                sort($custom_types);

                                                // Output custom types as checkboxes
                                                foreach ($custom_types as $type) {
                                                    echo '<div class="work-type-checkbox-item">';
                                                    echo '<label>';
                                                    echo '<input type="checkbox" name="type_of_work[]" value="' . htmlspecialchars($type) . '"> ';
                                                    echo htmlspecialchars($type);
                                                    echo '</label>';
                                                    echo '</div>';
                                                }

                                            } catch (Exception $e) {
                                                // Silently handle any errors
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <div class="work-type-actions">
                                        <button type="button" class="btn btn-primary" id="add_work_type_btn">
                                            <i class="fas fa-plus"></i> Add New Type
                                        </button>
                                        <button type="button" class="btn btn-secondary" id="manage_work_types_btn">
                                            <i class="fas fa-cog"></i> Manage Types
                                        </button>
                                    </div>
                                </div>
                                <div id="new_work_type_container" style="display: none; margin-top: 10px;">
                                    <div class="input-group">
                                        <input type="text" id="new_work_type" class="form-control" placeholder="Enter new type of work">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-success" id="save_work_type_btn">
                                                <i class="fas fa-check"></i> Add
                                            </button>
                                            <button type="button" class="btn btn-secondary" id="cancel_work_type_btn">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p class="form-help"><i class="fas fa-info-circle"></i> Select one or more types of work or click "Add New Type" to add a custom type.</p>
                            <div id="work_type_error" class="error-message" style="display: none; color: #dc3545; margin-top: 5px;">
                                <i class="fas fa-exclamation-circle"></i> Please select at least one type of work.
                            </div>

                            <div id="auto_selection_details" class="alert alert-info" style="margin-top: 10px; display: none;">
                                <i class="fas fa-info-circle"></i>
                                <span id="auto_selection_message">Work types have been automatically selected based on the technician's pest types.</span>
                            </div>

                            <div id="manual_selection_notice" class="alert alert-warning" style="margin-top: 10px; display: none;">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Technician selected "Other" pest type. Please manually select appropriate work types.</span>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h3><i class="fas fa-calendar-alt"></i> Schedule</h3>
                        <div class="detail-grid">
                            <div class="form-group">
                                <label><i class="fas fa-calendar-day"></i> Preferred Date:</label>
                                <input type="date" name="preferred_date" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-clock"></i> Preferred Time:</label>
                                <input type="time" name="preferred_time" class="form-control" required>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top: 15px;">
                            <label><i class="fas fa-sync-alt"></i> Treatment Frequency:</label>
                            <select name="frequency" id="frequency" class="form-control" required>
                                <option value="one-time">One-time Treatment</option>
                                <option value="weekly">Weekly (Recurring for 1 year)</option>
                                <option value="monthly">Monthly (Recurring for 1 year)</option>
                                <option value="quarterly">Quarterly (Recurring for 1 year)</option>
                            </select>
                            <p class="form-help"><i class="fas fa-info-circle"></i> For recurring treatments, appointments will be automatically scheduled for one year from the initial date.</p>
                            <p class="form-help" style="color: #e74c3c;"><i class="fas fa-exclamation-circle"></i> <strong>Important:</strong> Recurring job orders require client approval before they appear in the calendar. One-time treatments are automatically approved.</p>
                        </div>

                        <div class="form-group" style="margin-top: 15px;">
                            <label><i class="fas fa-money-bill-wave"></i> Service Cost (PHP):</label>
                            <input type="number" name="cost" class="form-control" min="0" step="0.01" required placeholder="Enter service cost">
                            <p class="form-help"><i class="fas fa-info-circle"></i> Enter the total cost for this service. The client will need to confirm payment.</p>
                        </div>
                    </div>

                    <!-- Chemical Recommendations Section -->
                    <div class="detail-section" id="chemicalRecommendationsSection">
                        <h3><i class="fas fa-flask"></i> Chemical Recommendations</h3>
                        <div id="chemicalRecommendationsContent">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <span>Click "Generate Recommendations" to get chemical recommendations based on pest types and area.</span>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-spray-can"></i> Application Method:</label>
                                <select id="applicationMethod" class="form-control">
                                    <option value="spray">Spray Application</option>
                                    <option value="fogging">Fogging Application</option>
                                    <option value="soil drench">Soil Drench (for Termites)</option>
                                    <option value="bait">Bait Application</option>
                                </select>
                            </div>
                            <button type="button" id="generateQuotationRecommendationsBtn" class="btn btn-primary">
                                <i class="fas fa-sync-alt"></i> Generate Recommendations
                            </button>
                            <div id="quotationRecommendationsLoading" style="display: none; margin-top: 15px; text-align: center;">
                                <i class="fas fa-spinner fa-spin" style="font-size: 1.5rem;"></i>
                                <p>Generating recommendations...</p>
                            </div>
                            <div id="quotationRecommendationsResult" style="display: none; margin-top: 15px;">
                                <!-- Recommendations will be displayed here -->
                            </div>
                            <input type="hidden" name="selected_chemicals" id="selectedChemicals" value="">
                        </div>
                    </div>

                    <!-- Technician assignment has been removed from quotation generation.
                    Technicians will be assigned later in the calendar after client approval. -->
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelJobOrderBtn">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" form="jobOrderForm" name="create_job_order" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Send Quotation
                </button>
            </div>
        </div>
    </div>

    <!-- PDF Generation Loading Overlay -->
    <div id="pdfLoadingOverlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(255, 255, 255, 0.8); display: none; flex-direction: column; align-items: center; justify-content: center; z-index: 9999;">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div style="margin-top: 1rem; font-size: 1.2rem; color: #007bff;">Generating PDF, please wait...</div>
    </div>

    <!-- Manage Work Types Modal -->
    <div id="manageWorkTypesModal" class="modal">
        <div class="modal-content" style="max-width: 600px; height: auto; max-height: 80vh;">
            <div class="modal-header">
                <h2 class="modal-title"><i class="fas fa-tasks"></i> Manage Work Types</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Manage Work Types</strong>
                        <p>Here you can view and delete work types. Default work types cannot be deleted.</p>
                    </div>
                </div>

                <div id="workTypesList" class="work-types-list">
                    <!-- Work types will be loaded here -->
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i> Loading work types...
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="closeManageTypesBtn">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title"><i class="fas fa-clipboard-check"></i> Assessment Report Details</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="detail-section">
                    <h3><i class="fas fa-user"></i> Client Information</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-user"></i> Name:</div>
                            <div class="detail-value" id="detailClient"></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-envelope"></i> Email:</div>
                            <div class="detail-value" id="detailEmail"></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-phone"></i> Phone:</div>
                            <div class="detail-value" id="detailPhone"></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-map-marked-alt"></i> Address:</div>
                            <div class="detail-value" id="detailLocation"></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-home"></i> Property Type:</div>
                            <div class="detail-value" id="detailProperty"></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-bug"></i> Pest Problems:</div>
                            <div class="detail-value" id="detailPestProblems"></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-user-edit"></i> Client Notes:</div>
                            <div class="detail-value" id="detailClientNotes"></div>
                        </div>
                    </div>
                </div>

                <div class="detail-section">
                    <h3><i class="fas fa-calendar-check"></i> Assessment Details</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-calendar-day"></i> Date:</div>
                            <div class="detail-value" id="detailDate"></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-clock"></i> Time:</div>
                            <div class="detail-value" id="detailTime"></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-user-md"></i> Technician:</div>
                            <div class="detail-value" id="detailTechnician"></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-ruler-combined"></i> Area:</div>
                            <div class="detail-value" id="detailArea"></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-bug"></i> Pest Types:</div>
                            <div class="detail-value" id="detailPestTypes"></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-map-marker-alt"></i> Problem Area:</div>
                            <div class="detail-value" id="detailProblemArea"></div>
                        </div>
                        <div class="detail-item" style="margin-top: 15px;">
                            <div class="detail-label"><i class="fas fa-clipboard-check"></i> Assessment Notes:</div>
                            <div class="detail-value" id="detailNotes"></div>
                        </div>
                        <div class="detail-item" style="margin-top: 15px;">
                            <div class="detail-label"><i class="fas fa-lightbulb"></i> Recommendation:</div>
                            <div class="detail-value" id="detailRecommendation"></div>
                        </div>
                    </div>
                </div>

                <div class="detail-section" id="attachmentsSection" style="display: none;">
                    <h3><i class="fas fa-camera"></i> Picture of the infestation</h3>
                    <div class="attachments-grid" id="detailAttachments">
                        <!-- Attachments will be added here dynamically -->
                    </div>
                </div>

                <div class="detail-section" id="verificationSection" style="display: none;">
                    <h3><i class="fas fa-clipboard-check"></i> Client Verification on Technician Job</h3>
                    <div style="padding: 20px;">
                        <div class="alert" id="verificationAlert" style="border-left: 4px solid; margin-bottom: 20px; border-radius: 8px; padding: 15px;">
                            <i id="verificationIcon" class="fas fa-check-circle" style="font-size: 1.2rem; margin-right: 10px;"></i>
                            <div>
                                <strong id="verificationStatus" style="font-size: 1.1rem;">Verification Status</strong>
                                <p id="verificationMessage" style="margin-bottom: 0; margin-top: 5px;">Verification details will appear here.</p>
                            </div>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 15px; margin-bottom: 20px; background-color: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #eaeaea;">
                            <div style="display: flex; align-items: center;">
                                <i class="fas fa-user-check" style="color: #28a745; margin-right: 10px; width: 20px;"></i>
                                <span style="font-weight: 500;">The Technician Arrived:</span>
                                <span class="ml-2" id="detailTechnicianArrived" style="margin-left: 10px;"></span>
                            </div>
                            <div style="display: flex; align-items: center;">
                                <i class="fas fa-tasks" style="color: #28a745; margin-right: 10px; width: 20px;"></i>
                                <span style="font-weight: 500;">The Job Completed:</span>
                                <span class="ml-2" id="detailJobCompleted" style="margin-left: 10px;"></span>
                            </div>
                        </div>

                        <div id="verificationNotesContainer" style="margin-top: 15px; display: none; background-color: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #eaeaea;">
                            <div style="display: flex; align-items: center; margin-bottom: 10px;">
                                <i class="fas fa-clipboard" style="color: #17a2b8; margin-right: 10px;"></i>
                                <strong>Verification Notes:</strong>
                            </div>
                            <div id="detailVerificationNotes" style="padding-left: 30px;"></div>
                        </div>
                    </div>
                </div>

                <div class="detail-section" id="feedbackSection" style="display: none;">
                    <h3><i class="fas fa-comments"></i> Client Feedback on the Inspection of the Technician</h3>
                    <div class="rating-stars" id="detailRating">
                        <!-- Stars will be added here dynamically -->
                    </div>
                    <div class="feedback-comments">
                        <p id="detailFeedbackComments"></p>
                        <small id="detailFeedbackDate"></small>
                    </div>
                </div>

                <div class="detail-section" id="jobOrderSection" style="display: none;">
                    <h3><i class="fas fa-clipboard-list"></i> Job Order Information</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-briefcase"></i> Type of Work:</div>
                            <div class="detail-value" id="detailJobType"></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-calendar-day"></i> Date:</div>
                            <div class="detail-value" id="detailJobDate"></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-clock"></i> Time:</div>
                            <div class="detail-value" id="detailJobTime"></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-users"></i> Technicians:</div>
                            <div class="detail-value" id="detailJobTechs"></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-sync-alt"></i> Frequency:</div>
                            <div class="detail-value" id="detailJobFrequency"></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label"><i class="fas fa-money-bill-wave"></i> Service Cost:</div>
                            <div class="detail-value" id="detailJobCost"></div>
                        </div>
                        <div class="detail-item" id="clientApprovalContainer">
                            <div class="detail-label"><i class="fas fa-check-circle"></i> Client Response:</div>
                            <div class="detail-value" style="padding: 0; background: none; border: none;">
                                <span class="client-approval-badge" id="detailClientApproval" style="display: inline-block; padding: 8px 12px; margin-bottom: 8px;"></span>
                                <div class="approval-date" id="detailApprovalDate" style="font-size: 0.85rem; color: #666;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Chemical Recommendations Section -->
                    <div id="chemicalRecommendationsDetailSection" style="margin-top: 20px; display: none;">
                        <h4 style="margin-bottom: 15px; font-size: 1.1rem; color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                            <i class="fas fa-flask"></i> Chemical Recommendations
                        </h4>
                        <div id="chemicalRecommendationsDetailContent">
                            <!-- Chemical recommendations will be displayed here -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding: 15px 25px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-primary" id="saveAsPdfBtn" style="background-color: #007bff; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">
                    <i class="fas fa-file-pdf"></i> Save as PDF
                </button>
                <button type="button" class="btn btn-info" id="printReportBtn" style="background-color: #17a2b8; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">
                    <i class="fas fa-print"></i> Print Report
                </button>
                <button type="button" class="btn btn-secondary" id="closeDetailsBtn" style="background-color: #f8f9fa; color: #333; border: 1px solid #ddd; padding: 8px 15px; border-radius: 4px; cursor: pointer;">
                    <i class="fas fa-times"></i> Close
                </button>
                <button type="button" class="btn btn-success" id="createJobFromDetailsBtn" style="display: none; background-color: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">
                    <i class="fas fa-plus-circle"></i> Create Job Order
                </button>
            </div>
        </div>
    </div>

    <script src="js/quotation-chemical-recommendations.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // We'll handle filter submission with the Apply Filters button instead of auto-submit
            // This allows users to select multiple filters before submitting

            // Job Order Modal Elements
            const jobOrderModal = document.getElementById('jobOrderModal');
            const createJobBtns = document.querySelectorAll('.create-job-btn');
            const closeJobOrderBtn = document.querySelector('#jobOrderModal .close');
            const cancelJobOrderBtn = document.getElementById('cancelJobOrderBtn');
            const existingJobMessage = document.getElementById('existingJobMessage');
            const jobOrderForm = document.getElementById('jobOrderForm');

            // Details Modal Elements
            const detailsModal = document.getElementById('detailsModal');
            const viewDetailsBtns = document.querySelectorAll('.view-details-btn');
            const closeDetailsBtn = document.querySelector('#detailsModal .close');
            const closeDetailsBtnFooter = document.getElementById('closeDetailsBtn');
            const createJobFromDetailsBtn = document.getElementById('createJobFromDetailsBtn');

            // Open Job Order modal when Create Job Order button is clicked
            createJobBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const reportId = this.getAttribute('data-report-id');
                    const jobOrderExists = this.getAttribute('data-job-order-exists') === '1';

                    document.getElementById('modalReportId').value = reportId;

                    if (jobOrderExists) {
                        existingJobMessage.style.display = 'block';
                        jobOrderForm.style.display = 'none';
                    } else {
                        existingJobMessage.style.display = 'none';
                        jobOrderForm.style.display = 'block';

                        // Reset work type fields
                        const workTypeCheckboxes = document.querySelectorAll('input[name="type_of_work[]"]');
                        const newWorkTypeContainer = document.getElementById('new_work_type_container');
                        const newWorkTypeInput = document.getElementById('new_work_type');
                        const workTypeError = document.getElementById('work_type_error');

                        // Uncheck all checkboxes
                        workTypeCheckboxes.forEach(checkbox => {
                            checkbox.checked = false;
                        });

                        newWorkTypeInput.value = '';
                        newWorkTypeContainer.style.display = 'none';
                        workTypeError.style.display = 'none';
                    }

                    // Set default date to tomorrow
                    const tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    const dateInput = jobOrderForm.querySelector('input[name="preferred_date"]');
                    if (dateInput) {
                        dateInput.min = tomorrow.toISOString().split('T')[0]; // Set min date to tomorrow
                        dateInput.value = tomorrow.toISOString().split('T')[0];
                    }

                    jobOrderModal.style.display = 'block';
                });
            });

            // Close Job Order modal
            function closeJobOrderModal() {
                jobOrderModal.style.display = 'none';
            }

            if (closeJobOrderBtn) closeJobOrderBtn.addEventListener('click', closeJobOrderModal);
            if (cancelJobOrderBtn) cancelJobOrderBtn.addEventListener('click', closeJobOrderModal);

            // Open Details modal when View button is clicked
            viewDetailsBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    // Get data from button attributes
                    const reportId = this.getAttribute('data-report-id');
                    const client = this.getAttribute('data-client');
                    const email = this.getAttribute('data-email');
                    const phone = this.getAttribute('data-phone');
                    const location = this.getAttribute('data-location');
                    const property = this.getAttribute('data-property');
                    const area = this.getAttribute('data-area');
                    const date = this.getAttribute('data-date');
                    const time = this.getAttribute('data-time');
                    const technician = this.getAttribute('data-technician');
                    const notes = this.getAttribute('data-notes');
                    const recommendation = this.getAttribute('data-recommendation');
                    const clientNotes = this.getAttribute('data-client-notes');
                    const attachments = this.getAttribute('data-attachments');
                    const pestTypes = this.getAttribute('data-pest-types');
                    const pestProblems = this.getAttribute('data-pest-problems');
                    const problemArea = this.getAttribute('data-problem-area');
                    const feedbackRating = this.getAttribute('data-feedback-rating');
                    const feedbackComments = this.getAttribute('data-feedback-comments');
                    const feedbackDate = this.getAttribute('data-feedback-date');
                    const technicianArrived = this.getAttribute('data-technician-arrived');
                    const jobCompleted = this.getAttribute('data-job-completed');
                    const verificationNotes = this.getAttribute('data-verification-notes');
                    const jobOrderId = this.getAttribute('data-job-order-id');
                    const jobOrderType = this.getAttribute('data-job-order-type');
                    const jobOrderDate = this.getAttribute('data-job-order-date');
                    const jobOrderTime = this.getAttribute('data-job-order-time');
                    const jobOrderTechs = this.getAttribute('data-job-order-techs');

                    // Fill in the details modal
                    document.getElementById('detailClient').textContent = client;
                    document.getElementById('detailEmail').textContent = email;
                    document.getElementById('detailPhone').textContent = phone;
                    document.getElementById('detailLocation').textContent = location;
                    document.getElementById('detailProperty').textContent = property;
                    document.getElementById('detailArea').textContent = area + ' sqm';
                    document.getElementById('detailDate').textContent = date;
                    document.getElementById('detailTime').textContent = time;
                    document.getElementById('detailTechnician').textContent = technician;
                    document.getElementById('detailPestTypes').textContent = pestTypes || 'Not specified';
                    document.getElementById('detailPestProblems').textContent = pestProblems || 'Not specified';
                    document.getElementById('detailProblemArea').textContent = problemArea || 'Not specified';
                    document.getElementById('detailNotes').textContent = notes || 'No assessment notes provided.';
                    document.getElementById('detailRecommendation').textContent = recommendation || 'No recommendations provided.';
                    document.getElementById('detailClientNotes').textContent = clientNotes || 'No client notes provided.';

                    // Handle attachments
                    const attachmentsSection = document.getElementById('attachmentsSection');
                    const attachmentsContainer = document.getElementById('detailAttachments');

                    if (attachments && attachments.trim() !== '') {
                        attachmentsSection.style.display = 'block';
                        attachmentsContainer.innerHTML = '';

                        const attachmentsList = attachments.split(',');
                        attachmentsList.forEach(attachment => {
                            if (attachment.trim() !== '') {
                                const attachmentItem = document.createElement('div');
                                attachmentItem.className = 'attachment-item';

                                const attachmentLink = document.createElement('a');
                                attachmentLink.href = '../uploads/' + attachment.trim();
                                attachmentLink.target = '_blank';

                                const attachmentImg = document.createElement('img');
                                attachmentImg.src = '../uploads/' + attachment.trim();
                                attachmentImg.className = 'attachment-img';
                                attachmentImg.alt = 'Attachment';

                                attachmentLink.appendChild(attachmentImg);
                                attachmentItem.appendChild(attachmentLink);
                                attachmentsContainer.appendChild(attachmentItem);
                            }
                        });
                    } else {
                        attachmentsSection.style.display = 'none';
                    }

                    // Handle job order information
                    const jobOrderSection = document.getElementById('jobOrderSection');
                    const jobOrderFrequency = this.getAttribute('data-job-order-frequency');
                    const clientApprovalStatus = this.getAttribute('data-job-order-approval-status');
                    const clientApprovalDate = this.getAttribute('data-job-order-approval-date');
                    const chemicalRecommendations = this.getAttribute('data-chemical-recommendations');
                    const jobOrderCost = this.getAttribute('data-job-order-cost');

                    if (jobOrderId && jobOrderId.trim() !== '') {
                        jobOrderSection.style.display = 'block';
                        // Display job types as a list if there are multiple types
                        const jobTypes = jobOrderType.split(',').map(type => type.trim());
                        const jobTypeElement = document.getElementById('detailJobType');

                        if (jobTypes.length > 1) {
                            jobTypeElement.innerHTML = '';
                            const typesList = document.createElement('ul');
                            typesList.style.margin = '0';
                            typesList.style.paddingLeft = '20px';

                            jobTypes.forEach(type => {
                                if (type) {
                                    const typeItem = document.createElement('li');
                                    typeItem.textContent = type;
                                    typesList.appendChild(typeItem);
                                }
                            });

                            jobTypeElement.appendChild(typesList);
                        } else {
                            jobTypeElement.textContent = jobOrderType;
                        }
                        document.getElementById('detailJobDate').textContent = jobOrderDate;
                        document.getElementById('detailJobTime').textContent = jobOrderTime;
                        document.getElementById('detailJobTechs').textContent = jobOrderTechs || 'None assigned';
                        document.getElementById('detailJobFrequency').textContent = jobOrderFrequency ? jobOrderFrequency.charAt(0).toUpperCase() + jobOrderFrequency.slice(1) : 'Not specified';

                        // Format and display the cost
                        if (jobOrderCost) {
                            // Store the raw cost value in a data attribute for easy access
                            const costElement = document.getElementById('detailJobCost');
                            costElement.setAttribute('data-raw-cost', jobOrderCost);

                            // Parse the cost value, ensuring commas are removed for proper parsing
                            const cost = parseFloat(jobOrderCost.replace(/,/g, ''));
                            console.log('Job order cost (raw):', jobOrderCost);
                            console.log('Job order cost (parsed):', cost);

                            // Format with Philippine Peso sign (₱) and proper thousands separators
                            costElement.textContent = `₱ ${cost.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                        } else {
                            document.getElementById('detailJobCost').textContent = 'Not specified';
                        }

                        // Handle client approval status
                        const clientApprovalElement = document.getElementById('detailClientApproval');
                        const approvalDateElement = document.getElementById('detailApprovalDate');
                        const clientApprovalContainer = document.getElementById('clientApprovalContainer');

                        if (clientApprovalStatus) {
                            clientApprovalContainer.style.display = 'block';
                            clientApprovalElement.className = 'client-approval-badge';

                            let statusText = '';

                            switch(clientApprovalStatus) {
                                case 'approved':
                                    clientApprovalElement.classList.add('approval-approved');
                                    statusText = 'Approved';
                                    break;
                                case 'declined':
                                    clientApprovalElement.classList.add('approval-declined');
                                    statusText = 'Declined';
                                    break;
                                case 'one-time':
                                    clientApprovalElement.classList.add('approval-one-time');
                                    statusText = 'One-time Treatment Only';
                                    break;
                                case 'pending':
                                    clientApprovalElement.classList.add('approval-pending');
                                    statusText = 'Pending Client Approval';
                                    break;
                                default:
                                    clientApprovalElement.classList.add('approval-pending');
                                    statusText = 'Status Unknown';
                            }

                            clientApprovalElement.textContent = statusText;

                            if (clientApprovalDate && clientApprovalStatus !== 'pending') {
                                approvalDateElement.textContent = `Response received on ${clientApprovalDate}`;
                            } else if (clientApprovalStatus === 'pending') {
                                approvalDateElement.textContent = 'Awaiting client response';
                            } else {
                                approvalDateElement.textContent = '';
                            }
                        } else {
                            clientApprovalContainer.style.display = 'none';
                        }

                        // Handle chemical recommendations
                        const chemicalRecommendationsSection = document.getElementById('chemicalRecommendationsDetailSection');
                        const chemicalRecommendationsContent = document.getElementById('chemicalRecommendationsDetailContent');

                        if (chemicalRecommendations && chemicalRecommendations.trim() !== '') {
                            try {
                                const chemicals = JSON.parse(chemicalRecommendations);

                                if (Array.isArray(chemicals) && chemicals.length > 0) {
                                    chemicalRecommendationsSection.style.display = 'block';

                                    // Create table for chemical recommendations
                                    const table = document.createElement('table');
                                    table.className = 'table table-bordered table-hover';
                                    table.style.width = '100%';
                                    table.style.borderCollapse = 'collapse';
                                    table.style.marginBottom = '15px';

                                    // Create table header
                                    const thead = document.createElement('thead');
                                    thead.style.backgroundColor = '#f8f9fa';
                                    thead.style.fontWeight = 'bold';

                                    const headerRow = document.createElement('tr');

                                    const headers = ['Chemical Name', 'Type', 'Target Pest', 'Recommended Dosage'];
                                    headers.forEach(headerText => {
                                        const th = document.createElement('th');
                                        th.textContent = headerText;
                                        th.style.padding = '8px 12px';
                                        th.style.borderBottom = '2px solid #dee2e6';
                                        headerRow.appendChild(th);
                                    });

                                    thead.appendChild(headerRow);
                                    table.appendChild(thead);

                                    // Create table body
                                    const tbody = document.createElement('tbody');

                                    chemicals.forEach(chem => {
                                        const row = document.createElement('tr');

                                        // Chemical Name
                                        const nameCell = document.createElement('td');
                                        nameCell.style.padding = '8px 12px';
                                        nameCell.style.borderBottom = '1px solid #dee2e6';
                                        nameCell.innerHTML = `<strong>${chem.name || 'N/A'}</strong>`;
                                        row.appendChild(nameCell);

                                        // Type
                                        const typeCell = document.createElement('td');
                                        typeCell.style.padding = '8px 12px';
                                        typeCell.style.borderBottom = '1px solid #dee2e6';
                                        typeCell.textContent = chem.type || 'N/A';
                                        row.appendChild(typeCell);

                                        // Target Pest
                                        const pestCell = document.createElement('td');
                                        pestCell.style.padding = '8px 12px';
                                        pestCell.style.borderBottom = '1px solid #dee2e6';
                                        pestCell.textContent = chem.target_pest || 'N/A';
                                        row.appendChild(pestCell);

                                        // Recommended Dosage
                                        const dosageCell = document.createElement('td');
                                        dosageCell.style.padding = '8px 12px';
                                        dosageCell.style.borderBottom = '1px solid #dee2e6';
                                        dosageCell.textContent = `${chem.dosage || '0'} ${chem.dosage_unit || 'ml'}`;
                                        row.appendChild(dosageCell);

                                        tbody.appendChild(row);
                                    });

                                    table.appendChild(tbody);

                                    // Clear previous content and add the table
                                    chemicalRecommendationsContent.innerHTML = '';
                                    chemicalRecommendationsContent.appendChild(table);

                                    // Add note about chemical recommendations
                                    const note = document.createElement('div');
                                    note.className = 'alert alert-info';
                                    note.style.marginTop = '10px';
                                    note.style.fontSize = '0.9rem';
                                    note.innerHTML = '<i class="fas fa-info-circle"></i> These chemicals have been recommended based on the assessment report and target pests.';

                                    chemicalRecommendationsContent.appendChild(note);
                                } else {
                                    chemicalRecommendationsSection.style.display = 'none';
                                }
                            } catch (e) {
                                console.error('Error parsing chemical recommendations:', e);
                                chemicalRecommendationsSection.style.display = 'none';
                            }
                        } else {
                            chemicalRecommendationsSection.style.display = 'none';
                        }

                        createJobFromDetailsBtn.style.display = 'none';
                    } else {
                        jobOrderSection.style.display = 'none';
                        createJobFromDetailsBtn.style.display = 'inline-flex';
                        createJobFromDetailsBtn.setAttribute('data-report-id', reportId);
                    }

                    // Handle verification
                    const verificationSection = document.getElementById('verificationSection');
                    const verificationAlert = document.getElementById('verificationAlert');
                    const verificationStatus = document.getElementById('verificationStatus');
                    const verificationMessage = document.getElementById('verificationMessage');
                    const verificationNotesContainer = document.getElementById('verificationNotesContainer');

                    if (feedbackRating && feedbackRating.trim() !== '') {
                        verificationSection.style.display = 'block';

                        // Set verification status
                        const arrived = technicianArrived == 1;
                        const completed = jobCompleted == 1;

                        const verificationIcon = document.getElementById('verificationIcon');

                        if (arrived && completed) {
                            verificationAlert.className = 'alert alert-success';
                            verificationAlert.style.borderLeftColor = '#28a745';
                            verificationAlert.style.backgroundColor = '#e8f5e9';
                            verificationStatus.textContent = 'Verification Successful';
                            verificationStatus.style.color = '#28a745';
                            verificationMessage.textContent = 'The client has verified that the technician arrived and completed the job.';
                            verificationIcon.className = 'fas fa-check-circle';
                            verificationIcon.style.color = '#28a745';
                        } else {
                            verificationAlert.className = 'alert alert-danger';
                            verificationAlert.style.borderLeftColor = '#dc3545';
                            verificationAlert.style.backgroundColor = '#f8d7da';
                            verificationStatus.textContent = 'Verification Failed';
                            verificationStatus.style.color = '#dc3545';
                            verificationMessage.textContent = 'The client has reported issues with the technician\'s work.';
                            verificationIcon.className = 'fas fa-exclamation-circle';
                            verificationIcon.style.color = '#dc3545';
                        }

                        // Set verification details
                        document.getElementById('detailTechnicianArrived').innerHTML = arrived ?
                            '<span class="badge bg-success" style="padding: 5px 10px; border-radius: 20px;">Yes</span>' :
                            '<span class="badge bg-danger" style="padding: 5px 10px; border-radius: 20px;">No</span>';

                        document.getElementById('detailJobCompleted').innerHTML = completed ?
                            '<span class="badge bg-success" style="padding: 5px 10px; border-radius: 20px;">Yes</span>' :
                            '<span class="badge bg-danger" style="padding: 5px 10px; border-radius: 20px;">No</span>';

                        // Handle verification notes
                        if (verificationNotes && verificationNotes.trim() !== '') {
                            verificationNotesContainer.style.display = 'block';
                            document.getElementById('detailVerificationNotes').textContent = verificationNotes;
                        } else {
                            verificationNotesContainer.style.display = 'none';
                        }
                    } else {
                        verificationSection.style.display = 'none';
                    }

                    // Handle feedback
                    const feedbackSection = document.getElementById('feedbackSection');
                    const ratingContainer = document.getElementById('detailRating');

                    if (feedbackRating && feedbackRating.trim() !== '') {
                        feedbackSection.style.display = 'block';

                        // Create star rating
                        ratingContainer.innerHTML = '';
                        const rating = parseInt(feedbackRating);

                        for (let i = 1; i <= 5; i++) {
                            const star = document.createElement('i');
                            star.className = 'fas fa-star ' + (i <= rating ? 'text-warning' : 'text-secondary');
                            ratingContainer.appendChild(star);
                        }

                        document.getElementById('detailFeedbackComments').textContent = feedbackComments || 'No additional comments provided.';
                        document.getElementById('detailFeedbackDate').textContent = 'Submitted on ' + feedbackDate;
                    } else {
                        feedbackSection.style.display = 'none';
                    }

                    // Show the modal
                    detailsModal.style.display = 'block';
                });
            });

            // Close Details modal
            function closeDetailsModal() {
                detailsModal.style.display = 'none';
            }

            if (closeDetailsBtn) closeDetailsBtn.addEventListener('click', closeDetailsModal);
            if (closeDetailsBtnFooter) closeDetailsBtnFooter.addEventListener('click', closeDetailsModal);

            // Create Job Order from Details modal
            if (createJobFromDetailsBtn) {
                createJobFromDetailsBtn.addEventListener('click', function() {
                    const reportId = this.getAttribute('data-report-id');
                    document.getElementById('modalReportId').value = reportId;

                    // Get pest types from the details modal
                    let pestTypes = document.getElementById('detailPestTypes').textContent;
                    const pestProblems = document.getElementById('detailPestProblems').textContent;

                    // Debug: Log both pest types and pest problems
                    console.log('Create Job From Details - Pest Types:', pestTypes);
                    console.log('Create Job From Details - Pest Problems:', pestProblems);

                    // If pest types is empty or "Not specified", try using pest problems instead
                    if (!pestTypes || pestTypes.trim() === '' || pestTypes.trim() === 'Not specified') {
                        if (pestProblems && pestProblems.trim() !== '' && pestProblems.trim() !== 'Not specified' && pestProblems.trim() !== 'None specified') {
                            console.log('Using pest problems instead of pest types');
                            pestTypes = pestProblems;
                        }
                    }

                    // Close details modal and open job order modal
                    detailsModal.style.display = 'none';
                    existingJobMessage.style.display = 'none';
                    jobOrderForm.style.display = 'block';
                    jobOrderModal.style.display = 'block';

                    // Reset all checkboxes first
                    const workTypeCheckboxes = document.querySelectorAll('input[name="type_of_work[]"]');
                    workTypeCheckboxes.forEach(checkbox => {
                        checkbox.checked = false;
                    });

                    // Use the server-side PHP script to determine work types
                    if (pestTypes && pestTypes.trim() !== '' && pestTypes.trim() !== 'Not specified') {
                        console.log('Sending request to server to determine work types...');

                        // Log all available checkboxes for debugging
                        console.log('Available work type checkboxes:');
                        workTypeCheckboxes.forEach(checkbox => {
                            console.log(`- ${checkbox.value} (id: ${checkbox.id}, checked: ${checkbox.checked})`);
                        });

                        // Create form data
                        const formData = new FormData();
                        formData.append('pest_types', pestTypes);

                        // Send AJAX request to get work types
                        fetch('get_work_types_from_pests.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok: ' + response.statusText);
                            }
                            return response.json();
                        })
                        .then(data => {
                            // Log the response
                            console.log('Server response:', data);

                            if (data.success) {
                                // Handle manual selection case
                                if (data.manual_selection) {
                                    console.log('Manual selection required due to "Other" pest type');
                                    const manualSelectionNotice = document.getElementById('manual_selection_notice');
                                    if (manualSelectionNotice) {
                                        manualSelectionNotice.style.display = 'block';
                                    }
                                    return;
                                }

                                // Select work types
                                if (data.work_types && data.work_types.length > 0) {
                                    console.log('Automatically selecting work types:', data.work_types.join(', '));

                                    // Select each work type
                                    let foundMatches = false;
                                    workTypeCheckboxes.forEach(checkbox => {
                                        console.log(`Checking if ${checkbox.value} is in [${data.work_types.join(', ')}]`);
                                        if (data.work_types.includes(checkbox.value)) {
                                            checkbox.checked = true;
                                            foundMatches = true;
                                            console.log('Selected:', checkbox.value);
                                        }
                                    });

                                    if (!foundMatches) {
                                        console.warn('Could not find matching checkboxes for work types:', data.work_types);

                                        // Try case-insensitive matching
                                        workTypeCheckboxes.forEach(checkbox => {
                                            const checkboxValue = checkbox.value.toLowerCase();
                                            data.work_types.forEach(workType => {
                                                if (checkboxValue === workType.toLowerCase()) {
                                                    checkbox.checked = true;
                                                    console.log('Selected (case-insensitive):', checkbox.value);
                                                }
                                            });
                                        });
                                    }

                                    // Show auto-selection message
                                    const autoSelectionDetails = document.getElementById('auto_selection_details');
                                    if (autoSelectionDetails) {
                                        autoSelectionDetails.style.display = 'block';
                                        const autoSelectionMessage = document.getElementById('auto_selection_message');
                                        if (autoSelectionMessage) {
                                            autoSelectionMessage.textContent = 'Work types have been automatically selected based on the pest types: ' + data.work_types.join(', ');
                                        }
                                    }
                                } else {
                                    console.log('No work types determined. Please select manually.');
                                }
                            } else {
                                console.error('Error:', data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                        });
                    } else {
                        console.log('No pest types available. Please select work types manually.');
                    }
                });
            }

            // Close modals when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === jobOrderModal) {
                    jobOrderModal.style.display = 'none';
                }
                if (event.target === detailsModal) {
                    detailsModal.style.display = 'none';
                }
            });

            // Print Report Button
            const printReportBtn = document.getElementById('printReportBtn');
            if (printReportBtn) {
                printReportBtn.addEventListener('click', function() {
                    printAssessmentReport();
                });
            }

            // Save as PDF Button
            const saveAsPdfBtn = document.getElementById('saveAsPdfBtn');
            if (saveAsPdfBtn) {
                saveAsPdfBtn.addEventListener('click', function() {
                    saveAssessmentReportAsPDF();
                });
            }

            // Function to print the assessment report
            function printAssessmentReport() {
                // Create a title for the printed page
                const originalTitle = document.title;
                const clientName = document.getElementById('detailClient').textContent;
                document.title = `Assessment Report - ${clientName}`;

                // Make sure all sections are visible for printing
                const allSections = document.querySelectorAll('#detailsModal .detail-section');
                const originalDisplayStates = [];

                // Store original display states and make all sections visible
                allSections.forEach(section => {
                    originalDisplayStates.push({
                        element: section,
                        display: section.style.display
                    });
                    section.style.display = 'block';
                });

                // Hide all buttons in the modal content
                const allButtons = document.querySelectorAll('#detailsModal button, #detailsModal .btn');
                const originalButtonStates = [];

                allButtons.forEach(button => {
                    originalButtonStates.push({
                        element: button,
                        display: button.style.display
                    });
                    button.style.display = 'none';
                });

                // Print the report
                setTimeout(() => {
                    window.print();

                    // Restore original display states after printing
                    originalDisplayStates.forEach(item => {
                        item.element.style.display = item.display;
                    });

                    originalButtonStates.forEach(item => {
                        item.element.style.display = item.display;
                    });

                    // Restore the original title
                    document.title = originalTitle;
                }, 100);
            }

            // Function to save the assessment report as PDF
            function saveAssessmentReportAsPDF() {
                // Get report content and data
                const clientName = document.getElementById('detailClient').textContent;
                const clientEmail = document.getElementById('detailEmail').textContent;
                const clientPhone = document.getElementById('detailPhone').textContent;
                const clientAddress = document.getElementById('detailLocation').textContent;
                const propertyType = document.getElementById('detailProperty').textContent;
                const reportDate = document.getElementById('detailDate').textContent;
                const area = document.getElementById('detailArea').textContent;
                const pestTypes = document.getElementById('detailPestTypes').textContent;
                const problemArea = document.getElementById('detailProblemArea').textContent;
                const notes = document.getElementById('detailNotes').textContent;
                const recommendation = document.getElementById('detailRecommendation').textContent;

                // Get chemical recommendations if available
                let chemicalRecommendations = [];
                const chemicalSection = document.getElementById('chemicalRecommendationsDetailContent');
                if (chemicalSection && chemicalSection.innerHTML.trim() !== '') {
                    // Try to extract chemical recommendations from the content
                    const chemicalItems = chemicalSection.querySelectorAll('tr');
                    chemicalItems.forEach(item => {
                        const cells = item.querySelectorAll('td');
                        if (cells.length >= 2) {
                            const chemicalName = cells[0].textContent.trim();
                            const dosage = cells[1].textContent.trim();
                            if (chemicalName && dosage) {
                                chemicalRecommendations.push({ name: chemicalName, dosage: dosage });
                            }
                        }
                    });
                }

                // Show loading overlay
                const loadingOverlay = document.getElementById('pdfLoadingOverlay');
                loadingOverlay.style.display = 'flex';

                // Show loading indicator on button
                const saveBtn = document.getElementById('saveAsPdfBtn');
                const originalBtnText = saveBtn.innerHTML;
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating PDF...';

                // Set PDF filename - replace spaces and special characters
                const safeClientName = clientName.replace(/[^a-z0-9]/gi, '_').toLowerCase();
                const filename = `Quotation_${safeClientName}.pdf`;

                // Initialize jsPDF
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('p', 'mm', 'a4');

                // A4 dimensions
                const pageWidth = 210;
                const pageHeight = 297;
                const margin = 15;

                // Generate a quotation number
                const today = new Date();
                const quotationNumber = `Q-${today.getFullYear()}${(today.getMonth() + 1).toString().padStart(2, '0')}${today.getDate().toString().padStart(2, '0')}-${Math.floor(Math.random() * 1000).toString().padStart(3, '0')}`;

                // Define the primary color theme (#2563EB - blue)
                const primaryColor = [37, 99, 235]; // RGB for #2563EB

                // Create a function to generate the PDF content
                const generatePDFContent = function() {
                    // Add company logo and header - reduced height from 40 to 30
                    pdf.setFillColor(240, 247, 255); // Light blue background
                    pdf.rect(0, 0, pageWidth, 30, 'F');

                    // Add Quotation text - reduced font size and adjusted position
                    pdf.setFont('helvetica', 'bold');
                    pdf.setFontSize(18); // Reduced from 22
                    pdf.setTextColor(primaryColor[0], primaryColor[1], primaryColor[2]); // Primary blue color
                    pdf.text('Quotation', margin, 20); // Adjusted Y position from 25 to 20

                    // Add company name and tagline - reduced font size and adjusted position
                    pdf.setFontSize(14); // Reduced from 16
                    pdf.setTextColor(50, 50, 50); // Dark gray for better contrast
                    pdf.text('MacJ Pest Control', pageWidth - margin, 15, { align: 'right' }); // Adjusted Y position
                    pdf.setFontSize(9); // Reduced from 10
                    pdf.setFont('helvetica', 'normal');
                    pdf.text('Professional Pest Control Services', pageWidth - margin, 20, { align: 'right' }); // Adjusted Y position

                    // Add quotation details
                    pdf.setDrawColor(primaryColor[0], primaryColor[1], primaryColor[2]); // Blue border

                    // Left side box - From details - reduced height from 50 to 35
                    pdf.setFillColor(245, 250, 255); // Very light blue background
                    pdf.rect(margin, 40, 85, 35, 'FD'); // Adjusted Y position from 50 to 40 and height from 50 to 35
                    pdf.setFont('helvetica', 'bold');
                    pdf.setFontSize(9); // Reduced from 10
                    pdf.setTextColor(primaryColor[0], primaryColor[1], primaryColor[2]); // Blue color for headers
                    pdf.text('Quotation by', margin + 5, 46); // Adjusted Y position

                    pdf.setTextColor(50, 50, 50); // Dark gray for better contrast
                    pdf.text('MacJ Pest Control', margin + 5, 52); // Adjusted Y position

                    pdf.setFont('helvetica', 'normal');
                    pdf.setFontSize(8); // Reduced from 9
                    pdf.setTextColor(60, 60, 60); // Slightly darker for better visibility
                    pdf.text('#30 Sto. Tomas St. Brgy. Don Manuel', margin + 5, 58); // Adjusted Y position
                    pdf.text('Quezon City', margin + 5, 63); // Adjusted Y position
                    pdf.text('Phone: (02) 7 369 3904 / 09171457316', margin + 5, 68); // Adjusted Y position
                    pdf.text('Email: macpest@yahoo.com', margin + 5, 73); // Adjusted Y position

                    // Right side box - Client details - using same light background as left box
                    pdf.setFillColor(245, 250, 255); // Very light blue background (same as left box)
                    pdf.rect(pageWidth - margin - 85, 40, 85, 35, 'FD'); // Adjusted Y position from 50 to 40 and height from 50 to 35
                    pdf.setFont('helvetica', 'bold');
                    pdf.setFontSize(9); // Reduced from 10
                    pdf.setTextColor(primaryColor[0], primaryColor[1], primaryColor[2]); // Blue color for headers
                    pdf.text('Quotation to', pageWidth - margin - 80, 46); // Adjusted Y position

                    pdf.setTextColor(50, 50, 50); // Dark gray for better contrast
                    pdf.text(clientName, pageWidth - margin - 80, 52); // Adjusted Y position

                    pdf.setFont('helvetica', 'normal');
                    pdf.setFontSize(8); // Reduced from 9
                    pdf.setTextColor(60, 60, 60); // Slightly darker for better visibility
                    pdf.text(clientAddress, pageWidth - margin - 80, 58, { maxWidth: 75 }); // Adjusted Y position
                    pdf.text(`Phone: ${clientPhone}`, pageWidth - margin - 80, 68); // Adjusted Y position
                    pdf.text(`Email: ${clientEmail}`, pageWidth - margin - 80, 73); // Adjusted Y position

                    // Quotation details - adjusted position from 110 to 85
                    pdf.setFillColor(230, 240, 255); // Light blue background
                    pdf.rect(margin, 85, pageWidth - (margin * 2), 10, 'FD'); // Adjusted Y position and reduced height

                    pdf.setFont('helvetica', 'bold');
                    pdf.setFontSize(8); // Reduced from 9
                    pdf.setTextColor(primaryColor[0], primaryColor[1], primaryColor[2]); // Blue color for headers
                    pdf.text('Quotation #', margin + 5, 92); // Adjusted Y position
                    pdf.text('Date', margin + 50, 92); // Adjusted Y position
                    pdf.text('Property Type', margin + 90, 92); // Adjusted Y position
                    pdf.text('Area', margin + 140, 92); // Adjusted Y position

                    pdf.setFont('helvetica', 'normal');
                    pdf.setTextColor(60, 60, 60); // Darker text for better visibility
                    pdf.text(quotationNumber, margin + 25, 92); // Adjusted Y position
                    pdf.text(reportDate, margin + 65, 92); // Adjusted Y position
                    pdf.text(propertyType, margin + 115, 92); // Adjusted Y position
                    pdf.text(area, margin + 155, 92); // Adjusted Y position

                    // Service details header - adjusted position from 130 to 100
                    pdf.setFillColor(primaryColor[0], primaryColor[1], primaryColor[2]); // Blue background
                    pdf.rect(margin, 100, pageWidth - (margin * 2), 8, 'F'); // Adjusted Y position and reduced height

                    pdf.setFont('helvetica', 'bold');
                    pdf.setFontSize(9); // Reduced from 10
                    pdf.setTextColor(255, 255, 255); // White text on blue background
                    pdf.text('Chemicals to be Used', margin + 5, 106); // Adjusted Y position

                    // Service items
                    pdf.setTextColor(40, 40, 40); // Darker text for better visibility
                    pdf.setFont('helvetica', 'normal');
                    pdf.setFontSize(8); // Reduced from 9

                    let yPos = 115; // Adjusted from 145 to 115

                    // Calculate a reasonable rate based on area for later use
                    const areaValue = parseInt(area.replace(/[^0-9]/g, '')) || 100;
                    const baseRate = Math.round(areaValue * 15); // 15 per square meter
                    const problemAreaRate = Math.round(baseRate * 0.5); // 50% of base rate
                    let chemicalRate = 0;

                    // List chemicals - reduced spacing between items
                    if (chemicalRecommendations.length > 0) {
                        chemicalRate = Math.round(baseRate * 0.3); // 30% of base rate

                        chemicalRecommendations.forEach((chem, index) => {
                            if (index < 5) { // Show up to 5 chemicals
                                pdf.text(`• ${chem.name} (${chem.dosage})`, margin + 5, yPos);
                                yPos += 6; // Reduced spacing from 8 to 6
                            } else if (index === 5) {
                                pdf.text(`• And other appropriate chemicals as needed`, margin + 5, yPos);
                                yPos += 6; // Reduced spacing from 8 to 6
                            }
                        });
                    } else {
                        // If no specific chemicals, show general information
                        pdf.text(`• Appropriate chemicals will be selected based on pest type: ${pestTypes}`, margin + 5, yPos);
                        yPos += 6; // Reduced spacing from 8 to 6
                        pdf.text(`• Treatment area: ${problemArea}`, margin + 5, yPos);
                        yPos += 6; // Reduced spacing from 8 to 6
                    }

                    // Add a note about chemical application
                    pdf.setFont('helvetica', 'italic');
                    pdf.setFontSize(7); // Reduced from 8
                    pdf.text('Note: Specific chemicals and dosages may be adjusted based on on-site assessment.', margin + 5, yPos);
                    yPos += 8; // Reduced from 12

                    // Get the cost from the job_order table if available
                    let total = 0;
                    const costElement = document.getElementById('detailJobCost');

                    // First try to get the raw cost value from the data attribute we added to the cost element
                    if (costElement && costElement.hasAttribute('data-raw-cost')) {
                        const rawCost = costElement.getAttribute('data-raw-cost');
                        console.log('Raw cost from data-raw-cost attribute:', rawCost);

                        // Parse the raw cost value
                        total = parseFloat(rawCost.replace(/,/g, '')) || 0;
                        console.log('Parsed cost from data-raw-cost attribute:', total);
                    }
                    // Then try to get it from the view details button
                    else if (document.querySelector(`button[data-report-id="${reportId}"]`)) {
                        const viewDetailsBtn = document.querySelector(`button[data-report-id="${reportId}"]`);
                        if (viewDetailsBtn && viewDetailsBtn.hasAttribute('data-job-order-cost')) {
                            // Get the raw cost value from the data attribute
                            const rawCost = viewDetailsBtn.getAttribute('data-job-order-cost');
                            console.log('Raw cost from button data attribute:', rawCost);

                            // Parse the raw cost value
                            total = parseFloat(rawCost.replace(/,/g, '')) || 0;
                            console.log('Parsed cost from button data attribute:', total);
                        }
                    }
                    // Fallback to getting cost from the element text
                    else if (costElement && costElement.textContent && costElement.textContent !== 'Not specified') {
                        const costText = costElement.textContent;
                        console.log('Cost text from element:', costText);

                        // Remove currency symbols and any spaces, but keep commas and decimal points
                        const cleanedCostText = costText.replace(/[^\d,.]/g, '');
                        console.log('Cleaned cost text:', cleanedCostText);

                        // Replace commas with nothing to properly parse large numbers
                        total = parseFloat(cleanedCostText.replace(/,/g, '')) || 0;
                        console.log('Parsed cost from element text:', total);
                    }
                    // Last resort: use calculated total
                    else {
                        // Fallback to calculated total if cost is not available
                        total = baseRate + problemAreaRate + chemicalRate;
                        console.log('Using calculated total (fallback):', total);
                    }

                    // Add total - removed the minimum position constraint
                    yPos += 6; // Reduced from 10

                    // Draw line above total
                    pdf.setDrawColor(primaryColor[0], primaryColor[1], primaryColor[2]); // Blue line
                    pdf.line(margin, yPos, pageWidth - margin, yPos);

                    yPos += 8; // Reduced from 10

                    // Total
                    pdf.setFont('helvetica', 'bold');
                    pdf.setFontSize(10); // Reduced from 12
                    pdf.setTextColor(primaryColor[0], primaryColor[1], primaryColor[2]); // Blue color for total
                    pdf.text('Total Amount', pageWidth - margin - 70, yPos);

                    // Log the total value before formatting
                    console.log('Total value before formatting:', total);

                    // Format the total with commas for thousands and ensure it displays the full amount
                    // Use the Philippine Peso sign (₱)
                    const formattedTotal = `₱ ${total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                    console.log('Formatted total:', formattedTotal);

                    pdf.text(formattedTotal, pageWidth - margin - 10, yPos, { align: 'right' });

                    yPos += 15; // Reduced from 20

                    // Check if we need to add a new page for terms and conditions
                    if (yPos > pageHeight - 100) { // Reduced space requirement from 120 to 100
                        pdf.addPage(); // Add a new page
                        yPos = 20; // Reduced from 30
                    }

                    // Create a two-column layout for terms and conditions with increased spacing
                    const columnWidth = (pageWidth - (margin * 4)) / 2; // Added extra margin between columns

                    // Left column - Terms and Conditions
                    pdf.setFont('helvetica', 'bold');
                    pdf.setFontSize(9); // Reduced from 11
                    pdf.setTextColor(primaryColor[0], primaryColor[1], primaryColor[2]); // Blue color for section headers
                    pdf.text('Terms and Conditions', margin, yPos);

                    let leftColumnY = yPos + 6; // Reduced from 8

                    pdf.setFont('helvetica', 'normal');
                    pdf.setFontSize(8); // Reduced from 9
                    pdf.setTextColor(60, 60, 60); // Darker text for better visibility

                    // Use bullet points with 1.5 spacing but more compact
                    pdf.text('• This quotation is valid for 30 days from the', margin, leftColumnY, { maxWidth: columnWidth - 5 });
                    leftColumnY += 4; // Reduced from 5
                    pdf.text('  date of issue.', margin, leftColumnY, { maxWidth: columnWidth - 5 });

                    leftColumnY += 9; // Adjusted from 12 to maintain 1.5 spacing with smaller font

                    // Updated payment terms to 30 days upon acceptance
                    pdf.text('• Payment terms: 30 days upon acceptance.', margin, leftColumnY, { maxWidth: columnWidth - 5 });

                    leftColumnY += 9; // Adjusted from 12 to maintain 1.5 spacing with smaller font

                    pdf.text('• Service warranty: 30 days from treatment.', margin, leftColumnY, { maxWidth: columnWidth - 5 });

                    leftColumnY += 9; // Adjusted from 12 to maintain 1.5 spacing with smaller font

                    pdf.text('• Additional visits may be required for severe', margin, leftColumnY, { maxWidth: columnWidth - 5 });
                    leftColumnY += 4; // Reduced from 5
                    pdf.text('  infestations.', margin, leftColumnY, { maxWidth: columnWidth - 5 });

                    // Right column - Job Order Information with increased spacing
                    const rightColumnX = margin * 2.5 + columnWidth; // Increased spacing between columns

                    pdf.setFont('helvetica', 'bold');
                    pdf.setFontSize(9); // Reduced from 11
                    pdf.setTextColor(primaryColor[0], primaryColor[1], primaryColor[2]); // Blue color for section headers
                    pdf.text('Job Order Information', rightColumnX, yPos);

                    let rightColumnY = yPos + 6; // Reduced from 8

                    pdf.setFont('helvetica', 'normal');
                    pdf.setFontSize(8); // Reduced from 9
                    pdf.setTextColor(60, 60, 60); // Darker text for better visibility

                    // Get frequency information from the assessment report
                    const jobOrderFrequency = document.getElementById('detailJobFrequency') ?
                        document.getElementById('detailJobFrequency').textContent : 'One-time Treatment';

                    // Get job type information from the assessment report
                    const jobOrderType = document.getElementById('detailJobType') ?
                        document.getElementById('detailJobType').textContent : 'Pest Control Service';

                    // Get job time information from the assessment report
                    const jobOrderTime = document.getElementById('detailJobTime') ?
                        document.getElementById('detailJobTime').textContent : 'To be scheduled';

                    // Format job order information with bullet points and 1.5 spacing but more compact
                    pdf.text(`• Treatment Frequency: ${jobOrderFrequency}`, rightColumnX, rightColumnY, { maxWidth: columnWidth - 5 });

                    rightColumnY += 9; // Adjusted from 12 to maintain 1.5 spacing with smaller font

                    // Handle potentially long job type text by splitting it if needed
                    const jobTypeText = `• Type of Work: ${jobOrderType}`;
                    if (jobTypeText.length > 40) {
                        // Split the text at a logical point
                        const parts = jobOrderType.split('/');
                        if (parts.length > 1) {
                            // If there are slashes, split at them
                            pdf.text(`• Type of Work: ${parts[0]}`, rightColumnX, rightColumnY, { maxWidth: columnWidth - 5 });
                            rightColumnY += 4; // Reduced from 5
                            pdf.text(`  ${parts.slice(1).join('/')}`, rightColumnX, rightColumnY, { maxWidth: columnWidth - 5 });
                        } else {
                            // Otherwise split at a reasonable length
                            const firstPart = jobOrderType.substring(0, 20);
                            const secondPart = jobOrderType.substring(20);
                            pdf.text(`• Type of Work: ${firstPart}`, rightColumnX, rightColumnY, { maxWidth: columnWidth - 5 });
                            rightColumnY += 4; // Reduced from 5
                            pdf.text(`  ${secondPart}`, rightColumnX, rightColumnY, { maxWidth: columnWidth - 5 });
                        }
                    } else {
                        pdf.text(jobTypeText, rightColumnX, rightColumnY, { maxWidth: columnWidth - 5 });
                    }

                    rightColumnY += 9; // Adjusted from 12 to maintain 1.5 spacing with smaller font

                    pdf.text(`• Treatment Time: ${jobOrderTime}`, rightColumnX, rightColumnY, { maxWidth: columnWidth - 5 });

                    rightColumnY += 9; // Adjusted from 12 to maintain 1.5 spacing with smaller font

                    pdf.text(`• Treatment will be performed according`, rightColumnX, rightColumnY, { maxWidth: columnWidth - 5 });
                    rightColumnY += 4; // Reduced from 5
                    pdf.text(`  to the assessment findings.`, rightColumnX, rightColumnY, { maxWidth: columnWidth - 5 });

                    // Update yPos to the maximum of both columns plus some spacing
                    yPos = Math.max(leftColumnY, rightColumnY) + 12; // Reduced from 20

                    // Signature
                    pdf.setDrawColor(primaryColor[0], primaryColor[1], primaryColor[2]); // Blue line
                    pdf.line(pageWidth - margin - 60, yPos, pageWidth - margin, yPos);

                    yPos += 4; // Reduced from 5

                    pdf.setFont('helvetica', 'normal');
                    pdf.setFontSize(8); // Reduced from 9
                    pdf.setTextColor(60, 60, 60); // Darker text for better visibility
                    pdf.text('Authorized Signature', pageWidth - margin - 30, yPos, { align: 'center' });

                    // Ensure fixed position for footer with enough space from content
                    // Footer - positioned at fixed distance from bottom of page
                    const footerY1 = pageHeight - 20;
                    const footerY2 = pageHeight - 14;
                    const footerY3 = pageHeight - 8;

                    pdf.setFont('helvetica', 'normal');
                    pdf.setFontSize(8);
                    pdf.setTextColor(primaryColor[0], primaryColor[1], primaryColor[2]); // Blue color for footer
                    pdf.text('Thank you for choosing MacJ Pest Control Services', pageWidth / 2, footerY1, { align: 'center' });
                    pdf.setTextColor(80, 80, 80); // Slightly lighter for secondary footer text
                    pdf.text('For inquiries, please contact us at (02) 7 369 3904 / 09171457316 or macpest@yahoo.com', pageWidth / 2, footerY2, { align: 'center' });
                    pdf.text(`Quotation #${quotationNumber} | Generated on ${new Date().toLocaleDateString()}`, pageWidth / 2, footerY3, { align: 'center' });

                    // Save the PDF
                    pdf.save(filename);

                    // Hide loading overlay
                    loadingOverlay.style.display = 'none';

                    // Restore button state
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalBtnText;

                    // Show success message
                    alert('Quotation PDF has been generated successfully!');
                };

                // Helper function to convert number to words
                function numberToWords(num) {
                    const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
                    const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

                    if (num === 0) return 'Zero';

                    function convertLessThanOneThousand(num) {
                        if (num === 0) return '';
                        if (num < 20) return ones[num];

                        const ten = Math.floor(num / 10) % 10;
                        const one = num % 10;

                        return (ten > 0 ? tens[ten] + (one > 0 ? '-' + ones[one] : '') : ones[one]);
                    }

                    let result = '';

                    // Handle millions
                    const millions = Math.floor(num / 1000000);
                    if (millions > 0) {
                        result += convertLessThanOneThousand(millions) + ' Million ';
                        num %= 1000000;
                    }

                    // Handle thousands
                    const thousands = Math.floor(num / 1000);
                    if (thousands > 0) {
                        result += convertLessThanOneThousand(thousands) + ' Thousand ';
                        num %= 1000;
                    }

                    // Handle hundreds
                    const hundreds = Math.floor(num / 100);
                    if (hundreds > 0) {
                        result += ones[hundreds] + ' Hundred ';
                        num %= 100;
                    }

                    // Handle tens and ones
                    if (num > 0) {
                        result += convertLessThanOneThousand(num);
                    }

                    return result.trim();
                }

                // Try to load the logo first, then generate the PDF
                try {
                    // Create a new Image object to load the logo
                    const logoImg = new Image();
                    logoImg.crossOrigin = "Anonymous"; // Handle CORS issues

                    // Set up event handlers before setting the src
                    logoImg.onload = function() {
                        try {
                            // Create a canvas to convert the image to base64
                            const canvas = document.createElement('canvas');
                            const ctx = canvas.getContext('2d');
                            canvas.width = logoImg.width;
                            canvas.height = logoImg.height;
                            ctx.drawImage(logoImg, 0, 0);

                            // Get base64 data
                            const logoData = canvas.toDataURL('image/png');

                            // Add logo to PDF (right aligned)
                            const logoWidth = 40; // Width in mm
                            const logoHeight = 20; // Height in mm
                            pdf.addImage(logoData, 'PNG', pageWidth - margin - logoWidth, 10, logoWidth, logoHeight);
                        } catch (error) {
                            console.error('Error processing logo:', error);
                        }

                        // Continue with PDF generation regardless of logo success
                        generatePDFContent();
                    };

                    logoImg.onerror = function() {
                        console.error('Error loading logo');
                        // Continue with PDF generation without the logo
                        generatePDFContent();
                    };

                    // Set the source to trigger loading
                    logoImg.src = 'Landingpage/assets/img/MACJLOGO.png';
                } catch (error) {
                    console.error('Error in logo loading process:', error);
                    // Continue with PDF generation without the logo
                    generatePDFContent();
                }
            }
        });
    </script>

    <!-- Notification Scripts -->
    <script src="js/notifications.js"></script>
    <script>
        // Initialize mobile menu when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Set up event listeners for work type functionality
            const addWorkTypeBtn = document.getElementById('add_work_type_btn');
            const saveWorkTypeBtn = document.getElementById('save_work_type_btn');
            const cancelWorkTypeBtn = document.getElementById('cancel_work_type_btn');
            const newWorkTypeContainer = document.getElementById('new_work_type_container');
            const newWorkTypeInput = document.getElementById('new_work_type');
            const workTypeDropdown = document.getElementById('work_type_dropdown');
            const manageWorkTypesBtn = document.getElementById('manage_work_types_btn');
            const manageWorkTypesModal = document.getElementById('manageWorkTypesModal');
            const closeManageTypesBtn = document.getElementById('closeManageTypesBtn');
            const workTypesList = document.getElementById('workTypesList');

            // Show the new work type input when "Add New" button is clicked
            if (addWorkTypeBtn) {
                addWorkTypeBtn.addEventListener('click', function() {
                    newWorkTypeContainer.style.display = 'block';
                    newWorkTypeInput.focus();
                });
            }

            // Hide the new work type input when "Cancel" button is clicked
            if (cancelWorkTypeBtn) {
                cancelWorkTypeBtn.addEventListener('click', function() {
                    newWorkTypeContainer.style.display = 'none';
                    newWorkTypeInput.value = '';
                });
            }

            // Add the new work type when "Add" button is clicked
            if (saveWorkTypeBtn) {
                saveWorkTypeBtn.addEventListener('click', function() {
                    const newWorkType = newWorkTypeInput.value.trim();

                    if (!newWorkType) {
                        alert('Please enter a type of work');
                        newWorkTypeInput.focus();
                        return;
                    }

                    // Check if this work type already exists in the checkboxes
                    const workTypeCheckboxes = document.querySelectorAll('input[name="type_of_work[]"]');
                    let exists = false;

                    workTypeCheckboxes.forEach(checkbox => {
                        if (checkbox.value.toLowerCase() === newWorkType.toLowerCase()) {
                            exists = true;
                            checkbox.checked = true;
                            checkbox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    });

                    if (exists) {
                        alert('This work type already exists. It has been selected for you.');
                        newWorkTypeContainer.style.display = 'none';
                        newWorkTypeInput.value = '';
                        return;
                    }

                    // Save the new work type to the database
                    const formData = new FormData();
                    formData.append('work_type', newWorkType);

                    fetch('save_work_type.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Add the new work type to the checkboxes
                            const workTypesGrid = document.querySelector('.work-types-grid');
                            const newCheckboxItem = document.createElement('div');
                            newCheckboxItem.className = 'work-type-checkbox-item';

                            newCheckboxItem.innerHTML = `
                                <label>
                                    <input type="checkbox" name="type_of_work[]" value="${newWorkType}" checked>
                                    ${newWorkType}
                                </label>
                            `;

                            workTypesGrid.appendChild(newCheckboxItem);

                            // Hide the new work type input
                            newWorkTypeContainer.style.display = 'none';
                            newWorkTypeInput.value = '';

                            // Scroll to the new checkbox
                            newCheckboxItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        } else {
                            alert(data.error || 'Failed to save work type');
                        }
                    })
                    .catch(error => {
                        alert('Error saving work type: ' + error.message);
                    });
                });
            }

            // Set up form submission handler
            const jobOrderForm = document.getElementById('jobOrderForm');
            if (jobOrderForm) {
                jobOrderForm.addEventListener('submit', function(e) {
                    // Make sure at least one work type is selected
                    const workTypeCheckboxes = document.querySelectorAll('input[name="type_of_work[]"]:checked');
                    const workTypeError = document.getElementById('work_type_error');

                    if (workTypeCheckboxes.length === 0) {
                        e.preventDefault();
                        workTypeError.style.display = 'block';
                        document.querySelector('.work-types-grid').scrollIntoView({ behavior: 'smooth', block: 'start' });
                        return false;
                    } else {
                        workTypeError.style.display = 'none';
                    }
                });
            }

            // Manage Work Types functionality
            if (manageWorkTypesBtn) {
                manageWorkTypesBtn.addEventListener('click', function() {
                    // Show the modal
                    manageWorkTypesModal.style.display = 'block';

                    // Load work types
                    loadWorkTypes();
                });
            }

            // Close Manage Types modal
            if (closeManageTypesBtn) {
                closeManageTypesBtn.addEventListener('click', function() {
                    manageWorkTypesModal.style.display = 'none';
                });
            }

            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === manageWorkTypesModal) {
                    manageWorkTypesModal.style.display = 'none';
                }
            });

            // Close modal when clicking the X
            const closeManageTypesX = manageWorkTypesModal.querySelector('.close');
            if (closeManageTypesX) {
                closeManageTypesX.addEventListener('click', function() {
                    manageWorkTypesModal.style.display = 'none';
                });
            }

            // Function to load work types
            function loadWorkTypes() {
                fetch('get_work_types.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            displayWorkTypes(data.data);
                        } else {
                            workTypesList.innerHTML = `<div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> ${data.error}
                            </div>`;
                        }
                    })
                    .catch(error => {
                        workTypesList.innerHTML = `<div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> Error loading work types: ${error.message}
                        </div>`;
                    });
            }

            // Function to display work types
            function displayWorkTypes(workTypes) {
                workTypesList.innerHTML = '';

                // Display default work types
                if (workTypes.default && workTypes.default.length > 0) {
                    const defaultTypesHeader = document.createElement('h3');
                    defaultTypesHeader.textContent = 'Default Work Types';
                    defaultTypesHeader.style.marginTop = '20px';
                    defaultTypesHeader.style.marginBottom = '15px';
                    defaultTypesHeader.style.fontSize = '1.1rem';
                    defaultTypesHeader.style.color = '#333';
                    workTypesList.appendChild(defaultTypesHeader);

                    workTypes.default.forEach(type => {
                        const typeItem = document.createElement('div');
                        typeItem.className = 'work-type-item default-type';
                        typeItem.innerHTML = `
                            <div class="work-type-name">${type}</div>
                            <div class="work-type-actions">
                                <button class="btn btn-secondary" disabled>
                                    <i class="fas fa-lock"></i> Default
                                </button>
                            </div>
                        `;
                        workTypesList.appendChild(typeItem);
                    });
                }

                // Display custom work types
                if (workTypes.custom && workTypes.custom.length > 0) {
                    const customTypesHeader = document.createElement('h3');
                    customTypesHeader.textContent = 'Custom Work Types';
                    customTypesHeader.style.marginTop = '30px';
                    customTypesHeader.style.marginBottom = '15px';
                    customTypesHeader.style.fontSize = '1.1rem';
                    customTypesHeader.style.color = '#333';
                    workTypesList.appendChild(customTypesHeader);

                    workTypes.custom.forEach(type => {
                        const typeItem = document.createElement('div');
                        typeItem.className = 'work-type-item';
                        typeItem.innerHTML = `
                            <div class="work-type-name">${type}</div>
                            <div class="work-type-actions">
                                <button class="delete-work-type-btn" data-type="${type}">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        `;
                        workTypesList.appendChild(typeItem);
                    });

                    // Add event listeners to delete buttons
                    const deleteButtons = workTypesList.querySelectorAll('.delete-work-type-btn');
                    deleteButtons.forEach(button => {
                        button.addEventListener('click', function() {
                            const workType = this.getAttribute('data-type');
                            deleteWorkType(workType);
                        });
                    });
                } else if (workTypes.custom && workTypes.custom.length === 0) {
                    const noCustomTypes = document.createElement('div');
                    noCustomTypes.className = 'alert alert-info';
                    noCustomTypes.style.marginTop = '20px';
                    noCustomTypes.innerHTML = `
                        <i class="fas fa-info-circle"></i> No custom work types found.
                        You can add custom work types by clicking the "Add New" button when creating a quotation.
                    `;
                    workTypesList.appendChild(noCustomTypes);
                }
            }

            // Function to delete a work type
            function deleteWorkType(workType) {
                if (confirm(`Are you sure you want to delete the work type "${workType}"?`)) {
                    const formData = new FormData();
                    formData.append('work_type', workType);

                    fetch('delete_work_type.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Reload work types
                            loadWorkTypes();

                            // Remove from checkboxes if exists
                            const workTypeCheckboxes = document.querySelectorAll('input[name="type_of_work[]"]');
                            workTypeCheckboxes.forEach(checkbox => {
                                if (checkbox.value === workType) {
                                    // Remove the entire checkbox item (parent div)
                                    const checkboxItem = checkbox.closest('.work-type-checkbox-item');
                                    if (checkboxItem) {
                                        checkboxItem.remove();
                                    }
                                }
                            });
                        } else {
                            alert(data.error);
                        }
                    })
                    .catch(error => {
                        alert('Error deleting work type: ' + error.message);
                    });
                }
            }

            // Mobile menu toggle
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.querySelector('.sidebar');

            if (menuToggle) {
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }
        });
    </script>

    <!-- Notification Scripts -->
    <script src="js/notifications.js"></script>
    <script src="js/chemical-notifications.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize notification functionality
            const notificationIcon = $('.notification-icon');
            const notificationDropdown = $('.notification-dropdown');

            // Toggle dropdown when notification icon is clicked
            notificationIcon.on('click', function(e) {
                e.stopPropagation();
                notificationDropdown.toggleClass('show');
                console.log('Notification icon clicked - dropdown toggled');
            });

            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!notificationDropdown.is(e.target) && notificationDropdown.has(e.target).length === 0 && !notificationIcon.is(e.target)) {
                    notificationDropdown.removeClass('show');
                }
            });

            // Handle mark all as read
            $('.mark-all-read').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof markAllNotificationsAsRead === 'function') {
                    markAllNotificationsAsRead();
                }
            });

            // Fetch notifications immediately
            if (typeof fetchNotifications === 'function') {
                fetchNotifications();

                // Set up periodic notification checks
                setInterval(fetchNotifications, 60000); // Check every minute
            } else {
                console.error("fetchNotifications function not found");
            }
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>