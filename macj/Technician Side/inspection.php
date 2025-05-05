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

// Clear any output buffering and set no-cache headers
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Fetch appointments using the old method - always use this for now
// We'll add the is_primary flag with a default value of 1 (true)
$stmt = $conn->prepare("
    SELECT a.*, c.first_name, c.last_name, c.contact_number, c.email, 1 as is_primary
    FROM appointments a
    JOIN clients c ON a.client_id = c.client_id
    WHERE a.technician_id = ? AND a.status != 'completed'
    ORDER BY a.preferred_date ASC
");
$stmt->bind_param("i", $technician_id);

$stmt->execute();
$result = $stmt->get_result();

$todayJobs = [];
$upcomingJobs = [];
$finishedJobs = [];

while ($row = $result->fetch_assoc()) {
    $row['client_name'] = $row['first_name'] . ' ' . $row['last_name'];

    // Direct string comparison for dates in YYYY-MM-DD format
    // This is the simplest and most reliable method for this specific format
    if ($row['preferred_date'] === $today) {
        $todayJobs[] = $row;
        // Debug output
        error_log("Added to TODAY: Client {$row['client_name']}, Date: {$row['preferred_date']}, Today: {$today}");
    } elseif ($row['preferred_date'] > $today) {
        $upcomingJobs[] = $row;
        // Debug output
        error_log("Added to UPCOMING: Client {$row['client_name']}, Date: {$row['preferred_date']}, Today: {$today}");
    } else {
        // Debug output for past dates
        error_log("PAST DATE: Client {$row['client_name']}, Date: {$row['preferred_date']}, Today: {$today}");
    }
}

// Fetch completed jobs
$stmt = $conn->prepare("
    SELECT a.*, c.first_name, c.last_name, c.contact_number, c.email,
           r.end_time, r.area, r.notes, r.recommendation, r.attachments, r.pest_types, r.problem_area, r.created_at as report_date,
           1 as is_primary
    FROM appointments a
    JOIN clients c ON a.client_id = c.client_id
    LEFT JOIN assessment_report r ON a.appointment_id = r.appointment_id
    WHERE a.technician_id = ? AND a.status = 'completed'
    ORDER BY a.preferred_date DESC
");
$stmt->bind_param("i", $technician_id);
$stmt->execute();
$finishedJobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
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
    <link rel="stylesheet" href="css/sidebar-fix.css">
    <style>
        /* Hide the scheduled for badge */
        .scheduled-date {
            display: none !important;
        }

        .badge-custom {
            background-color: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-bottom: 10px;
            display: inline-block;
        }
    </style>
</head>
<body>
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
            <a href="inspection.php" class="active">
                <i class="fas fa-clipboard-list fa-icon"></i>
                Inspection Board
            </a>
            <a href="job_order.php">
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
            <h1><i class="fas fa-clipboard-list"></i> Inspection Board</h1>
        </div>
        <!-- Today's Jobs -->
        <div class="job-section">
            <h3><i class="fas fa-calendar-day"></i> Today's Inspection</h3>
            <div class="row">
                <?php foreach ($todayJobs as $job): ?>
                <div class="col-md-4 mb-3">
                    <div class="card job-card" style="cursor: pointer; transition: transform 0.3s ease;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($job['client_name']) ?></h5>
                            <p class="text-muted"><i class="fas fa-clock"></i> <?= date('h:i A', strtotime($job['preferred_time'])) ?></p>
                            <span class="badge bg-primary mb-2">Today's Schedule</span>
                            <?php if (isset($job['is_primary']) && $job['is_primary']): ?>
                            <span class="badge bg-info mb-2">Primary Technician</span>
                            <?php else: ?>
                            <span class="badge bg-secondary mb-2">Secondary Technician</span>
                            <?php endif; ?>
                            <button class="btn btn-primary btn-sm" onclick="openDetails(<?= htmlspecialchars(json_encode($job)) ?>)">
                                View Details
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($todayJobs)): ?>
                <div class="col-12">
                    <div class="alert alert-info">No inspections scheduled for today</div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upcoming Jobs -->
        <div class="job-section upcoming-inspections">
            <h3><i class="fas fa-calendar-alt"></i> Upcoming Inspection</h3>
            <div class="row">
                <?php foreach ($upcomingJobs as $job): ?>
                <div class="col-md-4 mb-3">
                    <div class="card job-card" style="opacity: 0.8; background-color: #f8f9fa;">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($job['client_name']) ?></h5>
                            <p class="text-muted"><?= $job['preferred_date'] ?></p>
                            <p class="text-muted"><i class="fas fa-clock"></i> <?= date('h:i A', strtotime($job['preferred_time'])) ?></p>
                            <span class="badge bg-secondary">Upcoming - Not Yet Available</span>
                            <?php if (isset($job['is_primary']) && $job['is_primary']): ?>
                            <span class="badge bg-info">Primary Technician</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Secondary Technician</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($upcomingJobs)): ?>
                <div class="col-12">
                    <div class="alert alert-info">No upcoming inspections scheduled</div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Finished Jobs -->
        <div class="job-section">
            <h3><i class="fas fa-check-circle"></i> Finished Inspection</h3>
            <div class="row">
                <?php foreach ($finishedJobs as $job): ?>
                <div class="col-md-4 mb-3">
                    <div class="card job-card" onclick="openFinishedDetails(<?= htmlspecialchars(json_encode($job)) ?>)">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($job['client_name']) ?></h5>
                            <span class="badge badge-custom">Completed</span>
                            <?php if($job['end_time']): ?>
                                <p class="text-muted mb-0">Report submitted: <?= date('M j, Y', strtotime($job['report_date'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal">
        <div class="modal-dialog modal-lg modal-zoom">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Inspection Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalDetailsContent">
                    <!-- Dynamic content loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" onclick="openReportForm()" id="sendReportBtn">
                        <i class="fas fa-paper-plane"></i> Send Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Form Modal -->
    <div class="modal fade" id="reportModal" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-zoom">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Submit Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="reportForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="appointment_id" id="reportAppointmentId">
                        <!-- End time is now automatically recorded upon submission -->
                        <div class="mb-3">
                            <label>Area (m²)</label>
                            <input type="number" step="0.01" name="area" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Pest Types</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="pest_types[]" value="Flies" id="pestFlies">
                                        <label class="form-check-label" for="pestFlies">Flies</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="pest_types[]" value="Ants" id="pestAnts">
                                        <label class="form-check-label" for="pestAnts">Ants</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="pest_types[]" value="Cockroaches" id="pestCockroaches">
                                        <label class="form-check-label" for="pestCockroaches">Cockroaches</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="pest_types[]" value="Bed Bugs" id="pestBedBugs">
                                        <label class="form-check-label" for="pestBedBugs">Bed Bugs</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="pest_types[]" value="Mice/Rats" id="pestRats">
                                        <label class="form-check-label" for="pestRats">Mice/Rats</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="pest_types[]" value="Termites" id="pestTermites">
                                        <label class="form-check-label" for="pestTermites">Termites</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="pest_types[]" value="Mosquitoes" id="pestMosquitoes">
                                        <label class="form-check-label" for="pestMosquitoes">Mosquitoes</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="pest_types[]" value="Disinfect Area" id="pestDisinfect">
                                        <label class="form-check-label" for="pestDisinfect">Disinfect Area</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="pest_types[]" value="Grass" id="pestGrass">
                                        <label class="form-check-label" for="pestGrass">Grass</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="pest_types[]" value="Others" id="pestOthers" onchange="toggleOtherPestField()">
                                        <label class="form-check-label" for="pestOthers">Others</label>
                                    </div>
                                </div>
                            </div>
                            <div id="otherPestTypeContainer" style="display: none; margin-top: 8px;" class="row">
                                <div class="col-12">
                                    <input type="text" class="form-control" name="other_pest_type" id="otherPestType" placeholder="Please specify other pest types">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Problem Area</label>
                            <input type="text" name="problem_area" class="form-control" placeholder="e.g. Kitchen, Living Room, Bedroom, etc.">
                        </div>
                        <div class="mb-3">
                            <label>Additional Notes</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label>Recommendation</label>
                            <textarea name="recommendation" class="form-control" rows="3" placeholder="Enter your recommendations for pest control treatment"></textarea>
                        </div>
                        <div class="mb-3">
                            <label>Upload Images</label>
                            <input type="file" name="attachments[]" class="form-control" multiple accept="image/*">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Submit Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Finished Insepction Details Modal -->
    <div class="modal fade" id="finishedDetailsModal">
        <div class="modal-dialog modal-lg modal-zoom">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Completed Inspection Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="finishedModalContent">
                    <!-- Dynamic content loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Confirmation Modal -->
    <div class="modal fade" id="reportConfirmationModal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Report Submission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="fw-bold">Please verify that your report is complete and accurate before final submission.</p>
                    <p>Once submitted, this report cannot be edited and will be sent to the client and admin.</p>

                    <div class="alert alert-info">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">Please confirm you have:</h6>
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" id="checkAll">
                                <label class="form-check-label" for="checkAll"><strong>Check All</strong></label>
                            </div>
                        </div>
                        <!-- End time is now automatically recorded upon submission -->
                        <div class="form-check mb-2">
                            <input class="form-check-input confirmation-checkbox" type="checkbox" id="checkArea">
                            <label class="form-check-label" for="checkArea">Measured and entered the correct area</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input confirmation-checkbox" type="checkbox" id="checkPestTypes">
                            <label class="form-check-label" for="checkPestTypes">Selected all relevant pest types</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input confirmation-checkbox" type="checkbox" id="checkProblemArea">
                            <label class="form-check-label" for="checkProblemArea">Specified the problem area</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input confirmation-checkbox" type="checkbox" id="checkNotes">
                            <label class="form-check-label" for="checkNotes">Added detailed notes about the inspection</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input confirmation-checkbox" type="checkbox" id="checkRecommendation">
                            <label class="form-check-label" for="checkRecommendation">Provided treatment recommendations</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input confirmation-checkbox" type="checkbox" id="checkAttachments">
                            <label class="form-check-label" for="checkAttachments">Attached all relevant photos/documents</label>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-3">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Go Back & Edit</button>
                        <button type="button" class="btn btn-success" id="finalSubmitBtn" disabled>
                            <i class="fas fa-paper-plane me-1"></i> Submit Final Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <!-- Sidebar Fix Script -->
    <script src="js/sidebar-fix.js"></script>
    <script>
        let currentJob = null;

        // Function to toggle the visibility of the "Other" pest type field
        function toggleOtherPestField() {
            const otherCheckbox = document.getElementById('pestOthers');
            const otherFieldContainer = document.getElementById('otherPestTypeContainer');

            if (otherCheckbox.checked) {
                otherFieldContainer.style.display = 'block';
            } else {
                otherFieldContainer.style.display = 'none';
                document.getElementById('otherPestType').value = '';
            }
        }

        // Reset the form when the modal is closed
        document.getElementById('reportModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('reportForm').reset();
            document.getElementById('otherPestTypeContainer').style.display = 'none';
        });

        function openDetails(job) {
            currentJob = job;
            const content = `
                <h5>INFORMATION OF THE CLIENT</h5>
                <p><strong>Client Name:</strong> ${job.client_name}</p>
                <p><strong>Date:</strong> ${job.preferred_date}</p>
                <p><strong>Time:</strong> ${job.preferred_time}</p>
                <p><strong>Location:</strong> ${job.location_address}</p>
                <p><strong>Type of Place:</strong> ${job.kind_of_place}</p>
                <p><strong>Contact:</strong> ${job.contact_number}</p>
                <p><strong>Pest Problems:</strong> ${job.pest_problems || 'None specified'}</p>
                <p><strong>Notes:</strong> ${job.notes || 'N/A'}</p>
            `;
            document.getElementById('modalDetailsContent').innerHTML = content;

            // Always show the Send Report button for now
            const sendReportBtn = document.getElementById('sendReportBtn');
            sendReportBtn.style.display = 'inline-block';

            new bootstrap.Modal('#detailsModal').show();
        }

        function openReportForm() {
            new bootstrap.Modal('#detailsModal').hide();
            document.getElementById('reportAppointmentId').value = currentJob.appointment_id;
            new bootstrap.Modal('#reportModal').show();
        }

        // Store the form data globally for later submission
        let reportFormData = null;

        // Handle report form submission
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            e.preventDefault();

            // Check if "Others" is selected but the other field is empty
            const othersChecked = document.getElementById('pestOthers').checked;
            const otherFieldValue = document.getElementById('otherPestType').value.trim();

            if (othersChecked && otherFieldValue === '') {
                // Show a warning and focus on the field
                Swal.fire({
                    title: 'Missing Information',
                    text: 'You selected "Others" for pest types. Please specify what other pest types were found.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                document.getElementById('otherPestType').focus();
                return;
            }

            // Store the form data for later use
            reportFormData = new FormData(this);

            // Get form values for validation
            const area = this.querySelector('[name="area"]').value;
            const notes = this.querySelector('[name="notes"]').value;
            const recommendation = this.querySelector('[name="recommendation"]').value;
            const problemArea = this.querySelector('[name="problem_area"]').value;
            const pestTypesChecked = this.querySelectorAll('[name="pest_types[]"]:checked');
            const attachments = this.querySelector('[name="attachments[]"]').files;

            // Show the confirmation modal
            const confirmationModal = new bootstrap.Modal(document.getElementById('reportConfirmationModal'));
            confirmationModal.show();

            // Reset checkboxes
            document.querySelectorAll('#reportConfirmationModal .form-check-input').forEach(checkbox => {
                checkbox.checked = false;
            });

            // Update the confirmation dialog with field status
            const areaCheck = document.getElementById('checkArea');
            const pestTypesCheck = document.getElementById('checkPestTypes');
            const problemAreaCheck = document.getElementById('checkProblemArea');
            const notesCheck = document.getElementById('checkNotes');
            const recommendationCheck = document.getElementById('checkRecommendation');
            const attachmentsCheck = document.getElementById('checkAttachments');

            // Add visual indicators for filled fields

            if (area) {
                areaCheck.parentElement.classList.add('text-success');
                areaCheck.parentElement.querySelector('label').innerHTML =
                    `Measured and entered the correct area <strong>(${area} m²)</strong>`;
            }

            if (pestTypesChecked && pestTypesChecked.length > 0) {
                pestTypesCheck.parentElement.classList.add('text-success');

                // Create a list of selected pest types, including the "Other" value if specified
                let selectedPests = Array.from(pestTypesChecked).map(cb => cb.value);

                // If "Others" is selected and the other field has a value, include it in the display
                if (selectedPests.includes('Others') && document.getElementById('otherPestType').value) {
                    const otherIndex = selectedPests.indexOf('Others');
                    selectedPests[otherIndex] = 'Others: ' + document.getElementById('otherPestType').value;
                }

                pestTypesCheck.parentElement.querySelector('label').innerHTML =
                    `Selected all relevant pest types <strong>(${selectedPests.join(', ')})</strong>`;
            }

            if (problemArea) {
                problemAreaCheck.parentElement.classList.add('text-success');
                problemAreaCheck.parentElement.querySelector('label').innerHTML =
                    `Specified the problem area <strong>(${problemArea})</strong>`;
            }

            if (notes && notes.length > 10) {
                notesCheck.parentElement.classList.add('text-success');
                notesCheck.parentElement.querySelector('label').innerHTML =
                    `Added detailed notes about the inspection <strong>(${notes.length} characters)</strong>`;
            }

            if (recommendation && recommendation.length > 10) {
                recommendationCheck.parentElement.classList.add('text-success');
                recommendationCheck.parentElement.querySelector('label').innerHTML =
                    `Provided treatment recommendations <strong>(${recommendation.length} characters)</strong>`;
            }

            if (attachments && attachments.length > 0) {
                attachmentsCheck.parentElement.classList.add('text-success');
                attachmentsCheck.parentElement.querySelector('label').innerHTML =
                    `Attached all relevant photos/documents <strong>(${attachments.length} files)</strong>`;
            }

            // Disable the final submit button until all checkboxes are checked
            document.getElementById('finalSubmitBtn').disabled = true;
        });

        // Handle "Check All" checkbox
        document.getElementById('checkAll').addEventListener('change', function() {
            // Get all confirmation checkboxes
            const confirmationCheckboxes = document.querySelectorAll('.confirmation-checkbox');

            // Set all checkboxes to the same state as the "Check All" checkbox
            confirmationCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });

            // Enable or disable the final submit button
            document.getElementById('finalSubmitBtn').disabled = !this.checked;
        });

        // Handle individual checkbox changes in the confirmation modal
        document.querySelectorAll('.confirmation-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                // Check if all confirmation checkboxes are checked
                const allConfirmationChecked = Array.from(
                    document.querySelectorAll('.confirmation-checkbox')
                ).every(cb => cb.checked);

                // Update the "Check All" checkbox
                document.getElementById('checkAll').checked = allConfirmationChecked;

                // Enable or disable the final submit button
                document.getElementById('finalSubmitBtn').disabled = !allConfirmationChecked;
            });
        });

        // Reset confirmation modal when it's closed
        document.getElementById('reportConfirmationModal').addEventListener('hidden.bs.modal', function() {
            // Reset all checkboxes including "Check All"
            document.querySelectorAll('#reportConfirmationModal .form-check-input').forEach(checkbox => {
                checkbox.checked = false;
            });

            // Reset visual indicators
            document.querySelectorAll('#reportConfirmationModal .form-check').forEach(checkItem => {
                checkItem.classList.remove('text-success');
            });

            // Reset labels
            document.getElementById('checkArea').parentElement.querySelector('label').textContent = 'Measured and entered the correct area';
            document.getElementById('checkPestTypes').parentElement.querySelector('label').textContent = 'Selected all relevant pest types';
            document.getElementById('checkProblemArea').parentElement.querySelector('label').textContent = 'Specified the problem area';
            document.getElementById('checkNotes').parentElement.querySelector('label').textContent = 'Added detailed notes about the inspection';
            document.getElementById('checkRecommendation').parentElement.querySelector('label').textContent = 'Provided treatment recommendations';
            document.getElementById('checkAttachments').parentElement.querySelector('label').textContent = 'Attached all relevant photos/documents';

            // Disable the final submit button
            document.getElementById('finalSubmitBtn').disabled = true;
            document.getElementById('finalSubmitBtn').innerHTML = '<i class="fas fa-paper-plane me-1"></i> Submit Final Report';
        });

        // Handle final submission
        document.getElementById('finalSubmitBtn').addEventListener('click', function() {
            // Show loading state
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Submitting...';

            // Submit the form data
            fetch('submit_report.php', {
                method: 'POST',
                body: reportFormData
            })
            .then(response => response.json())
            .then(data => {
                // Hide the confirmation modal
                bootstrap.Modal.getInstance(document.getElementById('reportConfirmationModal')).hide();

                if (data.success) {
                    // Reset the form fields
                    document.getElementById('reportForm').reset();
                    document.getElementById('otherPestTypeContainer').style.display = 'none';

                    // Show success message with more details
                    Swal.fire({
                        title: 'Success!',
                        text: 'Your report has been submitted successfully.',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        // Reload the page to refresh the data
                        window.location.reload();
                    });
                } else {
                    // Show error message
                    Swal.fire({
                        title: 'Error',
                        text: data.message || 'Failed to submit report',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);

                // Hide the confirmation modal
                bootstrap.Modal.getInstance(document.getElementById('reportConfirmationModal')).hide();

                // Show error message
                Swal.fire({
                    title: 'Error',
                    text: 'An unexpected error occurred. Please try again.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
        });
        function openFinishedDetails(job) {
            const attachments = job.attachments ? job.attachments.split(',') : [];
            const attachmentList = attachments.map(file =>
                `<a href="../uploads/${file}" target="_blank" class="list-group-item list-group-item-action">
                    <i class="fas fa-paperclip me-2"></i>${file}
                </a>`
            ).join('');

            const content = `
                <div class="row">
                    <div class="col-md-6">
                        <h4>INFORMATION OF THE CLIENT</h4>
                        <p><strong>Client Name:</strong> ${job.client_name}</p>
                        <p><strong>Date:</strong> ${job.preferred_date}</p>
                        <p><strong>Time:</strong> ${job.preferred_time}</p>
                        <p><strong>Location:</strong> ${job.location_address}</p>
                        <p><strong>Type of Place:</strong> ${job.kind_of_place}</p>
                        <p><strong>Contact:</strong> ${job.contact_number}</p>
                        <p><strong>Pest Problems:</strong> ${job.pest_problems || 'None specified'}</p>
                        <hr>
                        <h4>Assessment Report</h4>
                        <p><strong>Completion Time:</strong> ${job.end_time}</p>
                        <p><strong>Area Treated:</strong> ${job.area} m²</p>
                        <p><strong>Pest Types Found:</strong> ${job.pest_types || 'None specified'}</p>
                        <p><strong>Problem Area:</strong> ${job.problem_area || 'None specified'}</p>
                        <p><strong>Report Date:</strong> ${new Date(job.report_date).toLocaleDateString()}</p>
                        <p><strong>Technician Notes:</strong></p>
                        <div class="border p-2 mb-3">${job.notes || 'No additional notes'}</div>
                        <p><strong>Recommendation:</strong></p>
                        <div class="border p-2 mb-3">${job.recommendation || 'No recommendations provided'}</div>
                        <h5>Attachments:</h5>
                        <div class="list-group">${attachmentList}</div>
                    </div>
                </div>
            `;

            document.getElementById('finishedModalContent').innerHTML = content;
            new bootstrap.Modal('#finishedDetailsModal').show();
        }
    </script>

    <!-- Sidebar and Notification Scripts -->
    <script src="js/sidebar.js"></script>
    <script src="js/notifications.js"></script>
    <script src="js/tools-checklist.js"></script>
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

            // Check if any upcoming inspections have today's date
            checkUpcomingInspectionsForToday(clientDate);
        }

        // Function to check if any upcoming inspections have today's date
        function checkUpcomingInspectionsForToday(todayDate) {
            // Get all upcoming inspection cards
            const upcomingCards = document.querySelectorAll('.upcoming-inspections .job-card');
            let needsRefresh = false;

            // Loop through each card and check the date
            upcomingCards.forEach(card => {
                // Find the date element (it's a p.text-muted that contains the date)
                const dateElement = card.querySelector('p.text-muted:first-of-type');
                if (dateElement) {
                    // Extract the date from the element (format is YYYY-MM-DD)
                    const dateText = dateElement.textContent.trim();

                    console.log('Checking upcoming inspection date:', dateText, 'against today:', todayDate);

                    // If the date matches today's date, we need to refresh
                    if (dateText === todayDate) {
                        console.log('Found an inspection that should be moved to today!');
                        needsRefresh = true;
                    }
                }
            });

            // If we found an inspection that needs to be moved, refresh the page
            if (needsRefresh) {
                console.log('Refreshing page to update inspections...');
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