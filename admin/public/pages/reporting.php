<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Use relative paths from the current file's directory for includes
// Correct path from /public/pages/ to /backend/
// Assuming reporting.php is inside /public/pages/
include_once __DIR__ . '/../../backend/check_role.php'; // Check if this correctly includes logic
include_once __DIR__ . '/../../backend/db_connection.php'; // Needed for role check below

// --- Permission Check ---
$isLoggedIn = isset($_SESSION['admin_user_id']) || isset($_SESSION['client_user_id']) || isset($_SESSION['user_id']);
if (!$isLoggedIn) {
    // Redirect to the admin login page, adjust path if needed
    header('Location: /admin/public/index.php');
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
            // Ensure 'Reporting' is the correct key used in your roles table
            if (in_array('Reporting', $allowedPages)) {
                $isAllowed = true;
            }
        }
        $stmt->close();
    } else {
        // Log error for debugging
        error_log("Error preparing statement for role check in reporting.php: " . $conn->error);
    }
}

// If not allowed, show access denied message and exit cleanly
if (!$isAllowed) {
    // It's good practice to include layout even for errors if possible
    // For simplicity, just outputting the message
    echo "<!DOCTYPE html><html><head><title>Access Denied</title><link rel=\"stylesheet\" href=\"/admin/public/css/sidebar.css\"><link rel=\"stylesheet\" href=\"/admin/public/css/accounts.css\"></head><body>";
    // Basic structure matching main content area might be better
    include __DIR__ . '/../sidebar.php'; // Try to include sidebar
    echo "<div class='main-content'>";
    echo "<h1>Access Denied</h1>";
    echo "<p>You do not have permission to view this page.</p>";
    echo "</div>";
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
    <!-- Make sure these paths are correct relative to your web root -->
    <link rel="stylesheet" href="/admin/public/css/sidebar.css">
    <link rel="stylesheet" href="/admin/public/css/accounts.css"> <!-- Base styles -->
    <link rel="stylesheet" href="/admin/public/css/toast.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    <!-- Link to the NEW reporting-specific CSS file -->
    <link rel="stylesheet" href="/admin/public/css/reporting.css">

    <!-- REMOVED the <style> block from here -->

</head>
<body>
    <div id="toast-container"></div> <!-- For notifications -->

    <?php
    // Include the sidebar using the relative path
    // Assuming sidebar.php is in /admin/public/
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
                    <!-- Add more report types if needed -->
                </select>

                <label for="start-date">From:</label>
                <input type="date" id="start-date" name="start-date">
                <label for="end-date">To:</label>
                <input type="date" id="end-date" name="end-date">

                <button id="generate-report-btn" onclick="generateReport()"><i class="fas fa-play"></i> Generate</button>
            </div>
        </div>

        <hr style="border: 0; border-top: 1px solid #e1e4e8; margin: 20px 0;"> <!-- Styled HR -->

        <!-- Area to display the selected report -->
        <div id="report-display-area">
            <p style="text-align: center; color: #666; margin-top: 20px;">Select a report type and date range (if applicable), then click "Generate".</p>
        </div>

    </div> <!-- End main-content -->

    <!-- JS Includes -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <!-- Make sure this path is correct -->
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
            const generateButton = $('#generate-report-btn'); // Use ID selector

            if (!reportType) {
                showToast('Please select a report type.', 'warning');
                return;
            }

            // Basic date validation for relevant reports
            if ((reportType === 'sales_summary' || reportType === 'order_trends') && startDate && endDate && startDate > endDate) {
                 showToast('Start date cannot be after end date.', 'warning');
                 return;
            }

            // Show loading state and disable button
            displayArea.html(`<div class="loading-message">Generating ${reportType.replace(/_/g, ' ')} report... <i class="fas fa-spinner fa-spin"></i></div>`);
            generateButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Generating...');


            const formData = new FormData();
            formData.append('report_type', reportType);
            // Only send dates if they are relevant and have values
            if (reportType === 'sales_summary' || reportType === 'order_trends') {
                 if(startDate) formData.append('start_date', startDate);
                 if(endDate) formData.append('end_date', endDate);
            }


            // Use fetch API - ENSURE this path is correct
            fetch('/admin/backend/fetch_report.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    // Try to get error text from response body
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
                return response.text(); // Expecting HTML content
            })
            .then(html => {
                displayArea.html(html); // Display the HTML returned from backend
            })
            .catch(error => {
                console.error('Error fetching report:', error);
                // Display the specific error message caught
                 displayArea.html(`<div class="report-error-message">Error loading report: ${error.message}. Check console for details.</div>`);
                showToast(`Error loading report: ${error.message}`, 'error');
            })
            .finally(() => {
                 // Re-enable button and restore text
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