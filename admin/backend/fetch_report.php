<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Pre-check: Verify get_result() is available ---
if (!method_exists('mysqli_stmt', 'get_result')) {
    error_log("CRITICAL WARNING: mysqli_stmt::get_result() method does not exist. The 'mysqlnd' driver is likely not installed or enabled in PHP. Reports requiring get_result() will fail.");
    // You might want to send an error immediately if get_result is essential for all reports
    // sendError("Server configuration error: Required database driver (mysqlnd) is missing.", 500);
}

// Basic security check - ensure user is logged in.
$isLoggedIn = isset($_SESSION['admin_user_id']) || isset($_SESSION['client_user_id']) || isset($_SESSION['user_id']);
if (!$isLoggedIn) {
    http_response_code(403); // Forbidden
    echo "<div class='report-error-message'>Access Denied. Please log in.</div>";
    exit;
}

// Include database connection - ensure path is correct
// Assuming fetch_report.php is in /backend/
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

// Main try-catch block for report generation
try {
    switch ($reportType) {
        case 'sales_summary':
            generateSalesSummary($conn, $startDate, $endDate);
            break;

        case 'inventory_status':
            // Inventory status doesn't typically use date ranges
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
    // Log the detailed SQL error for the admin - THIS IS WHERE THE ERROR IS LIKELY CAUGHT
    error_log("!!! CAUGHT SQL Exception in fetch_report.php (Report: {$reportType}): " . $e->getMessage() . " | SQL State: " . $e->getSqlState() . " | Error Code: " . $e->getCode() . " | Trace: " . $e->getTraceAsString());
    // Show a generic error to the user
    sendError("An unexpected database error occurred while generating the report. Please try again later or contact support.", 500);
} catch (Exception $e) {
    // Log other types of errors
    error_log("!!! CAUGHT General Exception in fetch_report.php (Report: {$reportType}): " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
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
    error_log("--- Starting generateSalesSummary ---"); // Log start

    // --- Build conditions/params for the FIRST query (Summary) ---
    $summaryDateCondition = "";
    $summaryParams = [];
    $summaryParamTypes = "";
    if ($startDate) {
        $summaryDateCondition .= " AND DATE(o.order_date) >= ?";
        $summaryParams[] = $startDate;
        $summaryParamTypes .= "s";
    }
    if ($endDate) {
        $summaryDateCondition .= " AND DATE(o.order_date) <= ?";
        $summaryParams[] = $endDate;
        $summaryParamTypes .= "s";
    }
    error_log("Summary conditions: Condition='{$summaryDateCondition}', Types='{$summaryParamTypes}', Params=" . json_encode($summaryParams));

    // 1. Get Summary Stats
    $sqlSummary = "SELECT
                       COUNT(o.id) as total_orders,
                       SUM(o.total_amount) as total_sales_value,
                       COUNT(DISTINCT o.username) as unique_customer_count
                   FROM orders o
                   WHERE o.status = 'Completed' {$summaryDateCondition}"; // Use summary condition
    error_log("Summary SQL: " . $sqlSummary);

    $stmtSummary = $conn->prepare($sqlSummary);
    if (!$stmtSummary) {
        error_log("!!! DB prepare error (Sales Summary): " . $conn->error);
        throw new mysqli_sql_exception("DB prepare error (Sales Summary): " . $conn->error, $conn->errno);
    }
    error_log("Summary statement prepared.");

    if (!empty($summaryParamTypes)) {
        error_log("Binding summary params...");
        // Use summary params/types
        $stmtSummary->bind_param($summaryParamTypes, ...$summaryParams);
         if ($stmtSummary->errno) {
            error_log("!!! DB bind_param error (Summary): " . $stmtSummary->error);
            throw new mysqli_sql_exception("DB bind_param error (Summary): " . $stmtSummary->error, $stmtSummary->errno);
         }
        error_log("Summary params bound.");
    }

    if (!$stmtSummary->execute()) {
        $err = $stmtSummary->error; $errno = $stmtSummary->errno;
        error_log("!!! DB execute error (Sales Summary): " . $err);
        $stmtSummary->close(); // Close before throwing
        throw new mysqli_sql_exception("DB execute error (Sales Summary): " . $err, $errno);
    }
    error_log("Summary statement executed.");

    // Check if get_result is available before calling it
    if (!method_exists($stmtSummary, 'get_result')) {
        error_log("!!! CRITICAL: get_result() failed for Summary Statement. mysqlnd driver likely missing.");
        $stmtSummary->close();
        throw new Exception("Server configuration error: Required database driver feature (get_result) is missing.");
    }

    $resultSummary = $stmtSummary->get_result();
     if ($stmtSummary->errno) {
        error_log("!!! DB get_result error (Summary): " . $stmtSummary->error);
        $stmtSummary->close(); // Close before throwing
        throw new mysqli_sql_exception("DB get_result error (Summary): " . $stmtSummary->error, $stmtSummary->errno);
     }
    error_log("Summary result obtained.");

    $summary = $resultSummary->fetch_assoc();
    $resultSummary->close();
    $stmtSummary->close();
    error_log("Summary data fetched and statement closed.");

    // Calculate and format summary output
    $totalOrders = $summary['total_orders'] ?? 0;
    $totalSales = $summary['total_sales_value'] ?? 0;
    $uniqueCustomers = $summary['unique_customer_count'] ?? 0;
    $averageOrderValue = ($totalOrders > 0) ? ($totalSales / $totalOrders) : 0;
    $dateRangeStr = '';
    if ($startDate && $endDate) $dateRangeStr = " from " . htmlspecialchars($startDate) . " to " . htmlspecialchars($endDate);
    elseif ($startDate) $dateRangeStr = " from " . htmlspecialchars($startDate) . " onwards";
    elseif ($endDate) $dateRangeStr = " up to " . htmlspecialchars($endDate);

    // Output Summary Box
    echo "<h3 class='report-title'>Sales Summary Report" . $dateRangeStr . "</h3>";
    echo "<div class='report-summary-box'>";
    echo "<table class='summary-table'>";
    echo "<tbody>";
    echo "<tr><th>Total Completed Orders:</th><td>" . number_format($totalOrders) . "</td></tr>";
    echo "<tr><th>Total Sales Value:</th><td>₱ " . number_format($totalSales, 2) . "</td></tr>";
    echo "<tr><th>Average Order Value:</th><td>₱ " . number_format($averageOrderValue, 2) . "</td></tr>";
    echo "<tr><th>Unique Customers:</th><td>" . number_format($uniqueCustomers) . "</td></tr>";
    echo "</tbody></table></div>";
    error_log("Summary box output generated.");

    // --- Optional: Daily Sales Breakdown Table ---
    // *** THIS IS THE MOST LIKELY FAILURE POINT ***
    if ($startDate && $endDate) {
        error_log("--- Starting Daily Sales Breakdown section ---");

        // *** REBUILD conditions/params for the SECOND query (Daily) ***
        $dailyDateCondition = ""; // Use new variable
        $dailyParams = [];        // Use new variable
        $dailyParamTypes = "";    // Use new variable
        if ($startDate) {
            // Check if 'order_date' column exists and is correct
            $dailyDateCondition .= " AND DATE(order_date) >= ?";
            $dailyParams[] = $startDate;
            $dailyParamTypes .= "s";
        }
        if ($endDate) {
            $dailyDateCondition .= " AND DATE(order_date) <= ?";
            $dailyParams[] = $endDate;
            $dailyParamTypes .= "s";
        }
        error_log("Daily conditions: Condition='{$dailyDateCondition}', Types='{$dailyParamTypes}', Params=" . json_encode($dailyParams));
        // *** End Rebuild ***

        // Check table and column names carefully in this query
        $sqlDaily = "SELECT DATE(order_date) as sale_date, COUNT(id) as daily_orders, SUM(total_amount) as daily_sales
                     FROM orders
                     WHERE status = 'Completed' {$dailyDateCondition} -- Use daily condition
                     GROUP BY DATE(order_date)
                     ORDER BY sale_date ASC";
        error_log("Daily Sales SQL: " . $sqlDaily);

        $stmtDaily = $conn->prepare($sqlDaily);
        if (!$stmtDaily) {
             error_log("!!! DB prepare error (Daily Sales): " . $conn->error);
             throw new mysqli_sql_exception("DB prepare error (Daily Sales): " . $conn->error, $conn->errno);
        }
        error_log("Daily Sales statement prepared.");

        if (!empty($dailyParamTypes)) {
             error_log("Binding Daily Sales params...");
             // Use the specifically built daily params/types
             $stmtDaily->bind_param($dailyParamTypes, ...$dailyParams);
             if ($stmtDaily->errno) {
                error_log("!!! DB bind_param error (Daily Sales): " . $stmtDaily->error);
                 $stmtDaily->close(); // Close before throwing
                throw new mysqli_sql_exception("DB bind_param error (Daily Sales): " . $stmtDaily->error, $stmtDaily->errno);
             }
             error_log("Daily Sales params bound.");
        }

        if (!$stmtDaily->execute()){
            $err = $stmtDaily->error; $errno = $stmtDaily->errno;
            error_log("!!! DB execute error (Daily Sales): " . $err);
            $stmtDaily->close(); // Close before throwing
            throw new mysqli_sql_exception("DB execute error (Daily Sales): " . $err, $errno);
        }
        error_log("Daily Sales statement executed.");

        // Check if get_result is available before calling it
        if (!method_exists($stmtDaily, 'get_result')) {
            error_log("!!! CRITICAL: get_result() failed for Daily Statement. mysqlnd driver likely missing.");
            $stmtDaily->close();
            throw new Exception("Server configuration error: Required database driver feature (get_result) is missing.");
        }

        $resultDaily = $stmtDaily->get_result();
         if ($stmtDaily->errno) {
            error_log("!!! DB get_result error (Daily Sales): " . $stmtDaily->error);
            $stmtDaily->close(); // Close before throwing
            throw new mysqli_sql_exception("DB get_result error (Daily Sales): " . $stmtDaily->error, $stmtDaily->errno);
         }
        error_log("Daily Sales result obtained. Num rows: " . $resultDaily->num_rows);

        // Output Daily Breakdown Table
        if ($resultDaily->num_rows > 0) {
            echo "<h4 class='report-subtitle'>Daily Sales Breakdown</h4>";
            echo "<table class='accounts-table report-table'>";
            echo "<thead><tr><th>Date</th><th>Orders</th><th>Sales Value</th></tr></thead>";
            echo "<tbody>";
            while ($row = $resultDaily->fetch_assoc()) {
                // Check if keys exist, provide defaults if not
                $saleDate = htmlspecialchars($row['sale_date'] ?? 'N/A');
                $dailyOrders = number_format($row['daily_orders'] ?? 0);
                $dailySales = number_format($row['daily_sales'] ?? 0, 2);
                echo "<tr>";
                echo "<td>" . $saleDate . "</td>";
                echo "<td class='numeric'>" . $dailyOrders . "</td>";
                echo "<td class='currency'>₱ " . $dailySales . "</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
            error_log("Daily sales table generated.");
        } else {
            echo "<p class='no-data-message'>No daily sales data found for the selected period.</p>";
            error_log("No daily sales data found message generated.");
        }
        $resultDaily->close();
        $stmtDaily->close();
        error_log("--- Daily Sales Breakdown section completed. ---");
    } else {
         error_log("Skipping Daily Sales Breakdown (no start/end date provided).");
    }
    error_log("--- Finished generateSalesSummary ---"); // Log end
}


/**
 * Generates a Low Inventory Stock Report, separated by type.
 * Queries 'products' (Company Orders) and 'walkin_products' (Walk-in).
 */
function generateInventoryStatus($conn) {
    error_log("--- Starting generateInventoryStatus ---");
    $lowStockThreshold = 50;
    $companyStockData = [];
    $walkinStockData = [];
    $errorOccurred = false;
    $companyQueryError = false;
    $walkinQueryError = false;

    // --- Query for Company Order Low Stock (products table) ---
    $sqlCompany = "SELECT item_description, stock_quantity FROM products WHERE stock_quantity <= ? ORDER BY item_description ASC";
    error_log("Company Inventory SQL: " . $sqlCompany);
    $stmtCompany = $conn->prepare($sqlCompany);
    if (!$stmtCompany) {
        error_log("!!! DB prepare error (Company Inventory): " . $conn->error);
        $companyQueryError = true; $errorOccurred = true;
    } else {
        $stmtCompany->bind_param("i", $lowStockThreshold);
        if (!$stmtCompany->execute()) {
            error_log("!!! DB execute error (Company Inventory): " . $stmtCompany->error);
            $companyQueryError = true; $errorOccurred = true;
        } else {
             // Check if get_result is available
            if (!method_exists($stmtCompany, 'get_result')) {
                error_log("!!! CRITICAL: get_result() failed for Company Inventory. mysqlnd driver likely missing.");
                $companyQueryError = true; $errorOccurred = true;
            } else {
                $resultCompany = $stmtCompany->get_result();
                if ($stmtCompany->errno) {
                    error_log("!!! DB get_result error (Company Inventory): " . $stmtCompany->error);
                    $companyQueryError = true; $errorOccurred = true;
                } else {
                    while ($row = $resultCompany->fetch_assoc()) { $companyStockData[] = $row; }
                    $resultCompany->close();
                    error_log("Company inventory fetched. Count: " . count($companyStockData));
                }
            }
        }
        $stmtCompany->close();
    }

    // --- Query for Walk-in Low Stock (walkin_products table) ---
    $sqlWalkin = "SELECT item_description, stock_quantity FROM walkin_products WHERE stock_quantity <= ? ORDER BY item_description ASC";
    error_log("Walkin Inventory SQL: " . $sqlWalkin);
    $stmtWalkin = $conn->prepare($sqlWalkin);
    if (!$stmtWalkin) {
        error_log("!!! DB prepare error (Walkin Inventory): " . $conn->error);
        $walkinQueryError = true; $errorOccurred = true;
    } else {
        $stmtWalkin->bind_param("i", $lowStockThreshold);
        if (!$stmtWalkin->execute()) {
            error_log("!!! DB execute error (Walkin Inventory): " . $stmtWalkin->error);
            $walkinQueryError = true; $errorOccurred = true;
        } else {
            // Check if get_result is available
            if (!method_exists($stmtWalkin, 'get_result')) {
                 error_log("!!! CRITICAL: get_result() failed for Walkin Inventory. mysqlnd driver likely missing.");
                 $walkinQueryError = true; $errorOccurred = true;
            } else {
                $resultWalkin = $stmtWalkin->get_result();
                 if ($stmtWalkin->errno) {
                    error_log("!!! DB get_result error (Walkin Inventory): " . $stmtWalkin->error);
                    $walkinQueryError = true; $errorOccurred = true;
                } else {
                    while ($row = $resultWalkin->fetch_assoc()) { $walkinStockData[] = $row; }
                    $resultWalkin->close();
                    error_log("Walkin inventory fetched. Count: " . count($walkinStockData));
                }
            }
        }
        $stmtWalkin->close();
    }

    // --- Display Results ---
    echo "<h3 class='report-title'>Low Inventory Stock Report (Threshold: " . htmlspecialchars($lowStockThreshold) . " or less)</h3>";
    if ($companyQueryError) { echo "<div class='report-error-message'>Error retrieving Company Order stock data.</div>"; }
    if ($walkinQueryError) { echo "<div class='report-error-message'>Error retrieving Walk-in stock data.</div>"; }
    // Stop if core errors occurred
    if ($errorOccurred && ($companyQueryError || $walkinQueryError)) {
        error_log("Inventory report stopped due to query errors.");
        return;
    }

    // Display Company Order Low Stock Table
    echo "<div class='inventory-section company-inventory'><h4 class='report-subtitle'>Company Order Inventory</h4>";
    if (!empty($companyStockData)) {
        echo "<table class='accounts-table report-table'><thead><tr><th>Item Description</th><th>Stock Quantity</th></tr></thead><tbody>";
        foreach ($companyStockData as $row) {
            $itemDesc = htmlspecialchars($row['item_description'] ?? 'N/A');
            $stockQty = htmlspecialchars($row['stock_quantity'] ?? 0);
            echo "<tr><td>{$itemDesc}</td><td class='low-stock-highlight numeric'>{$stockQty}</td></tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p class='no-data-message'>No low stock items found for Company Orders (Threshold: " . htmlspecialchars($lowStockThreshold) . ").</p>";
    }
    echo "</div>"; // End Company Stock container

    // Display Walk-in Low Stock Table
    echo "<div class='inventory-section walkin-inventory'><h4 class='report-subtitle'>Walk-in Inventory</h4>";
    if (!empty($walkinStockData)) {
        echo "<table class='accounts-table report-table'><thead><tr><th>Item Description</th><th>Stock Quantity</th></tr></thead><tbody>";
        foreach ($walkinStockData as $row) {
            $itemDesc = htmlspecialchars($row['item_description'] ?? 'N/A');
            $stockQty = htmlspecialchars($row['stock_quantity'] ?? 0);
            echo "<tr><td>{$itemDesc}</td><td class='low-stock-highlight numeric'>{$stockQty}</td></tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p class='no-data-message'>No low stock items found for Walk-in (Threshold: " . htmlspecialchars($lowStockThreshold) . ").</p>";
    }
    echo "</div>"; // End Walk-in Stock container

    error_log("--- Finished generateInventoryStatus ---");
}


/**
 * Generates an Order Listing Report.
 * Uses 'orders' table: id, po_number, order_date, username, company, total_amount, status
 */
function generateOrderTrends($conn, $startDate, $endDate) {
    error_log("--- Starting generateOrderTrends ---");
    // Ensure all selected columns exist in the 'orders' table
    $sql = "SELECT id, po_number, order_date, username, company, total_amount, status FROM orders WHERE 1=1";
    $params = []; $paramTypes = "";

    if ($startDate) { $sql .= " AND DATE(order_date) >= ?"; $params[] = $startDate; $paramTypes .= "s"; }
    if ($endDate) { $sql .= " AND DATE(order_date) <= ?"; $params[] = $endDate; $paramTypes .= "s"; }
    $sql .= " ORDER BY order_date DESC, id DESC";
    error_log("Order Trends SQL: " . $sql);

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("!!! DB prepare error (Order Trends): " . $conn->error);
        throw new mysqli_sql_exception("DB prepare error (Order Trends): " . $conn->error, $conn->errno);
    }
     error_log("Order Trends statement prepared.");

    if (!empty($paramTypes)) {
        error_log("Binding Order Trends params: Types=" . $paramTypes . ", Values=" . json_encode($params));
        $stmt->bind_param($paramTypes, ...$params);
         if ($stmt->errno) {
            error_log("!!! DB bind_param error (Order Trends): " . $stmt->error);
             $stmt->close();
            throw new mysqli_sql_exception("DB bind_param error (Order Trends): " . $stmt->error, $stmt->errno);
         }
         error_log("Order Trends params bound.");
    }

    if (!$stmt->execute()){
        $err = $stmt->error; $errno = $stmt->errno;
        error_log("!!! DB execute error (Order Trends): " . $err);
        $stmt->close(); // Close before throwing
        throw new mysqli_sql_exception("DB execute error (Order Trends): " . $err, $errno);
    }
     error_log("Order Trends statement executed.");

     // Check if get_result is available
    if (!method_exists($stmt, 'get_result')) {
        error_log("!!! CRITICAL: get_result() failed for Order Trends Statement. mysqlnd driver likely missing.");
        $stmt->close();
        throw new Exception("Server configuration error: Required database driver feature (get_result) is missing.");
    }

    $result = $stmt->get_result();
     if ($stmt->errno) {
        error_log("!!! DB get_result error (Order Trends): " . $stmt->error);
        $stmt->close(); // Close before throwing
        throw new mysqli_sql_exception("DB get_result error (Order Trends): " . $stmt->error, $stmt->errno);
     }
     error_log("Order Trends result obtained. Num rows: " . $result->num_rows);

    // Format and output results
    $dateRangeStr = '';
    if ($startDate && $endDate) $dateRangeStr = " from " . htmlspecialchars($startDate) . " to " . htmlspecialchars($endDate);
    elseif ($startDate) $dateRangeStr = " from " . htmlspecialchars($startDate) . " onwards";
    elseif ($endDate) $dateRangeStr = " up to " . htmlspecialchars($endDate);

    echo "<h3 class='report-title'>Order Listing" . $dateRangeStr . "</h3>";
    if ($result->num_rows > 0) {
        echo "<table class='accounts-table report-table'><thead><tr><th>Order ID</th><th>PO Number</th><th>Date</th><th>Username</th><th>Company</th><th>Total</th><th>Status</th></tr></thead><tbody>";
        while ($row = $result->fetch_assoc()) {
             $statusClass = 'status-' . strtolower(preg_replace('/[^a-z0-9]+/', '-', $row['status'] ?? 'unknown'));
             $orderId = htmlspecialchars($row['id'] ?? 'N/A');
             $poNum = htmlspecialchars($row['po_number'] ?? 'N/A');
             $orderDate = htmlspecialchars(isset($row['order_date']) ? date('Y-m-d H:i', strtotime($row['order_date'])) : 'N/A');
             $username = htmlspecialchars($row['username'] ?? 'N/A');
             $company = htmlspecialchars($row['company'] ?? 'N/A');
             $total = number_format($row['total_amount'] ?? 0, 2);
             $status = htmlspecialchars($row['status'] ?? 'N/A');

            echo "<tr><td>{$orderId}</td><td>{$poNum}</td><td>{$orderDate}</td><td>{$username}</td><td>{$company}</td><td class='currency'>₱ {$total}</td><td class='{$statusClass}'>{$status}</td></tr>";
        }
        echo "</tbody></table>";
         error_log("Order trends table generated.");
    } else {
        echo "<p class='no-data-message'>No orders found within the specified criteria.</p>";
         error_log("No order trends data found message generated.");
    }
    $result->close();
    $stmt->close();
    error_log("--- Finished generateOrderTrends ---");
}

// Make sure the closing PHP tag is present if this is the end of the file
?>