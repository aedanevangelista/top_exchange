<?php
// Set timezone to ensure correct date calculations
date_default_timezone_set('Asia/Manila'); // Philippines timezone

session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'client') {
    header("Location: SignIn.php");
    exit;
}
include '../db_connect.php';
include '../notification_functions.php';

// Get client info
$client_id = $_SESSION['client_id'];
$stmt = $conn->prepare("SELECT * FROM clients WHERE client_id = ?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$client = $stmt->get_result()->fetch_assoc();

// Extract coordinates from location_address if they exist and are not already in location_lat/location_lng
if (empty($client['location_lat']) || empty($client['location_lng'])) {
    // Check if coordinates are embedded in the address string
    if (preg_match('/\[([-\d\.]+),([-\d\.]+)\]$/', $client['location_address'], $matches)) {
        $client['location_lat'] = $matches[1];
        $client['location_lng'] = $matches[2];

        // Update the database with the extracted coordinates
        $updateCoords = $conn->prepare("UPDATE clients SET location_lat = ?, location_lng = ? WHERE client_id = ?");
        $updateCoords->bind_param("ssi", $client['location_lat'], $client['location_lng'], $client_id);
        $updateCoords->execute();
        $updateCoords->close();
    }
}

// Clean the location address for display (remove coordinates)
$client['display_address'] = preg_replace('/\[[-\d\.]+,[-\d\.]+\]$/', '', $client['location_address']);

// Get current month and year
// Use DateTime with explicit timezone to ensure correct date
$now = new DateTime('now', new DateTimeZone('Asia/Manila'));
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)$now->format('n');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)$now->format('Y');

// Function to generate calendar days
function generateCalendarDays($month, $year) {
    global $conn;
    $firstDay = mktime(0, 0, 0, $month, 1, $year);
    $daysInMonth = date('t', $firstDay);
    $monthName = date('F', $firstDay);
    $firstDayOfWeek = date('w', $firstDay);

    $days = [];

    // Add empty cells for days before the first day of the month
    for ($i = 0; $i < $firstDayOfWeek; $i++) {
        $days[] = '<div class="calendar-day empty"></div>';
    }

    // Get appointments for this month to show event indicators
    $startDate = sprintf("%04d-%02d-01", $year, $month);
    $endDate = sprintf("%04d-%02d-%02d", $year, $month, $daysInMonth);

    $sql = "SELECT preferred_date, COUNT(*) as count FROM appointments WHERE preferred_date BETWEEN ? AND ? GROUP BY preferred_date";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $appointmentDates = [];
    while ($row = $result->fetch_assoc()) {
        $appointmentDates[$row['preferred_date']] = $row['count'];
    }

    // Add days of the month
    // Get today's date with explicit timezone to ensure accuracy
    $todayObj = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $today = $todayObj->format('Y-m-d');
    for ($i = 1; $i <= $daysInMonth; $i++) {
        $date = sprintf("%04d-%02d-%02d", $year, $month, $i);
        $isPast = $date < $today;
        $isToday = $date === $today;

        // Check if the date is a Sunday (0 = Sunday, 1 = Monday, etc.)
        $dateObj = new DateTime($date);
        $isSunday = $dateObj->format('w') == 0;

        // Determine class based on date status
        // Mark past dates and Sundays as unavailable for scheduling
        // Allow scheduling for today (unless it's a Sunday)
        if ($isPast) {
            $class = 'calendar-day past';
        } elseif ($isSunday) {
            $class = 'calendar-day sunday';
        } else {
            $class = 'calendar-day';
        }

        // Add a special class for today to highlight it
        if ($isToday) {
            $class .= ' today';
        }

        // Check if there are appointments on this date
        $hasAppointments = isset($appointmentDates[$date]);

        // Create the day element with event indicators if needed
        $dayContent = $i;
        if ($hasAppointments) {
            $dayContent .= '<div class="event-dots"><span class="event-indicator appointment"></span></div>';
        }

        // Add a note for Sundays
        if ($isSunday && !$isPast) {
            $dayContent .= '<div class="sunday-note">Closed</div>';
        }

        $days[] = '<div class="' . $class . '" data-date="' . $date . '">' . $dayContent . '</div>';
    }

    return [
        'monthName' => $monthName,
        'year' => $year,
        'days' => $days
    ];
}

