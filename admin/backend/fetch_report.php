<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Basic security check - ensure user is logged in.
// Consider adding role-based checks here if needed for reporting access specifically
$isLoggedIn = isset($_SESSION['admin_user_id']) || isset($_SESSION['client_user_id']) || isset($_SESSION['user_id']);
if (!$isLoggedIn) {
    http_response_code(403); // Forbidden
    // Using a more structured error message div
    echo "<div class='report-error-message'>Access Denied. Please log in.</div>";
    exit;
}

include_once __DIR__ . '/db_connection.php'; // Establishes $conn

// Default error response function
function sendError($message, $httpCode = 500) {
    http_response_code($httpCode);
    // Using a more structured error message div
    echo "<div class='report-error-message'>" . htmlspecialchars($message) . "</div>";
    // Ensure connection is closed if it was opened
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
// Validate date formats if they are provided (basic validation)
$startDateInput = trim($_POST['start_date'] ?? '');
$endDateInput = trim($_POST['end_date'] ?? '');
$startDate = (!empty($startDateInput) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDateInput)) ? $startDateInput : null;
$endDate = (!empty($endDateInput) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDateInput)) ? $endDateInput : null;

// Additional validation: Start date should not be after end date
if ($startDate && $endDate && $startDate > $endDate) {
    sendError("Start date cannot be after end date.", 400);
}


// --- Report Generation Logic ---

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
     // Log the detailed error for the admin, show a generic message to the user
     error_log("Database connection failed in fetch_report.php: " . ($conn->connect_error ?? 'Unknown error'));
     sendError("Database connection failed. Please contact support.", 500);
}

try {
    switch ($reportType) {
        case 'sales_summary':
            generateSalesSummary($conn, $startDate, $endDate);
            break;

        case 'inventory_status':
            // Inventory status doesn't typically use date ranges, so we ignore $startDate, $endDate here
            generateInventoryStatus($conn);
            break;

        case 'order_trends':
            generateOrderTrends($conn, $startDate, $endDate);
            break;

        default:
            sendError("Invalid report type specified: " . htmlspecialchars($reportType), 400);
            break;
    }
} catch (mysqli_sql_exception $e) {
    // Log the detailed SQL error for the admin
    error_log("SQL Exception in fetch_report.php (Report: {$reportType}): " . $e->getMessage() . " | SQL State: " . $e->getSqlState() . " | Error Code: " . $e->getCode());
    // Show a generic error to the user
    sendError("An unexpected database error occurred while generating the report. Please try again later or contact support.", 500);
} catch (Exception $e) {
    // Log the detailed general error for the admin
    error_log("General Exception in fetch_report.php (Report: {$reportType}): " . $e->getMessage());
    // Show a generic error to the user
    sendError("An unexpected error occurred. Please try again later or contact support.", 500);
} finally {
    // Ensure the connection is closed if it's still open
    if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
        $conn->close();
    }
}

// --- Report Generating Functions ---

/**
 * Generates an Enhanced Sales Summary Report.
 * Uses 'orders' table: id, total_amount, order_date, status, username
 */
