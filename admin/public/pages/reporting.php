<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Use relative paths from the current file's directory for includes
// Correct path from /public/pages/ to /backend/
include_once __DIR__ . '/../../backend/check_role.php';
include_once __DIR__ . '/../../backend/db_connection.php'; // Need connection for role check

// --- Permission Check ---
$isLoggedIn = isset($_SESSION['admin_user_id']) || isset($_SESSION['client_user_id']) || isset($_SESSION['user_id']);
if (!$isLoggedIn) {
    // It's better to redirect to the main index/login page if not logged in
    // Adjust the path if your login page is elsewhere
    header('Location: /admin/public/index.php');
    exit;
}

// Determine role - ensure session variables are checked safely
$role = $_SESSION['admin_role'] ?? $_SESSION['client_role'] ?? $_SESSION['role'] ?? 'guest';
$isAllowed = false;

// Check permissions only if logged in and connection exists
if ($role !== 'guest' && isset($conn)) {
    // Assuming 'Reporting' is the page_name in the 'pages' table or similar logic in check_role.php/direct query
    // This simplified check assumes 'Reporting' is the key permission name. Adapt if needed.
    $stmt = $conn->prepare("SELECT pages FROM roles WHERE role_name = ? AND status = 'active'");
    if ($stmt) {
        $stmt->bind_param("s", $role);
        $stmt->execute();
        $stmt->bind_result($pages);
        if ($stmt->fetch()) {
            $allowedPages = array_map('trim', explode(',', $pages ?? ''));
            // Check if 'Reporting' or a similar identifier is in the allowed pages
            if (in_array('Reporting', $allowedPages)) { // Adjust 'Reporting' if needed
                $isAllowed = true;
            }
        }
        $stmt->close();
    } else {
        error_log("Error preparing statement for role check in reporting.php: " . $conn->error);
        // Optionally show a generic error, but denying access is safer
    }
}