$calendarData = generateCalendarDays($currentMonth, $currentYear);
$nextMonth = $currentMonth == 12 ? 1 : $currentMonth + 1;
$nextYear = $currentMonth == 12 ? $currentYear + 1 : $currentYear;
$prevMonth = $currentMonth == 1 ? 12 : $currentMonth - 1;
$prevYear = $currentMonth == 1 ? $currentYear - 1 : $currentYear;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Appointment | MacJ Pest Control</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/client-common.css">
    <link rel="stylesheet" href="css/calendar.css">
    <link rel="stylesheet" href="css/form-validation-fix.css">
    <link rel="stylesheet" href="css/content-spacing-fix.css">
    <link rel="stylesheet" href="css/notifications.css">
    <link rel="stylesheet" href="css/sidebar-fix.css">
    <!-- Leaflet.js for OpenStreetMap -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="anonymous"></script>
    <style>
        /* Notification Dropdown Override */
        .notification-dropdown.show {
            display: block !important;
            z-index: 9999;
        }

        /* Calendar Styles */
        .calendar-container {
            display: flex;
            flex-wrap: wrap;
            gap: var(--spacing-xl);
            margin-top: var(--spacing-lg);
        }

        .calendar-card {
            flex: 1;
            min-width: 320px;
            background: var(--card-color);
            border-radius: var(--border-radius-md);
            box-shadow: var(--shadow-md);
            padding: var(--spacing-lg);
            transition: var(--transition-normal);
        }

        .calendar-card:hover {
            box-shadow: var(--shadow-lg);
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-md);
            padding-bottom: var(--spacing-sm);
            border-bottom: 1px solid var(--border-color);
        }

        .calendar-nav {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }

        .calendar-nav h3 {
            margin: 0;
            color: var(--primary-color);
            font-weight: 600;
        }

        .calendar-nav button {
            background: none;
            border: none;
            color: var(--primary-color);
            cursor: pointer;
            font-size: var(--font-size-lg);
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition-fast);
        }

        .calendar-nav button:hover {
            background-color: rgba(67, 97, 238, 0.1);
            transform: scale(1.1);
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }

        .calendar-day {
            padding: var(--spacing-sm);
            text-align: center;
            border-radius: var(--border-radius-sm);
            font-weight: 500;
            transition: var(--transition-fast);
        }

        .calendar-day:not(.empty):not(:nth-child(-n+7)) {
            border: 1px solid var(--border-color);
            cursor: pointer;
        }

        .calendar-day:nth-child(-n+7) {
            font-weight: 600;
            color: var(--primary-color);
            padding-bottom: var(--spacing-md);
        }

        .calendar-day.empty {
            background: transparent;
            border: none;
        }

        .calendar-day.past {
            opacity: 0.5;
            background-color: #f0f0f0;
            color: #999;
            cursor: not-allowed !important;
            text-decoration: line-through;
        }

        /* Style for Sundays - not available for scheduling */
        .calendar-day.sunday {
            background-color: #ffeeee;
            color: #d9534f;
            cursor: not-allowed !important;
            position: relative;
        }

        .sunday-note {
            position: absolute;
            bottom: 2px;
            left: 0;
            right: 0;
            font-size: 8px;
            color: #d9534f;
            font-weight: bold;
        }

        /* Special style for today's date - now available for scheduling */
        <?php
        $todayForCSS = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
        ?>
        .calendar-day.today {
            background-color: #e6f7ff; /* Light blue background */
            border: 2px solid #4361ee;
            position: relative;
            font-weight: bold;
        }

        .calendar-day.today::after {
            content: 'Today';
            position: absolute;
            bottom: 2px;
            left: 0;
            right: 0;
            font-size: 8px;
            color: #4361ee;
        }

        .calendar-day:not(.empty):not(:nth-child(-n+7)):not(.past):hover {
            background-color: rgba(67, 97, 238, 0.1);
            border-color: var(--primary-color);
        }

        .calendar-day.selected {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: scale(1.05);
            box-shadow: var(--shadow-sm);
        }

        /* Time Slots */
        .time-selection {
            flex: 1;
            min-width: 320px;
        }

        /* Map Styles */
        #map-container {
            height: 400px; /* Increased height for better visibility */
            width: 100%;
            margin-bottom: var(--spacing-md);
            border-radius: var(--border-radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            position: relative;
            border: 1px solid var(--border-color);
            display: block !important; /* Force display */
        }

        #map {
            height: 100% !important;
            width: 100% !important;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 1;
            background-color: #f8f9fa; /* Light background color */
            display: block !important; /* Force display */
        }

        /* Fix for Leaflet controls */
        .leaflet-control-container .leaflet-top,
        .leaflet-control-container .leaflet-bottom {
            z-index: 10;
        }

        /* Fix for Leaflet attribution */
        .leaflet-control-attribution {
            z-index: 10;
            background-color: rgba(255, 255, 255, 0.8) !important;
        }

        /* Fix for map tiles */
        .leaflet-tile-container img {
            width: 256px !important;
            height: 256px !important;
        }

        /* Fix for map container in appointment form */
        #appointmentForm #map-container,
        #appointmentForm #map {
            display: block !important;
            visibility: visible !important;
            height: 400px !important;
            width: 100% !important;
            opacity: 1 !important;
            z-index: 1 !important;
        }

        /* Custom marker styles */
        .custom-marker {
            background-color: var(--primary-color);
            border-radius: 50%;
            border: 2px solid white;
            width: 16px;
            height: 16px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
        }

        .custom-marker::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            border-left: 8px solid transparent;
            border-right: 8px solid transparent;
            border-top: 8px solid var(--primary-color);
        }

        .marker-pulse {
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.3); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }

        #location-search {
            width: 100%;
            padding: var(--spacing-sm);
            margin-bottom: var(--spacing-sm);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-sm);
        }

        .map-controls {
            margin-bottom: var(--spacing-md);
            position: relative;
        }

        /* Map control buttons */
        .map-controls .btn-group {
            display: flex;
            width: 100%;
            margin-bottom: var(--spacing-sm);
        }

        .map-controls .btn-group button {
            flex: 1;
            white-space: nowrap;
            text-align: center;
            padding: 6px 12px;
            font-size: 14px;
            border-radius: 0;
        }

        .map-controls .btn-group button:first-child {
            border-top-left-radius: var(--border-radius-sm);
            border-bottom-left-radius: var(--border-radius-sm);
        }

        .map-controls .btn-group button:last-child {
            border-top-right-radius: var(--border-radius-sm);
            border-bottom-right-radius: var(--border-radius-sm);
        }

        /* Responsive adjustments for small screens */
        @media (max-width: 576px) {
            .map-controls .btn-group {
                flex-wrap: wrap;
            }

            .map-controls .btn-group button {
                flex: 1 0 auto;
                min-width: 33%;
            }
        }

        /* Search results dropdown */
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background-color: white;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-md);
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
        }

        .search-results ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .search-results li {
            padding: 10px 15px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            font-size: 14px;
        }

        .search-results li:last-child {
            border-bottom: none;
        }

        .search-results li:hover {
            background-color: var(--light-bg-color);
        }

        /* Loading indicator */
        .loading {
            background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiIgd2lkdGg9IjMyIiBoZWlnaHQ9IjMyIiBmaWxsPSIjMzMzIj48cGF0aCBvcGFjaXR5PSIuMjUiIGQ9Ik0xNiAwIEExNiAxNiAwIDAgMCAxNiAzMiBBMTYgMTYgMCAwIDAgMTYgMCBNMTYgNCBBMTIgMTIgMCAwIDEgMTYgMjggQTEyIDEyIDAgMCAxIDE2IDQiLz48cGF0aCBkPSJNMTYgMCBBMTYgMTYgMCAwIDEgMzIgMTYgTDI4IDE2IEExMiAxMiAwIDAgMCAxNiA0eiI+PGFuaW1hdGVUcmFuc2Zvcm0gYXR0cmlidXRlTmFtZT0idHJhbnNmb3JtIiB0eXBlPSJyb3RhdGUiIGZyb209IjAgMTYgMTYiIHRvPSIzNjAgMTYgMTYiIGR1cj0iMC44cyIgcmVwZWF0Q291bnQ9ImluZGVmaW5pdGUiIC8+PC9wYXRoPjwvc3ZnPg==');
            background-position: right 10px center;
            background-repeat: no-repeat;
            background-size: 20px 20px;
        }

        .time-slots-card {
            background: var(--card-color);
            border-radius: var(--border-radius-md);
            box-shadow: var(--shadow-md);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }

        .time-slots-header {
            margin-bottom: var(--spacing-md);
            padding-bottom: var(--spacing-sm);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .time-slots-header h3 {
            margin: 0;
            color: var(--primary-color);
            font-weight: 600;
        }

        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-top: var(--spacing-md);
        }

        .time-slot {
            padding: var(--spacing-sm);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            text-align: center;
            cursor: pointer;
            transition: var(--transition-fast);
            font-weight: 500;
        }

        .time-slot:hover:not(.booked) {
            border-color: var(--primary-color);
            background-color: rgba(67, 97, 238, 0.1);
        }

        .time-slot.selected {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .time-slot.booked {
            background-color: rgba(255, 51, 51, 0.1);
            border-color: var(--error-color);
            color: var(--error-color);
            cursor: not-allowed;
            opacity: 0.7;
        }

        /* Style for past time slots */
        .time-slot.past-time {
            background-color: rgba(150, 150, 150, 0.1);
            border-color: #999;
            color: #999;
            cursor: not-allowed;
            opacity: 0.7;
            text-decoration: line-through;
        }

        /* Appointment Form */
        .appointment-form-card {
            background: var(--card-color);
            border-radius: var(--border-radius-md);
            box-shadow: var(--shadow-md);
            padding: var(--spacing-lg);
            display: none;
        }

        .appointment-form-header {
            margin-bottom: var(--spacing-md);
            padding-bottom: var(--spacing-sm);
            border-bottom: 1px solid var(--border-color);
        }

        .appointment-form-header h3 {
            margin: 0;
            color: var(--primary-color);
            font-weight: 600;
        }

        .appointment-summary {
            background-color: rgba(67, 97, 238, 0.1);
            border-radius: var(--border-radius-sm);
            padding: var(--spacing-md);
            margin-bottom: var(--spacing-md);
        }

        .appointment-summary p {
            margin: var(--spacing-xs) 0;
            display: flex;
            align-items: center;
        }

        .appointment-summary i {
            margin-right: var(--spacing-sm);
            color: var(--primary-color);
        }

        .form-control-static {
            padding: 0.375rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            background-color: #f8f9fa;
            min-height: 38px;
            display: flex;
            align-items: center;
        }

        .pest-problem-checkboxes {
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            background-color: #f8f9fa;
            margin-bottom: 1rem;
        }

        .form-check {
            margin-bottom: 0.5rem;
        }

        .form-check-input {
            margin-right: 0.5rem;
        }

        .form-check-label {
            cursor: pointer;
        }

        .loading-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: var(--spacing-xl);
            color: var(--text-secondary);
        }

        .loading-indicator i {
            margin-right: var(--spacing-sm);
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .calendar-container {
                flex-direction: column;
            }
        }
    </style>
