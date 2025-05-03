<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Use relative paths from the current file's directory for includes
include_once __DIR__ . '/../../backend/check_role.php';
include_once __DIR__ . '/../../backend/db_connection.php';

// --- Permission Check ---
$isLoggedIn = isset($_SESSION['admin_user_id']) || isset($_SESSION['client_user_id']) || isset($_SESSION['user_id']);
if (!$isLoggedIn) {
    header('Location: /public/index.php'); // Redirect to login page if not logged in
    exit;
}

$role = $_SESSION['admin_role'] ?? $_SESSION['client_role'] ?? $_SESSION['role'] ?? 'guest';
$isAllowed = false;

if ($role !== 'guest' && isset($conn)) {
    $stmt = $conn->prepare("SELECT pages FROM roles WHERE role_name = ? AND status = 'active'");
    if ($stmt) {
        $stmt->bind_param("s", $role);
        $stmt->execute();
        $stmt->bind_result($pages);
        if ($stmt->fetch()) {
            $allowedPages = array_map('trim', explode(',', $pages ?? ''));
            if (in_array('Reporting', $allowedPages)) {
                $isAllowed = true;
            }
        }
        $stmt->close();
    } else {
        error_log("Error preparing statement for role check: " . $conn->error);
    }
}

if (!$isAllowed) {
    echo "Access Denied. You do not have permission to view this page.";
    exit;
}
// --- End Permission Check ---


$pageTitle = "Reporting"; // Set the page title

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle) . ' - TopExchange'; ?></title>
    <!-- Core CSS - MAKE SURE THIS PATH IS CORRECT -->
    <link rel="stylesheet" href="/public/css/styles.css"> <!-- <== VERIFY THIS PATH -->
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Page-specific CSS -->
    <style>
        /* Styles specific to the reporting page controls/display */
        .report-controls {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border: 1px solid #eee;
            border-radius: 4px;
        }
        .report-controls label,
        .report-controls select,
        .report-controls input,
        .report-controls button {
            margin-right: 10px;
            margin-bottom: 10px; /* Spacing for wrapping */
        }
        #report-display-area {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            background-color: #fff;
            min-height: 200px;
        }
        /* Basic loading spinner */
        .fa-spinner {
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <!-- This structure MUST match accounts.php and rely on styles.css -->
    <div class="main-container">
        <?php include __DIR__ . '/../sidebar.php'; // Include the sidebar ?>

        <div class="content-area">
            <?php /* REMOVED Header Include Line */ ?>

            <div class="page-content">
                <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                <hr>

                <!-- Reporting controls area -->
                <div class="report-controls">
                    <label for="report-type">Select Report:</label>
                    <select id="report-type" name="report-type">
                        <option value="">-- Choose Report --</option>
                        <option value="sales_summary">Sales Summary</option>
                        <option value="inventory_status">Inventory Status</option>
                        <option value="order_trends">Order Trends</option>
                        <!-- Add more report types -->
                    </select>

                    <!-- Example Date Range Picker -->
                    <label for="start-date">From:</label>
                    <input type="date" id="start-date" name="start-date">
                    <label for="end-date">To:</label>
                    <input type="date" id="end-date" name="end-date">

                    <button onclick="generateReport()">Generate Report</button>
                </div>

                <!-- Area to display the selected report -->
                <div id="report-display-area">
                    <p>Select a report type and click "Generate Report".</p>
                </div>

            </div> <!-- End page-content -->
        </div> <!-- End content-area -->
    </div> <!-- End main-container -->

    <!-- Core JS - MAKE SURE THIS PATH IS CORRECT -->
    <script src="/public/js/script.js"></script> <!-- <== VERIFY THIS PATH -->

    <!-- Page-specific JS -->
    <script>
        function generateReport() {
            const reportType = document.getElementById('report-type').value;
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;
            const displayArea = document.getElementById('report-display-area');

            if (!reportType) {
                displayArea.innerHTML = '<p style="color: red;">Please select a report type.</p>';
                return;
            }

            displayArea.innerHTML = `<p>Generating ${reportType.replace(/_/g, ' ')} report... <i class="fas fa-spinner fa-spin"></i></p>`;

            const formData = new FormData();
            formData.append('report_type', reportType);
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);

            fetch('/backend/fetch_report.php', { // We will create this backend file
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    // Try to get error text from response body
                    return response.text().then(text => {
                         throw new Error(`HTTP error ${response.status}: ${text || 'Server error'}`);
                    });
                }
                return response.text(); // Expecting HTML content for the report
            })
            .then(html => {
                displayArea.innerHTML = html;
            })
            .catch(error => {
                console.error('Error fetching report:', error);
                displayArea.innerHTML = `<p style="color: red;">Error loading report: ${error.message}. Check console for details.</p>`;
            });
        }
    </script>
</body>
</html>