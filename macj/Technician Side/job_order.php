<?php
session_start();
if ($_SESSION['role'] !== 'technician') {
    header("Location: SignIn.php");
    exit;
}
require_once '../db_connect.php';
require_once '../notification_functions.php';

$technician_id = $_SESSION['user_id'];

// Make sure we have the correct timezone set
date_default_timezone_set('Asia/Manila');

// Get today's date in YYYY-MM-DD format with the correct timezone
$today = date('Y-m-d', time());

// Get sorting parameter from URL if it exists
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'date_asc';

// Clear any output buffering and set no-cache headers
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// First, let's get the table structure to understand the correct column names
$tableStructure = $conn->query("DESCRIBE job_order");
$columns = [];
if ($tableStructure) {
    while ($column = $tableStructure->fetch_assoc()) {
        $columns[] = $column['Field'];
    }
}

// Check if the table has a technician_id column or similar
$technicianColumn = '';
$possibleColumns = ['technician_id', 'tech_id', 'assigned_technician_id', 'assigned_to'];
foreach ($possibleColumns as $colName) {
    if (in_array($colName, $columns)) {
        $technicianColumn = $colName;
        break;
    }
}

// Check if the table has a status column
$hasStatusColumn = in_array('status', $columns);

// Check if the table has a preferred_date column
$dateColumn = '';
$possibleDateColumns = ['preferred_date', 'date', 'scheduled_date', 'appointment_date'];
foreach ($possibleDateColumns as $colName) {
    if (in_array($colName, $columns)) {
        $dateColumn = $colName;
        break;
    }
}

// Check if the table has a client_id column
$clientIdColumn = '';
$possibleClientIdColumns = ['client_id', 'customer_id', 'client', 'customer'];
foreach ($possibleClientIdColumns as $colName) {
    if (in_array($colName, $columns)) {
        $clientIdColumn = $colName;
        break;
    }
}

// If we couldn't find a technician column, we'll try to get all job orders
$whereClause = $technicianColumn ? "jo.$technicianColumn = ?" : "1=1";

// Add status condition only if the status column exists
// Include 'completed' status to show finished job orders
$statusCondition = $hasStatusColumn ? "AND (jo.status = 'approved' OR jo.status = 'scheduled' OR jo.status = 'completed' OR jo.status IS NULL)" : "";

// Add client approval condition to only show approved job orders
$clientApprovalCondition = "AND (jo.client_approval_status = 'approved' OR jo.client_approval_status = 'one-time')";

// Build the ORDER BY clause based on the detected date column
$orderBy = $dateColumn ? "ORDER BY jo.$dateColumn ASC" : "";

// Build the JOIN clause to get client information through the assessment_report and appointments tables
$joinClause = "JOIN job_order_technicians jot ON jo.job_order_id = jot.job_order_id
    JOIN assessment_report ar ON jo.report_id = ar.report_id
    JOIN appointments a ON ar.appointment_id = a.appointment_id
    LEFT JOIN clients c ON a.client_id = c.client_id
    LEFT JOIN job_order_report jor ON jo.job_order_id = jor.job_order_id";

// Query to get job orders with correct primary technician status for each job
$stmt = $conn->prepare("
    SELECT
        jo.*,
        a.client_name,
        a.location_address,
        a.kind_of_place,
        c.first_name,
        c.last_name,
        c.contact_number,
        ar.area,
        ar.attachments,
        ar.pest_types,
        ar.problem_area,
        ar.notes as technician_notes,
        jor.observation_notes,
        '' as recommendation, -- Placeholder for recommendation field
        jor.attachments as report_attachments,
        jor.created_at as report_created_at,
        jo.chemical_recommendations,
        jot.is_primary, -- Get the actual is_primary value for this specific job
        COALESCE(jor.created_at, jo.created_at) as sort_date -- Use report creation date if available, otherwise job order creation date
    FROM job_order jo
    $joinClause
    WHERE jot.technician_id = ? $statusCondition $clientApprovalCondition
    $orderBy
");
try {
    // Bind the technician_id parameter
    $stmt->bind_param("i", $technician_id);
    $stmt->execute();
    $result = $stmt->get_result();
} catch (Exception $e) {
    // Display a user-friendly message
    echo "<div class='alert alert-danger'>
            <h4>Database Error</h4>
            <p>There was an error retrieving job orders. Please contact the administrator.</p>
          </div>";
    // Initialize empty arrays to prevent errors in the rest of the code
    $result = false;
}

$todayJobOrders = [];
$upcomingJobOrders = [];
$finishedJobOrders = [];
$pastDueJobOrders = []; // New array for past due job orders

// Only process results if the query was successful
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Check if job is completed - always add to finished section regardless of date
        if (isset($row['status']) && $row['status'] === 'completed') {
            $finishedJobOrders[] = $row;
            continue; // Skip to next job
        }

        // Check if the date column exists in this row
        if ($dateColumn && isset($row[$dateColumn])) {
            // Direct string comparison for dates in YYYY-MM-DD format
            // This is the simplest and most reliable method for this specific format
            if ($row[$dateColumn] === $today) {
                $todayJobOrders[] = $row;
                // Job added to today's list
            } elseif ($row[$dateColumn] > $today) {
                $upcomingJobOrders[] = $row;
                // Job added to upcoming list
            } else {
                // Past date job order - add to past due section
                // These are jobs that were scheduled for a past date but not completed
                $pastDueJobOrders[] = $row;
            }
        } else {
            // If no date column is found, add to today's job orders by default
            $todayJobOrders[] = $row;
        }
    }

    // Apply sorting based on the selected sort order
    // Define sorting functions
    $sortByDateAsc = function($a, $b) use ($dateColumn) {
        return strtotime($a[$dateColumn]) - strtotime($b[$dateColumn]);
    };

    $sortByDateDesc = function($a, $b) use ($dateColumn) {
        return strtotime($b[$dateColumn]) - strtotime($a[$dateColumn]);
    };

    $sortByClientNameAsc = function($a, $b) {
        return strcasecmp($a['client_name'] ?? '', $b['client_name'] ?? '');
    };

    $sortByClientNameDesc = function($a, $b) {
        return strcasecmp($b['client_name'] ?? '', $a['client_name'] ?? '');
    };

    $sortByTypeOfWorkAsc = function($a, $b) {
        return strcasecmp($a['type_of_work'] ?? '', $b['type_of_work'] ?? '');
    };

    // Define a function to sort by report creation date (for finished job orders)
    $sortByReportCreatedDesc = function($a, $b) {
        // Use the sort_date field which combines report_created_at and created_at
        if (isset($a['sort_date']) && isset($b['sort_date'])) {
            return strtotime($b['sort_date']) - strtotime($a['sort_date']);
        }
        // If report_created_at is available, use it
        else if (isset($a['report_created_at']) && isset($b['report_created_at'])) {
            return strtotime($b['report_created_at']) - strtotime($a['report_created_at']);
        }
        // Fallback to job order creation date if available
        else if (isset($a['created_at']) && isset($b['created_at'])) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        }
        // If neither is available, return 0 (no change in order)
        return 0;
    };

    // Apply the selected sorting to all job order arrays
    switch ($sort_order) {
        case 'date_desc':
            if ($dateColumn) {
                usort($todayJobOrders, $sortByDateDesc);
                usort($upcomingJobOrders, $sortByDateDesc);
                usort($pastDueJobOrders, $sortByDateDesc);
                // For finished job orders, always use LIFO order
                usort($finishedJobOrders, $sortByReportCreatedDesc);
            }
            break;
        case 'client_asc':
            usort($todayJobOrders, $sortByClientNameAsc);
            usort($upcomingJobOrders, $sortByClientNameAsc);
            usort($pastDueJobOrders, $sortByClientNameAsc);
            // For finished job orders, always use LIFO order
            usort($finishedJobOrders, $sortByReportCreatedDesc);
            break;
        case 'client_desc':
            usort($todayJobOrders, $sortByClientNameDesc);
            usort($upcomingJobOrders, $sortByClientNameDesc);
            usort($pastDueJobOrders, $sortByClientNameDesc);
            // For finished job orders, always use LIFO order
            usort($finishedJobOrders, $sortByReportCreatedDesc);
            break;
        case 'type_asc':
            usort($todayJobOrders, $sortByTypeOfWorkAsc);
            usort($upcomingJobOrders, $sortByTypeOfWorkAsc);
            usort($pastDueJobOrders, $sortByTypeOfWorkAsc);
            // For finished job orders, always use LIFO order
            usort($finishedJobOrders, $sortByReportCreatedDesc);
            break;
        case 'date_asc':
        default:
            if ($dateColumn) {
                usort($todayJobOrders, $sortByDateAsc);
                usort($upcomingJobOrders, $sortByDateAsc);
                usort($pastDueJobOrders, $sortByDateAsc);
                // For finished job orders, always use LIFO order
                usort($finishedJobOrders, $sortByReportCreatedDesc);
            }
            break;
    }
}
?>
<!-- Debug information has been removed for production -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Dashboard</title>
    <link href="https://unpkg.com/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Unified Design System CSS -->
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/sidebar-new.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/technician-common.css">
    <link rel="stylesheet" href="css/footer.css">
    <link rel="stylesheet" href="css/notifications.css">
    <link rel="stylesheet" href="css/tools-checklist.css">
    <link rel="stylesheet" href="css/table-fix.css">
    <link rel="stylesheet" href="css/header-fix.css">
    <link rel="stylesheet" href="css/modal-fix.css">
    <link rel="stylesheet" href="css/sidebar-fix.css">
    <style>
        /* Additional styles for user info */
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

        /* Hide the scheduled for badge */
        .scheduled-date {
            display: none !important;
        }

        /* Past Due Job Orders styling */
        .past-due-job-orders .job-card {
            background-color: #fff8f8;
            border-color: #dc3545;
        }

        .past-due-job-orders h3 {
            color: #dc3545;
        }

        .past-due-job-orders .badge.bg-danger {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
            100% {
                opacity: 1;
            }
        }

        /* Additional fixes for sidebar in job_order.php */
        @media (max-width: 768px) {
            #sidebar.active {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                left: 0 !important;
                transform: translateX(0) !important;
                width: 250px !important;
                z-index: 1050 !important;
                position: fixed !important;
                top: 0 !important;
                height: 100% !important;
            }

            #menuToggle {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                z-index: 1060 !important;
                position: fixed !important;
            }
        }

        /* Filter Container Styles */
        .filter-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 200px;
            flex: 1;
        }

        .filter-group label {
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
            font-size: 14px;
        }

        .filter-group select {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background-color: white;
            font-size: 14px;
            color: var(--text-color);
            transition: all 0.3s ease;
        }

        .filter-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        @media (max-width: 768px) {
            .filter-container {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }
        }

        /* Chemical Dosage Table Styles */
        #chemicalDosageInputs {
            overflow-x: auto;
            width: 100%;
            padding-bottom: 10px;
        }

        #chemicalDosageInputs .table {
            width: 100%;
            min-width: 800px; /* Ensure table has minimum width for all columns */
        }

        #chemicalDosageInputs .table th,
        #chemicalDosageInputs .table td {
            padding: 0.5rem;
            vertical-align: middle;
            white-space: normal;
            word-break: break-word;
        }

        #chemicalDosageInputs .input-group-sm {
            width: 100%;
        }

        /* Validation styles */
        .is-invalid {
            border-color: #dc3545 !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }

        @media (max-width: 992px) {
            #chemicalDosageSection {
                margin-bottom: 20px;
            }

            #chemicalDosageInputs {
                margin-bottom: 10px;
                border: 1px solid #dee2e6;
                border-radius: 4px;
            }
        }
    </style>
