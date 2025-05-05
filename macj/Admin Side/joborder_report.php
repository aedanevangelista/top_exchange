<?php
session_start();
require_once '../db_connect.php';
require_once '../notification_functions.php';

// Get Dashboard Metrics
try {
    // Total Job Order Reports
    $stmt = $conn->query("SELECT COUNT(*) AS total FROM job_order_report");
    $total_reports = $stmt->fetch_assoc()['total'];

    // Set payment metrics to 0 since payment field has been removed
    $total_payment = 0;
    $avg_payment = 0;

    // Reports with Attachments
    $stmt = $conn->query("SELECT COUNT(*) AS with_attachments FROM job_order_report WHERE attachments IS NOT NULL AND attachments != ''");
    $with_attachments = $stmt->fetch_assoc()['with_attachments'];

    // Reports with Client Feedback
    $stmt = $conn->query("SELECT COUNT(*) AS with_feedback FROM job_order_report jor
                         JOIN job_order jo ON jor.job_order_id = jo.job_order_id
                         JOIN joborder_feedback jf ON jo.job_order_id = jf.job_order_id");
    $with_feedback = $stmt->fetch_assoc()['with_feedback'];

    // Average Rating
    $stmt = $conn->query("SELECT AVG(rating) AS avg_rating FROM joborder_feedback");
    $avg_rating_result = $stmt->fetch_assoc();
    $avg_rating = $avg_rating_result['avg_rating'] ? round($avg_rating_result['avg_rating'], 1) : 0;

} catch (Exception $e) {
    // Handle any errors
    $total_reports = 0;
    $total_payment = 0;
    $avg_payment = 0;
    $with_attachments = 0;
    $with_feedback = 0;
    $avg_rating = 0;
}

// Get filter parameters
$technician_filter = isset($_GET['technician_id']) ? intval($_GET['technician_id']) : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Check if recommendation column exists in job_order_report table
$checkRecommendationColumn = $conn->query("SHOW COLUMNS FROM job_order_report LIKE 'recommendation'");
$recommendationColumnExists = $checkRecommendationColumn->num_rows > 0;

// Check if chemical_usage column exists in job_order_report table
$checkChemicalUsageColumn = $conn->query("SHOW COLUMNS FROM job_order_report LIKE 'chemical_usage'");
$chemicalUsageColumnExists = $checkChemicalUsageColumn->num_rows > 0;

// Build the SELECT part of the query based on column existence
$select_fields = "
    jor.report_id,
    jor.job_order_id,
    jor.technician_id,
    jor.observation_notes,";

// Add recommendation field if it exists
if ($recommendationColumnExists) {
    $select_fields .= "
    jor.recommendation,";
} else {
    $select_fields .= "
    '' AS recommendation,";
}

$select_fields .= "
    jor.attachments,";

// Add chemical_usage field if it exists
if ($chemicalUsageColumnExists) {
    $select_fields .= "
    jor.chemical_usage,";
} else {
    $select_fields .= "
    NULL AS chemical_usage,";
}

$select_fields .= "
    jor.created_at,
    t.username AS technician_name,
    jo.type_of_work,
    jo.preferred_date,
    jo.preferred_time,
    jo.chemical_recommendations,
    a.client_name,
    a.location_address,
    a.kind_of_place,
    jf.feedback_id,
    jf.rating,
    jf.comments AS feedback_comments,
    jf.created_at AS feedback_date,
    jf.technician_arrived,
    jf.job_completed,
    jf.verification_notes,
    a.client_name AS feedback_client_name";

// Fetch job order reports with filters
$report_query = "SELECT $select_fields
    FROM job_order_report jor
    JOIN technicians t ON jor.technician_id = t.technician_id
    JOIN job_order jo ON jor.job_order_id = jo.job_order_id
    JOIN assessment_report ar ON jo.report_id = ar.report_id
    JOIN appointments a ON ar.appointment_id = a.appointment_id
    LEFT JOIN joborder_feedback jf ON jo.job_order_id = jf.job_order_id
    WHERE 1=1";

// Add filters if provided
if ($technician_filter > 0) {
    $report_query .= " AND jor.technician_id = $technician_filter";
}

if (!empty($date_from)) {
    $report_query .= " AND DATE(jor.created_at) >= '$date_from'";
}

if (!empty($date_to)) {
    $report_query .= " AND DATE(jor.created_at) <= '$date_to'";
}

$report_query .= " ORDER BY jor.created_at DESC";
$report_result = $conn->query($report_query);

// Fetch technicians for dropdown
$tech_query = "SELECT technician_id, username FROM technicians ORDER BY username ASC";
$tech_result = $conn->query($tech_query);
$technicians = [];
while ($tech = $tech_result->fetch_assoc()) {
    $technicians[] = $tech;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Order Report - MacJ Pest Control</title>
    <link rel="stylesheet" href="css/joborder-report-page.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/notifications.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

        /* Chemical Usage Table Styles */
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
        }

        .table-bordered {
            border: 1px solid #dee2e6;
        }

        .table th, .table td {
            padding: 0.75rem;
            vertical-align: top;
            border: 1px solid #dee2e6;
        }

        .table thead th {
            vertical-align: bottom;
            border-bottom: 2px solid #dee2e6;
            background-color: #f8f9fa;
        }

        .table-responsive {
            display: block;
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .text-success {
            color: #28a745;
        }

        .text-warning {
            color: #ffc107;
        }

        .text-danger {
            color: #dc3545;
        }

        .mt-3 {
            margin-top: 1rem;
        }

        .badge {
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
        }

        .bg-success {
            background-color: #28a745;
            color: white;
        }

        .text-warning {
            color: #ffc107;
        }

        .text-muted {
            color: #6c757d;
        }

        /* Client Feedback Styles */
        .feedback-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #e9ecef;
        }

        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }

        .feedback-client {
            display: flex;
            flex-direction: column;
        }

        .feedback-date {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }

        .feedback-rating {
            display: flex;
            align-items: center;
        }

        .star-filled {
            color: #ffc107;
        }

        .star-empty {
            color: #e9ecef;
        }

        .rating-text {
            margin-left: 10px;
            font-weight: bold;
        }

        .feedback-verification {
            background-color: #fff;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #e9ecef;
        }

        .feedback-verification h4 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1.1rem;
            color: #495057;
        }

        .verification-items {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .verification-item {
            flex: 1;
            min-width: 200px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }

        .verification-label {
            font-weight: 600;
            display: block;
            margin-bottom: 5px;
        }

        .verification-notes {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #e9ecef;
        }

        .verification-notes h5 {
            margin-top: 0;
            font-size: 0.95rem;
            color: #495057;
        }

        .feedback-comments {
            background-color: #fff;
            border-radius: 6px;
            padding: 15px;
            border: 1px solid #e9ecef;
        }

        .feedback-comments h4 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1.1rem;
            color: #495057;
        }

        .comment-box {
            background-color: #f8f9fa;
            border-radius: 4px;
            padding: 10px;
            border-left: 3px solid #6c757d;
        }

        .comment-box p {
            margin: 0;
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
                    <li><a href="assessment_report.php"><i class="fas fa-clipboard-check"></i> Assessment Report</a></li>
                    <li class="active"><a href="joborder_report.php"><i class="fas fa-tasks"></i> Job Order Report</a></li>
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

            <div class="chemicals-content">
                <div class="chemicals-header">
                    <h1>Job Order Reports</h1>
                </div>

                <!-- Job Order Report Summary -->
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
                        <div class="summary-icon" style="background-color: var(--info-color);">
                            <i class="fas fa-paperclip"></i>
                        </div>
                        <div class="summary-info">
                            <h3>With Attachments</h3>
                            <p><?= $with_attachments ?></p>
                        </div>
                    </div>

                    <?php if ($chemicalUsageColumnExists): ?>
                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--success-color);">
                            <i class="fas fa-flask"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Chemical Usage</h3>
                            <p>
                                <?php
                                // Count reports with chemical usage
                                $stmt = $conn->query("SELECT COUNT(*) AS with_chemicals FROM job_order_report WHERE chemical_usage IS NOT NULL AND chemical_usage != ''");
                                $with_chemicals = $stmt->fetch_assoc()['with_chemicals'];
                                echo $with_chemicals;
                                ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--warning-color);">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Client Feedback</h3>
                            <p><?= $with_feedback ?> <small class="text-muted">(<?= $avg_rating ?>/5 <i class="fas fa-star" style="color: #ffc107;"></i>)</small></p>
                        </div>
                    </div>
                </div>

                <!-- Filter Controls -->
                <div class="filter-container">
                    <form id="filterForm" method="GET" style="display: flex; flex-wrap: wrap; gap: 15px; width: 100%;">
                        <div class="filter-group">
                            <label for="technician_id">Technician:</label>
                            <select name="technician_id" id="technician_id" onchange="this.form.submit()">
                                <option value="">All Technicians</option>
                                <?php foreach ($technicians as $tech): ?>
                                    <option value="<?= $tech['technician_id'] ?>" <?= ($technician_filter == $tech['technician_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tech['username']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="date_from">Date From:</label>
                            <input type="date" name="date_from" id="date_from" value="<?= $date_from ?>" onchange="this.form.submit()">
                        </div>
                        <div class="filter-group">
                            <label for="date_to">Date To:</label>
                            <input type="date" name="date_to" id="date_to" value="<?= $date_to ?>" onchange="this.form.submit()">
                        </div>
                        <div class="filter-group">
                            <a href="joborder_report.php" class="btn btn-secondary" style="margin-top: 24px;">
                                <i class="fas fa-sync-alt"></i> Reset Filters
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Filter Status Message -->
                <?php if ($technician_filter > 0 || !empty($date_from) || !empty($date_to)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-filter"></i>
                    <div>
                        <strong>Filtered Results:</strong>
                        <?php if ($technician_filter > 0):
                            $tech_name = '';
                            foreach ($technicians as $tech) {
                                if ($tech['technician_id'] == $technician_filter) {
                                    $tech_name = $tech['username'];
                                    break;
                                }
                            }
                        ?>
                            Technician: <strong><?= htmlspecialchars($tech_name) ?></strong>
                        <?php endif; ?>
                        <?php if (!empty($date_from)): ?>
                            From: <strong><?= date('M d, Y', strtotime($date_from)) ?></strong>
                        <?php endif; ?>
                        <?php if (!empty($date_to)): ?>
                            To: <strong><?= date('M d, Y', strtotime($date_to)) ?></strong>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Job Order Reports List -->
                <?php if ($report_result && $report_result->num_rows > 0): ?>
                <div class="reports-container">
                    <?php while ($report = $report_result->fetch_assoc()): ?>
                    <div class="report-card">
                        <div class="report-header" onclick="toggleReportDetails(this)">
                            <div class="report-info">
                                <h3><?= htmlspecialchars($report['client_name']) ?></h3>
                                <div class="report-meta">
                                    <div class="report-location">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?= htmlspecialchars($report['location_address']) ?>
                                    </div>
                                    <div class="report-time">
                                        <span><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($report['preferred_date'])) ?></span>
                                        <span><i class="fas fa-clock"></i> <?= date('h:i A', strtotime($report['preferred_time'])) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="job-order-container">
                                <div class="job-order-status">
                                    <div class="job-order-badge">
                                        <i class="fas fa-check-circle"></i> Completed
                                    </div>
                                    <div class="job-order-details">
                                        <div class="job-order-detail">
                                            <span class="detail-label"><i class="fas fa-user"></i> Technician</span>
                                            <span class="detail-value"><?= htmlspecialchars($report['technician_name']) ?></span>
                                        </div>
                                        <div class="job-order-detail">
                                            <span class="detail-label"><i class="fas fa-tools"></i> Type</span>
                                            <span class="detail-value"><?= htmlspecialchars($report['type_of_work']) ?></span>
                                        </div>

                                        <div class="job-order-detail">
                                            <span class="detail-label"><i class="fas fa-calendar-check"></i> Completed</span>
                                            <span class="detail-value"><?= date('M d, Y', strtotime($report['created_at'])) ?></span>
                                        </div>

                                        <?php if (!empty($report['feedback_id'])): ?>
                                        <div class="job-order-detail">
                                            <span class="detail-label"><i class="fas fa-star"></i> Client Feedback</span>
                                            <span class="detail-value">
                                                <span class="badge bg-success">
                                                    <?= $report['rating'] ?>/5 <i class="fas fa-star text-warning"></i>
                                                </span>
                                            </span>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ($chemicalUsageColumnExists && !empty($report['chemical_usage'])):
                                            $chemicals = json_decode($report['chemical_usage'], true);
                                            if ($chemicals && is_array($chemicals)):
                                                $totalChemicals = count($chemicals);
                                                $optimalCount = 0;

                                                foreach ($chemicals as $chemical) {
                                                    $recommended = isset($chemical['recommended_dosage']) ? floatval($chemical['recommended_dosage']) : 0;
                                                    $actual = isset($chemical['dosage']) ? floatval($chemical['dosage']) : 0;
                                                    $minAcceptable = $recommended * 0.8;
                                                    $maxAcceptable = $recommended * 1.2;

                                                    if ($actual >= $minAcceptable && $actual <= $maxAcceptable) {
                                                        $optimalCount++;
                                                    }
                                                }

                                                $statusClass = ($optimalCount == $totalChemicals) ? 'text-success' :
                                                              ($optimalCount > 0 ? 'text-warning' : 'text-danger');
                                        ?>
                                        <div class="job-order-detail">
                                            <span class="detail-label"><i class="fas fa-flask"></i> Chemical Usage</span>
                                            <span class="detail-value <?= $statusClass ?>">
                                                <?= $optimalCount ?>/<?= $totalChemicals ?> Optimal
                                            </span>
                                        </div>
                                        <?php endif; endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="report-details">
                            <div class="detail-section">
                                <h3><i class="fas fa-clipboard"></i> Observation Notes</h3>
                                <div style="padding: 20px;">
                                    <p><?= nl2br(htmlspecialchars($report['observation_notes'])) ?></p>
                                </div>
                            </div>

                            <div class="detail-section">
                                <h3><i class="fas fa-lightbulb"></i> Recommendation</h3>
                                <div style="padding: 20px;">
                                    <?php if ($recommendationColumnExists && !empty($report['recommendation'])): ?>
                                        <p><?= nl2br(htmlspecialchars($report['recommendation'])) ?></p>
                                    <?php else: ?>
                                        <p><em>No recommendation available. This may be from a report created before recommendations were required.</em></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($chemicalUsageColumnExists && !empty($report['chemical_usage'])):
                                $chemicals = json_decode($report['chemical_usage'], true);
                                if ($chemicals && is_array($chemicals)):
                            ?>
                            <div class="detail-section">
                                <h3><i class="fas fa-flask"></i> Chemical Usage</h3>
                                <div style="padding: 20px;">
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Chemical Name</th>
                                                    <th>Type</th>
                                                    <th>Target Pest</th>
                                                    <th>Recommended Dosage</th>
                                                    <th>Actual Dosage Used</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                foreach ($chemicals as $chemical):
                                                    // Calculate if actual dosage is within acceptable range (±20% of recommended)
                                                    $recommended = isset($chemical['recommended_dosage']) ? floatval($chemical['recommended_dosage']) : 0;
                                                    $actual = isset($chemical['dosage']) ? floatval($chemical['dosage']) : 0;
                                                    $minAcceptable = $recommended * 0.8;
                                                    $maxAcceptable = $recommended * 1.2;

                                                    $status = '';
                                                    $statusClass = '';

                                                    if ($actual < $minAcceptable) {
                                                        $status = 'Under-dosed';
                                                        $statusClass = 'text-warning';
                                                    } elseif ($actual > $maxAcceptable) {
                                                        $status = 'Over-dosed';
                                                        $statusClass = 'text-danger';
                                                    } else {
                                                        $status = 'Optimal';
                                                        $statusClass = 'text-success';
                                                    }
                                                ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($chemical['name'] ?? 'N/A') ?></strong></td>
                                                    <td><?= htmlspecialchars($chemical['type'] ?? 'N/A') ?></td>
                                                    <td><?= htmlspecialchars($chemical['target_pest'] ?? 'N/A') ?></td>
                                                    <td><?= $recommended ?> <?= htmlspecialchars($chemical['dosage_unit'] ?? 'ml') ?></td>
                                                    <td><?= $actual ?> <?= htmlspecialchars($chemical['dosage_unit'] ?? 'ml') ?></td>
                                                    <td class="<?= $statusClass ?>"><strong><?= $status ?></strong></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle"></i>
                                            Chemical usage is considered optimal when within ±20% of the recommended dosage.
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endif; endif; ?>

                            <?php if (!empty($report['attachments'])): ?>
                            <div class="detail-section">
                                <h3><i class="fas fa-images"></i> Attachments</h3>
                                <div class="attachments-grid">
                                    <?php
                                    $attachments = explode(',', $report['attachments']);
                                    foreach ($attachments as $attachment):
                                        if (trim($attachment) === '') continue;
                                    ?>
                                    <div class="attachment-item">
                                        <a href="../uploads/<?= trim($attachment) ?>" target="_blank">
                                            <img src="../uploads/<?= trim($attachment) ?>" alt="Attachment" class="attachment-img">
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($report['feedback_id'])): ?>
                            <div class="detail-section">
                                <h3><i class="fas fa-star"></i> Client Feedback</h3>
                                <div style="padding: 20px;">
                                    <div class="feedback-container">
                                        <div class="feedback-header">
                                            <div class="feedback-client">
                                                <strong><i class="fas fa-user"></i> <?= htmlspecialchars($report['feedback_client_name']) ?></strong>
                                                <span class="feedback-date">
                                                    <i class="fas fa-calendar-alt"></i> <?= date('M d, Y', strtotime($report['feedback_date'])) ?>
                                                </span>
                                            </div>
                                            <div class="feedback-rating">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?= ($i <= $report['rating']) ? 'star-filled' : 'star-empty' ?>"></i>
                                                <?php endfor; ?>
                                                <span class="rating-text"><?= $report['rating'] ?>/5</span>
                                            </div>
                                        </div>

                                        <div class="feedback-verification">
                                            <h4>Verification</h4>
                                            <div class="verification-items">
                                                <div class="verification-item">
                                                    <span class="verification-label">Technician Arrived:</span>
                                                    <span class="verification-value <?= $report['technician_arrived'] ? 'text-success' : 'text-danger' ?>">
                                                        <i class="fas <?= $report['technician_arrived'] ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                                                        <?= $report['technician_arrived'] ? 'Yes' : 'No' ?>
                                                    </span>
                                                </div>
                                                <div class="verification-item">
                                                    <span class="verification-label">Job Completed:</span>
                                                    <span class="verification-value <?= $report['job_completed'] ? 'text-success' : 'text-danger' ?>">
                                                        <i class="fas <?= $report['job_completed'] ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                                                        <?= $report['job_completed'] ? 'Yes' : 'No' ?>
                                                    </span>
                                                </div>
                                            </div>

                                            <?php if (!empty($report['verification_notes'])): ?>
                                            <div class="verification-notes">
                                                <h5>Verification Notes:</h5>
                                                <p><?= nl2br(htmlspecialchars($report['verification_notes'])) ?></p>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!empty($report['feedback_comments'])): ?>
                                        <div class="feedback-comments">
                                            <h4>Comments</h4>
                                            <div class="comment-box">
                                                <p><?= nl2br(htmlspecialchars($report['feedback_comments'])) ?></p>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="detail-section">
                                <h3><i class="fas fa-info-circle"></i> Additional Information</h3>
                                <div class="detail-grid">
                                    <div>
                                        <div class="detail-label"><i class="fas fa-building"></i> Property Type</div>
                                        <div class="detail-value"><?= htmlspecialchars($report['kind_of_place']) ?></div>
                                    </div>
                                    <div>
                                        <div class="detail-label"><i class="fas fa-hashtag"></i> Job Order ID</div>
                                        <div class="detail-value"><?= $report['job_order_id'] ?></div>
                                    </div>
                                    <div>
                                        <div class="detail-label"><i class="fas fa-hashtag"></i> Report ID</div>
                                        <div class="detail-value"><?= $report['report_id'] ?></div>
                                    </div>
                                    <div>
                                        <div class="detail-label"><i class="fas fa-clock"></i> Report Time</div>
                                        <div class="detail-value"><?= date('h:i A', strtotime($report['created_at'])) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="no-reports">
                    <i class="fas fa-clipboard-list"></i>
                    <p>No job order reports found.</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
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

                // Set up periodic notification checks
                setInterval(fetchNotifications, 60000); // Check every minute
            } else {
                console.error("fetchNotifications function not found");
            }
        });

        // Function to toggle report details
        function toggleReportDetails(header) {
            const reportCard = header.closest('.report-card');
            const details = reportCard.querySelector('.report-details');

            if (details.classList.contains('active')) {
                details.classList.remove('active');
            } else {
                // Close any other open details first
                document.querySelectorAll('.report-details.active').forEach(function(el) {
                    el.classList.remove('active');
                });

                details.classList.add('active');
            }
        }
    </script>
</body>
</html>