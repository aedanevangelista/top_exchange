<?php
// UTC: 2025-05-04 07:36:05
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Use relative paths from the current file's directory for includes
include_once __DIR__ . '/../../backend/check_role.php';
include_once __DIR__ . '/../../backend/db_connection.php'; // Needed for role check below

// --- Permission Check ---
$isLoggedIn = isset($_SESSION['admin_user_id']) || isset($_SESSION['client_user_id']) || isset($_SESSION['user_id']);
if (!$isLoggedIn) {
    header('Location: /public/index.php');
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
        error_log("Error preparing statement for role check in reporting.php: " . $conn->error);
    }
}

if (!$isAllowed) {
    echo "Access Denied. You do not have permission to view this page.";
    if (isset($conn) && $conn instanceof mysqli) $conn->close();
    exit;
}
// --- End Permission Check ---

$pageTitle = "Reporting";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle) . ' - TopExchange'; ?></title>

    <!-- CSS Includes -->
    <link rel="stylesheet" href="/css/accounts.css">
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="/css/toast.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="/css/reporting.css">

    <style>
        /* Basic styles if reporting.css is missing or incomplete */
        .main-content { margin-left: 250px; padding: 20px; }
        .reporting-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 15px; }
        .reporting-header h1 { margin: 0; flex-grow: 1; }
        .report-controls { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .report-controls label { font-weight: 500; }
        .report-controls select, .report-controls input[type="date"], .report-controls button { padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
        .report-controls button { background-color: #007bff; color: white; cursor: pointer; transition: background-color 0.2s; }
        .report-controls button:hover { background-color: #0056b3; }
        .report-controls button:disabled { background-color: #aaa; cursor: not-allowed; }
        #report-display-area { margin-top: 20px; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); min-height: 100px; }
        .loading-message, .report-error-message, .no-data-message { text-align: center; padding: 30px; font-size: 1.1em; color: #666; }
        .report-error-message { color: #dc3545; font-weight: bold; }
        .report-container h2, .report-container h3 { margin-top: 0; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .report-container h4 { margin-top: 15px; margin-bottom: 10px; font-size: 1.1em; }
        .report-period { font-size: 0.9em; color: #555; margin-bottom: 15px; }
        .report-table, .summary-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .report-table th, .report-table td, .summary-table th, .summary-table td { padding: 10px 12px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
        .report-table th, .summary-table th { background-color: #f8f9fa; font-weight: 600; }
        .report-table tbody tr:nth-child(even) { background-color: #f9f9f9; }
        .report-table tbody tr:hover { background-color: #f1f1f1; }
        .summary-table th { width: 30%; } /* Adjust summary table layout */
        .numeric { text-align: right; }
        .currency { text-align: right; }
        .low-stock-highlight { color: orange; font-weight: bold; } /* Style for low stock numbers */
        .inventory-section { margin-bottom: 25px; }
        /* Add more specific styles as needed in reporting.css */
    </style>

</head>
<body>
    <div id="toast-container"></div> <!-- For notifications -->

    <?php include __DIR__ . '/../sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="main-content">

        <div class="reporting-header">
             <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
             <div class="report-controls">
                <label for="report-type">Report:</label>
                <select id="report-type" name="report-type">
                    <option value="">-- Choose --</option>
                    <option value="sales_summary">Sales Summary</option>
                    <option value="sales_by_client">Sales by Client</option>
                    <!-- **** ADDED NEW REPORT TYPE **** -->
                    <option value="sales_by_product">Sales by Product</option>
                    <!-- <option value="inventory_status">Low Inventory Status</option> -->
                    <option value="order_trends">Order Listing</option>
                    <!-- Add more report types here -->
                </select>

                <label for="start-date">From:</label>
                <input type="date" id="start-date" name="start-date">
                <label for="end-date">To:</label>
                <input type="date" id="end-date" name="end-date">

                <button id="generate-report-btn" onclick="generateReport()"><i class="fas fa-play"></i> Generate</button>
            </div>
        </div>

        <hr style="border: 0; border-top: 1px solid #e1e4e8; margin: 20px 0;">

        <!-- Area to display the selected report -->
        <div id="report-display-area">
            <p style="text-align: center; color: #666; margin-top: 20px;">Select a report type and date range (if applicable), then click "Generate".</p>
        </div>

    </div> <!-- End main-content -->

    <!-- JS Includes -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="/js/toast.js"></script> <!-- VERIFY PATH -->

    <!-- Page-specific JS for Reporting -->
    <script>
        function generateReport() {
            const reportType = $('#report-type').val();
            const startDate = $('#start-date').val();
            const endDate = $('#end-date').val();
            const displayArea = $('#report-display-area');
            const generateButton = $('#generate-report-btn');

            if (!reportType) {
                if (typeof showToast === 'function') { showToast('Please select a report type.', 'warning'); }
                else { alert('Please select a report type.'); console.error('showToast function not found.'); }
                return;
            }

            // Define which reports require date range
            // **** ADDED sales_by_product ****
            const requiresDates = ['sales_summary', 'order_trends', 'sales_by_client', 'sales_by_product'];

            // Basic date validation for relevant reports
            if (requiresDates.includes(reportType) && startDate && endDate && startDate > endDate) {
                 if (typeof showToast === 'function') { showToast('Start date cannot be after end date.', 'warning'); }
                 else { alert('Start date cannot be after end date.'); }
                 return;
            }

            // Show loading state and disable button
            displayArea.html(`<div class=\"loading-message\">Generating ${reportType.replace(/_/g, ' ')} report... <i class=\"fas fa-spinner fa-spin\"></i></div>`);
            generateButton.prop('disabled', true).html('<i class=\"fas fa-spinner fa-spin\"></i> Generating...');

            const formData = new FormData();
            formData.append('report_type', reportType);
            // Only send dates if they are relevant
            if (requiresDates.includes(reportType)) {
                 if(startDate) formData.append('start_date', startDate);
                 if(endDate) formData.append('end_date', endDate);
            }

            const fetchUrl = '/backend/fetch_report.php'; // VERIFY PATH

            fetch(fetchUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                         try { // Try to extract specific error message
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(text, "text/html");
                            const errorElement = doc.querySelector('.report-error-message');
                            if (errorElement && errorElement.textContent.trim()) {
                                throw new Error(errorElement.textContent.trim());
                            }
                         } catch (parseError) { /* Ignore */ }
                         throw new Error(`HTTP error ${response.status}: ${text || response.statusText || 'Server error'}`); // Fallback
                    });
                }
                return response.text(); // Expecting HTML content
            })
            .then(html => {
                displayArea.html(html); // Display the HTML returned from backend
            })
            .catch(error => {
                console.error('Error fetching report:', error);
                 displayArea.html(`<div class=\"report-error-message\">Error loading report: ${error.message}. Check console for details.</div>`);
                 if (typeof showToast === 'function') { showToast(`Error loading report: ${error.message}`, 'error'); }
                 else { alert(`Error loading report: ${error.message}`); }
            })
            .finally(() => {
                 generateButton.prop('disabled', false).html('<i class=\"fas fa-play\"></i> Generate');
            });
        }
    </script>
</body>
</html>
<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>