<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has permission for this page
include_once __DIR__ . '/../../backend/check_role.php';
include_once __DIR__ . '/../../backend/db_connection.php';

// Redirect if not logged in or doesn't have permission
if (!isset($_SESSION['admin_user_id']) && !isset($_SESSION['client_user_id']) && !isset($_SESSION['user_id'])) {
    header('Location: /public/index.php'); // Redirect to login page
    exit;
}

// Determine role and check if 'Reporting' is allowed for this role
$role = '';
if (isset($_SESSION['admin_role'])) {
    $role = $_SESSION['admin_role'];
} elseif (isset($_SESSION['client_role'])) {
    $role = $_SESSION['client_role'];
} elseif (isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
}

$isAllowed = false;
if (!empty($role) && isset($conn)) {
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
    }
}

if (!$isAllowed) {
    // Optionally show an 'Access Denied' message or redirect
    echo "Access Denied."; // Or header('Location: /public/pages/dashboard.php');
    exit;
}

// --- Page Specific Logic Starts Here ---

$pageTitle = "Reporting"; // Set the page title

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - TopExchange' : 'TopExchange'; ?></title>
    <!-- Include your CSS files -->
    <link rel="stylesheet" href="/public/css/styles.css"> <!-- Adjust path as needed -->
    <!-- Include Font Awesome if used -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Add any page-specific CSS or JS libraries here (e.g., for charts, date pickers) -->

</head>
<body>
    <div class="main-container">
        <?php include __DIR__ . '/../sidebar.php'; // Include the sidebar ?>

        <div class="content-area">
            <?php include __DIR__ . '/../header.php'; // Include the header ?>

            <div class="page-content">
                <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                <hr>

                <!-- Reporting controls and display area will go here -->
                <p>Select the report you want to generate:</p>

                <!-- Example: Buttons or links for different reports -->
                <div>
                    <button onclick="loadReport('sales_summary')">Sales Summary</button>
                    <button onclick="loadReport('inventory_status')">Inventory Status</button>
                    <!-- Add more buttons as needed -->
                </div>

                <hr>

                <!-- Area to display the selected report -->
                <div id="report-display-area">
                    <p>Report results will be shown here.</p>
                </div>

            </div> <!-- End page-content -->
        </div> <!-- End content-area -->
    </div> <!-- End main-container -->

    <!-- Include your JS files -->
    <script src="/public/js/script.js"></script> <!-- Adjust path as needed -->
    <script>
        function loadReport(reportType) {
            const displayArea = document.getElementById('report-display-area');
            displayArea.innerHTML = `<p>Loading ${reportType.replace('_', ' ')} report...</p>`;

            // In a real application, you would use AJAX (fetch) here
            // to call a backend script (e.g., /backend/fetch_report.php?type=reportType)
            // and display the results.

            // For now, just a placeholder:
            setTimeout(() => {
                 displayArea.innerHTML = `<p>Displaying results for <strong>${reportType.replace('_', ' ')}</strong> report.</p>
                 <p>(Actual data fetching and display logic needs to be implemented).</p>`;
            }, 1000); // Simulate loading delay
        }
    </script>
</body>
</html>