<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Basic security check - ensure user is logged in.
// Consider adding role-based access control similar to reporting.php if needed.
$isLoggedIn = isset($_SESSION['admin_user_id']) || isset($_SESSION['client_user_id']) || isset($_SESSION['user_id']);
if (!$isLoggedIn) {
    http_response_code(403); // Forbidden
    echo "<p class='error-message' style='color: red;'>Access Denied. Please log in.</p>"; // Use a class for styling errors
    exit;
}

include_once __DIR__ . '/db_connection.php'; // Establishes $conn

// Default error response function
function sendError($message, $httpCode = 500) {
    http_response_code($httpCode);
    // Use a consistent error display, maybe match modal error styles if possible
    echo "<div class='error-message' style='padding: 10px; background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; border-radius: 4px; margin-top: 10px;'>" . htmlspecialchars($message) . "</div>";
    // Ensure connection is closed if it exists before exiting
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        $GLOBALS['conn']->close();
    }
    exit;
}

// Check if it's a POST request and report_type is set
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['report_type'])) {
    sendError("Invalid request method or missing report type.", 400);
}

// --- Input Sanitization/Retrieval ---
$reportType = trim($_POST['report_type']);
// Basic date validation can be added here if needed
$startDate = !empty(trim($_POST['start_date'])) ? trim($_POST['start_date']) : null;
$endDate = !empty(trim($_POST['end_date'])) ? trim($_POST['end_date']) : null;

// --- Report Generation Logic ---

// Ensure database connection is established
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
     sendError("Database connection failed: " . ($conn->connect_error ?? 'Unknown error'), 500);
}

try {
    switch ($reportType) {
        case 'sales_summary':
            generateSalesSummary($conn, $startDate, $endDate);
            break;

        case 'inventory_status':
            generateInventoryStatus($conn);
            break;

        case 'order_trends':
            generateOrderTrends($conn, $startDate, $endDate);
            break;

        // Add more report cases here

        default:
            sendError("Invalid report type specified: " . htmlspecialchars($reportType), 400);
            break;
    }
} catch (mysqli_sql_exception $e) {
    // Catch potential SQL errors during execution
    error_log("SQL Exception in fetch_report.php: " . $e->getMessage()); // Log the detailed error
    sendError("An unexpected database error occurred while generating the report.", 500);
} catch (Exception $e) {
    // Catch other general exceptions
    error_log("General Exception in fetch_report.php: " . $e->getMessage()); // Log the detailed error
    sendError("An unexpected error occurred.", 500);
} finally {
    // Ensure DB connection is closed if it's still open
    if (isset($conn) && $conn instanceof mysqli && $conn->ping()) { // Check if connection is still alive
        $conn->close();
    }
}

// --- Report Generating Functions ---

/**
 * Generates an Enhanced Sales Summary Report.
 * Uses 'orders' table with columns: id, total_amount, order_date, status, username
 */
