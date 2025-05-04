<?php
// UTC: 2025-05-04 10:00:01
// Location: public/api/report_handler.php
// Purpose: Acts as a web-accessible endpoint to include the real backend script.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Basic check if called via POST from the reporting page
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['report_type'])) {
    http_response_code(400);
    // You can output a simple error or just exit
    echo "<div class='report-error-message'>Invalid request.</div>";
    exit;
}

// --- Include the actual backend report generator ---
// Use file system path relative to this handler file.
// __DIR__ is /public/api
// Go up one level to /public
// Go up another level to / (project root)
// Go into /backend/
$backend_script_path = __DIR__ . '/../../backend/fetch_report.php';

if (file_exists($backend_script_path)) {
    // The included script ('fetch_report.php') handles the rest:
    // - DB connection (via its own include)
    // - Input processing ($_POST)
    // - Running the correct function based on 'report_type'
    // - Echoing the HTML output
    // - Error handling and sending error messages/codes
    // - Closing the DB connection
    include $backend_script_path;
} else {
    // Log error if the backend script isn't found
    error_log("FATAL ERROR in report_handler.php: Backend script not found at: " . $backend_script_path);
    http_response_code(500);
    echo "<div class='report-error-message'>Server configuration error: Report generator not found. Please contact support.</div>";
}

// No further code needed here, the included script handles output and exit/close.
?>