<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Use relative paths from the current file's directory for includes
// Correct path from /public/pages/ to /backend/
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
    // Consider including sidebar/footer for consistency even on error pages
    if (isset($conn) && $conn instanceof mysqli) $conn->close(); // Close connection if exiting
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

    <!-- CSS Includes copied from accounts.php -->
    <!-- Assuming /css/ points to public_html/admin/public/css/ -->
    <link rel="stylesheet" href="/css/accounts.css"> <!-- May contain general table/button styles -->
    <link rel="stylesheet" href="/css/sidebar.css">  <!-- <== LIKELY CONTAINS LAYOUT RULES -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="/css/toast.css">

    <!-- Page-specific CSS for Reporting -->
    <style>
        /* Styles specific to the reporting page controls/display */
        .reporting-header { /* Mimic accounts-header */
             display: flex;
             justify-content: space-between; /* Or adjust as needed */
             align-items: center;
             margin-bottom: 20px;
        }
        .reporting-header h1 {
             margin: 0; /* Remove default margin */
             margin-right: auto; /* Push controls to the right */
        }
        .report-controls {
            display: flex; /* Arrange controls inline */
            align-items: center;
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
            /* Remove background/border if inheriting from accounts.css is enough */
            /* margin-bottom: 20px; */
            /* padding: 15px; */
            /* background-color: #f9f9f9; */
            /* border: 1px solid #eee; */
            /* border-radius: 4px; */
        }
        .report-controls label,
        .report-controls select,
        .report-controls input,
        .report-controls button {
            margin-right: 10px;
            margin-bottom: 10px; /* Spacing for wrapping */
        }
         /* Make button consistent */
        .report-controls button {
             padding: 8px 15px;
             background-color: #2980b9; /* Match accounts.php style */
             color: white;
             border: none;
             border-radius: 4px;
             cursor: pointer;
             transition: background-color 0.2s ease;
         }
         .report-controls button:hover {
              background-color: #2471a3; /* Match accounts.php style */
         }

        #report-display-area {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            background-color: #fff;
            min-height: 200px;
             overflow-x: auto; /* Add horizontal scroll if table is wide */
        }
        /* Basic loading spinner */
        .fa-spinner {
            margin-left: 5px;
        }

         /* Basic Table styling (if not fully covered by accounts.css) */
         #report-display-area table {
             width: 100%;
             border-collapse: collapse;
             margin-top: 15px;
         }
         #report-display-area th,
         #report-display-area td {
             border: 1px solid #ddd;
             padding: 8px 12px;
             text-align: left;
         }
         #report-display-area th {
             background-color: #f2f2f2; /* Light grey header */
         }
    </style>
</head>
<body>
    <div id="toast-container"></div> <!-- For notifications -->

    <?php
    // Include the sidebar using the relative path from /public/pages/
    // This goes up one level to /public/ where sidebar.php is assumed to be
    include __DIR__ . '/../sidebar.php';
    ?>

    <!-- Main Content Area (matches accounts.php structure) -->
    <div class="main-content">

        <div class="reporting-header">
             <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
             <!-- Reporting controls moved into the header div -->
             <div class="report-controls">
                <label for="report-type">Report:</label>
                <select id="report-type" name="report-type">
                    <option value="">-- Choose --</option>
                    <option value="sales_summary">Sales Summary</option>
                    <option value="inventory_status">Inventory Status</option>
                    <option value="order_trends">Order Trends</option>
                    <!-- Add more report types -->
                </select>

                <label for="start-date">From:</label>
                <input type="date" id="start-date" name="start-date">
                <label for="end-date">To:</label>
                <input type="date" id="end-date" name="end-date">

                <button onclick="generateReport()"><i class="fas fa-play"></i> Generate</button>
            </div>
        </div>

        <hr style="margin: 0 0 20px 0;"> <!-- Add a separator like in accounts.php -->

        <!-- Area to display the selected report -->
        <div id="report-display-area">
            <p>Select a report type and date range, then click "Generate".</p>
        </div>

    </div> <!-- End main-content -->

    <!-- JS Includes copied from accounts.php -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <!-- Assuming /js/ points to public_html/admin/public/js/ -->
    <script src="/js/toast.js"></script>

    <!-- Page-specific JS for Reporting -->
    <script>
        // Ensure toastr options are set (copied from toast.js logic if needed)
        // Or rely on toast.js to set them globally
        // toastr.options = { ... };

        function generateReport() {
            const reportType = $('#report-type').val();
            const startDate = $('#start-date').val();
            const endDate = $('#end-date').val();
            const displayArea = $('#report-display-area');

            if (!reportType) {
                showToast('Please select a report type.', 'warning'); // Use toastr
                // displayArea.html('<p style="color: orange;">Please select a report type.</p>');
                return;
            }

            // Basic date validation (optional but recommended)
            if (startDate && endDate && startDate > endDate) {
                 showToast('Start date cannot be after end date.', 'warning');
                 return;
            }


            displayArea.html(`<p>Generating ${reportType.replace(/_/g, ' ')} report... <i class="fas fa-spinner fa-spin"></i></p>`);

            const formData = new FormData();
            formData.append('report_type', reportType);
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);

            // Use fetch API (jQuery $.ajax is also fine if you prefer)
            fetch('/backend/fetch_report.php', { // We still need to create this backend file
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    // Try to get error text from response body for better feedback
                    return response.text().then(text => {
                         throw new Error(`HTTP error ${response.status}: ${text || 'Server error'}`);
                    });
                }
                return response.text(); // Expecting HTML content for the report table/data
            })
            .then(html => {
                displayArea.html(html); // Display the HTML returned from backend
                // Optional: Initialize any JS needed for the loaded report content (e.g., datatables)
            })
            .catch(error => {
                console.error('Error fetching report:', error);
                displayArea.html(`<p style="color: red;">Error loading report: ${error.message}. Check console for details.</p>`);
                showToast(`Error loading report: ${error.message}`, 'error'); // Show error in toast
            });
        }

        // Optional: Trigger report generation if parameters are in URL on page load
        // $(document).ready(function() {
        //     const urlParams = new URLSearchParams(window.location.search);
        //     const reportType = urlParams.get('report_type');
        //     const startDate = urlParams.get('start_date');
        //     const endDate = urlParams.get('end_date');
        //
        //     if (reportType) {
        //         $('#report-type').val(reportType);
        //         if(startDate) $('#start-date').val(startDate);
        //         if(endDate) $('#end-date').val(endDate);
        //         generateReport();
        //     }
        // });

    </script>
</body>
</html>
<?php
// Close DB connection at the very end
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>