// If not allowed, show access denied message and exit cleanly
if (!$isAllowed) {
    // Include header/sidebar for consistency is good practice, but requires more setup
    // For now, just output the denial message
    echo "<!DOCTYPE html><html><head><title>Access Denied</title></head><body>";
    echo "Access Denied. You do not have permission to view this page.";
    echo "</body></html>";
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

    <!-- CSS Includes -->
    <!-- Adjust paths based on your actual file structure -->
    <link rel="stylesheet" href="/admin/public/css/sidebar.css">
    <link rel="stylesheet" href="/admin/public/css/accounts.css"> <!-- Base table styles -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="/admin/public/css/toast.css">

    <!-- Page-specific CSS for Reporting -->
    <style>
        /* General Reporting Page Layout */
        .reporting-header {
             display: flex;
             justify-content: space-between;
             align-items: center;
             margin-bottom: 20px;
             flex-wrap: wrap; /* Allow wrapping on smaller screens */
        }
        .reporting-header h1 {
             margin: 0 20px 10px 0; /* Adjust margins */
        }
        .report-controls {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px; /* Use gap for spacing */
            /* Removed background/border - let the page background show */
        }
        .report-controls label {
            margin-right: 5px;
            font-weight: 500;
        }
        .report-controls select,
        .report-controls input[type="date"] {
            padding: 6px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }
         /* Consistent button style */
        .report-controls button {
             padding: 8px 15px;
             background-color: #2980b9; /* Match accounts.php style */
             color: white;
             border: none;
             border-radius: 4px;
             cursor: pointer;
             transition: background-color 0.2s ease;
             font-size: 14px;
         }
         .report-controls button:hover {
              background-color: #2471a3; /* Match accounts.php style */
         }
         .report-controls button i {
             margin-right: 5px;
         }

        /* Report Display Area */
        #report-display-area {
            margin-top: 20px;
            padding: 15px; /* Padding inside the main display area */
            border: 1px solid #ddd;
            background-color: #f9f9f9; /* Light background for the whole area */
            min-height: 200px;
             border-radius: 4px;
             overflow-x: auto; /* Add horizontal scroll if table is wide */
        }
        /* Loading state */
         #report-display-area .loading-message {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 150px;
            color: #555;
            font-size: 1.1em;
         }
         #report-display-area .loading-message i {
             margin-left: 10px;
         }

        /* --- Styling for Report Content Generated by Backend --- */

        .report-title {
            margin-bottom: 25px;
            color: #24292e; /* Darker heading color */
            font-size: 1.5em;
            border-bottom: 1px solid #e1e4e8;
            padding-bottom: 10px;
        }

        .report-subtitle {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.2em; /* Slightly larger subtitle */
            color: #0366d6; /* GitHub-like blue for heading */
        }

        /* Base Report Table Style (inherits from accounts.css but can be overridden) */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            background-color: #fff; /* Ensure tables have white background if section doesn't */
        }

        .report-table th,
        .report-table td {
            border: 1px solid #e1e4e8; /* Light borders for general tables */
            padding: 10px 12px;
            text-align: left;
            vertical-align: top;
        }

        .report-table th {
            background-color: #f6f8fa; /* Lighter GitHub-like header */
            font-weight: 600;
            color: #24292e;
        }

        /* Alignment classes */
        .report-table td.numeric,
        .report-table td.currency {
            text-align: right;
        }
        .report-table td.currency::before {
            content: "₱ "; /* Add currency symbol if needed globally */
            /* float: left; */ /* Alternative positioning */
        }


        /* --- Enhanced Inventory Section Styling --- */
        .inventory-section {
            background-color: #ffffff; /* White background for the card */
            border: 1px solid #e1e4e8; /* Light border, similar to GitHub's */
            border-radius: 6px;       /* Rounded corners */
            padding: 20px;            /* Ample internal padding */
            margin-bottom: 25px;      /* Space between sections */
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05); /* Subtle shadow for depth */
        }
        .inventory-section:last-child {
            margin-bottom: 0; /* Remove margin bottom from the last section */
        }
        /* Style the heading within each inventory section */
        .inventory-section .report-subtitle {
            margin-top: 0;
            margin-bottom: 15px; /* Space below the subtitle */
            padding-bottom: 10px; /* Space below the subtitle text */
            border-bottom: 1px solid #e1e4e8; /* Separator line below title */
            font-size: 1.1em;
        }
        /* Table styling specific to inventory sections */
        .inventory-section .report-table {
            margin-bottom: 0; /* No margin needed inside the padded section */
            border: none; /* Remove table border, section has its own */
        }
        .inventory-section .report-table th,
        .inventory-section .report-table td {
             border: none; /* Remove cell borders */
             border-bottom: 1px solid #e1e4e8; /* Use horizontal lines only */
             padding: 10px 8px; /* Adjust cell padding */
        }
        .inventory-section .report-table tr:last-child td {
            border-bottom: none; /* No border for the last row */
        }
        .inventory-section .report-table th {
            background-color: transparent; /* Remove grey header background */
            color: #586069; /* Dimmer color for table headers */
            font-weight: 600;
            border-bottom-width: 2px; /* Thicker line below header */
             border-color: #e1e4e8;
        }
        /* Style for low stock quantity cells */
        .low-stock-highlight {
            color: #d9534f; /* Using a red color for low stock */
            font-weight: bold;
        }


        /* Summary Box Styling */
        .report-summary-box {
            margin-bottom: 25px;
            padding: 15px;
            background-color: #f6f8fa; /* Slightly different background */
            border: 1px solid #e1e4e8;
            border-radius: 6px;
            display: inline-block; /* Makes the box wrap its content */
        }
        .summary-table {
            width: auto;
            border-collapse: collapse;
        }
        .summary-table th,
        .summary-table td {
            padding: 6px 10px;
            text-align: left;
            border-bottom: 1px dotted #d1d5da; /* Dotted separator */
        }
        .summary-table th {
            font-weight: 600; /* Bolder labels */
            color: #586069;
            padding-right: 15px; /* Space between label and value */
        }
        .summary-table td {
             text-align: right;
             font-weight: 500;
        }
        .summary-table tr:last-child th,
        .summary-table tr:last-child td {
            border-bottom: none;
        }


        /* Message for no data */
        .no-data-message {
            color: #586069;
            font-style: italic;
            padding: 15px 5px; /* Add some padding */
            text-align: center;
        }

        /* Error message styling */
        .report-error-message {
            padding: 10px 15px;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            border-radius: 4px;
            margin: 15px 0; /* Add vertical margin */
        }

        /* Status colors (add to your main CSS if used elsewhere) */
        .status-pending { color: #f0ad4e; font-weight: bold; }
        .status-active { color: #5bc0de; font-weight: bold; }
        .status-for-delivery { color: #337ab7; font-weight: bold; }
        .status-in-transit { color: #777; font-weight: bold; }
        .status-rejected { color: #d9534f; font-weight: bold; }
        .status-completed { color: #5cb85c; font-weight: bold; }

    </style>
</head>
<body>
    <div id="toast-container"></div> <!-- For notifications -->

    <?php
    // Include the sidebar using the relative path from /public/pages/
    // Adjust the path if sidebar.php is located elsewhere relative to reporting.php
    include __DIR__ . '/../sidebar.php';
    ?>

    <!-- Main Content Area -->
    <div class="main-content">

        <div class="reporting-header">
             <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
             <div class="report-controls">
                <label for="report-type">Report:</label>
                <select id="report-type" name="report-type">
                    <option value="">-- Choose --</option>
                    <option value="sales_summary">Sales Summary</option>
                    <option value="inventory_status">Low Inventory Status</option> <!-- Updated label -->
                    <option value="order_trends">Order Listing</option> <!-- Updated label -->
                    <!-- Add more report types -->
                </select>

                <label for="start-date">From:</label>
                <input type="date" id="start-date" name="start-date">
                <label for="end-date">To:</label>
                <input type="date" id="end-date" name="end-date">

                <button onclick="generateReport()"><i class="fas fa-play"></i> Generate</button>
            </div>
        </div>

        <hr style="margin: 0 0 20px 0;"> <!-- Visual separator -->

        <!-- Area to display the selected report -->
        <div id="report-display-area">
            <p>Select a report type and date range (if applicable), then click "Generate".</p>
        </div>

    </div> <!-- End main-content -->

    <!-- JS Includes -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <!-- Adjust path based on your actual file structure -->
    <script src="/admin/public/js/toast.js"></script>

    <!-- Page-specific JS for Reporting -->
    <script>
        // Ensure toastr options are set (rely on toast.js to set them globally)
        // toastr.options = { ... };

        function generateReport() {
            const reportType = $('#report-type').val();
            const startDate = $('#start-date').val();
            const endDate = $('#end-date').val();
            const displayArea = $('#report-display-area');
            const generateButton = $('.report-controls button'); // Get the button

            if (!reportType) {
                showToast('Please select a report type.', 'warning');
                return;
            }

            // Basic date validation
            if ((reportType === 'sales_summary' || reportType === 'order_trends') && startDate && endDate && startDate > endDate) {
                 showToast('Start date cannot be after end date.', 'warning');
                 return;
            }

            // Show loading state and disable button
            displayArea.html(`<div class="loading-message">Generating ${reportType.replace(/_/g, ' ')} report... <i class="fas fa-spinner fa-spin"></i></div>`);
            generateButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Generating...'); // Update button text/icon


            const formData = new FormData();
            formData.append('report_type', reportType);
            // Only send dates if they are relevant and have values
            if (reportType === 'sales_summary' || reportType === 'order_trends') {
                 if(startDate) formData.append('start_date', startDate);
                 if(endDate) formData.append('end_date', endDate);
            }


            // Use fetch API
            fetch('/admin/backend/fetch_report.php', { // Verify this is the correct path
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    // Try to get error text from response body for better feedback
                    return response.text().then(text => {
                         // Attempt to parse as HTML to find a specific error message div
                         try {
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(text, "text/html");
                            const errorElement = doc.querySelector('.report-error-message');
                            if (errorElement && errorElement.textContent.trim()) {
                                throw new Error(errorElement.textContent.trim());
                            }
                         } catch (parseError) { /* Ignore parsing error, use raw text */ }

                         // Fallback to status text or raw response text
                         throw new Error(`HTTP error ${response.status}: ${text || response.statusText || 'Server error'}`);
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
                // Display the specific error message caught from the response or fetch failure
                 displayArea.html(`<div class="report-error-message">Error loading report: ${error.message}. Check console for details.</div>`);
                showToast(`Error loading report: ${error.message}`, 'error'); // Show error in toast
            })
            .finally(() => {
                 // Re-enable button and restore text regardless of success or failure
                 generateButton.prop('disabled', false).html('<i class="fas fa-play"></i> Generate');
            });
        }

    </script>
</body>
</html>
<?php
// Close DB connection at the very end if it was opened for role check
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>