</head>
<body class="job_order">
    <!-- Menu Toggle Button for Mobile -->
    <button id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Navigation -->
    <aside id="sidebar">
        <div class="sidebar-header">
            <h2>MacJ Pest Control</h2>
            <h3>Welcome, <?= $_SESSION['username'] ?? 'Technician' ?></h3>
        </div>
        <nav class="sidebar-menu">
            <a href="schedule.php">
                <i class="fas fa-calendar-alt fa-icon"></i>
                My Schedule
            </a>
            <a href="inspection.php">
                <i class="fas fa-clipboard-list fa-icon"></i>
                Inspection Board
            </a>
            <a href="job_order.php" class="active">
                <i class="fas fa-tasks fa-icon"></i>
                Job Order Board
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

    <!-- Header -->
    <header class="header">
        <div class="header-title">
            <h1>Technician Dashboard</h1>
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
                    <div class="user-name"><?= $_SESSION['username'] ?? 'Technician' ?></div>
                    <div class="user-role">Pest Control Expert</div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-content">
        <div class="dashboard-header">
            <h1><i class="fas fa-tasks"></i> Job Order Board</h1>
        </div>

        <!-- Sorting Filter -->
        <div class="filter-container">
            <div class="filter-group">
                <label for="sort-order"><i class="fas fa-sort me-1"></i>Sort By:</label>
                <select id="sort-order" class="form-select" onchange="changeSortOrder(this.value)">
                    <option value="date_asc" <?= $sort_order === 'date_asc' ? 'selected' : '' ?>>Date (Newest First)</option>
                    <option value="date_desc" <?= $sort_order === 'date_desc' ? 'selected' : '' ?>>Date (Future First)</option>
                    <option value="client_asc" <?= $sort_order === 'client_asc' ? 'selected' : '' ?>>Client Name (A-Z)</option>
                    <option value="client_desc" <?= $sort_order === 'client_desc' ? 'selected' : '' ?>>Client Name (Z-A)</option>
                    <option value="type_asc" <?= $sort_order === 'type_asc' ? 'selected' : '' ?>>Type of Work (A-Z)</option>
                </select>
            </div>
        </div>

        <!-- Today's Job Orders -->
        <div class="job-section">
            <h3><i class="fas fa-calendar-day"></i> Today's Job Orders</h3>
            <div class="row">
                <?php foreach ($todayJobOrders as $job): ?>
                <div class="col-md-4 mb-3">
                    <div class="card job-card" onclick="openJobDetails(<?= htmlspecialchars(json_encode($job)) ?>)"
                         style="cursor: pointer; transition: transform 0.3s ease;"
                         onmouseover="this.style.transform='translateY(-5px)'"
                         onmouseout="this.style.transform='translateY(0)'">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars(trim($job['client_name'] ?? '') ?: 'Unknown Client') ?></h5>
                            <div class="d-flex gap-2 mb-2">
                                <?php if (!empty($job['kind_of_place'])): ?>
                                <span class="detail-badge"><?= htmlspecialchars($job['kind_of_place']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($job['type_of_work'])): ?>
                                <span class="detail-badge"><?= htmlspecialchars($job['type_of_work']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($dateColumn && isset($job[$dateColumn])): ?>
                            <p class="text-muted mb-1">
                                <i class="fas fa-calendar me-1"></i>
                                <?= date('M d, Y', strtotime($job[$dateColumn])) ?>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($job['preferred_time'])): ?>
                            <p class="text-muted mb-1">
                                <i class="fas fa-clock me-1"></i>
                                <?= date('h:i A', strtotime($job['preferred_time'])) ?>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($job['location_address'])): ?>
                            <small class="text-primary">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?= htmlspecialchars($job['location_address']) ?>
                            </small>
                            <?php endif; ?>
                            <div class="mt-2">
                                <span class="badge bg-primary">Today's Schedule</span>
                                <?php if (isset($job['is_primary']) && $job['is_primary']): ?>
                                <span class="badge bg-info">Primary Technician</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Secondary Technician</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($todayJobOrders)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">No job orders scheduled for today</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upcoming Job Orders -->
        <div class="job-section upcoming-job-orders">
            <h3><i class="fas fa-calendar-alt"></i> Upcoming Job Orders</h3>
            <div class="row">
                <?php foreach ($upcomingJobOrders as $job): ?>
                <div class="col-md-4 mb-3">
                    <div class="card job-card" style="opacity: 0.8; background-color: #f8f9fa;">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars(trim($job['client_name'] ?? '') ?: 'Unknown Client') ?></h5>
                            <div class="d-flex gap-2 mb-2">
                                <?php if (!empty($job['kind_of_place'])): ?>
                                <span class="detail-badge"><?= htmlspecialchars($job['kind_of_place']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($job['type_of_work'])): ?>
                                <span class="detail-badge"><?= htmlspecialchars($job['type_of_work']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($dateColumn && isset($job[$dateColumn])): ?>
                            <p class="text-muted mb-1">
                                <i class="fas fa-calendar me-1"></i>
                                <?= date('M d, Y', strtotime($job[$dateColumn])) ?>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($job['preferred_time'])): ?>
                            <p class="text-muted mb-1">
                                <i class="fas fa-clock me-1"></i>
                                <?= date('h:i A', strtotime($job['preferred_time'])) ?>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($job['location_address'])): ?>
                            <small class="text-primary">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?= htmlspecialchars($job['location_address']) ?>
                            </small>
                            <?php endif; ?>
                            <div class="mt-2">
                                <span class="badge bg-secondary">Upcoming - Not Yet Available</span>
                                <?php if (isset($job['is_primary']) && $job['is_primary']): ?>
                                <span class="badge bg-info">Primary Technician</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Secondary Technician</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($upcomingJobOrders)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">No upcoming job orders</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Past Due Job Orders -->
        <div class="job-section past-due-job-orders">
            <h3><i class="fas fa-exclamation-triangle"></i> Past Due Job Orders</h3>
            <div class="row">
                <?php foreach ($pastDueJobOrders as $job): ?>
                <div class="col-md-4 mb-3">
                    <div class="card job-card" onclick="openJobDetails(<?= htmlspecialchars(json_encode($job)) ?>)"
                         style="cursor: pointer; transition: transform 0.3s ease; border-left: 4px solid #dc3545;"
                         onmouseover="this.style.transform='translateY(-5px)'"
                         onmouseout="this.style.transform='translateY(0)'">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars(trim($job['client_name'] ?? '') ?: 'Unknown Client') ?></h5>
                            <div class="d-flex gap-2 mb-2">
                                <?php if (!empty($job['kind_of_place'])): ?>
                                <span class="detail-badge"><?= htmlspecialchars($job['kind_of_place']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($job['type_of_work'])): ?>
                                <span class="detail-badge"><?= htmlspecialchars($job['type_of_work']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($dateColumn && isset($job[$dateColumn])): ?>
                            <p class="text-muted mb-1">
                                <i class="fas fa-calendar me-1"></i>
                                <?= date('M d, Y', strtotime($job[$dateColumn])) ?>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($job['preferred_time'])): ?>
                            <p class="text-muted mb-1">
                                <i class="fas fa-clock me-1"></i>
                                <?= date('h:i A', strtotime($job['preferred_time'])) ?>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($job['location_address'])): ?>
                            <small class="text-primary">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?= htmlspecialchars($job['location_address']) ?>
                            </small>
                            <?php endif; ?>
                            <div class="mt-2">
                                <span class="badge bg-danger">Past Due - Needs Attention</span>
                                <?php if (isset($job['is_primary']) && $job['is_primary']): ?>
                                <span class="badge bg-info">Primary Technician</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Secondary Technician</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($pastDueJobOrders)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">No past due job orders</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Finished Job Orders -->
        <div class="job-section finished-job-orders">
            <h3><i class="fas fa-check-circle"></i> Finished Job Orders</h3>

            <?php if (isset($_GET['debug'])): ?>
            <div class="alert alert-info">
                <h5>Debug Information</h5>
                <p>Number of finished job orders: <?= count($finishedJobOrders) ?></p>
                <p>SQL Condition: <?= htmlspecialchars($statusCondition) ?></p>
                <?php
                // Check if there are any job orders with status 'completed'
                $completedCount = 0;
                if ($result) {
                    $result->data_seek(0); // Reset result pointer
                    while ($row = $result->fetch_assoc()) {
                        if (isset($row['status']) && $row['status'] === 'completed') {
                            $completedCount++;
                        }
                    }
                    $result->data_seek(0); // Reset result pointer again
                }
                ?>
                <p>Number of job orders with status 'completed' in database: <?= $completedCount ?></p>
            </div>
            <?php endif; ?>

            <div class="row">
                <?php foreach ($finishedJobOrders as $job): ?>
                <div class="col-md-4 mb-3">
                    <div class="card job-card" onclick="openJobDetails(<?= htmlspecialchars(json_encode($job)) ?>)"
                         style="cursor: pointer; transition: transform 0.3s ease; border-left: 4px solid #28a745;"
                         onmouseover="this.style.transform='translateY(-5px)'"
                         onmouseout="this.style.transform='translateY(0)'">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars(trim($job['client_name'] ?? '') ?: 'Unknown Client') ?></h5>
                            <div class="d-flex gap-2 mb-2">
                                <?php if (!empty($job['kind_of_place'])): ?>
                                <span class="detail-badge"><?= htmlspecialchars($job['kind_of_place']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($job['type_of_work'])): ?>
                                <span class="detail-badge"><?= htmlspecialchars($job['type_of_work']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($dateColumn && isset($job[$dateColumn])): ?>
                            <p class="text-muted mb-1">
                                <i class="fas fa-calendar me-1"></i>
                                <?= date('M d, Y', strtotime($job[$dateColumn])) ?>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($job['preferred_time'])): ?>
                            <p class="text-muted mb-1">
                                <i class="fas fa-clock me-1"></i>
                                <?= date('h:i A', strtotime($job['preferred_time'])) ?>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($job['location_address'])): ?>
                            <small class="text-primary">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?= htmlspecialchars($job['location_address']) ?>
                            </small>
                            <?php endif; ?>
                            <div class="mt-2">
                                <span class="badge bg-success">Completed<?= isset($job['status']) ? ' - ' . $job['status'] : '' ?></span>
                                <?php if (isset($job['is_primary']) && $job['is_primary']): ?>
                                <span class="badge bg-info">Primary Technician</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Secondary Technician</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($finishedJobOrders)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">No completed job orders</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Job Details Modal -->
    <div class="modal fade" id="jobDetailsModal">
        <div class="modal-dialog modal-lg modal-zoom">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Job Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="jobDetailsContent"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="createReportBtn" onclick="openReportForm()"><i class="fas fa-file-medical me-2"></i>Create Job Order Report</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Job Order Report Form Modal -->
    <div class="modal fade" id="reportFormModal" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl modal-zoom">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Job Order Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="jobOrderReportForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="job_order_id" id="reportJobOrderId">

                        <div class="mb-3">
                            <label for="observation_notes" class="form-label">Observation Notes <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="observation_notes" name="observation_notes" rows="4" required></textarea>
                            <div class="form-text">Provide detailed notes about the job completion, observations, and any issues encountered.</div>
                        </div>

                        <div class="mb-3">
                            <label for="recommendation" class="form-label">Recommendation <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="recommendation" name="recommendation" rows="3" required></textarea>
                            <div class="form-text">Provide recommendations for future pest control measures or maintenance.</div>
                        </div>

                        <div class="mb-3">
                            <label for="attachments" class="form-label">Attachments <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="attachments" name="attachments[]" multiple accept="image/*" required>
                            <div class="form-text">Upload photos of the completed job (before/after, receipts, etc.)</div>
                        </div>

                        <!-- Chemical Dosage Section -->
                        <div class="mb-4" id="chemicalDosageSection">
                            <label class="form-label">Chemical Dosage Used <span class="text-danger">*</span></label>
                            <div class="alert alert-secondary">
                                <i class="fas fa-flask me-2"></i> Please enter the actual amount of each chemical used during the treatment.
                            </div>
                            <div id="chemicalDosageInputs" class="mt-3">
                                <!-- Chemical dosage inputs will be added here dynamically -->
                                <div class="text-center py-3">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2">Loading chemical recommendations...</p>
                                </div>
                            </div>
                            <div class="form-text mt-2">
                                <i class="fas fa-info-circle me-1"></i> Swipe left/right if the table is not fully visible on your device.
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Submitting this report will mark the job order as completed.
                            <div class="mt-2"><strong>Note:</strong> All fields marked with <span class="text-danger">*</span> are required.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Submit Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Report Submission Success Modal -->
    <div class="modal fade" id="reportSuccessModal" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i>Report Submitted</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div class="success-checkmark">
                            <div class="check-icon">
                                <span class="icon-line line-tip"></span>
                                <span class="icon-line line-long"></span>
                            </div>
                        </div>
                        <h4>Job Order Report Submitted Successfully!</h4>
                        <p class="text-muted">The job order has been marked as completed.</p>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> If you don't see the job order in the Finished Job Orders section, please refresh the page.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Close
                    </button>
                    <button type="button" class="btn btn-primary" onclick="window.location.reload()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh Page
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Function to handle sort order changes
        function changeSortOrder(sortOrder) {
            // Redirect to the same page with the new sort parameter
            window.location.href = `job_order.php?sort=${sortOrder}`;
        }

        // Store the current job for reference
        let currentJob = null;

        function openJobDetails(job) {
            // Store the current job for later use
            currentJob = job;

            // Equipment section has been removed as requested

            const content = `
                <div class="modal-container">
                    <!-- Header Section -->
                    <div class="modal-header-section mb-3">
                        <h4 class="mb-2">${job.client_name && job.client_name.trim() ? job.client_name : 'Unknown Client'}</h4>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            ${job.kind_of_place ? `<span class="badge bg-primary">${job.kind_of_place}</span>` : ''}
                            ${job.type_of_work ? `<span class="badge bg-secondary">${job.type_of_work}</span>` : ''}
                            <span class="badge bg-info"><i class="fas fa-hashtag me-1"></i>Job #${job.job_order_id}</span>
                        </div>
                    </div>

                    <!-- Main Content -->
                    <div class="row g-3">
                        <!-- Left Column -->
                        <div class="col-md-6">
                            <div class="info-card">
                                <h6 class="card-subtitle mb-3 text-muted">
                                    <i class="fas fa-info-circle me-2"></i>Job Details
                                </h6>
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <span class="text-muted"><i class="fas fa-calendar-day me-2"></i>Date:</span>
                                        <div class="fw-bold">${new Date(job.preferred_date).toLocaleDateString()}</div>
                                    </li>
                                    <li class="mb-2">
                                        <span class="text-muted"><i class="fas fa-clock me-2"></i>Time:</span>
                                        <div class="fw-bold">${job.preferred_time ? job.preferred_time.substr(0,5) : 'Not specified'}</div>
                                    </li>
                                    <li class="mb-2">
                                        <span class="text-muted"><i class="fas fa-phone me-2"></i>Contact:</span>
                                        <div class="fw-bold">${job.contact_number || 'N/A'}</div>
                                    </li>
                                    ${job.created_at ? `
                                    <li class="mb-2">
                                        <span class="text-muted"><i class="fas fa-calendar-plus me-2"></i>Created:</span>
                                        <div class="fw-bold">${new Date(job.created_at).toLocaleDateString()}</div>
                                    </li>
                                    ` : ''}
                                </ul>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="col-md-6">
                            <div class="info-card">
                                <h6 class="card-subtitle mb-3 text-muted">
                                    <i class="fas fa-map-marked-alt me-2"></i>Location Information
                                </h6>
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <span class="text-muted"><i class="fas fa-map-marker-alt me-2"></i>Address:</span>
                                        <div class="fw-bold">${job.location_address || 'N/A'}</div>
                                    </li>
                                    <li class="mb-2">
                                        <span class="text-muted"><i class="fas fa-home me-2"></i>Type of Place:</span>
                                        <div class="fw-bold">${job.kind_of_place || 'N/A'}</div>
                                    </li>
                                    <li class="mb-2">
                                        <span class="text-muted"><i class="fas fa-tools me-2"></i>Type of Work:</span>
                                        <div class="fw-bold">${job.type_of_work || 'N/A'}</div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Full Width Sections -->
                    <div class="row mt-3">
                        <div class="col-12">
                            <!-- Assessment Details -->
                            <div class="info-card">
                                <h6 class="card-subtitle mb-3 text-muted">
                                    <i class="fas fa-clipboard-check me-2"></i>Assessment Details
                                </h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <p class="mb-2">
                                            <span class="text-muted"><i class="fas fa-ruler-combined me-2"></i>Area:</span>
                                            <span class="fw-bold">${job.area ? job.area + ' m²' : 'Not specified'}</span>
                                        </p>
                                        <p class="mb-2">
                                            <span class="text-muted"><i class="fas fa-bug me-2"></i>Pest Types:</span>
                                            <span class="fw-bold">${job.pest_types || 'Not specified'}</span>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-2">
                                            <span class="text-muted"><i class="fas fa-map-pin me-2"></i>Problem Area:</span>
                                            <span class="fw-bold">${job.problem_area || 'Not specified'}</span>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Technician Notes -->
                            <div class="info-card">
                                <h6 class="card-subtitle mb-3 text-muted">
                                    <i class="fas fa-sticky-note me-2"></i>Technician Notes
                                </h6>
                                <div class="notes-content p-3 bg-light rounded">
                                    ${job.technician_notes ? job.technician_notes : '<em class="text-muted">No notes available</em>'}
                                </div>
                            </div>

                            <!-- Attachments if available -->
                            ${job.attachments ? `
                            <div class="info-card">
                                <h6 class="card-subtitle mb-3 text-muted">
                                    <i class="fas fa-paperclip me-2"></i>Attachments
                                </h6>
                                <div class="attachments-list">
                                    ${job.attachments.split(',').map(file =>
                                        file.trim() ? `<a href="../uploads/${file.trim()}" target="_blank" class="attachment-link">
                                            <i class="fas fa-file-image me-2"></i>${file.trim()}
                                        </a>` : ''
                                    ).join('')}
                                </div>
                            </div>
                            ` : ''}

                            <!-- Chemical Recommendations Section -->
                            ${job.chemical_recommendations ? `
                            <div class="info-card">
                                <h6 class="card-subtitle mb-3 text-muted">
                                    <i class="fas fa-flask me-2"></i>Chemical Recommendations
                                </h6>
                                <div class="chemical-recommendations-container">
                                    ${(() => {
                                        try {
                                            const chemicals = JSON.parse(job.chemical_recommendations);
                                            if (Array.isArray(chemicals) && chemicals.length > 0) {
                                                return `
                                                    <div class="table-responsive">
                                                        <table class="table table-bordered table-hover">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th>Chemical Name</th>
                                                                    <th>Type</th>
                                                                    <th>Target Pest</th>
                                                                    <th>Recommended Dosage</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                ${chemicals.map(chem => `
                                                                    <tr>
                                                                        <td><strong>${chem.name || 'N/A'}</strong></td>
                                                                        <td>${chem.type || 'N/A'}</td>
                                                                        <td>${chem.target_pest || 'N/A'}</td>
                                                                        <td>${chem.dosage || '0'} ${chem.dosage_unit || 'ml'}</td>
                                                                    </tr>
                                                                `).join('')}
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div class="alert alert-info mt-2">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        <small>These chemicals have been recommended based on the assessment report and target pests.</small>
                                                    </div>
                                                `;
                                            } else {
                                                return '<div class="alert alert-warning">No specific chemicals recommended</div>';
                                            }
                                        } catch (e) {
                                            console.error('Error parsing chemical recommendations:', e);
                                            return '<div class="alert alert-danger">Error displaying chemical recommendations</div>';
                                        }
                                    })()}
                                </div>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                </div>`;

                document.getElementById('jobDetailsContent').innerHTML = content;

                // Show or hide the Create Report button based on job status and primary technician status
                const createReportBtn = document.getElementById('createReportBtn');
                if (job.status === 'completed') {
                    createReportBtn.style.display = 'none';
                } else if (!job.is_primary) {
                    // Hide the button if the technician is not the primary technician
                    createReportBtn.style.display = 'none';
                    // Add a note to the modal footer explaining why the button is hidden
                    const modalFooter = document.querySelector('#jobDetailsModal .modal-footer');
                    const noteElement = document.createElement('div');
                    noteElement.className = 'text-muted small mt-2';
                    noteElement.innerHTML = '<i class="fas fa-info-circle me-1"></i> Only the primary technician can submit reports for this job order.';
                    modalFooter.appendChild(noteElement);

                    // Add job order report section if we have the data
                    if (job.observation_notes || job.payment_amount) {
                        // Create report section HTML
                        const reportHTML = `
                        <div class="info-card mt-3">
                            <h6 class="card-subtitle mb-3 text-muted">
                                <i class="fas fa-file-alt me-2"></i>Job Order Report
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <span class="text-muted"><i class="fas fa-calendar-check me-2"></i>Completed On:</span>
                                        <span class="fw-bold">${job.report_created_at ? new Date(job.report_created_at).toLocaleString() : 'N/A'}</span>
                                    </p>
                                </div>
                                <div class="col-12">
                                    <p class="mb-2">
                                        <span class="text-muted"><i class="fas fa-clipboard me-2"></i>Observation Notes:</span>
                                    </p>
                                    <div class="p-3 bg-light rounded">
                                        ${job.observation_notes || '<em class="text-muted">No notes available</em>'}
                                    </div>
                                </div>
                                <div class="col-12">
                                    <p class="mb-2">
                                        <span class="text-muted"><i class="fas fa-lightbulb me-2"></i>Recommendation:</span>
                                    </p>
                                    <div class="p-3 bg-light rounded">
                                        ${job.recommendation || '<em class="text-muted">No recommendation available</em>'}
                                    </div>
                                </div>

                                <!-- Chemical Usage Section -->
                                ${job.chemical_usage ? `
                                <div class="col-12">
                                    <p class="mb-2">
                                        <span class="text-muted"><i class="fas fa-flask me-2"></i>Chemical Usage:</span>
                                    </p>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Chemical Name</th>
                                                    <th>Type</th>
                                                    <th>Target Pest</th>
                                                    <th>Actual Dosage Used</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${(() => {
                                                    try {
                                                        const chemicals = JSON.parse(job.chemical_usage);
                                                        return chemicals.map(chem => `
                                                            <tr>
                                                                <td><strong>${chem.name || 'N/A'}</strong></td>
                                                                <td>${chem.type || 'N/A'}</td>
                                                                <td>${chem.target_pest || 'N/A'}</td>
                                                                <td>${chem.dosage || '0'} ${chem.dosage_unit || 'ml'}</td>
                                                            </tr>
                                                        `).join('');
                                                    } catch (e) {
                                                        console.error('Error parsing chemical usage:', e);
                                                        return '<tr><td colspan="4" class="text-danger">Error displaying chemical usage data</td></tr>';
                                                    }
                                                })()}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                ` : ''}

                                ${job.report_attachments ? `
                                <div class="col-12">
                                    <p class="mb-2">
                                        <span class="text-muted"><i class="fas fa-paperclip me-2"></i>Attachments:</span>
                                    </p>
                                    <div class="attachments-list">
                                        ${job.report_attachments.split(',').map(file =>
                                            file.trim() ? `<a href="../uploads/${file.trim()}" target="_blank" class="attachment-link">
                                                <i class="fas fa-file-image me-2"></i>${file.trim()}
                                            </a>` : ''
                                        ).join('')}
                                    </div>
                                </div>
                                ` : ''}
                            </div>
                        </div>`;

                        // Add the report section to the modal
                        document.getElementById('jobDetailsContent').innerHTML += reportHTML;
                    } else {
                        // If we don't have the report data directly, try to fetch it
                        fetch(`api/job_order_report.php?job_order_id=${job.job_order_id}`)
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error(`HTTP error! Status: ${response.status}`);
                                }
                                return response.json();
                            })
                            .then(data => {
                                if (data.success) {
                                    // Add report data to the modal
                                    const reportSection = document.createElement('div');
                                    reportSection.className = 'info-card mt-3';
                                    reportSection.innerHTML = `
                                        <h6 class="card-subtitle mb-3 text-muted">
                                            <i class="fas fa-file-alt me-2"></i>Job Order Report
                                        </h6>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <p class="mb-2">
                                                    <span class="text-muted"><i class="fas fa-calendar-check me-2"></i>Completed On:</span>
                                                    <span class="fw-bold">${new Date(data.report.timestamp).toLocaleString()}</span>
                                                </p>
                                            </div>
                                            <div class="col-12">
                                                <p class="mb-2">
                                                    <span class="text-muted"><i class="fas fa-clipboard me-2"></i>Observation Notes:</span>
                                                </p>
                                                <div class="p-3 bg-light rounded">
                                                    ${data.report.observation_notes || '<em class="text-muted">No notes available</em>'}
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <p class="mb-2">
                                                    <span class="text-muted"><i class="fas fa-lightbulb me-2"></i>Recommendation:</span>
                                                </p>
                                                <div class="p-3 bg-light rounded">
                                                    ${data.report.recommendation || '<em class="text-muted">No recommendation available</em>'}
                                                </div>
                                            </div>

                                            <!-- Chemical Usage Section -->
                                            ${data.report.chemical_usage ? `
                                            <div class="col-12">
                                                <p class="mb-2">
                                                    <span class="text-muted"><i class="fas fa-flask me-2"></i>Chemical Usage:</span>
                                                </p>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>Chemical Name</th>
                                                                <th>Type</th>
                                                                <th>Target Pest</th>
                                                                <th>Actual Dosage Used</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            ${(() => {
                                                                try {
                                                                    const chemicals = JSON.parse(data.report.chemical_usage);
                                                                    return chemicals.map(chem => `
                                                                        <tr>
                                                                            <td><strong>${chem.name || 'N/A'}</strong></td>
                                                                            <td>${chem.type || 'N/A'}</td>
                                                                            <td>${chem.target_pest || 'N/A'}</td>
                                                                            <td>${chem.dosage || '0'} ${chem.dosage_unit || 'ml'}</td>
                                                                        </tr>
                                                                    `).join('');
                                                                } catch (e) {
                                                                    console.error('Error parsing chemical usage:', e);
                                                                    return '<tr><td colspan="4" class="text-danger">Error displaying chemical usage data</td></tr>';
                                                                }
                                                            })()}
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            ` : ''}

                                            ${data.report.attachments ? `
                                            <div class="col-12">
                                                <p class="mb-2">
                                                    <span class="text-muted"><i class="fas fa-paperclip me-2"></i>Attachments:</span>
                                                </p>
                                                <div class="attachments-list">
                                                    ${data.report.attachments.split(',').map(file =>
                                                        file.trim() ? `<a href="../uploads/${file.trim()}" target="_blank" class="attachment-link">
                                                            <i class="fas fa-file-image me-2"></i>${file.trim()}
                                                        </a>` : ''
                                                    ).join('')}
                                                </div>
                                            </div>
                                            ` : ''}
                                        </div>
                                    `;

                                    // Insert the report section
                                    const modalBody = document.getElementById('jobDetailsContent');
                                    const container = modalBody.querySelector('.modal-container');
                                    const lastRow = container.querySelector('.row:last-child');
                                    lastRow.querySelector('.col-12').appendChild(reportSection);
                                }
                            })
                            .catch(error => console.error('Error fetching report:', error));
                    }
                } else {
                    createReportBtn.style.display = 'inline-block';
                }

                new bootstrap.Modal('#jobDetailsModal').show();
            }

            // Function to open the report form modal
            function openReportForm() {
                // First check if the technician is primary for this job
                if (!currentJob.is_primary) {
                    // Show an error message
                    Swal.fire({
                        title: 'Access Denied',
                        text: 'Only the primary technician can submit reports for this job order.',
                        icon: 'warning',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                // Hide the job details modal
                bootstrap.Modal.getInstance(document.getElementById('jobDetailsModal')).hide();

                // Set the job order ID in the form
                document.getElementById('reportJobOrderId').value = currentJob.job_order_id;

                // Populate chemical dosage inputs if chemical recommendations exist
                populateChemicalDosageInputs(currentJob.chemical_recommendations);

                // Show the report form modal
                new bootstrap.Modal('#reportFormModal').show();
            }

            // Function to validate dosage input
            function validateDosageInput(input) {
                // Remove any non-numeric characters except decimal point
                let value = input.value.replace(/[^\d.]/g, '');

                // Ensure there's only one decimal point
                const parts = value.split('.');
                if (parts.length > 2) {
                    value = parts[0] + '.' + parts.slice(1).join('');
                }

                // If empty, set to 0
                if (value === '' || value === '.') {
                    value = '0';
                }

                // Update the input value
                input.value = value;

                // Check if it's a valid positive number
                const numValue = parseFloat(value);
                if (isNaN(numValue) || numValue < 0) {
                    input.classList.add('is-invalid');
                    return false;
                } else {
                    input.classList.remove('is-invalid');
                    return true;
                }
            }

            // Function to populate chemical dosage inputs
            function populateChemicalDosageInputs(chemicalRecommendationsJson) {
                const chemicalDosageSection = document.getElementById('chemicalDosageSection');
                const chemicalDosageInputs = document.getElementById('chemicalDosageInputs');

                // Clear previous inputs
                chemicalDosageInputs.innerHTML = '';

                try {
                    // If no chemical recommendations, hide the section
                    if (!chemicalRecommendationsJson) {
                        chemicalDosageSection.style.display = 'none';
                        return;
                    }

                    // Parse chemical recommendations
                    const chemicals = JSON.parse(chemicalRecommendationsJson);

                    // If no chemicals or empty array, hide the section
                    if (!Array.isArray(chemicals) || chemicals.length === 0) {
                        chemicalDosageSection.style.display = 'none';
                        return;
                    }

                    // Show the section
                    chemicalDosageSection.style.display = 'block';

                    // Create a div to wrap the table for better responsiveness
                    const tableWrapper = document.createElement('div');
                    tableWrapper.className = 'table-responsive';

                    // Create a table for chemical dosage inputs
                    const table = document.createElement('table');
                    table.className = 'table table-bordered';

                    // Create table header
                    const thead = document.createElement('thead');
                    thead.innerHTML = `
                        <tr class="table-light">
                            <th>Chemical Name</th>
                            <th>Type</th>
                            <th>Target Pest</th>
                            <th>Recommended Dosage</th>
                            <th>Actual Dosage Used <span class="text-danger">*</span></th>
                        </tr>
                    `;
                    table.appendChild(thead);

                    // Add the table to the wrapper
                    tableWrapper.appendChild(table);

                    // Create table body
                    const tbody = document.createElement('tbody');

                    // Add a row for each chemical
                    chemicals.forEach((chem, index) => {
                        const tr = document.createElement('tr');

                        // Create input field for actual dosage
                        const dosageInput = `
                            <div class="input-group input-group-sm">
                                <input type="text"
                                       class="form-control chemical-dosage-input"
                                       name="chemical_dosage[${index}]"
                                       value="${chem.dosage || '0'}"
                                       required
                                       style="min-width: 80px;"
                                       placeholder="Enter dosage"
                                       pattern="[0-9]+(\\.[0-9]+)?"
                                       inputmode="decimal"
                                       onchange="validateDosageInput(this)"
                                       onkeyup="validateDosageInput(this)">
                                <span class="input-group-text">${chem.dosage_unit || 'ml'}</span>
                                <input type="hidden" name="chemical_name[${index}]" value="${chem.name || ''}">
                                <input type="hidden" name="chemical_type[${index}]" value="${chem.type || ''}">
                                <input type="hidden" name="chemical_target_pest[${index}]" value="${chem.target_pest || ''}">
                                <input type="hidden" name="chemical_recommended_dosage[${index}]" value="${chem.dosage || '0'}">
                                <input type="hidden" name="chemical_dosage_unit[${index}]" value="${chem.dosage_unit || 'ml'}">
                            </div>
                        `;

                        // Add cells to the row
                        tr.innerHTML = `
                            <td class="text-nowrap"><strong>${chem.name || 'N/A'}</strong></td>
                            <td class="text-nowrap">${chem.type || 'N/A'}</td>
                            <td class="text-nowrap">${chem.target_pest || 'N/A'}</td>
                            <td class="text-nowrap">${chem.dosage || '0'} ${chem.dosage_unit || 'ml'}</td>
                            <td>${dosageInput}</td>
                        `;

                        tbody.appendChild(tr);
                    });

                    table.appendChild(tbody);
                    tableWrapper.appendChild(table);
                    chemicalDosageInputs.appendChild(tableWrapper);

                    // Add a note about the dosage
                    const note = document.createElement('div');
                    note.className = 'form-text mt-2';
                    note.innerHTML = 'Enter the actual amount of each chemical used during the treatment. The recommended dosage is provided as a reference.';
                    chemicalDosageInputs.appendChild(note);

                } catch (error) {
                    console.error('Error parsing chemical recommendations:', error);
                    chemicalDosageInputs.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Error loading chemical recommendations. Please continue with the report submission.
                        </div>
                    `;
                }
            }

            // Handle job order report form submission
            document.getElementById('jobOrderReportForm').addEventListener('submit', function(e) {
                e.preventDefault();

                // Client-side validation
                const observationNotes = this.querySelector('[name="observation_notes"]').value.trim();
                const recommendation = this.querySelector('[name="recommendation"]').value.trim();
                const attachments = this.querySelector('[name="attachments[]"]').files;

                // Get chemical dosage inputs if the section is visible
                const chemicalDosageSection = document.getElementById('chemicalDosageSection');
                let chemicalDosageInputs = [];

                if (chemicalDosageSection && chemicalDosageSection.style.display !== 'none') {
                    // Use a more specific selector to get the chemical dosage inputs
                    chemicalDosageInputs = Array.from(this.querySelectorAll('.chemical-dosage-input'));
                    console.log('Found chemical dosage inputs:', chemicalDosageInputs.length);

                    // Validate each input before proceeding
                    chemicalDosageInputs.forEach((input, i) => {
                        console.log(`Input ${i}:`, input.name, input.value, input.type);
                        validateDosageInput(input);
                    });
                }

                let isValid = true;
                const errors = [];

                if (!observationNotes) {
                    errors.push('Observation notes are required');
                    isValid = false;
                }

                if (!recommendation) {
                    errors.push('Recommendation is required');
                    isValid = false;
                }

                if (attachments.length === 0) {
                    errors.push('At least one attachment is required');
                    isValid = false;
                }

                // Validate chemical dosage inputs if they exist
                if (chemicalDosageInputs.length > 0) {
                    let hasInvalidDosage = false;

                    // Debug information
                    console.log('Validating chemical dosage inputs:', chemicalDosageInputs.length, 'inputs found');

                    // First, ensure all inputs have valid values by calling our validation function
                    chemicalDosageInputs.forEach((input, index) => {
                        console.log(`Input ${index} value:`, input.value, 'type:', typeof input.value);

                        // Validate the input
                        const isValid = validateDosageInput(input);
                        console.log(`Input ${index} validation result:`, isValid);

                        if (!isValid) {
                            hasInvalidDosage = true;
                        }
                    });

                    console.log('Has invalid dosage:', hasInvalidDosage);

                    if (hasInvalidDosage) {
                        errors.push('All chemical dosage values must be valid positive numbers');
                        isValid = false;
                    }
                }

                if (!isValid) {
                    Swal.fire({
                        title: 'Validation Error',
                        html: errors.map(error => `<div>${error}</div>`).join(''),
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';

                // Create FormData object
                const formData = new FormData(this);

                // Final validation of all chemical dosage inputs before submission
                if (chemicalDosageInputs.length > 0) {
                    console.log('Final validation before submission');
                    chemicalDosageInputs.forEach(input => {
                        // Make sure all inputs are valid
                        validateDosageInput(input);
                        console.log('Input value after final validation:', input.value);
                    });
                }

                // Submit the form data via AJAX
                fetch('api/job_order_report.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    // Hide the report form modal
                    bootstrap.Modal.getInstance(document.getElementById('reportFormModal')).hide();

                    if (data.success) {
                        console.log('Job order report submitted successfully:', data);

                        // Update the current job object with the report data
                        currentJob.status = 'completed';
                        currentJob.observation_notes = data.report.observation_notes;
                        currentJob.recommendation = data.report.recommendation;
                        currentJob.report_attachments = data.report.attachments;
                        currentJob.report_created_at = data.report.timestamp;
                        currentJob.chemical_usage = data.report.chemical_usage;

                        // Verify job status was updated
                        fetch(`api/check_job_status.php?job_order_id=${currentJob.job_order_id}`)
                            .then(response => response.json())
                            .then(statusData => {
                                console.log('Job status check:', statusData);
                                if (statusData.status !== 'completed') {
                                    console.warn('Job status was not updated to completed in the database!');
                                }
                            })
                            .catch(error => console.error('Error checking job status:', error));

                        // Show success modal
                        const successModal = new bootstrap.Modal(document.getElementById('reportSuccessModal'));
                        successModal.show();

                        // Move the job to the finished section without reloading the page
                        setTimeout(() => {
                            // Find the job card in the current section
                            const jobCards = document.querySelectorAll('.job-card');
                            let jobCard = null;

                            jobCards.forEach(card => {
                                // Check if this card belongs to the current job
                                if (card.onclick && card.onclick.toString().includes(currentJob.job_order_id)) {
                                    jobCard = card;
                                }
                            });

                            if (jobCard) {
                                // Get the parent container
                                const parentContainer = jobCard.closest('.col-md-4');
                                if (parentContainer) {
                                    // Remove the card from its current section
                                    parentContainer.remove();

                                    // Create a new card for the finished section
                                    const finishedSection = document.querySelector('.finished-job-orders .row');
                                    if (finishedSection) {
                                        // Create a new column
                                        const newCol = document.createElement('div');
                                        newCol.className = 'col-md-4 mb-3';

                                        // Update the current job with completion info
                                        currentJob.status = 'completed';
                                        currentJob.report_created_at = new Date().toISOString();

                                        // Create the card with updated status
                                        newCol.innerHTML = `
                                            <div class="card job-card" onclick="openJobDetails(${JSON.stringify(currentJob)})"
                                                 style="cursor: pointer; transition: transform 0.3s ease; border-left: 4px solid #28a745;"
                                                 onmouseover="this.style.transform='translateY(-5px)'"
                                                 onmouseout="this.style.transform='translateY(0)'">
                                                <div class="card-body">
                                                    <h5 class="card-title">${currentJob.client_name && currentJob.client_name.trim() ? currentJob.client_name : 'Unknown Client'}</h5>
                                                    <div class="d-flex gap-2 mb-2">
                                                        ${currentJob.kind_of_place ? `<span class="detail-badge">${currentJob.kind_of_place}</span>` : ''}
                                                        ${currentJob.type_of_work ? `<span class="detail-badge">${currentJob.type_of_work}</span>` : ''}
                                                    </div>
                                                    ${currentJob.preferred_date ? `
                                                    <p class="text-muted mb-1">
                                                        <i class="fas fa-calendar me-1"></i>
                                                        ${new Date(currentJob.preferred_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'})}
                                                    </p>` : ''}
                                                    ${currentJob.preferred_time ? `
                                                    <p class="text-muted mb-1">
                                                        <i class="fas fa-clock me-1"></i>
                                                        ${new Date('1970-01-01T' + currentJob.preferred_time).toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit', hour12: true})}
                                                    </p>` : ''}
                                                    ${currentJob.location_address ? `
                                                    <small class="text-primary">
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        ${currentJob.location_address}
                                                    </small>` : ''}
                                                    <div class="mt-2">
                                                        <span class="badge bg-success">Completed</span>
                                                    </div>
                                                </div>
                                            </div>
                                        `;

                                        // Add the new card to the beginning of the finished section (LIFO)
                                        if (finishedSection.children.length > 0 && !finishedSection.querySelector('.alert')) {
                                            finishedSection.insertBefore(newCol, finishedSection.firstChild);
                                        } else {
                                            // If the section is empty, just append
                                            finishedSection.appendChild(newCol);
                                        }

                                        // Check if the finished section was empty
                                        const emptyAlert = finishedSection.querySelector('.alert');
                                        if (emptyAlert) {
                                            emptyAlert.remove();
                                        }
                                    }

                                    // Check if the original section is now empty
                                    const originalSection = document.querySelector('.job-section:not(.finished-job-orders):not(.upcoming-job-orders):not(.past-due-job-orders) .row');
                                    if (originalSection && originalSection.children.length === 0) {
                                        originalSection.innerHTML = '<div class="col-12"><div class="alert alert-info">No job orders scheduled for today</div></div>';
                                    }

                                    // Check if the past due section is now empty
                                    const pastDueSection = document.querySelector('.past-due-job-orders .row');
                                    if (pastDueSection && pastDueSection.children.length === 0) {
                                        pastDueSection.innerHTML = '<div class="col-12"><div class="alert alert-info">No past due job orders</div></div>';
                                    }
                                }
                            }
                        }, 1500);
                    } else {
                        // Show error message
                        let errorMessage = data.message || 'Failed to submit report';
                        if (data.errors && data.errors.length > 0) {
                            errorMessage += '<br>' + data.errors.map(err => `- ${err}`).join('<br>');
                        }

                        Swal.fire({
                            title: 'Error',
                            html: errorMessage,
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });

                        // Reset button state
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);

                    // Show error message
                    Swal.fire({
                        title: 'Error',
                        text: 'An unexpected error occurred. Please try again.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });

                    // Reset button state
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                });
            });
    </script>

    <!-- Sidebar and Notification Scripts -->
    <script src="js/sidebar.js"></script>
    <!-- Enhanced Sidebar Fix Script - Loads after sidebar.js to fix responsive issues -->
    <script src="js/sidebar-fix.js"></script>
    <script src="js/notifications.js"></script>
    <script src="js/tools-checklist.js"></script>

    <!-- Debug script for sidebar toggle -->
    <script>
        // Add debug logging for sidebar toggle
        console.log('Job Order page loaded - Debug mode enabled for sidebar');
        document.addEventListener('DOMContentLoaded', function() {
            // Log sidebar state on page load
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.getElementById('menuToggle');

            if (sidebar && menuToggle) {
                console.log('Sidebar elements found on page load');
                console.log('Initial sidebar state:', sidebar.classList.contains('active') ? 'active' : 'inactive');
                console.log('Window width:', window.innerWidth);

                // Add additional click logging to menuToggle
                menuToggle.addEventListener('click', function() {
                    console.log('Menu toggle clicked directly from job_order.php');
                    console.log('Sidebar state after click:', sidebar.classList.contains('active') ? 'active' : 'inactive');
                });
            } else {
                console.error('Sidebar elements not found on page load');
            }
        });
    </script>
    <script>
        // Initialize notifications when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Fetch notifications
            fetchNotifications();

            // Set up date checking and auto-refresh
            setupDateRefresh();
        });

        // Function to set up date checking and auto-refresh
        function setupDateRefresh() {
            // Store the server date
            const serverDate = '<?= $today ?>';
            console.log('Server date:', serverDate);

            // Check date every minute
            setInterval(function() {
                checkDateAndRefresh(serverDate);
            }, 60000); // 60 seconds

            // Set up midnight refresh
            setupMidnightRefresh();
        }

        // Function to check if the date has changed and refresh if needed
        function checkDateAndRefresh(serverDate) {
            // Get current client date in YYYY-MM-DD format
            const clientDate = new Date().toISOString().split('T')[0];
            console.log('Checking date - Client:', clientDate, 'Server:', serverDate);

            // If the client date is different from the server date, refresh the page
            if (clientDate !== serverDate) {
                console.log('Date changed! Refreshing page...');
                // Force a full page reload to bypass cache
                window.location.href = window.location.pathname + '?refresh=' + new Date().getTime();
                return;
            }

            // Check if any upcoming job orders have today's date
            checkUpcomingJobOrdersForToday(clientDate);
        }

        // Function to check if any upcoming job orders have today's date
        function checkUpcomingJobOrdersForToday(todayDate) {
            // Get all upcoming job order cards
            const upcomingCards = document.querySelectorAll('.upcoming-job-orders .job-card');
            let needsRefresh = false;

            // Loop through each card and check the date
            upcomingCards.forEach(card => {
                // Find the date element (it's a p.text-muted with calendar icon)
                const dateElement = card.querySelector('p.text-muted i.fas.fa-calendar');
                if (dateElement && dateElement.parentElement) {
                    // Extract the date from the element (format is "MMM DD, YYYY")
                    const dateText = dateElement.parentElement.textContent.trim();
                    const dateParts = dateText.match(/([A-Za-z]{3})\s+(\d{1,2}),\s+(\d{4})/);

                    if (dateParts && dateParts.length === 4) {
                        const month = dateParts[1];
                        const day = dateParts[2].padStart(2, '0');
                        const year = dateParts[3];

                        // Convert to YYYY-MM-DD format for comparison
                        const monthNum = new Date(`${month} 1, 2000`).getMonth() + 1;
                        const formattedDate = `${year}-${monthNum.toString().padStart(2, '0')}-${day}`;

                        console.log('Checking upcoming job order date:', formattedDate, 'against today:', todayDate);

                        // If the date matches today's date, we need to refresh
                        if (formattedDate === todayDate) {
                            console.log('Found a job order that should be moved to today!');
                            needsRefresh = true;
                        }
                    }
                }
            });

            // Also check if any today's job orders should be moved to past due
            const todayCards = document.querySelectorAll('.job-section:not(.upcoming-job-orders):not(.past-due-job-orders):not(.finished-job-orders) .job-card');

            todayCards.forEach(card => {
                const dateElement = card.querySelector('p.text-muted i.fas.fa-calendar');
                if (dateElement && dateElement.parentElement) {
                    const dateText = dateElement.parentElement.textContent.trim();
                    const dateParts = dateText.match(/([A-Za-z]{3})\s+(\d{1,2}),\s+(\d{4})/);

                    if (dateParts && dateParts.length === 4) {
                        const month = dateParts[1];
                        const day = dateParts[2].padStart(2, '0');
                        const year = dateParts[3];

                        // Convert to YYYY-MM-DD format for comparison
                        const monthNum = new Date(`${month} 1, 2000`).getMonth() + 1;
                        const formattedDate = `${year}-${monthNum.toString().padStart(2, '0')}-${day}`;

                        // If the date is before today's date, it should be in past due
                        if (formattedDate < todayDate) {
                            console.log('Found a job order that should be moved to past due!');
                            needsRefresh = true;
                        }
                    }
                }
            });

            // If we found a job order that needs to be moved, refresh the page
            if (needsRefresh) {
                console.log('Refreshing page to update job orders...');
                window.location.href = window.location.pathname + '?refresh=' + new Date().getTime();
            }
        }

        // Function to refresh the page at midnight
        function setupMidnightRefresh() {
            const now = new Date();
            const tomorrow = new Date(now);
            tomorrow.setDate(tomorrow.getDate() + 1);
            tomorrow.setHours(0, 0, 10, 0); // 00:00:10 - slight delay to ensure we're past midnight

            const msUntilMidnight = tomorrow - now;
            console.log('Setting up midnight refresh in', Math.floor(msUntilMidnight/1000/60), 'minutes');

            // Set timeout to refresh at midnight
            setTimeout(function() {
                console.log('Midnight reached! Refreshing page...');
                // Force a full page reload to bypass cache
                window.location.href = window.location.pathname + '?refresh=' + new Date().getTime();
            }, msUntilMidnight);
        }
    </script>
</body>
</html>