</head>
<body class="schedule">
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
                <a href="schedule.php" class="active">
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
                <a href="contract.php">
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
                    <h1>Schedule an Appointment</h1>
                    <p>Book a pest control service at your preferred date and time</p>
                    <div class="alert alert-info" style="margin-top: 10px;">
                        <i class="fas fa-info-circle"></i> Note: You can schedule appointments for today and future dates. Past dates are not available for scheduling.
                    </div>
                    <div class="alert alert-warning" style="margin-top: 10px;">
                        <i class="fas fa-calendar-times"></i> <strong>Important:</strong> We are closed on Sundays. Please select Monday through Saturday for your appointment.
                    </div>
                    <?php
                    // Debug info only shown when debug parameter is present
                    $showDebug = isset($_GET['debug']);
                    if ($showDebug):
                    ?>
                    <div class="alert alert-secondary" style="margin-top: 10px; font-size: 12px;">
                        <strong>Debug Info:</strong><br>
                        Today's date (date function): <?= date('Y-m-d') ?><br>
                        Today's date (DateTime): <?= (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d') ?><br>
                        Today's date (explicit): <?= $todayForCSS ?><br>
                        Server time: <?= date('Y-m-d H:i:s') ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div>
                    <p class="text-light"><?= date('l, F j, Y') ?></p>
                </div>
            </div>

            <div class="calendar-container">
                <div class="calendar-card">
                    <div class="calendar-header">
                        <div class="calendar-nav">
                            <button onclick="navigateMonth(<?= $prevMonth ?>, <?= $prevYear ?>)" title="Previous Month">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <h3><?= $calendarData['monthName'] ?> <?= $calendarData['year'] ?></h3>
                            <button onclick="navigateMonth(<?= $nextMonth ?>, <?= $nextYear ?>)" title="Next Month">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                        <?php if ($showDebug): ?>
                        <div style="font-size: 10px; color: #999; margin-top: 5px;">
                            Calendar month/year: <?= $currentMonth ?>/<?= $currentYear ?><br>
                            Today's date: <?= $todayForCSS ?>
                        </div>
                        <?php endif; ?>
                        <button onclick="navigateToday()" class="today-button" title="Go to Today">
                            <i class="fas fa-calendar-day"></i> Today
                        </button>
                    </div>
                    <div class="calendar-grid">
                        <div class="calendar-day">Sun</div>
                        <div class="calendar-day">Mon</div>
                        <div class="calendar-day">Tue</div>
                        <div class="calendar-day">Wed</div>
                        <div class="calendar-day">Thu</div>
                        <div class="calendar-day">Fri</div>
                        <div class="calendar-day">Sat</div>

                        <!-- Calendar days -->
                        <?= implode('', $calendarData['days']) ?>
                    </div>
                </div>

                <div class="time-selection">
                    <div class="time-slots-card">
                        <div class="time-slots-header">
                            <h3>Available Time Slots</h3>
                            <span id="selectedDateDisplay">Select a date</span>
                        </div>
                        <div id="timeSlotsContainer">
                            <div class="loading-indicator">
                                <i class="fas fa-info-circle"></i> Please select a date to view available times
                            </div>
                        </div>
                    </div>

                    <div class="appointment-form-card" id="appointmentForm" style="display: none;">
                        <div class="appointment-form-header">
                            <h3>Appointment Details</h3>
                        </div>

                        <div class="appointment-summary">
                            <p><i class="fas fa-calendar-day"></i> <span id="summaryDate"></span></p>
                            <p><i class="fas fa-clock"></i> <span id="summaryTime"></span></p>
                            <p><i class="fas fa-building"></i> Type of Place: <?= htmlspecialchars($client['type_of_place'] ?? 'Not set') ?></p>
                            <p style="display: none;"><i class="fas fa-bug"></i> Pest Problems: <span id="summaryPestProblems">None selected</span></p>
                        </div>

                        <form id="bookingForm">
                            <div class="form-group">
                                <label for="location" class="form-label">Location Address</label>
                                <div class="map-controls">
                                    <input type="text" id="location-search" class="form-control" placeholder="Search for an address">
                                </div>
                                <div id="map-container">
                                    <div id="map"></div>
                                </div>
                                <input type="text" id="location" name="location" class="form-control" value="<?= htmlspecialchars($client['display_address'] ?? '') ?>" required>
                                <input type="hidden" id="location-lat" name="location_lat" value="<?= htmlspecialchars($client['location_lat'] ?? '') ?>">
                                <input type="hidden" id="location-lng" name="location_lng" value="<?= htmlspecialchars($client['location_lng'] ?? '') ?>">
                                <!-- Remove duplicate validation message -->
                                <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        // Remove any duplicate validation messages for this field
                                        const locationInput = document.getElementById('location');
                                        const parent = locationInput.parentNode;
                                        const feedbackDivs = parent.querySelectorAll('.invalid-feedback');

                                        // If there are multiple feedback divs, remove all but the first one
                                        if (feedbackDivs.length > 1) {
                                            for (let i = 1; i < feedbackDivs.length; i++) {
                                                parent.removeChild(feedbackDivs[i]);
                                            }
                                        }
                                    });
                                </script>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Type of Place</label>
                                <div class="form-control-static"><?= htmlspecialchars($client['type_of_place'] ?? 'Not set') ?></div>
                                <input type="hidden" id="place_type" name="place_type" value="<?= htmlspecialchars($client['type_of_place'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">What kind of pest problem are you encountering?</label>
                                <div class="pest-problem-checkboxes">
                                    <div class="row">
                                        <div class="col-md-4 col-6">
                                            <div class="form-check">
                                                <input class="form-check-input pest-problem" type="checkbox" id="pest_flies" name="pest_problems[]" value="Flies">
                                                <label class="form-check-label" for="pest_flies">Flies</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4 col-6">
                                            <div class="form-check">
                                                <input class="form-check-input pest-problem" type="checkbox" id="pest_mice" name="pest_problems[]" value="Mice/Rats">
                                                <label class="form-check-label" for="pest_mice">Mice/Rats</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4 col-6">
                                            <div class="form-check">
                                                <input class="form-check-input pest-problem" type="checkbox" id="pest_ants" name="pest_problems[]" value="Ants">
                                                <label class="form-check-label" for="pest_ants">Ants</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4 col-6">
                                            <div class="form-check">
                                                <input class="form-check-input pest-problem" type="checkbox" id="pest_termites" name="pest_problems[]" value="Termites (White Ants)">
                                                <label class="form-check-label" for="pest_termites">Termites (White Ants)</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4 col-6">
                                            <div class="form-check">
                                                <input class="form-check-input pest-problem" type="checkbox" id="pest_cockroaches" name="pest_problems[]" value="Cockroaches">
                                                <label class="form-check-label" for="pest_cockroaches">Cockroaches</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4 col-6">
                                            <div class="form-check">
                                                <input class="form-check-input pest-problem" type="checkbox" id="pest_mosquitoes" name="pest_problems[]" value="Mosquitoes">
                                                <label class="form-check-label" for="pest_mosquitoes">Mosquitoes</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4 col-6">
                                            <div class="form-check">
                                                <input class="form-check-input pest-problem" type="checkbox" id="pest_bedbugs" name="pest_problems[]" value="Bed Bugs">
                                                <label class="form-check-label" for="pest_bedbugs">Bed Bugs</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4 col-6">
                                            <div class="form-check">
                                                <input class="form-check-input pest-problem" type="checkbox" id="pest_grass" name="pest_problems[]" value="Grass Problems">
                                                <label class="form-check-label" for="pest_grass">Grass Problems</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4 col-6">
                                            <div class="form-check">
                                                <input class="form-check-input pest-problem" type="checkbox" id="pest_disinfect" name="pest_problems[]" value="Disinfect Area">
                                                <label class="form-check-label" for="pest_disinfect">Disinfect Area</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4 col-6">
                                            <div class="form-check">
                                                <input class="form-check-input pest-problem" type="checkbox" id="pest_other" name="pest_problems[]" value="Other">
                                                <label class="form-check-label" for="pest_other">Other (please specifiy below on notes)</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="notes" class="form-label">Additional Notes</label>
                                <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Provide any additional details about your pest problem or special instructions"></textarea>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-calendar-check mr-2"></i> Confirm Appointment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/main.js"></script>
    <script src="js/sidebar.js"></script>
    <!-- Fixed sidebar script -->
    <script src="js/sidebar-fix.js"></script>
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

        let selectedDate = null;
        let selectedTime = null;
        let refreshInterval = null;
        let map = null; // Global map variable
        let marker = null; // Global marker variable

        // Navigate between months
        function navigateMonth(month, year) {
            window.location.href = `schedule.php?month=${month}&year=${year}`;
        }

        // Navigate to today
        function navigateToday() {
            const today = new Date();
            const month = today.getMonth() + 1; // JavaScript months are 0-indexed
            const year = today.getFullYear();
            window.location.href = `schedule.php?month=${month}&year=${year}`;
        }

        // Format date for display
        function formatDisplayDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        // Format time for display
        function formatDisplayTime(timeString) {
            const time = new Date(`1970-01-01T${timeString}`);
            return time.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        // Handle date selection - exclude Sundays
        $(document).on('click', '.calendar-day:not(.empty):not(:nth-child(-n+7)):not(.past):not(.sunday)', function() {
            selectedDate = $(this).data('date');
            $('.calendar-day').removeClass('selected');
            $(this).addClass('selected');

            // Update selected date display
            $('#selectedDateDisplay').text(formatDisplayDate(selectedDate));

            // Clear any existing refresh interval
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }

            // Show loading indicator
            $('#timeSlotsContainer').html(`
                <div class="loading-indicator">
                    <i class="fas fa-spinner fa-spin"></i> Loading available time slots...
                </div>
            `);

            // Fetch times immediately
            fetchAvailableTimes(selectedDate);

            // Set up periodic refresh (every 30 seconds)
            refreshInterval = setInterval(function() {
                fetchAvailableTimes(selectedDate);
            }, 30000);
        });

        // Add a message when clicking on a Sunday
        $(document).on('click', '.calendar-day.sunday:not(.past)', function() {
            showToast('Sorry, we are closed on Sundays. Please select another day.', 'warning');
        });

        // Fetch available time slots
        function fetchAvailableTimes(date) {
            if (!date) {
                $('#timeSlotsContainer').html(`
                    <div class="loading-indicator">
                        <i class="fas fa-exclamation-circle"></i> Error: No date selected. Please select a date.
                    </div>
                `);
                return;
            }

            $.ajax({
                url: 'get_times.php',
                method: 'POST',
                data: { date: date },
                dataType: 'json',
                success: function(response) {
                    const bookedTimes = response.booked;
                    const timeSlots = generateTimeSlots(bookedTimes);
                    $('#timeSlotsContainer').html(timeSlots);

                    // If a time was previously selected, re-select it if still available
                    if (selectedTime && !bookedTimes.includes(selectedTime)) {
                        $(`.time-slot[data-time="${selectedTime}"]`).addClass('selected');
                        updateAppointmentSummary();
                        $('#appointmentForm').show();
                    } else if (selectedTime && bookedTimes.includes(selectedTime)) {
                        selectedTime = null;
                        $('#appointmentForm').hide();
                        showToast('Your previously selected time is no longer available.', 'warning');
                    }
                },
                error: function(xhr, status, error) {
                    $('#timeSlotsContainer').html(`
                        <div class="loading-indicator">
                            <i class="fas fa-exclamation-circle"></i> Error loading time slots. Please try again.
                        </div>
                    `);
                }
            });
        }

        // Generate time slots HTML
        function generateTimeSlots(bookedTimes) {
            // Check if bookedTimes is valid
            if (!bookedTimes || typeof bookedTimes !== 'object') {
                return `
                    <div class="loading-indicator">
                        <i class="fas fa-exclamation-circle"></i> Error loading time slots. Please try again.
                    </div>
                `;
            }

            let html = '<div class="time-slots">';
            let hasAvailableSlots = false;

            // Check if the selected date is today
            const today = new Date();
            const selectedDateObj = new Date(selectedDate);
            const isToday = selectedDateObj.toDateString() === today.toDateString();

            // Get current time plus 30 minutes buffer for same-day appointments
            const currentHour = today.getHours();
            const currentMinute = today.getMinutes();
            const currentTimePlus30 = new Date(today.getTime() + 30 * 60000); // Add 30 minutes
            const bufferHour = currentTimePlus30.getHours();
            const bufferMinute = currentTimePlus30.getMinutes();

            // If it's today, add a note about the 30-minute buffer
            if (isToday) {
                html = `
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle mr-2"></i>
                        For same-day appointments, you can only select times at least 30 minutes from now.
                    </div>
                    <div class="time-slots">
                `;
            }

            for (let hour = 7; hour <= 21; hour++) {
                for (let minute = 0; minute < 60; minute += 60) {
                    const time = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}:00`;
                    const displayTime = formatDisplayTime(time);
                    const isBooked = bookedTimes.includes(time);

                    // For today, check if the time slot is in the past (plus 30 min buffer)
                    let isPastTime = false;
                    if (isToday) {
                        if (hour < bufferHour || (hour === bufferHour && minute < bufferMinute)) {
                            isPastTime = true;
                        }
                    }

                    // Determine if the slot is available
                    const isAvailable = !isBooked && !isPastTime;
                    if (isAvailable) hasAvailableSlots = true;

                    // Set appropriate classes
                    let slotClass = '';
                    if (isBooked) slotClass = 'booked';
                    if (isPastTime) slotClass = 'booked past-time'; // Use booked class for past times too

                    // Add a title/tooltip to explain why the slot is unavailable
                    let titleAttr = '';
                    if (isBooked) titleAttr = 'This time slot is already booked';
                    if (isPastTime) titleAttr = 'This time has already passed or is too soon to book';

                    html += `
                        <div class="time-slot ${slotClass}"
                             data-time="${time}"
                             ${!isAvailable ? 'disabled' : ''}
                             ${titleAttr ? `title="${titleAttr}"` : ''}>
                            ${displayTime}
                        </div>
                    `;
                }
            }

            html += '</div>';

            if (!hasAvailableSlots) {
                return `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        No available time slots for this date. Please select another date.
                    </div>
                `;
            }

            return html;
        }

        // Update appointment summary
        function updateAppointmentSummary() {
            if (selectedDate && selectedTime) {
                $('#summaryDate').text(formatDisplayDate(selectedDate));
                $('#summaryTime').text(formatDisplayTime(selectedTime));

                // Update pest problems summary
                updatePestProblemsSummary();
            }
        }

        // Update pest problems summary
        function updatePestProblemsSummary() {
            const selectedPestProblems = [];
            $('.pest-problem:checked').each(function() {
                selectedPestProblems.push($(this).val());
            });

            // Add or update pest problems in summary
            const pestSummaryElement = $('#summaryPestProblems');
            if (selectedPestProblems.length > 0) {
                pestSummaryElement.text(selectedPestProblems.join(', '));
                pestSummaryElement.parent().show();
            } else {
                pestSummaryElement.text('None selected');
                pestSummaryElement.parent().hide();
            }
        }

        // Handle pest problem checkbox changes
        $(document).on('change', '.pest-problem', function() {
            updatePestProblemsSummary();
        });

        // Handle time slot selection
        $(document).on('click', '.time-slot:not(.booked)', function() {
            $('.time-slot').removeClass('selected');
            $(this).addClass('selected');
            selectedTime = $(this).data('time');

            // Update appointment summary
            updateAppointmentSummary();

            // Show appointment form
            $('#appointmentForm').fadeIn(300);

            // Scroll to appointment form
            $('html, body').animate({
                scrollTop: $('#appointmentForm').offset().top - 20
            }, 500);
        });

        // Handle search box input
        $(document).on('keypress', '#location-search', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                const searchQuery = $(this).val().trim();
                if (searchQuery) {
                    searchLocation(searchQuery);
                }
            }
        });

        // Add search and clear buttons - this is now handled in the document ready function

        // Handle search button click
        $(document).on('click', '#search-button', function() {
            const searchQuery = $('#location-search').val().trim();
            if (searchQuery) {
                searchLocation(searchQuery);
            }
        });

        // Handle clear button click
        $(document).on('click', '#clear-map-button', function() {
            // Clear the search box
            $('#location-search').val('');

            // Clear any markers and overlays
            if (marker) {
                map.removeLayer(marker);
                marker = null;
            }

            // Remove all other layers (circles, etc.)
            map.eachLayer(function(layer) {
                if (layer instanceof L.Circle || layer instanceof L.Marker) {
                    map.removeLayer(layer);
                }
            });

            // Reset the map view
            map.setView([14.5995, 120.9842], 13);

            // Clear the location fields
            $('#location').val('');
            $('#location-lat').val('');
            $('#location-lng').val('');

            // Remove any search results
            $('.search-results').remove();
        });


        // Initialize the map
        function initMap() {
            try {
                // Get the map element
                const mapElement = document.getElementById('map');
                if (!mapElement) {
                    return;
                }

                // Make sure the map container is visible
                mapElement.style.display = 'block';
                document.getElementById('map-container').style.display = 'block';

                // Remove any existing map instance
                if (map) {
                    map.remove();
                }

                // Force the map container to be visible before initialization
                $('#map').css({
                    'height': '400px',
                    'width': '100%',
                    'display': 'block',
                    'visibility': 'visible',
                    'opacity': '1'
                });

                $('#map-container').css({
                    'height': '400px',
                    'width': '100%',
                    'display': 'block',
                    'visibility': 'visible',
                    'opacity': '1'
                });

                // Get client location coordinates if available
                const clientLat = $('#location-lat').val() || 14.5995;
                const clientLng = $('#location-lng').val() || 120.9842;
                const initialZoom = $('#location-lat').val() ? 15 : 13; // Zoom in more if we have coordinates

                // Create map centered on client location or Manila, Philippines as fallback
                map = L.map('map', {
                    center: [clientLat, clientLng],
                    zoom: initialZoom,
                    zoomControl: true,
                    scrollWheelZoom: true,
                    preferCanvas: true, // Use canvas for better performance
                    fadeAnimation: false, // Disable animations for better performance
                    markerZoomAnimation: false // Disable marker animations
                });

                // Use OpenStreetMap tiles directly (more reliable)
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    maxZoom: 19,
                    crossOrigin: true
                }).addTo(map);

                // Add click event to map
                map.on('click', function(e) {
                    setMarker(e.latlng);
                    updateLocationFields(e.latlng);
                });

                // Force a resize after initialization and again after a delay
                map.invalidateSize({ animate: false, pan: false });

                // Add a marker for the client's location if coordinates are available
                if ($('#location-lat').val() && $('#location-lng').val()) {
                    // Create custom icon for the marker
                    const customIcon = L.icon({
                        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
                        shadowSize: [41, 41]
                    });

                    // Add marker at the client's location
                    marker = L.marker([clientLat, clientLng], {
                        title: 'Your Location',
                        draggable: true,
                        icon: customIcon
                    }).addTo(map);
                    marker.bindPopup('Your Location<br>Drag to adjust').openPopup();

                    // Add a circle to highlight the area
                    L.circle([clientLat, clientLng], {
                        color: '#3B82F6',
                        weight: 2,
                        fillColor: '#3B82F6',
                        fillOpacity: 0.15,
                        radius: 200 // 200 meters radius
                    }).addTo(map);

                    // Handle marker drag end event
                    marker.on('dragend', function() {
                        const newPosition = marker.getLatLng();
                        updateLocationFields(newPosition);
                    });
                } else {
                    // Add a marker in the center as a fallback
                    const centerMarker = L.marker([14.5995, 120.9842], {
                        title: 'Map Center'
                    }).addTo(map);
                    centerMarker.bindPopup('Map is working! Click anywhere to select a location.').openPopup();
                }

                // Multiple resize attempts with increasing delays
                setTimeout(function() {
                    map.invalidateSize({ animate: false, pan: false });
                }, 500);

                setTimeout(function() {
                    map.invalidateSize({ animate: false, pan: false });
                }, 1000);

                setTimeout(function() {
                    map.invalidateSize({ animate: false, pan: false });
                }, 2000);

            } catch (error) {
                // Try a simpler initialization as fallback
                try {
                    // Clear the map container first
                    $('#map').html('');

                    // Simple initialization
                    map = L.map('map').setView([14.5995, 120.9842], 13);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                    }).addTo(map);

                    // Force resize
                    setTimeout(function() {
                        map.invalidateSize();
                    }, 1000);
                } catch (fallbackError) {
                    // Show error message in map container
                    $('#map').html('<div style="padding: 20px; text-align: center;"><strong>Error loading map.</strong><br>Please refresh the page and try again.</div>');
                }
            }
        }

        // Set marker on map
        function setMarker(latlng) {

            // Remove existing marker if any
            if (marker) {
                map.removeLayer(marker);
            }

            // Create custom icon for the marker
            const customIcon = L.icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            });

            // Create new marker with custom icon
            marker = L.marker(latlng, {
                icon: customIcon,
                draggable: true, // Allow marker to be dragged
                title: 'Drag to adjust location'
            }).addTo(map);

            // Add popup to the marker
            marker.bindPopup('Selected Location<br>Drag to adjust').openPopup();

            // Handle marker drag end event
            marker.on('dragend', function(event) {
                const newPosition = marker.getLatLng();
                updateLocationFields(newPosition);
            });

            // Center map on marker with animation
            map.setView(latlng, 15, { // Zoom level 15 for better detail
                animate: true,
                duration: 1
            });

            // Remove any existing circles
            map.eachLayer(function(layer) {
                if (layer instanceof L.Circle) {
                    map.removeLayer(layer);
                }
            });

            // Add a circle to highlight the area
            L.circle(latlng, {
                color: '#FF4136',
                weight: 2,
                fillColor: '#FF4136',
                fillOpacity: 0.15,
                radius: 200 // 200 meters radius for better visibility
            }).addTo(map);
        }

        // Update location fields in the form
        function updateLocationFields(latlng) {

            // Format coordinates with 6 decimal places
            const lat = latlng.lat.toFixed(6);
            const lng = latlng.lng.toFixed(6);

            // Update the hidden fields with coordinates
            $('#location-lat').val(lat);
            $('#location-lng').val(lng);

            // Set a temporary value while we fetch the address
            $('#location').val(`Fetching address for: ${lat}, ${lng}...`);

            // Use Nominatim for reverse geocoding
            $.ajax({
                url: `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`,
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data && data.display_name) {
                        // Update the location field with the found address
                        $('#location').val(data.display_name);
                    } else {
                        // Fallback to coordinates if no address found
                        $('#location').val(`Location at: ${lat}, ${lng}`);
                    }
                },
                error: function(xhr, status, error) {
                    // Fallback to coordinates if geocoding fails
                    $('#location').val(`Location at: ${lat}, ${lng}`);
                }
            });
        }

        // Search for a location
        function searchLocation(query) {

            // Show loading indicator
            $('#location-search').addClass('loading');
            $('#search-button').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Searching...');

            // Use Nominatim for geocoding (OpenStreetMap's geocoding service)
            $.ajax({
                url: `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5`,
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    // Reset loading state
                    $('#location-search').removeClass('loading');
                    $('#search-button').prop('disabled', false).text('Search');

                    if (data && data.length > 0) {
                        // If multiple results, show them in a dropdown
                        if (data.length > 1) {
                            // Create a dropdown for search results
                            let resultsHtml = '<div class="search-results"><ul>';
                            data.forEach(function(result, index) {
                                resultsHtml += `<li data-lat="${result.lat}" data-lon="${result.lon}">${result.display_name}</li>`;
                            });
                            resultsHtml += '</ul></div>';

                            // Remove any existing results dropdown
                            $('.search-results').remove();

                            // Add the new dropdown after the search box
                            $('.map-controls').append(resultsHtml);

                            // Add click handler for results
                            $('.search-results li').on('click', function() {
                                const lat = $(this).data('lat');
                                const lon = $(this).data('lon');
                                const latlng = L.latLng(lat, lon);

                                // Set marker and update fields
                                setMarker(latlng);
                                updateLocationFields(latlng);

                                // Remove the results dropdown
                                $('.search-results').remove();
                            });
                        } else {
                            // If only one result, use it directly
                            const result = data[0];
                            const latlng = L.latLng(result.lat, result.lon);

                            // Set marker and update fields
                            setMarker(latlng);

                            // Update location field with the found address
                            $('#location').val(result.display_name);
                            $('#location-lat').val(result.lat);
                            $('#location-lng').val(result.lon);
                        }
                    } else {
                        alert('Location not found. Please try a different search term.');
                    }
                },
                error: function(xhr, status, error) {
                    // Reset loading state
                    $('#location-search').removeClass('loading');
                    $('#search-button').prop('disabled', false).text('Search');

                    alert('Error searching for location. Please try again.');
                }
            });
        }

        // This handler has been moved to the document ready function

        // Handle form submission
        $('#bookingForm').addClass('ajax-form').submit(function(e) {
            e.preventDefault();

            // Get the form element
            const form = this;

            // If the form is already submitting, prevent another submission
            if ($(form).hasClass('is-submitting')) {
                return false;
            }

            // Mark the form as submitting
            $(form).addClass('is-submitting');

            // Disable submit button to prevent duplicate submissions
            const submitBtn = $(this).find('button[type="submit"]');
            const originalBtnHtml = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Processing...');
            submitBtn[0].dataset.originalHtml = originalBtnHtml; // Store original HTML for reset function

            // Collect selected pest problems
            const selectedPestProblems = [];
            $('.pest-problem:checked').each(function() {
                selectedPestProblems.push($(this).val());
            });

            // Create form data object
            const formData = {
                client_id: <?= $client['client_id'] ?>,
                client_name: "<?= $client['first_name'] ?> <?= $client['last_name'] ?>",
                email: "<?= $client['email'] ?>",
                contact_number: "<?= $client['contact_number'] ?>",
                preferred_date: selectedDate,
                preferred_time: selectedTime,
                location_address: $('#location').val(),
                location_lat: $('#location-lat').val(),
                location_lng: $('#location-lng').val(),
                kind_of_place: $('#place_type').val(),
                notes: $('#notes').val()
            };

            // Add pest problems as a serialized array
            if (selectedPestProblems.length > 0) {
                formData.pest_problems = selectedPestProblems;
            }

            $.ajax({
                url: 'save_appointment.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        showToast(response.message || 'Appointment scheduled successfully!', 'success');

                        // Reset form
                        $('#bookingForm')[0].reset();

                        // Refresh the time slots after successful booking
                        fetchAvailableTimes(selectedDate);

                        // Hide appointment form
                        $('#appointmentForm').fadeOut(300);

                        // Reset selected time
                        selectedTime = null;
                    } else {
                        // Show error message from server
                        showToast(response.message || 'Error scheduling appointment', 'error');
                    }

                    // Reset form submission state using our global function
                    window.resetFormSubmitState(form);
                },
                error: function(xhr) {
                    let errorMsg = 'Error scheduling appointment';

                    // Try to parse error message from JSON response
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response && response.message) {
                            errorMsg = response.message;
                        }
                    } catch (e) {
                        // If parsing fails, use the raw response text or a default message
                        errorMsg = xhr.responseText || 'Unknown error occurred';
                    }

                    // Show error message
                    showToast(errorMsg, 'error');

                    // Reset form submission state using our global function
                    window.resetFormSubmitState(form);
                },
                complete: function() {
                    // As an extra safety measure, ensure the button is re-enabled
                    if (submitBtn.prop('disabled')) {
                        submitBtn.prop('disabled', false).html(originalBtnHtml);
                    }
                }
            });
        });

        // Initialize page
        $(document).ready(function() {

            // Add direct click handler to each calendar day
            $('.calendar-day:not(.empty):not(:nth-child(-n+7)):not(.past)').each(function() {
                $(this).on('click', function() {

                    // Manually trigger the date selection
                    const date = $(this).data('date');
                    if (date) {
                        // Simulate a click on the date to use the existing handler
                        $(this).trigger('click');
                    }
                });
            });

            // Add map control buttons in a single button group
            $('.map-controls').append('<div class="btn-group mt-2"><button type="button" id="search-button" class="btn btn-primary btn-sm">Search</button><button type="button" id="clear-map-button" class="btn btn-outline-secondary btn-sm">Clear</button><button type="button" id="current-location-button" class="btn btn-secondary btn-sm">Use My Location</button></div>');

            // Initialize map immediately with multiple attempts
            setTimeout(function() {
                initMap();
            }, 500);

            // Try again after a longer delay in case the first attempt fails
            setTimeout(function() {
                if (!map || !map._loaded) {
                    initMap();
                }
            }, 1500);

            // Final attempt after an even longer delay
            setTimeout(function() {
                if (!map || !map._loaded) {
                    initMap();
                } else {
                    // If map exists but might not be fully rendered, force a resize
                    map.invalidateSize({ animate: false, pan: false });
                }
            }, 3000);

            // Also initialize map when appointment form becomes visible
            $(document).on('click', '.time-slot:not(.booked)', function() {
                // Show appointment form
                $('#appointmentForm').fadeIn(300);

                // Scroll to appointment form
                $('html, body').animate({
                    scrollTop: $('#appointmentForm').offset().top - 20
                }, 500);

                // Make sure map container is visible
                $('#map-container').css({
                    'height': '400px',
                    'width': '100%',
                    'display': 'block',
                    'visibility': 'visible',
                    'opacity': '1'
                });

                $('#map').css({
                    'height': '400px',
                    'width': '100%',
                    'display': 'block',
                    'visibility': 'visible',
                    'opacity': '1'
                });

                // Multiple attempts to refresh the map
                setTimeout(function() {
                    if (map) {
                        map.invalidateSize({ animate: false, pan: false });
                    } else {
                        initMap();
                    }
                }, 500);

                setTimeout(function() {
                    if (map) {
                        map.invalidateSize({ animate: false, pan: false });
                    }
                }, 1000);

                setTimeout(function() {
                    if (map) {
                        map.invalidateSize({ animate: false, pan: false });
                    }
                }, 2000);
            });

            // If there's a date in the URL, select it
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('date')) {
                const dateParam = urlParams.get('date');
                const dateElement = $(`.calendar-day[data-date="${dateParam}"]`);
                if (dateElement.length) {
                    dateElement.click();
                }
            }

            // Always select the next available date (not today, not Sunday) by default if no date is selected
            setTimeout(function() {
                if (!selectedDate) {
                    // Select the next available date (first date that's not past, not Sunday, and not a header)
                    const nextAvailableDay = $('.calendar-day:not(.empty):not(:nth-child(-n+7)):not(.past):not(.sunday)').first();
                    if (nextAvailableDay.length) {
                        nextAvailableDay.click();
                    }
                }
            }, 1000);

        });

        // Handle current location button click
        $(document).on('click', '#current-location-button', function() {
            // Show loading state
            $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Getting location...');

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    const latlng = L.latLng(lat, lng);



                    // Set marker and update map
                    setMarker(latlng);

                    // Update location fields (this will also do reverse geocoding)
                    updateLocationFields(latlng);

                    // Reset button state
                    $('#current-location-button').prop('disabled', false).html('Use My Location');

                    // Add accuracy circle
                    if (position.coords.accuracy) {
                        L.circle(latlng, {
                            color: 'rgba(66, 133, 244, 0.2)',
                            fillColor: 'rgba(66, 133, 244, 0.1)',
                            fillOpacity: 0.3,
                            radius: position.coords.accuracy
                        }).addTo(map);
                    }
                }, function(error) {
                    console.error('Error getting location:', error);

                    // Reset button state
                    $('#current-location-button').prop('disabled', false).html('Use My Location');

                    // Show appropriate error message
                    let errorMessage = 'Unable to get your location. Please enter it manually.';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage = 'Location access was denied. Please enable location services and try again.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage = 'Location information is unavailable. Please try again later.';
                            break;
                        case error.TIMEOUT:
                            errorMessage = 'Location request timed out. Please try again.';
                            break;
                    }
                    alert(errorMessage);
                }, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                });
            } else {
                // Reset button state
                $('#current-location-button').prop('disabled', false).html('Use My Location');

                alert('Geolocation is not supported by your browser. Please enter your location manually.');
            }
        });

    </script>
    <script src="js/main.js"></script>
    <script src="js/sidebar.js"></script>
    <script src="js/form-validation-fix.js"></script>
    <script>
        // Debug logging for sidebar
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Schedule page loaded');

            // Check if sidebar elements exist
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.getElementById('menuToggle');

            if (!sidebar) {
                console.error('Sidebar element not found in schedule.php');
            } else {
                console.log('Sidebar element found in schedule.php');
            }

            if (!menuToggle) {
                console.error('Menu toggle element not found in schedule.php');
            } else {
                console.log('Menu toggle element found in schedule.php');
            }
        });
    </script>
    <!-- Notification Scripts -->
    <script src="js/notifications.js"></script>
</body>
</html>