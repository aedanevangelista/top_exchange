<?php
include '../db_connect.php';
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contract | MacJ Pest Control</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/sidebar-fix.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/client-common.css">
    <link rel="stylesheet" href="css/calendar.css">
    <!-- Removed unnecessary CSS files -->
    <link rel="stylesheet" href="css/notifications.css">
    <!-- Leaflet.js for OpenStreetMap -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="anonymous"></script>
    <style>
        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .loading-spinner {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
        }

        .loading-spinner i {
            color: #1e3a8a;
            margin-bottom: 15px;
        }

        .loading-spinner p {
            margin: 0;
            font-weight: bold;
            color: #333;
        }

        .loading-spinner .small-text {
            font-size: 0.8rem;
            font-weight: normal;
            margin-top: 10px;
            color: #666;
        }

        /* Ensure validation messages are italicized */
        .invalid-feedback, .text-danger, .error-message {
            font-style: italic !important;
            color: var(--error-color) !important;
        }

        /* Notification Dropdown Override */
        .notification-dropdown.show {
            display: block !important;
            z-index: 9999;
        }

        /* Ensure notification container is properly positioned */
        .notification-container {
            position: relative;
            display: inline-block;
        }
    </style>
</head>
<body class="contract">
    <!-- Header -->
    <header class="header">
        <div class="header-title">
            <h1>Client Portal</h1>
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
                <div>
                    <div class="user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Client') ?></div>
                    <div class="user-role">Client</div>
                </div>
            </div>
        </div>
    </header>
    <button id="menuToggle"><i class="fas fa-bars"></i></button>

        <aside id="sidebar">
            <div class="sidebar-header">
                <h2>MacJ Pest Control</h2>
                <h3>Welcome, <?= htmlspecialchars($_SESSION['fullname'] ?? '') ?></h3>
            </div>
            <nav class="sidebar-menu">
                <a href="schedule.php">
                    <i class="fas fa-calendar-alt fa-icon"></i>
                    Schedule Appointment
                </a>
                <a href="profile.php">
                    <i class="fas fa-user fa-icon"></i>
                    My Profile
                </a>
                <a href="inspection_report.php">
                    <i class="fas fa-clipboard-check fa-icon"></i>
                    Inspection Report
                </a>
                <a href="contract.php" class="active">
                    <i class="fas fa-clipboard-check fa-icon"></i>
                    Contract
                </a>
                <a href="job_order_report.php">
                    <i class="fas fa-file-alt fa-icon"></i>
                    Job Order Report
                </a>
                <a href="SignOut.php">
                    <i class="fas fa-sign-out-alt fa-icon"></i>
                    Logout
                </a>
            </nav>
            <div class="sidebar-footer">
                <p>&copy; <?= date('Y') ?> MacJ Pest Control</p>
                <a href="https://www.facebook.com/MACJPEST" target="_blank"><i class="fab fa-facebook"></i> Facebook</a>
            </div>
        </aside>

        <main class="main-content" id="mainContent">
            <div class="page-header">
                <div>
                    <h1>Contract</h1>
                    <p>View and manage your recurring treatment contracts</p>
                </div>
                <div>
                    <p class="text-light"><?= date('l, F j, Y') ?></p>
                </div>
            </div>

                <div class="contract-container">

                <?php
                // Debug information
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    echo "<div style='display:none;'>";
                    echo "POST data: ";
                    print_r($_POST);
                    echo "<br>SESSION data: ";
                    print_r($_SESSION);
                    echo "</div>";
                }

                // Process form submission for treatment approval
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_order_id']) && isset($_POST['approval_action'])) {
                    $job_order_id = $conn->real_escape_string($_POST['job_order_id']);
                    $approval_action = $conn->real_escape_string($_POST['approval_action']);
                    $client_id = $_SESSION['client_id'] ?? 0;
                    $approval_date = date('Y-m-d H:i:s');

                    // Validate that this job order belongs to the current client
                    $validate_query = "SELECT jo.job_order_id, a.client_id, jo.client_approval_status
                                      FROM job_order jo
                                      JOIN assessment_report ar ON jo.report_id = ar.report_id
                                      JOIN appointments a ON ar.appointment_id = a.appointment_id
                                      WHERE jo.job_order_id = ?";
                    $validate_stmt = $conn->prepare($validate_query);
                    $validate_stmt->bind_param("i", $job_order_id);
                    $validate_stmt->execute();
                    $validate_result = $validate_stmt->get_result();
                    $validation_row = $validate_result->fetch_assoc();

                    // Debug validation
                    echo "<div style='display:none;'>";
                    echo "Validation query: " . $validate_query . "<br>";
                    echo "Job order ID: " . $job_order_id . "<br>";
                    echo "Client ID from session: " . $client_id . "<br>";
                    echo "Validation result: ";
                    print_r($validation_row);
                    echo "</div>";

                    if ($validate_result->num_rows > 0 && $validation_row['client_id'] == $client_id) {
                        // Check if the job order is already approved or declined
                        if ($validation_row['client_approval_status'] !== 'pending') {
                            $error_message = "This job order has already been " . $validation_row['client_approval_status'] . ".";
                        } else {
                            // Valid job order for this client

                        if ($approval_action === 'approve') {
                            // Debug information
                            error_log("Starting approval process for job_order_id: $job_order_id");

                            try {
                                // Handle payment proof upload
                                $payment_amount = isset($_POST['payment_amount']) ? floatval($_POST['payment_amount']) : 0;
                                $payment_proof = '';

                                // Process file upload if it exists
                                if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === 0) {
                                    $upload_dir = '../uploads/payments/';

                                    // Create directory if it doesn't exist
                                    if (!file_exists($upload_dir)) {
                                        mkdir($upload_dir, 0777, true);
                                    }

                                    $file_name = uniqid() . '_' . basename($_FILES['payment_proof']['name']);
                                    $target_path = $upload_dir . $file_name;

                                    if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $target_path)) {
                                        $payment_proof = $file_name;
                                    } else {
                                        throw new Exception("Failed to upload payment proof.");
                                    }
                                } else {
                                    throw new Exception("Payment proof is required.");
                                }

                                // Approve the recurring schedule with payment information
                                $update_query = "UPDATE job_order SET client_approval_status = 'approved', client_approval_date = ?,
                                                payment_amount = ?, payment_proof = ?, payment_date = ? WHERE job_order_id = ?";
                                $update_stmt = $conn->prepare($update_query);
                                if (!$update_stmt) {
                                    throw new Exception("Prepare statement failed: " . $conn->error);
                                }

                                $update_stmt->bind_param("sdssi", $approval_date, $payment_amount, $payment_proof, $approval_date, $job_order_id);
                                $result = $update_stmt->execute();
                                if (!$result) {
                                    throw new Exception("Execute statement failed: " . $update_stmt->error);
                                }

                                error_log("Successfully updated job_order_id: $job_order_id to approved");

                                // Get report_id and frequency for related job orders
                                $get_related_query = "SELECT report_id, frequency FROM job_order WHERE job_order_id = ?";
                                $get_related_stmt = $conn->prepare($get_related_query);
                                if (!$get_related_stmt) {
                                    throw new Exception("Prepare statement failed for related query: " . $conn->error);
                                }

                                $get_related_stmt->bind_param("i", $job_order_id);
                                $get_related_stmt->execute();
                                $related_result = $get_related_stmt->get_result();
                                $related_row = $related_result->fetch_assoc();

                                if ($related_row) {
                                    $report_id = $related_row['report_id'];
                                    $frequency = $related_row['frequency'];

                                    error_log("Found related job orders with report_id: $report_id and frequency: $frequency");

                                    // Update all related job orders in a single query
                                    $update_all_query = "UPDATE job_order SET client_approval_status = 'approved', client_approval_date = ?
                                                        WHERE report_id = ? AND frequency = ? AND job_order_id != ?";
                                    $update_all_stmt = $conn->prepare($update_all_query);
                                    if (!$update_all_stmt) {
                                        throw new Exception("Prepare statement failed for update all: " . $conn->error);
                                    }

                                    $update_all_stmt->bind_param("sisi", $approval_date, $report_id, $frequency, $job_order_id);
                                    $update_all_result = $update_all_stmt->execute();
                                    if (!$update_all_result) {
                                        throw new Exception("Execute statement failed for update all: " . $update_all_stmt->error);
                                    }

                                    error_log("Successfully updated all related job orders");
                                }

                                // Create a single notification for the first admin
                                $admin_query = "SELECT staff_id FROM office_staff LIMIT 1";
                                $admin_result = $conn->query($admin_query);
                                if ($admin_result && $admin_row = $admin_result->fetch_assoc()) {
                                    $admin_id = $admin_row['staff_id'];
                                    $title = "Contract Approved with Payment";
                                    $message = "A client has approved job order #$job_order_id and confirmed payment of PHP " . number_format($payment_amount, 2) . ".";

                                    $notif_query = "INSERT INTO notifications (user_id, user_type, title, message, related_id, related_type, is_read, created_at)
                                                  VALUES (?, 'admin', ?, ?, ?, 'job_order', 0, NOW())";
                                    $notif_stmt = $conn->prepare($notif_query);
                                    if ($notif_stmt) {
                                        $notif_stmt->bind_param("issi", $admin_id, $title, $message, $job_order_id);
                                        $notif_stmt->execute();
                                        error_log("Notification created for admin ID: $admin_id");
                                    }
                                }

                                $success_message = "You have approved the recurring treatment schedule and confirmed your payment of PHP " . number_format($payment_amount, 2) . ".";
                                error_log("Approval process completed successfully with payment confirmation");

                            } catch (Exception $e) {
                                error_log("Error in approval process: " . $e->getMessage());
                                $error_message = "An error occurred while processing your request: " . $e->getMessage();
                            }
                        } elseif ($approval_action === 'decline') {
                            // Debug information
                            error_log("Starting decline process for job_order_id: $job_order_id");

                            try {
                                // Decline the recurring schedule - SIMPLIFIED VERSION
                                $update_query = "UPDATE job_order SET client_approval_status = 'declined', client_approval_date = ? WHERE job_order_id = ?";
                                $update_stmt = $conn->prepare($update_query);
                                if (!$update_stmt) {
                                    throw new Exception("Prepare statement failed: " . $conn->error);
                                }

                                $update_stmt->bind_param("si", $approval_date, $job_order_id);
                                $result = $update_stmt->execute();
                                if (!$result) {
                                    throw new Exception("Execute statement failed: " . $update_stmt->error);
                                }

                                error_log("Successfully updated job_order_id: $job_order_id to declined");

                                // Get report_id and frequency for related job orders
                                $get_related_query = "SELECT report_id, frequency FROM job_order WHERE job_order_id = ?";
                                $get_related_stmt = $conn->prepare($get_related_query);
                                if (!$get_related_stmt) {
                                    throw new Exception("Prepare statement failed for related query: " . $conn->error);
                                }

                                $get_related_stmt->bind_param("i", $job_order_id);
                                $get_related_stmt->execute();
                                $related_result = $get_related_stmt->get_result();
                                $related_row = $related_result->fetch_assoc();

                                if ($related_row) {
                                    $report_id = $related_row['report_id'];
                                    $frequency = $related_row['frequency'];

                                    error_log("Found related job orders with report_id: $report_id and frequency: $frequency");

                                    // Update all related job orders in a single query
                                    $update_all_query = "UPDATE job_order SET client_approval_status = 'declined', client_approval_date = ?
                                                        WHERE report_id = ? AND frequency = ? AND job_order_id != ?";
                                    $update_all_stmt = $conn->prepare($update_all_query);
                                    if (!$update_all_stmt) {
                                        throw new Exception("Prepare statement failed for update all: " . $conn->error);
                                    }

                                    $update_all_stmt->bind_param("sisi", $approval_date, $report_id, $frequency, $job_order_id);
                                    $update_all_result = $update_all_stmt->execute();
                                    if (!$update_all_result) {
                                        throw new Exception("Execute statement failed for update all: " . $update_all_stmt->error);
                                    }

                                    error_log("Successfully updated all related job orders");
                                }

                                // Create a single notification for the first admin
                                $admin_query = "SELECT staff_id FROM office_staff LIMIT 1";
                                $admin_result = $conn->query($admin_query);
                                if ($admin_result && $admin_row = $admin_result->fetch_assoc()) {
                                    $admin_id = $admin_row['staff_id'];
                                    $title = "Contract Declined";
                                    $message = "A client has declined job order #$job_order_id.";

                                    $notif_query = "INSERT INTO notifications (user_id, user_type, title, message, related_id, related_type, is_read, created_at)
                                                  VALUES (?, 'admin', ?, ?, ?, 'job_order', 0, NOW())";
                                    $notif_stmt = $conn->prepare($notif_query);
                                    if ($notif_stmt) {
                                        $notif_stmt->bind_param("issi", $admin_id, $title, $message, $job_order_id);
                                        $notif_stmt->execute();
                                        error_log("Notification created for admin ID: $admin_id");
                                    }
                                }

                                $success_message = "You have declined the recurring treatment schedule. An administrator will contact you to discuss alternatives.";
                                error_log("Decline process completed successfully");

                            } catch (Exception $e) {
                                error_log("Error in decline process: " . $e->getMessage());
                                $error_message = "An error occurred while processing your request: " . $e->getMessage();
                            }
                        } elseif ($approval_action === 'one-time') {
                            // Debug information
                            error_log("Starting one-time process for job_order_id: $job_order_id");

                            try {
                                // Handle payment proof upload
                                $payment_amount = isset($_POST['payment_amount']) ? floatval($_POST['payment_amount']) : 0;
                                $payment_proof = '';

                                // Process file upload if it exists
                                if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === 0) {
                                    $upload_dir = '../uploads/payments/';

                                    // Create directory if it doesn't exist
                                    if (!file_exists($upload_dir)) {
                                        mkdir($upload_dir, 0777, true);
                                    }

                                    $file_name = uniqid() . '_' . basename($_FILES['payment_proof']['name']);
                                    $target_path = $upload_dir . $file_name;

                                    if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $target_path)) {
                                        $payment_proof = $file_name;
                                    } else {
                                        throw new Exception("Failed to upload payment proof.");
                                    }
                                } else {
                                    throw new Exception("Payment proof is required.");
                                }

                                // Convert to one-time treatment with payment information
                                $update_query = "UPDATE job_order SET client_approval_status = 'one-time', frequency = 'one-time',
                                                client_approval_date = ?, payment_amount = ?, payment_proof = ?, payment_date = ?
                                                WHERE job_order_id = ?";
                                $update_stmt = $conn->prepare($update_query);
                                if (!$update_stmt) {
                                    throw new Exception("Prepare statement failed: " . $conn->error);
                                }

                                $update_stmt->bind_param("sdssi", $approval_date, $payment_amount, $payment_proof, $approval_date, $job_order_id);
                                $result = $update_stmt->execute();
                                if (!$result) {
                                    throw new Exception("Execute statement failed: " . $update_stmt->error);
                                }

                                error_log("Successfully updated job_order_id: $job_order_id to one-time");

                                // Get report_id and frequency for related job orders
                                $get_related_query = "SELECT report_id, frequency FROM job_order WHERE job_order_id = ?";
                                $get_related_stmt = $conn->prepare($get_related_query);
                                if (!$get_related_stmt) {
                                    throw new Exception("Prepare statement failed for related query: " . $conn->error);
                                }

                                $get_related_stmt->bind_param("i", $job_order_id);
                                $get_related_stmt->execute();
                                $related_result = $get_related_stmt->get_result();
                                $related_row = $related_result->fetch_assoc();

                                if ($related_row) {
                                    $report_id = $related_row['report_id'];
                                    $original_frequency = $related_row['frequency'];

                                    error_log("Found related job orders with report_id: $report_id and frequency: $original_frequency");

                                    // Delete technician assignments for related job orders
                                    $delete_techs_query = "DELETE jot FROM job_order_technicians jot
                                                         JOIN job_order jo ON jot.job_order_id = jo.job_order_id
                                                         WHERE jo.report_id = ? AND jo.frequency = ? AND jo.job_order_id != ?";
                                    $delete_techs_stmt = $conn->prepare($delete_techs_query);
                                    if ($delete_techs_stmt) {
                                        $delete_techs_stmt->bind_param("isi", $report_id, $original_frequency, $job_order_id);
                                        $delete_techs_stmt->execute();
                                        error_log("Deleted technician assignments for related job orders");
                                    }

                                    // Delete related job orders
                                    $delete_all_query = "DELETE FROM job_order WHERE report_id = ? AND frequency = ? AND job_order_id != ?";
                                    $delete_all_stmt = $conn->prepare($delete_all_query);
                                    if (!$delete_all_stmt) {
                                        throw new Exception("Prepare statement failed for delete all: " . $conn->error);
                                    }

                                    $delete_all_stmt->bind_param("isi", $report_id, $original_frequency, $job_order_id);
                                    $delete_all_result = $delete_all_stmt->execute();
                                    if (!$delete_all_result) {
                                        throw new Exception("Execute statement failed for delete all: " . $delete_all_stmt->error);
                                    }

                                    error_log("Successfully deleted all related job orders");
                                }

                                // Create a single notification for the first admin
                                $admin_query = "SELECT staff_id FROM office_staff LIMIT 1";
                                $admin_result = $conn->query($admin_query);
                                if ($admin_result && $admin_row = $admin_result->fetch_assoc()) {
                                    $admin_id = $admin_row['staff_id'];
                                    $title = "One-Time Treatment Selected with Payment";
                                    $message = "A client has selected a one-time treatment for job order #$job_order_id and confirmed payment of PHP " . number_format($payment_amount, 2) . ".";

                                    $notif_query = "INSERT INTO notifications (user_id, user_type, title, message, related_id, related_type, is_read, created_at)
                                                  VALUES (?, 'admin', ?, ?, ?, 'job_order', 0, NOW())";
                                    $notif_stmt = $conn->prepare($notif_query);
                                    if ($notif_stmt) {
                                        $notif_stmt->bind_param("issi", $admin_id, $title, $message, $job_order_id);
                                        $notif_stmt->execute();
                                        error_log("Notification created for admin ID: $admin_id");
                                    }
                                }

                                $success_message = "You have chosen a one-time treatment only and confirmed your payment of PHP " . number_format($payment_amount, 2) . ". All recurring appointments have been cancelled.";
                                error_log("One-time process completed successfully with payment confirmation");

                            } catch (Exception $e) {
                                error_log("Error in one-time process: " . $e->getMessage());
                                $error_message = "An error occurred while processing your request: " . $e->getMessage();
                            }
                        }
                        }
                    } else {
                        $error_message = "Invalid job order or you don't have permission to approve this treatment.";
                    }
                }

                // Display success or error messages
                if (isset($success_message)) {
                    echo "<div class='alert alert-success'><i class='fas fa-check-circle'></i> <strong>Success!</strong> $success_message</div>";
                }

                if (isset($error_message)) {
                    echo "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i> <strong>Error!</strong> $error_message</div>";
                }

                // Get client ID from session
                $client_id = $_SESSION['client_id'] ?? 0;

                // Check if client_id is set
                if ($client_id == 0) {
                    echo "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i> <strong>Error!</strong> You are not logged in or your session has expired. Please <a href='login.php'>log in</a> to continue.</div>";
                }

                // Check if there are any job orders for this client
                $check_job_orders_query = "SELECT COUNT(*) as count
                                          FROM job_order jo
                                          JOIN assessment_report ar ON jo.report_id = ar.report_id
                                          JOIN appointments a ON ar.appointment_id = a.appointment_id
                                          WHERE a.client_id = ?";
                $check_job_orders_stmt = $conn->prepare($check_job_orders_query);
                $check_job_orders_stmt->bind_param("i", $client_id);
                $check_job_orders_stmt->execute();
                $check_job_orders_result = $check_job_orders_stmt->get_result();
                $check_job_orders_row = $check_job_orders_result->fetch_assoc();
                $has_job_orders = $check_job_orders_row['count'] > 0;

                if (!$has_job_orders) {
                    echo "<div class='alert alert-info'><i class='fas fa-info-circle'></i> <strong>No Contracts Available:</strong> You don't have any treatment plans yet. Once a technician creates a treatment plan for you, it will appear here for your approval.</div>";
                }

                // Fetch pending job orders that require client approval
                $pending_query = "SELECT jo.job_order_id, jo.type_of_work, jo.preferred_date, jo.preferred_time, jo.frequency,
                                  jo.chemical_recommendations, jo.cost,
                                  ar.report_id, ar.created_at, ar.area, ar.pest_types, ar.problem_area,
                                  a.location_address as property_address, a.client_id
                                  FROM job_order jo
                                  JOIN assessment_report ar ON jo.report_id = ar.report_id
                                  JOIN appointments a ON ar.appointment_id = a.appointment_id
                                  WHERE a.client_id = ? AND jo.client_approval_status = 'pending'
                                  AND jo.frequency != 'one-time'
                                  GROUP BY ar.report_id, jo.frequency
                                  ORDER BY jo.preferred_date ASC";
                $pending_stmt = $conn->prepare($pending_query);
                $pending_stmt->bind_param("i", $client_id);
                $pending_stmt->execute();
                $pending_result = $pending_stmt->get_result();
                $pending_count = $pending_result->num_rows;

                // Fetch approved job orders
                $approved_query = "SELECT jo.job_order_id, jo.type_of_work, jo.preferred_date, jo.preferred_time, jo.frequency,
                                  jo.client_approval_status, jo.client_approval_date, jo.chemical_recommendations, jo.cost,
                                  jo.payment_amount, jo.payment_proof, jo.payment_date,
                                  ar.report_id, ar.area, ar.pest_types, ar.problem_area,
                                  a.location_address as property_address
                                  FROM job_order jo
                                  JOIN assessment_report ar ON jo.report_id = ar.report_id
                                  JOIN appointments a ON ar.appointment_id = a.appointment_id
                                  WHERE a.client_id = ? AND jo.client_approval_status IN ('approved', 'one-time')
                                  GROUP BY ar.report_id, jo.frequency
                                  ORDER BY jo.preferred_date ASC";
                $approved_stmt = $conn->prepare($approved_query);
                $approved_stmt->bind_param("i", $client_id);
                $approved_stmt->execute();
                $approved_result = $approved_stmt->get_result();
                $approved_count = $approved_result->num_rows;

                // Fetch declined job orders
                $declined_query = "SELECT jo.job_order_id, jo.type_of_work, jo.preferred_date, jo.preferred_time, jo.frequency,
                                  jo.client_approval_date, jo.chemical_recommendations,
                                  ar.report_id, ar.area, ar.pest_types, ar.problem_area,
                                  a.location_address as property_address
                                  FROM job_order jo
                                  JOIN assessment_report ar ON jo.report_id = ar.report_id
                                  JOIN appointments a ON ar.appointment_id = a.appointment_id
                                  WHERE a.client_id = ? AND jo.client_approval_status = 'declined'
                                  GROUP BY ar.report_id, jo.frequency
                                  ORDER BY jo.preferred_date ASC";
                $declined_stmt = $conn->prepare($declined_query);
                $declined_stmt->bind_param("i", $client_id);
                $declined_stmt->execute();
                $declined_result = $declined_stmt->get_result();
                $declined_count = $declined_result->num_rows;

                // Tab Navigation
                echo "<div class='contract-tabs'>";
                echo "<button class='contract-tab active' data-tab='pending'><i class='fas fa-clock'></i> Awaiting Approval";
                if ($pending_count > 0) echo "<span class='badge'>$pending_count</span>";
                echo "</button>";
                echo "<button class='contract-tab' data-tab='approved'><i class='fas fa-check-circle'></i> Approved Plans";
                if ($approved_count > 0) echo "<span class='badge'>$approved_count</span>";
                echo "</button>";
                echo "<button class='contract-tab' data-tab='declined'><i class='fas fa-times-circle'></i> Declined Plans";
                if ($declined_count > 0) echo "<span class='badge'>$declined_count</span>";
                echo "</button>";
                echo "</div>";

                // Pending Treatments Tab
                echo "<div class='tab-content active' id='pending-tab'>";
                if ($pending_count > 0) {
                    echo "<div class='alert alert-info'><i class='fas fa-info-circle'></i> <strong>Action Required:</strong> Review and approve treatment plans to schedule your pest control services.</div>";

                    echo "<div class='treatment-grid'>";

                    while ($row = $pending_result->fetch_assoc()) {
                        $job_order_id = $row['job_order_id'];
                        $type_of_work = htmlspecialchars($row['type_of_work']);
                        $preferred_date = !empty($row['preferred_date']) ? date('F j, Y', strtotime($row['preferred_date'])) : 'Not specified';
                        $preferred_time = !empty($row['preferred_time']) ? date('g:i A', strtotime($row['preferred_time'])) : 'Not specified';
                        $frequency = ucfirst(htmlspecialchars($row['frequency']));
                        $property_address = htmlspecialchars($row['property_address']);
                        $area = !empty($row['area']) ? number_format($row['area'], 2) : 'Not specified';
                        $pest_types = !empty($row['pest_types']) ? htmlspecialchars($row['pest_types']) : 'Not specified';
                        $problem_area = !empty($row['problem_area']) ? htmlspecialchars($row['problem_area']) : 'Not specified';
                        $cost = !empty($row['cost']) ? number_format($row['cost'], 2) : 'Not specified';

                        // Process chemical recommendations
                        $chemical_recommendations = [];
                        if (!empty($row['chemical_recommendations'])) {
                            // Add debug information (hidden from normal view)
                            echo "<div style='display:none;'>";
                            echo "Chemical recommendations raw data: " . htmlspecialchars($row['chemical_recommendations']) . "<br>";
                            echo "</div>";

                            $decoded = json_decode($row['chemical_recommendations'], true);
                            if ($decoded && json_last_error() === JSON_ERROR_NONE) {
                                // Add debug information (hidden from normal view)
                                echo "<div style='display:none;'>";
                                echo "Decoded data: ";
                                print_r($decoded);
                                echo "</div>";

                                // Format 1: Array of objects from quotation modal
                                if (is_array($decoded) && !isset($decoded['success'])) {
                                    foreach ($decoded as $chemical) {
                                        // Format from assessment_report.php (selected_chemicals)
                                        if (isset($chemical['name'])) {
                                            $chemical_recommendations[] = $chemical['name'];
                                        }
                                        // Format from chemical_recommendations.js
                                        else if (isset($chemical['chemical_name'])) {
                                            $chemical_recommendations[] = $chemical['chemical_name'];
                                        }
                                    }
                                }
                                // Format 2: Complete response object from get_chemical_recommendations.php
                                else if (isset($decoded['success']) && isset($decoded['recommendations'])) {
                                    foreach ($decoded['recommendations'] as $category => $chemicals) {
                                        foreach ($chemicals as $chemical) {
                                            if (isset($chemical['chemical_name'])) {
                                                $chemical_recommendations[] = $chemical['chemical_name'];
                                            }
                                        }
                                    }
                                }
                                // Format 3: String that might be a chemical name
                                else if (is_string($decoded)) {
                                    $chemical_recommendations[] = $decoded;
                                }
                            } else {
                                // Try to handle it as a plain string if JSON decode failed
                                if (is_string($row['chemical_recommendations'])) {
                                    $chemical_recommendations[] = $row['chemical_recommendations'];
                                }

                                // Add debug information about JSON error (hidden from normal view)
                                echo "<div style='display:none;'>";
                                echo "JSON decode error: " . json_last_error_msg() . "<br>";
                                echo "</div>";
                            }
                        }
                        $chemicals_text = !empty($chemical_recommendations) ? implode(", ", $chemical_recommendations) : 'Not specified';

                        // Determine visit text based on frequency
                        $visit_text = '';
                        if ($row['frequency'] === 'weekly') {
                            $visit_text = "Weekly treatments for one year (52 visits)";
                        } elseif ($row['frequency'] === 'monthly') {
                            $visit_text = "Monthly treatments for one year (12 visits)";
                        } elseif ($row['frequency'] === 'quarterly') {
                            $visit_text = "Quarterly treatments for one year (4 visits)";
                        }

                        echo "<div class='treatment-card status-pending'>";

                        echo "<div class='card-header'>";
                        echo "<h3 class='card-title'>$type_of_work</h3>";
                        echo "<span class='status-badge'>Action Required</span>";
                        echo "</div>";

                        echo "<div class='card-body'>";
                        echo "<div class='treatment-details'>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-map-marker-alt'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Property Address:</span>";
                        echo "<div class='detail-value'>$property_address</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-calendar-alt'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Treatment Date:</span>";
                        echo "<div class='detail-value'>$preferred_date</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-clock'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Treatment Time:</span>";
                        echo "<div class='detail-value'>$preferred_time</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-sync-alt'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Treatment Frequency:</span>";
                        echo "<div class='detail-value'>$frequency</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "</div>";

                        echo "<div class='treatment-schedule'>";
                        echo "<div class='schedule-title'><i class='fas fa-info-circle'></i> Treatment Plan Details</div>";
                        echo "<div class='schedule-text'>$visit_text</div>";
                        echo "</div>";

                        echo "<div class='job-order-details'>";
                        echo "<div class='details-title'><i class='fas fa-clipboard-list'></i> Job Order Information</div>";
                        echo "<div class='details-grid'>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Area:</span>";
                        echo "<span class='detail-value'>$area m²</span>";
                        echo "</div>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Pest Observed:</span>";
                        echo "<span class='detail-value'>$pest_types</span>";
                        echo "</div>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Problem Area:</span>";
                        echo "<span class='detail-value'>$problem_area</span>";
                        echo "</div>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Chemical Recommendation:</span>";
                        echo "<span class='detail-value'>$chemicals_text</span>";
                        echo "</div>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Service Cost:</span>";
                        echo "<span class='detail-value'>PHP $cost</span>";
                        echo "</div>";

                        echo "</div>"; // End details-grid
                        echo "</div>"; // End job-order-details

                        echo "</div>"; // End card-body

                        echo "<div class='card-footer'>";
                        echo "<form method='POST' class='approval-form' id='approval-form-$job_order_id' enctype='multipart/form-data'>";
                        echo "<input type='hidden' name='job_order_id' value='$job_order_id'>";

                        echo "<div class='payment-section'>";
                        echo "<h4><i class='fas fa-money-bill-wave'></i> Payment Information</h4>";
                        echo "<div class='form-group'>";
                        echo "<label for='payment_amount_$job_order_id'>Amount Paid (PHP):</label>";
                        echo "<input type='number' id='payment_amount_$job_order_id' name='payment_amount' class='form-control' min='0' step='0.01' required placeholder='Enter amount paid'>";
                        echo "</div>";

                        echo "<div class='form-group'>";
                        echo "<label for='payment_proof_$job_order_id'>Payment Proof (Receipt/Screenshot):</label>";
                        echo "<input type='file' id='payment_proof_$job_order_id' name='payment_proof' class='form-control' required accept='image/*'>";
                        echo "<small class='form-text'>Upload a photo or screenshot of your payment receipt</small>";
                        echo "</div>";
                        echo "</div>";

                        echo "<div class='action-buttons'>";
                        echo "<button type='button' name='approval_action' value='approve' class='btn btn-approve' onclick='confirmAction(\"approve\", $job_order_id)'><i class='fas fa-check'></i> Approve & Confirm Payment</button>";
                        echo "<button type='button' name='approval_action' value='decline' class='btn btn-decline' onclick='confirmAction(\"decline\", $job_order_id)'><i class='fas fa-times'></i> Decline Plan</button>";
                        echo "</div>";
                        echo "</form>";
                        echo "</div>";

                        echo "</div>";
                    }

                    echo "</div>";
                } else {
                    echo "<div class='empty-state'>";
                    echo "<div class='empty-icon'><i class='fas fa-check-circle'></i></div>";
                    echo "<h3 class='empty-title'>No Pending Approvals</h3>";
                    echo "<p class='empty-text'>No treatment plans require your approval at this time. New plans will appear here when created.</p>";
                    echo "</div>";
                }
                echo "</div>";

                // Fetch approved job orders
                $approved_query = "SELECT jo.job_order_id, jo.type_of_work, jo.preferred_date, jo.preferred_time, jo.frequency,
                                  jo.client_approval_status, jo.client_approval_date, jo.chemical_recommendations, jo.cost,
                                  jo.payment_amount, jo.payment_proof, jo.payment_date,
                                  ar.report_id, ar.area, ar.pest_types, ar.problem_area,
                                  a.location_address as property_address
                                  FROM job_order jo
                                  JOIN assessment_report ar ON jo.report_id = ar.report_id
                                  JOIN appointments a ON ar.appointment_id = a.appointment_id
                                  WHERE a.client_id = ? AND jo.client_approval_status IN ('approved', 'one-time')
                                  GROUP BY ar.report_id, jo.frequency
                                  ORDER BY jo.preferred_date ASC";
                $approved_stmt = $conn->prepare($approved_query);
                $approved_stmt->bind_param("i", $client_id);
                $approved_stmt->execute();
                $approved_result = $approved_stmt->get_result();

                // Approved Treatments Tab
                echo "<div class='tab-content' id='approved-tab'>";
                if ($approved_count > 0) {
                    echo "<div class='treatment-grid'>";

                    while ($row = $approved_result->fetch_assoc()) {
                        $type_of_work = htmlspecialchars($row['type_of_work']);
                        $preferred_date = !empty($row['preferred_date']) ? date('F j, Y', strtotime($row['preferred_date'])) : 'Not specified';
                        $preferred_time = !empty($row['preferred_time']) ? date('g:i A', strtotime($row['preferred_time'])) : 'Not specified';
                        $is_one_time = $row['client_approval_status'] === 'one-time';
                        $frequency = $is_one_time ? 'One-time' : ucfirst(htmlspecialchars($row['frequency']));
                        $property_address = htmlspecialchars($row['property_address']);
                        $approval_date = !empty($row['client_approval_date']) ? date('F j, Y', strtotime($row['client_approval_date'])) : 'Not specified';
                        $area = !empty($row['area']) ? number_format($row['area'], 2) : 'Not specified';
                        $pest_types = !empty($row['pest_types']) ? htmlspecialchars($row['pest_types']) : 'Not specified';
                        $problem_area = !empty($row['problem_area']) ? htmlspecialchars($row['problem_area']) : 'Not specified';
                        $cost = !empty($row['cost']) ? number_format($row['cost'], 2) : 'Not specified';
                        $payment_amount = !empty($row['payment_amount']) ? number_format($row['payment_amount'], 2) : 'Not specified';
                        $payment_proof = !empty($row['payment_proof']) ? $row['payment_proof'] : '';
                        $payment_date = !empty($row['payment_date']) ? date('F j, Y', strtotime($row['payment_date'])) : 'Not specified';

                        // Process chemical recommendations
                        $chemical_recommendations = [];
                        if (!empty($row['chemical_recommendations'])) {
                            $decoded = json_decode($row['chemical_recommendations'], true);
                            if ($decoded && json_last_error() === JSON_ERROR_NONE) {
                                // Format 1: Array of objects from quotation modal
                                if (is_array($decoded) && !isset($decoded['success'])) {
                                    foreach ($decoded as $chemical) {
                                        // Format from assessment_report.php (selected_chemicals)
                                        if (isset($chemical['name'])) {
                                            $chemical_recommendations[] = $chemical['name'];
                                        }
                                        // Format from chemical_recommendations.js
                                        else if (isset($chemical['chemical_name'])) {
                                            $chemical_recommendations[] = $chemical['chemical_name'];
                                        }
                                    }
                                }
                                // Format 2: Complete response object from get_chemical_recommendations.php
                                else if (isset($decoded['success']) && isset($decoded['recommendations'])) {
                                    foreach ($decoded['recommendations'] as $category => $chemicals) {
                                        foreach ($chemicals as $chemical) {
                                            if (isset($chemical['chemical_name'])) {
                                                $chemical_recommendations[] = $chemical['chemical_name'];
                                            }
                                        }
                                    }
                                }
                                // Format 3: String that might be a chemical name
                                else if (is_string($decoded)) {
                                    $chemical_recommendations[] = $decoded;
                                }
                            } else {
                                // Try to handle it as a plain string if JSON decode failed
                                if (is_string($row['chemical_recommendations'])) {
                                    $chemical_recommendations[] = $row['chemical_recommendations'];
                                }
                            }
                        }
                        $chemicals_text = !empty($chemical_recommendations) ? implode(", ", $chemical_recommendations) : 'Not specified';

                        // Determine visit text based on frequency
                        $visit_text = '';
                        if (!$is_one_time) {
                            if ($row['frequency'] === 'weekly') {
                                $visit_text = "Weekly treatments for one year (52 visits)";
                            } elseif ($row['frequency'] === 'monthly') {
                                $visit_text = "Monthly treatments for one year (12 visits)";
                            } elseif ($row['frequency'] === 'quarterly') {
                                $visit_text = "Quarterly treatments for one year (4 visits)";
                            }
                        } else {
                            $visit_text = "One-time treatment only (no recurring visits)";
                        }

                        $card_class = $is_one_time ? 'treatment-card status-one-time' : 'treatment-card status-approved';
                        $status_text = $is_one_time ? 'One-Time Only' : 'Approved';

                        echo "<div class='$card_class'>";

                        echo "<div class='card-header'>";
                        echo "<h3 class='card-title'>$type_of_work</h3>";
                        echo "<span class='status-badge'>$status_text</span>";
                        echo "</div>";

                        echo "<div class='card-body'>";
                        echo "<div class='treatment-details'>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-map-marker-alt'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Location</span>";
                        echo "<div class='detail-value'>$property_address</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-calendar-alt'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Date</span>";
                        echo "<div class='detail-value'>$preferred_date</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-clock'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Time</span>";
                        echo "<div class='detail-value'>$preferred_time</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-sync-alt'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Frequency</span>";
                        echo "<div class='detail-value'>$frequency</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-check-circle'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Approved On</span>";
                        echo "<div class='detail-value'>$approval_date</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "</div>";

                        echo "<div class='treatment-schedule'>";
                        echo "<div class='schedule-title'><i class='fas fa-info-circle'></i> Treatment Schedule</div>";
                        echo "<div class='schedule-text'>$visit_text</div>";
                        echo "</div>";

                        echo "<div class='job-order-details'>";
                        echo "<div class='details-title'><i class='fas fa-clipboard-list'></i> Job Order Information</div>";
                        echo "<div class='details-grid'>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Area:</span>";
                        echo "<span class='detail-value'>$area m²</span>";
                        echo "</div>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Pest Observed:</span>";
                        echo "<span class='detail-value'>$pest_types</span>";
                        echo "</div>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Problem Area:</span>";
                        echo "<span class='detail-value'>$problem_area</span>";
                        echo "</div>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Chemical Recommendation:</span>";
                        echo "<span class='detail-value'>$chemicals_text</span>";
                        echo "</div>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Service Cost:</span>";
                        echo "<span class='detail-value'>PHP $cost</span>";
                        echo "</div>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Payment Amount:</span>";
                        echo "<span class='detail-value'>PHP $payment_amount</span>";
                        echo "</div>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Payment Date:</span>";
                        echo "<span class='detail-value'>$payment_date</span>";
                        echo "</div>";

                        echo "</div>"; // End details-grid

                        if (!empty($payment_proof)) {
                            echo "<div class='payment-proof'>";
                            echo "<h4>Payment Proof</h4>";
                            echo "<div class='proof-image'>";
                            echo "<img src='../uploads/payments/$payment_proof' alt='Payment Proof' style='max-width: 100%; max-height: 200px;'>";
                            echo "</div>";
                            echo "</div>";
                        }

                        echo "</div>"; // End job-order-details

                        echo "</div>"; // End card-body
                        echo "</div>"; // End treatment-card
                    }

                    echo "</div>";
                } else {
                    echo "<div class='empty-state'>";
                    echo "<div class='empty-icon'><i class='fas fa-clipboard-list'></i></div>";
                    echo "<h3 class='empty-title'>No Approved Treatments</h3>";
                    echo "<p class='empty-text'>No approved treatment plans yet. Approved plans will appear here.</p>";
                    echo "</div>";
                }
                echo "</div>";

                // Fetch declined job orders
                $declined_query = "SELECT jo.job_order_id, jo.type_of_work, jo.preferred_date, jo.preferred_time, jo.frequency,
                                  jo.client_approval_date, jo.chemical_recommendations, jo.cost,
                                  ar.report_id, ar.area, ar.pest_types, ar.problem_area,
                                  a.location_address as property_address
                                  FROM job_order jo
                                  JOIN assessment_report ar ON jo.report_id = ar.report_id
                                  JOIN appointments a ON ar.appointment_id = a.appointment_id
                                  WHERE a.client_id = ? AND jo.client_approval_status = 'declined'
                                  GROUP BY ar.report_id, jo.frequency
                                  ORDER BY jo.preferred_date ASC";
                $declined_stmt = $conn->prepare($declined_query);
                $declined_stmt->bind_param("i", $client_id);
                $declined_stmt->execute();
                $declined_result = $declined_stmt->get_result();

                // Declined Treatments Tab
                echo "<div class='tab-content' id='declined-tab'>";
                if ($declined_count > 0) {
                    echo "<div class='treatment-grid'>";

                    while ($row = $declined_result->fetch_assoc()) {
                        $type_of_work = htmlspecialchars($row['type_of_work']);
                        $preferred_date = !empty($row['preferred_date']) ? date('F j, Y', strtotime($row['preferred_date'])) : 'Not specified';
                        $preferred_time = !empty($row['preferred_time']) ? date('g:i A', strtotime($row['preferred_time'])) : 'Not specified';
                        $frequency = ucfirst(htmlspecialchars($row['frequency']));
                        $property_address = htmlspecialchars($row['property_address']);
                        $declined_date = !empty($row['client_approval_date']) ? date('F j, Y', strtotime($row['client_approval_date'])) : 'Not specified';
                        $area = !empty($row['area']) ? number_format($row['area'], 2) : 'Not specified';
                        $pest_types = !empty($row['pest_types']) ? htmlspecialchars($row['pest_types']) : 'Not specified';
                        $problem_area = !empty($row['problem_area']) ? htmlspecialchars($row['problem_area']) : 'Not specified';
                        $cost = !empty($row['cost']) ? number_format($row['cost'], 2) : 'Not specified';

                        // Process chemical recommendations
                        $chemical_recommendations = [];
                        if (!empty($row['chemical_recommendations'])) {
                            $decoded = json_decode($row['chemical_recommendations'], true);
                            if ($decoded && json_last_error() === JSON_ERROR_NONE) {
                                // Format 1: Array of objects from quotation modal
                                if (is_array($decoded) && !isset($decoded['success'])) {
                                    foreach ($decoded as $chemical) {
                                        // Format from assessment_report.php (selected_chemicals)
                                        if (isset($chemical['name'])) {
                                            $chemical_recommendations[] = $chemical['name'];
                                        }
                                        // Format from chemical_recommendations.js
                                        else if (isset($chemical['chemical_name'])) {
                                            $chemical_recommendations[] = $chemical['chemical_name'];
                                        }
                                    }
                                }
                                // Format 2: Complete response object from get_chemical_recommendations.php
                                else if (isset($decoded['success']) && isset($decoded['recommendations'])) {
                                    foreach ($decoded['recommendations'] as $category => $chemicals) {
                                        foreach ($chemicals as $chemical) {
                                            if (isset($chemical['chemical_name'])) {
                                                $chemical_recommendations[] = $chemical['chemical_name'];
                                            }
                                        }
                                    }
                                }
                                // Format 3: String that might be a chemical name
                                else if (is_string($decoded)) {
                                    $chemical_recommendations[] = $decoded;
                                }
                            } else {
                                // Try to handle it as a plain string if JSON decode failed
                                if (is_string($row['chemical_recommendations'])) {
                                    $chemical_recommendations[] = $row['chemical_recommendations'];
                                }
                            }
                        }
                        $chemicals_text = !empty($chemical_recommendations) ? implode(", ", $chemical_recommendations) : 'Not specified';

                        // Determine visit text based on frequency
                        $visit_text = '';
                        if ($row['frequency'] === 'weekly') {
                            $visit_text = "Weekly treatments for one year (52 visits)";
                        } elseif ($row['frequency'] === 'monthly') {
                            $visit_text = "Monthly treatments for one year (12 visits)";
                        } elseif ($row['frequency'] === 'quarterly') {
                            $visit_text = "Quarterly treatments for one year (4 visits)";
                        }

                        echo "<div class='treatment-card status-declined'>";

                        echo "<div class='card-header'>";
                        echo "<h3 class='card-title'>$type_of_work</h3>";
                        echo "<span class='status-badge'>Declined</span>";
                        echo "</div>";

                        echo "<div class='card-body'>";
                        echo "<div class='treatment-details'>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-map-marker-alt'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Location</span>";
                        echo "<div class='detail-value'>$property_address</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-calendar-alt'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Date</span>";
                        echo "<div class='detail-value'>$preferred_date</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-clock'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Time</span>";
                        echo "<div class='detail-value'>$preferred_time</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-sync-alt'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Frequency</span>";
                        echo "<div class='detail-value'>$frequency</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "<div class='detail-item'>";
                        echo "<div class='detail-icon'><i class='fas fa-times-circle'></i></div>";
                        echo "<div class='detail-content'>";
                        echo "<span class='detail-label'>Declined On</span>";
                        echo "<div class='detail-value'>$declined_date</div>";
                        echo "</div>";
                        echo "</div>";

                        echo "</div>";

                        echo "<div class='treatment-schedule'>";
                        echo "<div class='schedule-title'><i class='fas fa-info-circle'></i> Next Steps</div>";
                        echo "<div class='schedule-text'>An administrator will contact you to discuss alternatives or make adjustments to better meet your needs.</div>";
                        echo "</div>";

                        echo "<div class='job-order-details'>";
                        echo "<div class='details-title'><i class='fas fa-clipboard-list'></i> Job Order Information</div>";
                        echo "<div class='details-grid'>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Area:</span>";
                        echo "<span class='detail-value'>$area m²</span>";
                        echo "</div>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Pest Observed:</span>";
                        echo "<span class='detail-value'>$pest_types</span>";
                        echo "</div>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Problem Area:</span>";
                        echo "<span class='detail-value'>$problem_area</span>";
                        echo "</div>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Chemical Recommendation:</span>";
                        echo "<span class='detail-value'>$chemicals_text</span>";
                        echo "</div>";

                        echo "<div class='detail-row'>";
                        echo "<span class='detail-label'>Service Cost:</span>";
                        echo "<span class='detail-value'>PHP $cost</span>";
                        echo "</div>";

                        echo "</div>"; // End details-grid
                        echo "</div>"; // End job-order-details

                        echo "</div>"; // End card-body
                        echo "</div>"; // End treatment-card
                    }

                    echo "</div>";
                } else {
                    echo "<div class='empty-state'>";
                    echo "<div class='empty-icon'><i class='fas fa-clipboard-check'></i></div>";
                    echo "<h3 class='empty-title'>No Declined Treatments</h3>";
                    echo "<p class='empty-text'>You haven't declined any treatment plans.</p>";
                    echo "</div>";
                }
                echo "</div>";
                ?>
                </div>
            </div>
        </main>

        <script src="js/notifications.js"></script>

        <script>
            // Tab functionality
            document.addEventListener('DOMContentLoaded', function() {
                const tabs = document.querySelectorAll('.contract-tab');
                const tabContents = document.querySelectorAll('.tab-content');

                tabs.forEach(tab => {
                    tab.addEventListener('click', function() {
                        // Remove active class from all tabs
                        tabs.forEach(t => t.classList.remove('active'));

                        // Add active class to clicked tab
                        this.classList.add('active');

                        // Hide all tab contents
                        tabContents.forEach(content => content.classList.remove('active'));

                        // Show the corresponding tab content
                        const tabId = this.getAttribute('data-tab');
                        document.getElementById(tabId + '-tab').classList.add('active');
                    });
                });
            });
        </script>

        <style>
            /* Override main content padding to reduce empty space */
            .main-content {
                padding: 1rem;
                padding-top: calc(var(--header-height) + 1rem);
            }

            /* Header and Container Styles */
            .contract-header {
                margin-bottom: 1rem;
                border-bottom: 1px solid #eee;
                padding-bottom: 0.75rem;
            }

            .contract-header h1 {
                font-size: 1.5rem;
                color: var(--primary-color);
                margin-bottom: 0.25rem;
            }

            .contract-header p {
                color: #6c757d;
                font-size: 0.9rem;
                margin-bottom: 0;
            }

            .contract-container {
                width: 100%;
                margin: 0 auto;
                padding: 0;
            }

            /* Tab Navigation */
            .contract-tabs {
                display: flex;
                margin-bottom: 1rem;
                border-bottom: 1px solid #dee2e6;
                background-color: transparent;
            }

            .contract-tab {
                flex: 1;
                text-align: center;
                padding: 0.75rem 1rem;
                cursor: pointer;
                font-weight: 600;
                transition: all 0.2s ease;
                position: relative;
                border: none;
                background: transparent;
                color: #6c757d;
                border-bottom: 3px solid transparent;
                margin-bottom: -1px;
                font-size: 0.9rem;
            }

            .contract-tab.active {
                color: var(--primary-color);
                border-bottom: 3px solid var(--primary-color);
            }

            .contract-tab:hover:not(.active) {
                color: #495057;
                border-bottom: 3px solid #dee2e6;
            }

            .contract-tab i {
                margin-right: 0.5rem;
                font-size: 1rem;
            }

            .contract-tab .badge {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                margin-left: 0.5rem;
                background-color: var(--primary-color);
                color: white;
                border-radius: 50%;
                width: 22px;
                height: 22px;
                font-size: 0.75rem;
                font-weight: 700;
            }

            /* Tab Content */
            .tab-content {
                display: none;
            }

            .tab-content.active {
                display: block;
                animation: fadeIn 0.5s ease;
            }

            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }

            /* Treatment Cards */
            .treatment-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
                gap: 1rem;
                margin-top: 1rem;
            }

            .treatment-card {
                background-color: #fff;
                border-radius: 6px;
                overflow: hidden;
                box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
                transition: all 0.2s ease;
                position: relative;
                display: flex;
                flex-direction: column;
                height: 100%;
                border: 1px solid #e9ecef;
            }

            .treatment-card:hover {
                transform: translateY(-3px);
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            }

            /* Status indicators */
            .status-pending {
                border-left: 4px solid var(--warning-color);
            }

            .status-approved {
                border-left: 4px solid var(--success-color);
            }

            .status-declined {
                border-left: 4px solid var(--error-color);
            }

            .status-one-time {
                border-left: 4px solid var(--primary-color);
            }

            .card-header {
                padding: 0.75rem 1rem 0.5rem;
                position: relative;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 1px solid #f1f1f1;
            }

            .card-title {
                font-size: 1.1rem;
                font-weight: 600;
                margin-bottom: 0;
                color: #333;
            }

            .status-badge {
                display: inline-block;
                padding: 0.25rem 0.75rem;
                border-radius: 4px;
                font-size: 0.7rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .status-pending .status-badge {
                background-color: var(--warning-color);
                color: white;
            }

            .status-approved .status-badge {
                background-color: var(--success-color);
                color: white;
            }

            .status-declined .status-badge {
                background-color: var(--error-color);
                color: white;
            }

            .status-one-time .status-badge {
                background-color: var(--primary-color);
                color: white;
            }

            .card-body {
                padding: 0.75rem 1rem;
                flex: 1;
            }

            .treatment-details {
                margin-bottom: 1rem;
            }

            .detail-item {
                display: flex;
                margin-bottom: 0.75rem;
                align-items: center;
                border-bottom: 1px dashed #f1f1f1;
                padding-bottom: 0.75rem;
            }

            .detail-item:last-child {
                margin-bottom: 0;
                border-bottom: none;
                padding-bottom: 0;
            }

            .detail-icon {
                flex-shrink: 0;
                width: 28px;
                height: 28px;
                border-radius: 4px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 0.75rem;
                font-size: 0.9rem;
                background-color: #f8f9fa;
                color: #6c757d;
            }

            .status-pending .detail-icon {
                color: var(--warning-color);
            }

            .status-approved .detail-icon {
                color: var(--success-color);
            }

            .status-declined .detail-icon {
                color: var(--error-color);
            }

            .status-one-time .detail-icon {
                color: var(--primary-color);
            }

            .detail-content {
                flex: 1;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .detail-label {
                font-size: 0.85rem;
                color: #6c757d;
                margin-bottom: 0;
                font-weight: 500;
            }

            .detail-value {
                font-weight: 600;
                color: #333;
                font-size: 0.9rem;
                text-align: right;
            }

            .treatment-schedule {
                background-color: #f8f9fa;
                border-radius: 4px;
                padding: 0.5rem 0.75rem;
                margin-bottom: 0.75rem;
                border-left: 3px solid var(--primary-color);
            }

            .status-pending .treatment-schedule {
                border-left-color: var(--warning-color);
            }

            .status-approved .treatment-schedule {
                border-left-color: var(--success-color);
            }

            .status-declined .treatment-schedule {
                border-left-color: var(--error-color);
            }

            .status-one-time .treatment-schedule {
                border-left-color: var(--primary-color);
            }

            .schedule-title {
                display: flex;
                align-items: center;
                margin-bottom: 0.5rem;
                font-weight: 600;
                color: #495057;
                font-size: 0.85rem;
            }

            .schedule-title i {
                margin-right: 0.5rem;
                color: inherit;
            }

            .schedule-text {
                font-size: 0.8rem;
                color: #6c757d;
                line-height: 1.5;
            }

            /* Job Order Details Styles */
            .job-order-details {
                background-color: #f8f9fa;
                border-radius: 4px;
                padding: 0.5rem 0.75rem;
                margin-top: 0.75rem;
                border-left: 3px solid var(--primary-color);
            }

            .status-pending .job-order-details {
                border-left-color: var(--warning-color);
            }

            .status-approved .job-order-details {
                border-left-color: var(--success-color);
            }

            .status-declined .job-order-details {
                border-left-color: var(--error-color);
            }

            .status-one-time .job-order-details {
                border-left-color: var(--primary-color);
            }

            .details-title {
                display: flex;
                align-items: center;
                margin-bottom: 0.5rem;
                font-weight: 600;
                color: #495057;
                font-size: 0.85rem;
            }

            .details-title i {
                margin-right: 0.5rem;
                color: inherit;
            }

            .details-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 0.25rem;
            }

            .detail-row {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                font-size: 0.8rem;
                padding: 0.25rem 0;
                border-bottom: 1px dashed rgba(0, 0, 0, 0.05);
            }

            .detail-row:last-child {
                border-bottom: none;
            }

            .detail-row .detail-label {
                font-weight: 600;
                color: #495057;
                flex: 0 0 40%;
                font-size: 0.8rem;
            }

            .detail-row .detail-value {
                color: #6c757d;
                flex: 0 0 60%;
                text-align: right;
                word-break: break-word;
                font-size: 0.8rem;
                font-weight: normal;
            }

            .card-footer {
                padding: 0.75rem 1rem;
                background-color: #f8f9fa;
                border-top: 1px solid #eee;
            }

            /* Action Buttons */
            .action-buttons {
                display: flex;
                gap: 0.5rem;
            }

            .btn {
                flex: 1;
                padding: 0.6rem 0.75rem;
                border-radius: 4px;
                font-weight: 500;
                font-size: 0.85rem;
                text-align: center;
                cursor: pointer;
                transition: all 0.2s ease;
                border: 1px solid transparent;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
            }

            .btn i {
                font-size: 0.8rem;
            }

            .btn-approve {
                background-color: white;
                color: var(--success-color);
                border-color: var(--success-color);
            }

            .btn-approve:hover {
                background-color: var(--success-color);
                color: white;
            }

            .btn-one-time {
                background-color: white;
                color: var(--primary-color);
                border-color: var(--primary-color);
            }

            .btn-one-time:hover {
                background-color: var(--primary-color);
                color: white;
            }

            .btn-decline {
                background-color: white;
                color: var(--error-color);
                border-color: var(--error-color);
            }

            .btn-decline:hover {
                background-color: var(--error-color);
                color: white;
            }

            /* Empty State */
            .empty-state {
                text-align: center;
                padding: 1.5rem 1rem;
                background-color: #fff;
                border-radius: 8px;
                border: 1px dashed #dee2e6;
                margin: 1rem 0;
                max-width: 600px;
                margin-left: auto;
                margin-right: auto;
            }

            .empty-icon {
                width: 50px;
                height: 50px;
                border-radius: 50%;
                background-color: #f8f9fa;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 0.75rem;
                font-size: 1.25rem;
                color: #adb5bd;
            }

            .status-pending .empty-icon {
                color: var(--warning-color);
            }

            .status-approved .empty-icon {
                color: var(--success-color);
            }

            .status-declined .empty-icon {
                color: var(--error-color);
            }

            .empty-title {
                font-size: 1rem;
                font-weight: 600;
                margin-bottom: 0.25rem;
                color: #495057;
            }

            .empty-text {
                color: #6c757d;
                max-width: 400px;
                margin: 0 auto;
                font-size: 0.85rem;
                line-height: 1.4;
            }

            /* Alerts */
            .alert {
                padding: 0.5rem 0.75rem;
                border-radius: 4px;
                margin-bottom: 0.75rem;
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 0.85rem;
            }

            .alert i {
                font-size: 1rem;
            }

            .alert-success {
                background-color: #d4edda;
                border-left: 3px solid var(--success-color);
                color: #155724;
            }

            .alert-danger {
                background-color: #f8d7da;
                border-left: 3px solid var(--error-color);
                color: #721c24;
            }

            .alert-info {
                background-color: #d1ecf1;
                border-left: 3px solid var(--primary-color);
                color: #0c5460;
            }

            /* Responsive Adjustments */
            @media (max-width: 1200px) {
                .treatment-grid {
                    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
                }
            }

            @media (max-width: 991px) {
                .treatment-grid {
                    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                }

                .contract-header h1 {
                    font-size: 1.4rem;
                }
            }

            @media (max-width: 767px) {
                .main-content {
                    padding: 0.75rem;
                    padding-top: calc(var(--header-height) + 0.75rem);
                }

                .contract-header {
                    margin-bottom: 0.75rem;
                }

                .contract-tabs {
                    flex-wrap: wrap;
                    border-bottom: none;
                    margin-bottom: 0.75rem;
                }

                .contract-tab {
                    flex: 1 0 33.333%;
                    padding: 0.5rem 0.25rem;
                    border-bottom: 1px solid #dee2e6;
                    margin-bottom: 0;
                    font-size: 0.8rem;
                }

                .contract-tab.active {
                    border-bottom: 3px solid var(--primary-color);
                    margin-bottom: -1px;
                }

                .treatment-grid {
                    grid-template-columns: 1fr;
                    gap: 0.75rem;
                    margin-top: 0.75rem;
                }

                .action-buttons {
                    flex-direction: row;
                    gap: 0.5rem;
                }

                .card-header, .card-body, .card-footer {
                    padding: 0.5rem 0.75rem;
                }

                .detail-content {
                    flex-direction: row;
                    align-items: center;
                    justify-content: space-between;
                }

                .detail-item {
                    margin-bottom: 0.5rem;
                    padding-bottom: 0.5rem;
                }
            }

            @media (max-width: 575px) {
                .main-content {
                    padding: 0.5rem;
                    padding-top: calc(var(--header-height) + 0.5rem);
                }

                .contract-header {
                    margin-bottom: 0.5rem;
                    padding-bottom: 0.5rem;
                }

                .contract-header h1 {
                    font-size: 1.2rem;
                    margin-bottom: 0.15rem;
                }

                .contract-header p {
                    font-size: 0.8rem;
                }

                .contract-tab {
                    font-size: 0.75rem;
                    padding: 0.4rem 0.2rem;
                }

                .contract-tab i {
                    margin-right: 0.15rem;
                    font-size: 0.9rem;
                }

                .contract-tab .badge {
                    width: 18px;
                    height: 18px;
                    font-size: 0.7rem;
                    margin-left: 0.25rem;
                }

                .card-title {
                    font-size: 0.95rem;
                }

                .detail-icon {
                    width: 22px;
                    height: 22px;
                    font-size: 0.75rem;
                    margin-right: 0.5rem;
                }

                .detail-label {
                    font-size: 0.75rem;
                }

                .detail-value {
                    font-size: 0.8rem;
                }

                .treatment-schedule {
                    padding: 0.4rem 0.6rem;
                    margin-bottom: 0.5rem;
                }

                .schedule-title {
                    font-size: 0.75rem;
                    margin-bottom: 0.25rem;
                }

                .schedule-text {
                    font-size: 0.7rem;
                }

                .btn {
                    padding: 0.4rem 0.5rem;
                    font-size: 0.75rem;
                }

                .btn i {
                    font-size: 0.7rem;
                }

                .empty-state {
                    padding: 1rem 0.75rem;
                }

                .empty-icon {
                    width: 40px;
                    height: 40px;
                    font-size: 1rem;
                    margin-bottom: 0.5rem;
                }

                .empty-title {
                    font-size: 0.9rem;
                }

                .empty-text {
                    font-size: 0.75rem;
                }
            }
        </style>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/main.js"></script>
    <script src="js/sidebar.js"></script>
    <!-- Fixed sidebar script -->
    <script src="js/sidebar-fix.js"></script>
    <!-- Notification Scripts -->
    <script src="js/notifications.js"></script>

    <!-- Debug script for notifications -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Manual initialization for notification dropdown
            const notificationIcon = document.querySelector('.notification-icon');
            const notificationDropdown = document.querySelector('.notification-dropdown');

            if (notificationIcon && notificationDropdown) {
                notificationIcon.addEventListener('click', function(e) {
                    e.stopPropagation();
                    notificationDropdown.classList.toggle('show');
                    console.log('Notification icon clicked, dropdown toggled');
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!notificationDropdown.contains(e.target) && !notificationIcon.contains(e.target)) {
                        notificationDropdown.classList.remove('show');
                    }
                });
            } else {
                console.error('Notification elements not found:', {
                    icon: notificationIcon,
                    dropdown: notificationDropdown
                });
            }
        });
    </script>

    <script>
        // Function to confirm action before form submission
        function confirmAction(action, jobOrderId) {
            let message = '';

            if (action === 'approve') {
                message = 'Are you sure you want to approve this recurring treatment plan?';
            } else if (action === 'one-time') {
                message = 'Are you sure you want to approve only a one-time treatment? All recurring appointments will be cancelled.';
            } else if (action === 'decline') {
                message = 'Are you sure you want to decline this treatment plan?';
            }

            if (confirm(message)) {
                try {
                    // Show loading overlay
                    const loadingOverlay = document.createElement('div');
                    loadingOverlay.className = 'loading-overlay';
                    loadingOverlay.id = 'loadingOverlay';
                    loadingOverlay.innerHTML = `
                        <div class="loading-spinner">
                            <i class="fas fa-spinner fa-spin fa-3x"></i>
                            <p>Processing your request...</p>
                            <p class="small-text">This may take a few moments. Please do not refresh the page.</p>
                        </div>
                    `;
                    document.body.appendChild(loadingOverlay);

                    // Disable the buttons to prevent double submission
                    const form = document.getElementById('approval-form-' + jobOrderId);
                    const buttons = form.querySelectorAll('button');

                    buttons.forEach(button => {
                        button.disabled = true;
                    });

                    // Use AJAX to submit the form instead of normal form submission
                    const formData = new FormData(form);

                    // Add the action value to the form data
                    formData.append('approval_action', action);

                    // Log the form data for debugging
                    console.log('Submitting form data for job order ID: ' + jobOrderId);
                    for (let pair of formData.entries()) {
                        console.log(pair[0] + ': ' + pair[1]);
                    }

                    // Submit the form using fetch API
                    fetch('contract.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        console.log('Response received');
                        // Reload the page to show the updated status
                        window.location.reload();
                    })
                    .catch(error => {
                        console.error('Error in form submission:', error);
                        alert('An error occurred while processing your request. Please try again.');

                        // Re-enable buttons and remove loading overlay
                        buttons.forEach(button => {
                            button.disabled = false;
                        });

                        const overlay = document.getElementById('loadingOverlay');
                        if (overlay) {
                            document.body.removeChild(overlay);
                        }
                    });

                    // Prevent the default form submission
                    return false;
                } catch (error) {
                    console.error('Error in form submission:', error);
                    alert('An error occurred while processing your request. Please try again.');
                    return false;
                }
            }

            return false;
        }

        // Tab switching functionality
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Contract page loaded');

            const tabs = document.querySelectorAll('.contract-tab');
            const tabContents = document.querySelectorAll('.tab-content');

            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');

                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));

                    // Add active class to clicked tab and corresponding content
                    this.classList.add('active');
                    document.getElementById(tabId + '-tab').classList.add('active');
                });
            });

            // Debug logging for sidebar
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.getElementById('menuToggle');

            if (!sidebar) {
                console.error('Sidebar element not found in contract.php');
            } else {
                console.log('Sidebar element found in contract.php');
            }

            if (!menuToggle) {
                console.error('Menu toggle element not found in contract.php');
            } else {
                console.log('Menu toggle element found in contract.php');
            }

            // Add event listeners to all approval forms
            const approvalForms = document.querySelectorAll('.approval-form');
            approvalForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    console.log('Form submitted:', this.id);
                });
            });
        });
    </script>

    <!-- Notification Scripts -->
    <script src="js/notifications.js"></script>
    <script>
        // Ensure notification dropdown works and initialize notifications
        $(document).ready(function() {
            // Initialize notifications
            if (typeof initNotifications === 'function') {
                initNotifications();
            } else {
                console.error("initNotifications function not found");
                
                // Fallback notification handling if initNotifications is not available
                $('.notification-container').on('click', function(e) {
                    e.stopPropagation();
                    $('.notification-dropdown').toggleClass('show');
                    console.log('Notification icon clicked');
                });

                $(document).on('click', function(e) {
                    if (!$(e.target).closest('.notification-container').length) {
                        $('.notification-dropdown').removeClass('show');
                    }
                });
                
                // Fetch notifications immediately
                if (typeof fetchNotifications === 'function') {
                    fetchNotifications();
                    
                    // Set up periodic notification checks
                    setInterval(fetchNotifications, 60000); // Check every minute
                }
            }
        });
    </script>
</body>
</html>