function generateSalesSummary($conn, $startDate, $endDate) {
    $dateCondition = "";
    $params = [];
    $paramTypes = "";

    // Build date condition for prepared statements
    if ($startDate) {
        $dateCondition .= " AND DATE(order_date) >= ?";
        $params[] = $startDate;
        $paramTypes .= "s";
    }
    if ($endDate) {
        // If end date is same as start date, ensure it covers the whole day if needed
        // For DATE comparison, <= is usually sufficient.
        $dateCondition .= " AND DATE(order_date) <= ?";
        $params[] = $endDate;
        $paramTypes .= "s";
    }

    // 1. Get Total Orders and Sales Value for 'Completed' orders
    $sqlSummary = "SELECT
                        COUNT(id) as total_orders,
                        SUM(total_amount) as total_sales_value
                    FROM orders
                    WHERE status = 'Completed' {$dateCondition}";

    $stmtSummary = $conn->prepare($sqlSummary);
    if (!$stmtSummary) throw new mysqli_sql_exception("DB prepare error (Summary): " . $conn->error, $conn->errno);

    if (!empty($paramTypes)) $stmtSummary->bind_param($paramTypes, ...$params);

    if (!$stmtSummary->execute()) {
        $err = $stmtSummary->error;
        $errno = $stmtSummary->errno;
        $stmtSummary->close();
        throw new mysqli_sql_exception("DB execute error (Summary): " . $err, $errno);
    }
    $resultSummary = $stmtSummary->get_result();
    $summary = $resultSummary->fetch_assoc();
    $resultSummary->close(); // Close result set promptly
    $stmtSummary->close();

    // 2. Get Unique Customers (using 'username') for 'Completed' orders
    $uniqueCustomers = 0; // Default to 0
    $sqlCustomers = "SELECT COUNT(DISTINCT username) as unique_customer_count
                     FROM orders
                     WHERE status = 'Completed' {$dateCondition}"; // Use username

    $stmtCustomers = $conn->prepare($sqlCustomers);
    if ($stmtCustomers) {
         if (!empty($paramTypes)) $stmtCustomers->bind_param($paramTypes, ...$params);
         if ($stmtCustomers->execute()) {
             $resultCustomers = $stmtCustomers->get_result();
             $customerData = $resultCustomers->fetch_assoc();
             $uniqueCustomers = $customerData['unique_customer_count'] ?? 0;
             $resultCustomers->close();
         } else {
              error_log("DB execute error (Customers): " . $stmtCustomers->error); // Log non-fatal error
         }
         $stmtCustomers->close();
    } else {
         error_log("DB prepare error (Customers): " . $conn->error); // Log non-fatal error
    }

    // Calculate derived metrics
    $totalOrders = $summary['total_orders'] ?? 0;
    $totalSales = $summary['total_sales_value'] ?? 0;
    $averageOrderValue = ($totalOrders > 0) ? ($totalSales / $totalOrders) : 0;

    // Format the output using a table for the summary
    $dateRangeStr = '';
    if ($startDate && $endDate) $dateRangeStr = " from " . htmlspecialchars($startDate) . " to " . htmlspecialchars($endDate);
    elseif ($startDate) $dateRangeStr = " from " . htmlspecialchars($startDate) . " onwards";
    elseif ($endDate) $dateRangeStr = " up to " . htmlspecialchars($endDate);

    echo "<h3>Sales Summary Report" . $dateRangeStr . "</h3>";
    // Use a simple table for the summary box
    echo "<div class='report-summary-box' style='margin-bottom: 20px;'>";
    echo "<table class='summary-table' style='width: auto; border: 1px solid #ddd; background-color: #f9f9f9; border-collapse: collapse;'>";
    echo "<tbody>";

    echo "<tr>";
    echo "<td style='padding: 5px 10px; font-weight: bold; text-align: left; border: 1px solid #ddd;'>Total Completed Orders:</td>";
    echo "<td style='padding: 5px 10px; text-align: right; border: 1px solid #ddd;'>" . number_format($totalOrders) . "</td>";
    echo "</tr>";

    echo "<tr>";
    echo "<td style='padding: 5px 10px; font-weight: bold; text-align: left; border: 1px solid #ddd;'>Total Sales Value:</td>";
    echo "<td style='padding: 5px 10px; text-align: right; border: 1px solid #ddd;'>₱ " . number_format($totalSales, 2) . "</td>";
    echo "</tr>";

    echo "<tr>";
    echo "<td style='padding: 5px 10px; font-weight: bold; text-align: left; border: 1px solid #ddd;'>Average Order Value:</td>";
    echo "<td style='padding: 5px 10px; text-align: right; border: 1px solid #ddd;'>₱ " . number_format($averageOrderValue, 2) . "</td>";
    echo "</tr>";

    // Display unique customers count
    echo "<tr>";
    echo "<td style='padding: 5px 10px; font-weight: bold; text-align: left; border: 1px solid #ddd;'>Unique Customers:</td>";
    echo "<td style='padding: 5px 10px; text-align: right; border: 1px solid #ddd;'>" . number_format($uniqueCustomers) . "</td>";
    echo "</tr>";

    echo "</tbody>";
    echo "</table>";
    echo "</div>"; // Close report-summary-box

    // --- Optional: Daily Sales Breakdown table ---
    if ($startDate && $endDate) { // Only show if a date range is specified
        $sqlDaily = "SELECT DATE(order_date) as sale_date, COUNT(id) as daily_orders, SUM(total_amount) as daily_sales
                     FROM orders
                     WHERE status = 'Completed' {$dateCondition}
                     GROUP BY DATE(order_date)
                     ORDER BY sale_date ASC";

        $stmtDaily = $conn->prepare($sqlDaily);
        if (!$stmtDaily) throw new mysqli_sql_exception("DB prepare error (Daily): " . $conn->error, $conn->errno);

        if (!empty($paramTypes)) $stmtDaily->bind_param($paramTypes, ...$params);

        if (!$stmtDaily->execute()){
             $err = $stmtDaily->error;
             $errno = $stmtDaily->errno;
             $stmtDaily->close();
             throw new mysqli_sql_exception("DB execute error (Daily): " . $err, $errno);
        }
        $resultDaily = $stmtDaily->get_result();

        if ($resultDaily->num_rows > 0) {
            echo "<h4>Daily Sales Breakdown</h4>";
            // Apply the same table class as used in accounts.php or reporting.php
            echo "<table class='accounts-table'>"; // Ensure this class exists and styles tables correctly
            echo "<thead><tr><th>Date</th><th>Orders</th><th>Sales Value</th></tr></thead>";
            echo "<tbody>";
            while ($row = $resultDaily->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['sale_date']) . "</td>";
                echo "<td>" . number_format($row['daily_orders']) . "</td>";
                echo "<td style='text-align: right;'>₱ " . number_format($row['daily_sales'], 2) . "</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else {
             echo "<p>No daily sales data found for the selected period.</p>"; // Added message if no daily data
        }
        $resultDaily->close();
        $stmtDaily->close();
    } // End if ($startDate && $endDate)
}