function generateSalesSummary($conn, $startDate, $endDate) {
    $dateCondition = "";
    $params = [];
    $paramTypes = "";

    // Build date condition for prepared statement
    if ($startDate) {
        $dateCondition .= " AND DATE(o.order_date) >= ?";
        $params[] = $startDate;
        $paramTypes .= "s";
    }
    if ($endDate) {
        $dateCondition .= " AND DATE(o.order_date) <= ?";
        $params[] = $endDate;
        $paramTypes .= "s";
    }

    // 1. Get Summary Stats for 'Completed' orders
    $sqlSummary = "SELECT
                       COUNT(o.id) as total_orders,
                       SUM(o.total_amount) as total_sales_value,
                       COUNT(DISTINCT o.username) as unique_customer_count
                   FROM orders o
                   WHERE o.status = 'Completed' {$dateCondition}";

    $stmtSummary = $conn->prepare($sqlSummary);
    if (!$stmtSummary) {
        // Log detailed error, throw exception to be caught by main handler
        error_log("DB prepare error (Sales Summary): " . $conn->error);
        throw new mysqli_sql_exception("DB prepare error (Sales Summary)", $conn->errno);
    }
    if (!empty($paramTypes)) {
        $stmtSummary->bind_param($paramTypes, ...$params);
    }
    if (!$stmtSummary->execute()) {
        $err = $stmtSummary->error; $errno = $stmtSummary->errno; $stmtSummary->close();
        error_log("DB execute error (Sales Summary): " . $err);
        throw new mysqli_sql_exception("DB execute error (Sales Summary)", $errno);
    }
    $resultSummary = $stmtSummary->get_result();
    $summary = $resultSummary->fetch_assoc();
    $resultSummary->close();
    $stmtSummary->close();


    // Calculate derived metrics
    $totalOrders = $summary['total_orders'] ?? 0;
    $totalSales = $summary['total_sales_value'] ?? 0;
    $uniqueCustomers = $summary['unique_customer_count'] ?? 0;
    $averageOrderValue = ($totalOrders > 0) ? ($totalSales / $totalOrders) : 0;

    // Format output
    $dateRangeStr = '';
    if ($startDate && $endDate) $dateRangeStr = " from " . htmlspecialchars($startDate) . " to " . htmlspecialchars($endDate);
    elseif ($startDate) $dateRangeStr = " from " . htmlspecialchars($startDate) . " onwards";
    elseif ($endDate) $dateRangeStr = " up to " . htmlspecialchars($endDate);

    echo "<h3 class='report-title'>Sales Summary Report" . $dateRangeStr . "</h3>";
    // Using CSS classes for potentially better styling control
    echo "<div class='report-summary-box'>";
    echo "<table class='summary-table'>"; // Consider adding a specific class like 'report-summary-table'
    echo "<tbody>";
    echo "<tr><th>Total Completed Orders:</th><td>" . number_format($totalOrders) . "</td></tr>";
    echo "<tr><th>Total Sales Value:</th><td>₱ " . number_format($totalSales, 2) . "</td></tr>";
    echo "<tr><th>Average Order Value:</th><td>₱ " . number_format($averageOrderValue, 2) . "</td></tr>";
    echo "<tr><th>Unique Customers:</th><td>" . number_format($uniqueCustomers) . "</td></tr>";
    echo "</tbody></table></div>";

    // --- Optional: Daily Sales Breakdown Table ---
    // Only show if a date range is specified
    if ($startDate && $endDate) {
        $sqlDaily = "SELECT DATE(order_date) as sale_date, COUNT(id) as daily_orders, SUM(total_amount) as daily_sales
                     FROM orders
                     WHERE status = 'Completed' {$dateCondition} -- Reuse date condition and params
                     GROUP BY DATE(order_date)
                     ORDER BY sale_date ASC";

        $stmtDaily = $conn->prepare($sqlDaily);
        if (!$stmtDaily) {
             error_log("DB prepare error (Daily Sales): " . $conn->error);
             throw new mysqli_sql_exception("DB prepare error (Daily Sales)", $conn->errno);
        }
        // Need to re-bind params as they were used in the previous statement
        if (!empty($paramTypes)) {
            $stmtDaily->bind_param($paramTypes, ...$params);
        }
        if (!$stmtDaily->execute()){
            $err = $stmtDaily->error; $errno = $stmtDaily->errno; $stmtDaily->close();
            error_log("DB execute error (Daily Sales): " . $err);
            throw new mysqli_sql_exception("DB execute error (Daily Sales)", $errno);
        }
        $resultDaily = $stmtDaily->get_result();

        if ($resultDaily->num_rows > 0) {
            echo "<h4 class='report-subtitle'>Daily Sales Breakdown</h4>";
            echo "<table class='accounts-table report-table'>"; // Use existing table class + specific report class
            echo "<thead><tr><th>Date</th><th>Orders</th><th>Sales Value</th></tr></thead>";
            echo "<tbody>";
            while ($row = $resultDaily->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['sale_date']) . "</td>";
                echo "<td class='numeric'>" . number_format($row['daily_orders']) . "</td>"; // Class for alignment
                // Assuming currency format is needed
                echo "<td class='currency'>₱ " . number_format($row['daily_sales'], 2) . "</td>"; // Class for alignment
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else {
            // Provide feedback if no data for the specific range
            echo "<p class='no-data-message'>No daily sales data found for the selected period.</p>";
        }
        $resultDaily->close();
        $stmtDaily->close();
    } else {
        // Message if no date range was provided for the daily breakdown
        // echo "<p class='no-data-message'>Select a date range to view daily sales breakdown.</p>";
    }
}


/**
 * Generates a Low Inventory Stock Report, separated by type.
 * Queries 'products' (Company Orders) and 'walkin_products' (Walk-in).
 */
function generateInventoryStatus($conn) {
    // *** LOW STOCK THRESHOLD SET TO 50 ***
    $lowStockThreshold = 50;

    $companyStockData = [];
    $walkinStockData = [];
    $errorOccurred = false;
    $companyQueryError = false;
    $walkinQueryError = false;

    // --- Query for Company Order Low Stock (products table) ---
    $sqlCompany = "SELECT item_description, stock_quantity
                   FROM products
                   WHERE stock_quantity <= ?
                   ORDER BY item_description ASC";

    $stmtCompany = $conn->prepare($sqlCompany);
    if (!$stmtCompany) {
        error_log("DB prepare error (Company Inventory): " . $conn->error);
        $companyQueryError = true;
        $errorOccurred = true;
    } else {
        $stmtCompany->bind_param("i", $lowStockThreshold);
        if (!$stmtCompany->execute()) {
            error_log("DB execute error (Company Inventory): " . $stmtCompany->error);
            $companyQueryError = true;
            $errorOccurred = true;
        } else {
            $resultCompany = $stmtCompany->get_result();
            while ($row = $resultCompany->fetch_assoc()) {
                $companyStockData[] = $row;
            }
            $resultCompany->close();
        }
        $stmtCompany->close();
    }

    // --- Query for Walk-in Low Stock (walkin_products table) ---
    $sqlWalkin = "SELECT item_description, stock_quantity
                  FROM walkin_products
                  WHERE stock_quantity <= ?
                  ORDER BY item_description ASC";

    $stmtWalkin = $conn->prepare($sqlWalkin);
     if (!$stmtWalkin) {
        error_log("DB prepare error (Walkin Inventory): " . $conn->error);
        $walkinQueryError = true;
        $errorOccurred = true;
    } else {
        $stmtWalkin->bind_param("i", $lowStockThreshold);
        if (!$stmtWalkin->execute()) {
            error_log("DB execute error (Walkin Inventory): " . $stmtWalkin->error);
            $walkinQueryError = true;
            $errorOccurred = true;
        } else {
            $resultWalkin = $stmtWalkin->get_result();
             while ($row = $resultWalkin->fetch_assoc()) {
                $walkinStockData[] = $row;
            }
            $resultWalkin->close();
        }
        $stmtWalkin->close();
    }

    // --- Display Results ---
    echo "<h3 class='report-title'>Low Inventory Stock Report (Threshold: " . htmlspecialchars($lowStockThreshold) . " or less)</h3>";

    // Display any query errors first
    if ($companyQueryError) {
         echo "<div class='report-error-message'>Error retrieving Company Order stock data.</div>";
    }
     if ($walkinQueryError) {
         echo "<div class='report-error-message'>Error retrieving Walk-in stock data.</div>";
    }
     if ($errorOccurred) {
        // If any error happened, don't proceed to display potentially incomplete data tables
        return;
     }


    // Display Company Order Low Stock Table
    echo "<div class='inventory-section company-inventory'>";
    echo "<h4 class='report-subtitle'>Company Order Inventory</h4>";
    if (!empty($companyStockData)) {
        echo "<table class='accounts-table report-table'>"; // Use consistent table classes
        echo "<thead><tr><th>Item Description</th><th>Stock Quantity</th></tr></thead>";
        echo "<tbody>";
        foreach ($companyStockData as $row) {
            $itemDescription = $row['item_description'] ?? 'N/A';
            $stockQuantity = $row['stock_quantity'] ?? 0;
            echo "<tr>";
            echo "<td>" . htmlspecialchars($itemDescription) . "</td>";
            echo "<td class='low-stock-highlight numeric'>" . htmlspecialchars($stockQuantity) . "</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p class='no-data-message'>No low stock items found for Company Orders (Threshold: " . htmlspecialchars($lowStockThreshold) . ").</p>";
    }
    echo "</div>"; // End Company Stock container


    // Display Walk-in Low Stock Table
    echo "<div class='inventory-section walkin-inventory'>";
    echo "<h4 class='report-subtitle'>Walk-in Inventory</h4>";
    if (!empty($walkinStockData)) {
        echo "<table class='accounts-table report-table'>"; // Use consistent table classes
        echo "<thead><tr><th>Item Description</th><th>Stock Quantity</th></tr></thead>";
        echo "<tbody>";
         foreach ($walkinStockData as $row) {
            $itemDescription = $row['item_description'] ?? 'N/A';
            $stockQuantity = $row['stock_quantity'] ?? 0;
            echo "<tr>";
            echo "<td>" . htmlspecialchars($itemDescription) . "</td>";
            echo "<td class='low-stock-highlight numeric'>" . htmlspecialchars($stockQuantity) . "</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p class='no-data-message'>No low stock items found for Walk-in (Threshold: " . htmlspecialchars($lowStockThreshold) . ").</p>";
    }
    echo "</div>"; // End Walk-in Stock container

    // Overall message if nothing found in either
    if (empty($companyStockData) && empty($walkinStockData)) {
         // The individual messages above might be sufficient, but uncomment if you want an overall one too.
         // echo "<p class='no-data-message' style='margin-top: 20px; font-weight: bold;'>No low stock items found in either inventory below the threshold.</p>";
    }
}


/**
 * Generates an Order Listing Report.
 * Uses 'orders' table: id, order_date, username, company, total_amount, status
 */
function generateOrderTrends($conn, $startDate, $endDate) {
    $sql = "SELECT id, po_number, order_date, username, company, total_amount, status
            FROM orders WHERE 1=1"; // Added po_number

    $params = [];
    $paramTypes = "";

    // Build date condition for prepared statement
    if ($startDate) { $sql .= " AND DATE(order_date) >= ?"; $params[] = $startDate; $paramTypes .= "s"; }
    if ($endDate) { $sql .= " AND DATE(order_date) <= ?"; $params[] = $endDate; $paramTypes .= "s"; }
    $sql .= " ORDER BY order_date DESC, id DESC"; // Sort by date, then ID

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("DB prepare error (Order Trends): " . $conn->error);
        throw new mysqli_sql_exception("DB prepare error (Order Trends)", $conn->errno);
    }
    if (!empty($paramTypes)) {
        $stmt->bind_param($paramTypes, ...$params);
    }
    if (!$stmt->execute()){
        $err = $stmt->error; $errno = $stmt->errno; $stmt->close();
        error_log("DB execute error (Order Trends): " . $err);
        throw new mysqli_sql_exception("DB execute error (Order Trends)", $errno);
    }
    $result = $stmt->get_result();

    $dateRangeStr = '';
    if ($startDate && $endDate) $dateRangeStr = " from " . htmlspecialchars($startDate) . " to " . htmlspecialchars($endDate);
    elseif ($startDate) $dateRangeStr = " from " . htmlspecialchars($startDate) . " onwards";
    elseif ($endDate) $dateRangeStr = " up to " . htmlspecialchars($endDate);

    echo "<h3 class='report-title'>Order Listing" . $dateRangeStr . "</h3>";
    if ($result->num_rows > 0) {
        echo "<table class='accounts-table report-table'>"; // Use consistent table classes
        echo "<thead><tr><th>Order ID</th><th>PO Number</th><th>Date</th><th>Username</th><th>Company</th><th>Total</th><th>Status</th></tr></thead>"; // Added PO Number header
        echo "<tbody>";
        while ($row = $result->fetch_assoc()) {
             // Generate a CSS class based on the status for potential styling
             $statusClass = 'status-' . strtolower(preg_replace('/[^a-z0-9]+/', '-', $row['status'] ?? 'unknown'));
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['po_number'] ?? 'N/A') . "</td>"; // Added PO Number data cell
            // Format date for better readability
            echo "<td>" . htmlspecialchars(date('Y-m-d H:i', strtotime($row['order_date']))) . "</td>";
            echo "<td>" . htmlspecialchars($row['username'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($row['company'] ?? 'N/A') . "</td>";
            echo "<td class='currency'>₱ " . number_format($row['total_amount'] ?? 0, 2) . "</td>"; // Class for alignment
            echo "<td class='" . htmlspecialchars($statusClass) . "'>" . htmlspecialchars($row['status']) . "</td>";
            echo "</tr>";
        }
        echo "</tbody>";
        echo "</table>";
    } else {
        echo "<p class='no-data-message'>No orders found within the specified criteria.</p>";
    }
    $result->close();
    $stmt->close();
}

?>