/**
 * Generates an Inventory Status Report (Low Stock Items).
 * NOTE: Requires an 'inventory' table. Adjust schema details if needed.
 * Assumes `inventory` table: `product_name`, `current_stock`, `reorder_level`, `status`
 */
function generateInventoryStatus($conn) {
    $tableName = 'inventory'; // Define table name once

    // Check if table exists first to avoid unnecessary query errors if it doesn't
    $checkTableSql = "SHOW TABLES LIKE '" . $conn->real_escape_string($tableName) . "'";
    $tableResult = $conn->query($checkTableSql);
    if ($tableResult->num_rows == 0) {
        echo "<h3>Inventory Status Report</h3>";
        echo "<p>Note: The '{$tableName}' table was not found in the database. Cannot generate this report.</p>";
        $tableResult->close();
        return; // Exit function
    }
    $tableResult->close();

    // Proceed with the actual query if the table exists
    $sql = "SELECT product_name, current_stock, reorder_level
            FROM " . $conn->real_escape_string($tableName) . "
            WHERE current_stock < reorder_level AND status = 'Active' -- Example filter
            ORDER BY product_name";

    $result = $conn->query($sql);
    // Check for errors *after* confirming the table exists
    if (!$result) {
        // Log the specific SQL error
        error_log("DB query error (Inventory): " . $conn->error);
        // Show a generic error to the user
        echo "<h3>Inventory Status Report</h3>";
        echo "<p style='color: red;'>Error retrieving inventory data.</p>";
        return; // Exit function
    }

    echo "<h3>Inventory Status Report (Low Stock Items)</h3>";
    if ($result->num_rows > 0) {
        echo "<table class='accounts-table'>"; // Ensure this class styles tables correctly
        echo "<thead><tr><th>Product Name</th><th>Current Stock</th><th>Reorder Level</th><th>Difference</th></tr></thead>";
        echo "<tbody>";
        while ($row = $result->fetch_assoc()) {
            $difference = $row['reorder_level'] - $row['current_stock'];
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['product_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['current_stock']) . "</td>";
            echo "<td>" . htmlspecialchars($row['reorder_level']) . "</td>";
            echo "<td style='color: red; font-weight: bold;'>" . htmlspecialchars($difference) . " below</td>";
            echo "</tr>";
        }
        echo "</tbody>";
        echo "</table>";
    } else {
        echo "<p>No active items are currently below their reorder level.</p>";
    }
    $result->close();
}


/**
 * Generates an Order Listing Report.
 * Uses 'orders' table: id, order_date, username, company, total_amount, status
 */
function generateOrderTrends($conn, $startDate, $endDate) {
    $sql = "SELECT id, order_date, username, company, total_amount, status
            FROM orders WHERE 1=1"; // Start with 1=1 for easy condition appending

    $params = [];
    $paramTypes = "";

    // Append date conditions if provided
    if ($startDate) { $sql .= " AND DATE(order_date) >= ?"; $params[] = $startDate; $paramTypes .= "s"; }
    if ($endDate) { $sql .= " AND DATE(order_date) <= ?"; $params[] = $endDate; $paramTypes .= "s"; }
    $sql .= " ORDER BY order_date DESC"; // Order by date descending

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new mysqli_sql_exception("DB prepare error (Order Trends): " . $conn->error, $conn->errno);

    if (!empty($paramTypes)) $stmt->bind_param($paramTypes, ...$params);

    if (!$stmt->execute()){
         $err = $stmt->error;
         $errno = $stmt->errno;
         $stmt->close();
         throw new mysqli_sql_exception("DB execute error (Order Trends): " . $err, $errno);
    }
    $result = $stmt->get_result();

    // Determine date range string for the heading
    $dateRangeStr = '';
    if ($startDate && $endDate) $dateRangeStr = " from " . htmlspecialchars($startDate) . " to " . htmlspecialchars($endDate);
    elseif ($startDate) $dateRangeStr = " from " . htmlspecialchars($startDate) . " onwards";
    elseif ($endDate) $dateRangeStr = " up to " . htmlspecialchars($endDate);

    echo "<h3>Order Listing" . $dateRangeStr . "</h3>";
    if ($result->num_rows > 0) {
        echo "<table class='accounts-table'>"; // Ensure this class styles tables correctly
        echo "<thead><tr><th>Order ID</th><th>Date</th><th>Username</th><th>Company</th><th>Total</th><th>Status</th></tr></thead>";
        echo "<tbody>";
        while ($row = $result->fetch_assoc()) {
             // Generate status class for potential styling (e.g., status-completed, status-pending)
             $statusClass = 'status-' . strtolower(preg_replace('/[^a-z0-9]+/', '-', $row['status'] ?? 'unknown')); // Make class CSS-friendly
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars(date('Y-m-d H:i', strtotime($row['order_date']))) . "</td>"; // Format date and time
            echo "<td>" . htmlspecialchars($row['username'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($row['company'] ?? 'N/A') . "</td>"; // Display Company, handle NULLs
            echo "<td style='text-align: right;'>₱ " . number_format($row['total_amount'] ?? 0, 2) . "</td>";
            // Apply status class for styling consistency
            echo "<td class='" . htmlspecialchars($statusClass) . "'>" . htmlspecialchars($row['status']) . "</td>";
            echo "</tr>";
        }
        echo "</tbody>";
        echo "</table>";
    } else {
        echo "<p>No orders found within the specified criteria.</p>";
    }
    $result->close(); // Close result set
    $stmt->close(); // Close statement
}

?>