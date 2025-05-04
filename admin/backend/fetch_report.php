<?php
// UTC: 2025-05-04 09:48:01
// Location: Assumed moved to public/api/fetch_report.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Pre-check: Verify get_result() is available ---
if (!function_exists('mysqli_stmt_get_result')) {
    if (!method_exists('mysqli_stmt', 'get_result')) {
        error_log("CRITICAL WARNING in fetch_report.php: mysqli_stmt::get_result() method does not exist. The 'mysqlnd' (MySQL Native Driver) is likely not installed or enabled in PHP. Reports requiring get_result() will fail.");
        http_response_code(500);
        echo "<div class='report-error-message'>Server configuration error: Required database driver (mysqlnd) is missing. Please contact the administrator.</div>";
        exit;
    }
}

// --- UPDATED INCLUDE PATHS ---
// Go up one level from /api/, then into /backend/
include_once __DIR__ . '/../backend/db_connection.php';

// Basic security check (using the included connection)
$isLoggedIn = isset($_SESSION['admin_user_id']) || isset($_SESSION['client_user_id']) || isset($_SESSION['user_id']);
if (!$isLoggedIn) {
    http_response_code(403);
    echo "<div class='report-error-message'>Access Denied. Please log in.</div>";
    if (isset($conn) && $conn instanceof mysqli) $conn->close(); // Close connection if opened by include
    exit;
}
// Note: Role check is now handled in reporting.php before calling this script.
// If you need finer control here, re-add role check logic using the included $conn.

// Default error response function
function sendError($message, $httpCode = 500) {
    http_response_code($httpCode);
    // Ensure the class name matches CSS in reporting.php
    echo "<div class='report-error-message'>" . htmlspecialchars($message) . "</div>";
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        $GLOBALS['conn']->close();
    }
    exit;
}

// --- Helper Function to Format Weight ---
function formatWeight($grams) {
    if (!is_numeric($grams)) return 'N/A';
    $grams = floatval($grams);
    if ($grams >= 1000) return number_format($grams / 1000, 2) . ' kg';
    else return number_format($grams, 0) . ' g';
}


// Check request method and input
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['report_type'])) {
    sendError("Invalid request method or missing report type.", 400);
}

// Input Sanitization/Retrieval
$reportType = trim($_POST['report_type']);
$startDateInput = trim($_POST['start_date'] ?? '');
$endDateInput = trim($_POST['end_date'] ?? '');
$startDate = (!empty($startDateInput) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDateInput)) ? $startDateInput : null;
$endDate = (!empty($endDateInput) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDateInput)) ? $endDateInput : null;

if ($startDate && $endDate && $startDate > $endDate) {
    sendError("Start date cannot be after end date.", 400);
}

// DB Connection Check (from included file)
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
     error_log("Database connection failed in fetch_report.php: " . ($conn->connect_error ?? 'Unknown error'));
     sendError("Database connection failed. Please contact support.", 500);
}

// Main try-catch block
try {
    error_log("--- Starting report generation for type: {$reportType} ---");
    switch ($reportType) {
        case 'sales_summary':
             generateSalesSummary($conn, $startDate, $endDate);
            break;

        case 'inventory_status':
            // --- This function now handles finished products AND raw materials ---
            generateInventoryStatus($conn);
            break;

        case 'order_trends':
            generateOrderTrends($conn, $startDate, $endDate);
            break;

        case 'sales_by_client':
            generateSalesByClient($conn, $startDate, $endDate);
            break;

        case 'sales_by_product':
            generateSalesByProduct($conn, $startDate, $endDate);
            break;

        default:
            error_log("Invalid report type specified: {$reportType}");
            sendError("Invalid report type specified: " . htmlspecialchars($reportType), 400);
            break;
    }
    error_log("--- Finished report generation for type: {$reportType} ---");
} catch (mysqli_sql_exception $e) {
    // Log the detailed SQL error
    error_log("!!! CAUGHT SQL Exception in fetch_report.php (Report: {$reportType}): " . $e->getMessage() . " | SQL State: " . $e->getSqlState() . " | Error Code: " . $e->getCode());
    // Send a generic error to the user
    sendError("An unexpected database error occurred while generating the report.", 500);
} catch (JsonException $e) { // Catch JSON decoding errors specifically
    error_log("!!! CAUGHT JSON Exception in fetch_report.php (Report: {$reportType}): " . $e->getMessage());
    sendError("Error processing order data. Invalid format found.", 500);
}
catch (Exception $e) {
    error_log("!!! CAUGHT General Exception in fetch_report.php (Report: {$reportType}): " . $e->getMessage());
    sendError("An unexpected error occurred.", 500);
} finally {
    if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
        $conn->close();
        error_log("Database connection closed.");
    } else {
        error_log("Database connection was already closed or invalid.");
    }
}

// --- Report Generating Functions ---

// generateSalesSummary, generateOrderTrends, generateSalesByClient, generateSalesByProduct functions remain the same as your provided code...
// (Include them here for completeness)

/**
 * Generates a Sales Summary Report.
 */
function generateSalesSummary($conn, $startDate, $endDate) {
    error_log("--- Starting generateSalesSummary ---");
    $summaryDateCondition = ""; $summaryParams = []; $summaryParamTypes = "";
    if ($startDate) { $summaryDateCondition .= " AND DATE(o.order_date) >= ?"; $summaryParams[] = $startDate; $summaryParamTypes .= "s"; }
    if ($endDate) { $summaryDateCondition .= " AND DATE(o.order_date) <= ?"; $summaryParams[] = $endDate; $summaryParamTypes .= "s"; }

    $sqlSummary = "SELECT COUNT(o.id) as total_orders, SUM(o.total_amount) as total_sales_value, COUNT(DISTINCT o.username) as unique_customer_count
                   FROM orders o WHERE o.status = 'Completed' {$summaryDateCondition}";
    $stmtSummary = $conn->prepare($sqlSummary);
    if (!$stmtSummary) throw new mysqli_sql_exception("DB prepare error (Sales Summary): " . $conn->error, $conn->errno);
    if (!empty($summaryParamTypes)) {
        $stmtSummary->bind_param($summaryParamTypes, ...$summaryParams);
        if ($stmtSummary->errno) { $err = $stmtSummary->error; $errno = $stmtSummary->errno; $stmtSummary->close(); throw new mysqli_sql_exception("DB bind_param error (Summary): " . $err, $errno); }
    }
    if (!$stmtSummary->execute()) { $err = $stmtSummary->error; $errno = $stmtSummary->errno; $stmtSummary->close(); throw new mysqli_sql_exception("DB execute error (Sales Summary): " . $err, $errno); }
    if (!method_exists($stmtSummary, 'get_result')) { $stmtSummary->close(); throw new Exception("Server configuration error: get_result missing."); }
    $resultSummary = $stmtSummary->get_result();
    if ($stmtSummary->errno) { $err = $stmtSummary->error; $errno = $stmtSummary->errno; $stmtSummary->close(); throw new mysqli_sql_exception("DB get_result error (Summary): " . $err, $errno); }
    $summary = $resultSummary->fetch_assoc();
    $resultSummary->close(); $stmtSummary->close();

    $totalOrders = $summary['total_orders'] ?? 0;
    $totalSales = $summary['total_sales_value'] ?? 0;
    $uniqueCustomers = $summary['unique_customer_count'] ?? 0;
    $averageOrderValue = ($totalOrders > 0) ? ($totalSales / $totalOrders) : 0;
    $dateRangeStr = '';
    if ($startDate && $endDate) $dateRangeStr = " from " . htmlspecialchars(date("M d, Y", strtotime($startDate))) . " to " . htmlspecialchars(date("M d, Y", strtotime($endDate)));
    elseif ($startDate) $dateRangeStr = " from " . htmlspecialchars(date("M d, Y", strtotime($startDate))) . " onwards";
    elseif ($endDate) $dateRangeStr = " up to " . htmlspecialchars(date("M d, Y", strtotime($endDate)));

    echo "<h3 class='report-title'>Sales Summary Report" . $dateRangeStr . "</h3>";
    echo "<div class='report-summary-box'><table class='summary-table'><tbody>";
    echo "<tr><th>Total Completed Orders:</th><td>" . number_format($totalOrders) . "</td></tr>";
    echo "<tr><th>Total Sales Value:</th><td>₱ " . number_format($totalSales, 2) . "</td></tr>";
    echo "<tr><th>Average Order Value:</th><td>₱ " . number_format($averageOrderValue, 2) . "</td></tr>";
    echo "<tr><th>Unique Customers:</th><td>" . number_format($uniqueCustomers) . "</td></tr>";
    echo "</tbody></table></div>";

    if ($startDate && $endDate) {
        $dailyDateCondition = ""; $dailyParams = []; $dailyParamTypes = "";
        if ($startDate) { $dailyDateCondition .= " AND DATE(order_date) >= ?"; $dailyParams[] = $startDate; $dailyParamTypes .= "s"; }
        if ($endDate) { $dailyDateCondition .= " AND DATE(order_date) <= ?"; $dailyParams[] = $endDate; $dailyParamTypes .= "s"; }
        $sqlDaily = "SELECT DATE(order_date) as sale_date, COUNT(id) as daily_orders, SUM(total_amount) as daily_sales
                     FROM orders WHERE status = 'Completed' {$dailyDateCondition} GROUP BY DATE(order_date) ORDER BY sale_date ASC";
        $stmtDaily = $conn->prepare($sqlDaily);
        if (!$stmtDaily) throw new mysqli_sql_exception("DB prepare error (Daily Sales): " . $conn->error, $conn->errno);
        if (!empty($dailyParamTypes)) {
            $stmtDaily->bind_param($dailyParamTypes, ...$dailyParams);
            if ($stmtDaily->errno) { $err = $stmtDaily->error; $errno = $stmtDaily->errno; $stmtDaily->close(); throw new mysqli_sql_exception("DB bind_param error (Daily): " . $err, $errno); }
        }
        if (!$stmtDaily->execute()) { $err = $stmtDaily->error; $errno = $stmtDaily->errno; $stmtDaily->close(); throw new mysqli_sql_exception("DB execute error (Daily): " . $err, $errno); }
        if (!method_exists($stmtDaily, 'get_result')) { $stmtDaily->close(); throw new Exception("Server configuration error: get_result missing."); }
        $resultDaily = $stmtDaily->get_result();
        if ($stmtDaily->errno) { $err = $stmtDaily->error; $errno = $stmtDaily->errno; $stmtDaily->close(); throw new mysqli_sql_exception("DB get_result error (Daily): " . $err, $errno); }

        if ($resultDaily && $resultDaily->num_rows > 0) {
            echo "<h4 class='report-subtitle'>Daily Sales Breakdown</h4><table class='accounts-table report-table'><thead><tr><th>Date</th><th>Orders</th><th>Sales Value</th></tr></thead><tbody>";
            while ($row = $resultDaily->fetch_assoc()) {
                $saleDate = htmlspecialchars(isset($row['sale_date']) ? date('M d, Y', strtotime($row['sale_date'])) : 'N/A');
                $dailyOrders = number_format($row['daily_orders'] ?? 0);
                $dailySales = number_format($row['daily_sales'] ?? 0, 2);
                echo "<tr><td>{$saleDate}</td><td class='numeric'>{$dailyOrders}</td><td class='currency'>₱ {$dailySales}</td></tr>";
            }
            echo "</tbody></table>";
        } else { echo "<p class='no-data-message'>No daily sales data found for the selected period.</p>"; }
        if ($resultDaily) $resultDaily->close();
        $stmtDaily->close();
    }
    error_log("--- Finished generateSalesSummary ---");
}


/**
 * Generates Inventory Status Report for 'products', 'walkin_products', AND low 'raw_materials'.
 * (Modified to include raw materials and improved error handling)
 */
function generateInventoryStatus($conn) {
    error_log("--- Starting generateInventoryStatus (Includes Raw Materials) ---");
    $lowStockThreshold = 50; // Threshold for finished products
    $rawMaterialThresholdGrams = 5000; // 5 kg threshold for raw materials

    $companyStockData = []; $walkinStockData = []; $rawMaterialData = [];
    $errorOccurred = false; $companyQueryError = false; $walkinQueryError = false; $rawMaterialQueryError = false;

    // --- Finished Products: Company Order Stock ('products') ---
    $sqlCompany = "SELECT item_description, stock_quantity FROM products WHERE stock_quantity <= ? ORDER BY item_description ASC";
    $stmtCompany = $conn->prepare($sqlCompany);
    if (!$stmtCompany) { error_log("!!! DB prepare error (Company Inventory): " . $conn->error); $companyQueryError = true; $errorOccurred = true; }
    else {
        $stmtCompany->bind_param("i", $lowStockThreshold);
        if ($stmtCompany->errno) { $err = $stmtCompany->error; $errno = $stmtCompany->errno; $stmtCompany->close(); throw new mysqli_sql_exception("DB bind_param error (Company Inventory): " . $err, $errno); }
        if (!$stmtCompany->execute()) { $err = $stmtCompany->error; $errno = $stmtCompany->errno; $stmtCompany->close(); throw new mysqli_sql_exception("DB execute error (Company Inventory): " . $err, $errno); }
        if (!method_exists($stmtCompany, 'get_result')) { $stmtCompany->close(); throw new Exception("Server configuration error: get_result missing (Company Inventory)."); }
        $resultCompany = $stmtCompany->get_result();
        if ($stmtCompany->errno) { $err = $stmtCompany->error; $errno = $stmtCompany->errno; $stmtCompany->close(); throw new mysqli_sql_exception("DB get_result error (Company Inventory): " . $err, $errno); }
        while ($row = $resultCompany->fetch_assoc()) { $companyStockData[] = $row; }
        $resultCompany->close();
        $stmtCompany->close();
    }

    // --- Finished Products: Walk-in Stock ('walkin_products') ---
    $sqlWalkin = "SELECT item_description, stock_quantity FROM walkin_products WHERE stock_quantity <= ? ORDER BY item_description ASC";
    $stmtWalkin = $conn->prepare($sqlWalkin);
    if (!$stmtWalkin) { error_log("!!! DB prepare error (Walkin Inventory): " . $conn->error . " - Check 'walkin_products' table."); $walkinQueryError = true; } // Non-critical if table might not exist
    else {
        $stmtWalkin->bind_param("i", $lowStockThreshold);
        if ($stmtWalkin->errno) { $err = $stmtWalkin->error; $errno = $stmtWalkin->errno; $stmtWalkin->close(); throw new mysqli_sql_exception("DB bind_param error (Walkin Inventory): " . $err, $errno); }
        if (!$stmtWalkin->execute()) { $err = $stmtWalkin->error; $errno = $stmtWalkin->errno; $stmtWalkin->close(); throw new mysqli_sql_exception("DB execute error (Walkin Inventory): " . $err, $errno); } // Execute error is critical
        if (!method_exists($stmtWalkin, 'get_result')) { $stmtWalkin->close(); throw new Exception("Server configuration error: get_result missing (Walkin Inventory)."); }
        $resultWalkin = $stmtWalkin->get_result();
        if ($stmtWalkin->errno) { $err = $stmtWalkin->error; $errno = $stmtWalkin->errno; $stmtWalkin->close(); throw new mysqli_sql_exception("DB get_result error (Walkin Inventory): " . $err, $errno); }
        while ($row = $resultWalkin->fetch_assoc()) { $walkinStockData[] = $row; }
        $resultWalkin->close();
        $stmtWalkin->close();
    }

    // --- Raw Materials Stock ('raw_materials') ---
    // Ensure table and column names match your database exactly
    $sqlRaw = "SELECT material_name, current_stock_grams FROM raw_materials WHERE current_stock_grams < ? AND status = 'active' ORDER BY material_name ASC"; // Added status check example
    $stmtRaw = $conn->prepare($sqlRaw);
    if (!$stmtRaw) {
        error_log("!!! DB prepare error (Raw Materials Inventory): " . $conn->error);
        $rawMaterialQueryError = true; // Set flag
        $errorOccurred = true; // Set general flag
        // IMPORTANT: Throw the exception so the main catch block handles it and sends generic error
        throw new mysqli_sql_exception("DB prepare error (Raw Materials Inventory): " . $conn->error, $conn->errno);
    }
    else {
        $stmtRaw->bind_param("d", $rawMaterialThresholdGrams); // Use 'd' for double
         if ($stmtRaw->errno) { // Check for bind_param errors
             $err = $stmtRaw->error; $errno = $stmtRaw->errno; $stmtRaw->close(); throw new mysqli_sql_exception("DB bind_param error (Raw Materials): " . $err, $errno);
         }
        if (!$stmtRaw->execute()) {
            $err = $stmtRaw->error; $errno = $stmtRaw->errno; $stmtRaw->close(); throw new mysqli_sql_exception("DB execute error (Raw Materials): " . $err, $errno);
        }
        if (!method_exists($stmtRaw, 'get_result')) {
             $stmtRaw->close(); throw new Exception("Server configuration error: get_result missing for Raw Materials.");
        }
        $resultRaw = $stmtRaw->get_result();
        if ($stmtRaw->errno) {
             $err = $stmtRaw->error; $errno = $stmtRaw->errno; $resultRaw->close(); $stmtRaw->close(); throw new mysqli_sql_exception("DB get_result error (Raw Materials): " . $err, $errno);
        }
        while ($row = $resultRaw->fetch_assoc()) {
            $rawMaterialData[] = $row;
        }
        $resultRaw->close();
        $stmtRaw->close();
    }
    // --- END Raw Materials Section ---


    // --- HTML Output ---
    // Ensure class names match reporting.php CSS
    echo "<h3 class='report-title'>Inventory Status Report</h3>";

    // Company Section
    echo "<div class='inventory-section company-inventory'><h4 class='report-subtitle'>Low Company Order Stock (Threshold: " . htmlspecialchars($lowStockThreshold) . " or less)</h4>";
    if ($companyQueryError) { echo "<div class='report-error-message'>Error retrieving Company Order stock data. Check logs.</div>"; }
    elseif (!empty($companyStockData)) {
        // Use consistent table class
        echo "<table class='report-table'><thead><tr><th>Item Description</th><th>Stock Quantity</th></tr></thead><tbody>";
        foreach ($companyStockData as $row) {
            $itemDesc = htmlspecialchars($row['item_description'] ?? 'N/A');
            $stockQty = htmlspecialchars($row['stock_quantity'] ?? 0);
            // Use CSS class for styling low stock
            $lowStockClass = ($row['stock_quantity'] <= 0) ? 'low-stock-highlight' : (($row['stock_quantity'] <= $lowStockThreshold) ? 'low-stock-highlight' : '');
            echo "<tr><td>{$itemDesc}</td><td class='numeric {$lowStockClass}'>{$stockQty}</td></tr>";
        } echo "</tbody></table>";
    } else { echo "<p class='no-data-message'>No low stock items found for Company Orders.</p>"; }
    echo "</div>";

    // Walk-in Section
    echo "<div class='inventory-section walkin-inventory'><h4 class='report-subtitle'>Low Walk-in Stock (Threshold: " . htmlspecialchars($lowStockThreshold) . " or less)</h4>";
    if ($walkinQueryError) { echo "<div class='report-error-message'>Error retrieving Walk-in stock data. Check logs (verify table/columns exist).</div>"; }
    elseif (!empty($walkinStockData)) {
        echo "<table class='report-table'><thead><tr><th>Item Description</th><th>Stock Quantity</th></tr></thead><tbody>";
        foreach ($walkinStockData as $row) {
            $itemDesc = htmlspecialchars($row['item_description'] ?? 'N/A');
            $stockQty = htmlspecialchars($row['stock_quantity'] ?? 0);
            $lowStockClass = ($row['stock_quantity'] <= 0) ? 'low-stock-highlight' : (($row['stock_quantity'] <= $lowStockThreshold) ? 'low-stock-highlight' : '');
            echo "<tr><td>{$itemDesc}</td><td class='numeric {$lowStockClass}'>{$stockQty}</td></tr>";
        } echo "</tbody></table>";
    } else { if (!$walkinQueryError) { echo "<p class='no-data-message'>No low stock items found for Walk-in.</p>"; } }
    echo "</div>";

    // --- Raw Materials Section Output ---
    echo "<div class='inventory-section raw-materials-inventory'><h4 class='report-subtitle'>Low Raw Materials (Threshold: &lt; 5 kg)</h4>";
    // Check the specific error flag for raw materials - NO, rely on exception handling above
    // if ($rawMaterialQueryError) { echo "<div class='report-error-message'>Error retrieving Raw Materials stock data. Check logs.</div>"; }
    if (!empty($rawMaterialData)) { // Only show table if data was successfully fetched
        echo "<table class='report-table'><thead><tr><th>Material Name</th><th>Current Stock</th></tr></thead><tbody>";
        foreach ($rawMaterialData as $row) {
            $materialName = htmlspecialchars($row['material_name'] ?? 'N/A');
            $stockFormatted = formatWeight($row['current_stock_grams'] ?? null); // Use helper function
            $gramsValue = $row['current_stock_grams'] ?? -1;
            // Use CSS class for styling
            $lowStockClass = ($gramsValue <= 0) ? 'low-stock-highlight' : (($gramsValue < $rawMaterialThresholdGrams) ? 'low-stock-highlight' : '');
            echo "<tr><td>{$materialName}</td><td class='numeric {$lowStockClass}'>{$stockFormatted}</td></tr>";
        } echo "</tbody></table>";
    } else {
        // Don't show 'No data' if there might have been an error fetching it (exception was thrown)
        if (!$errorOccurred) { // Only show 'No data' if no errors occurred at all in this function
             echo "<p class='no-data-message'>No raw materials found below the 5 kg threshold.</p>";
        } else if ($rawMaterialQueryError) { // Explicitly mention if raw material query failed
             echo "<div class='report-error-message'>Error retrieving Raw Materials stock data. Check logs.</div>";
        }
    }
    echo "</div>";
    // --- END Raw Materials Output ---

    error_log("--- Finished generateInventoryStatus ---");
}


/**
 * Generates an Order Listing Report.
 */
function generateOrderTrends($conn, $startDate, $endDate) {
    error_log("--- Starting generateOrderTrends ---");
    $sql = "SELECT id, po_number, order_date, username, company, total_amount, status FROM orders WHERE 1=1";
    $params = []; $paramTypes = "";
    if ($startDate) { $sql .= " AND DATE(order_date) >= ?"; $params[] = $startDate; $paramTypes .= "s"; }
    if ($endDate) { $sql .= " AND DATE(order_date) <= ?"; $params[] = $endDate; $paramTypes .= "s"; }
    $sql .= " ORDER BY order_date DESC, id DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new mysqli_sql_exception("DB prepare error (Order Trends): " . $conn->error, $conn->errno);
    if (!empty($paramTypes)) {
        $stmt->bind_param($paramTypes, ...$params);
        if ($stmt->errno) { $err = $stmt->error; $errno = $stmt->errno; $stmt->close(); throw new mysqli_sql_exception("DB bind_param error (Order Trends): " . $err, $errno); }
    }
    if (!$stmt->execute()) { $err = $stmt->error; $errno = $stmt->errno; $stmt->close(); throw new mysqli_sql_exception("DB execute error (Order Trends): " . $err, $errno); }
    if (!method_exists($stmt, 'get_result')) { $stmt->close(); throw new Exception("Server configuration error: get_result missing (Order Trends)."); }
    $result = $stmt->get_result();
    if ($stmt->errno) { $err = $stmt->error; $errno = $stmt->errno; $stmt->close(); throw new mysqli_sql_exception("DB get_result error (Order Trends): " . $err, $errno); }

    $dateRangeStr = '';
    if ($startDate && $endDate) $dateRangeStr = " from " . htmlspecialchars(date("M d, Y", strtotime($startDate))) . " to " . htmlspecialchars(date("M d, Y", strtotime($endDate)));
    elseif ($startDate) $dateRangeStr = " from " . htmlspecialchars(date("M d, Y", strtotime($startDate))) . " onwards";
    elseif ($endDate) $dateRangeStr = " up to " . htmlspecialchars(date("M d, Y", strtotime($endDate)));

    echo "<h3 class='report-title'>Order Listing" . $dateRangeStr . "</h3>";
    if ($result && $result->num_rows > 0) {
        // Use consistent table class
        echo "<table class='report-table'><thead><tr><th>Order ID</th><th>PO Number</th><th>Date</th><th>Username</th><th>Company</th><th>Total</th><th>Status</th></tr></thead><tbody>";
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
        } echo "</tbody></table>";
    } else { echo "<p class='no-data-message'>No orders found within the specified criteria.</p>"; }
    if ($result) $result->close();
    $stmt->close();
    error_log("--- Finished generateOrderTrends ---");
}

/**
 * Generates a Sales by Client Report.
 */
function generateSalesByClient($conn, $startDate, $endDate) {
    error_log("--- Starting generateSalesByClient ---");
    $query = "SELECT username, COUNT(*) as order_count, SUM(total_amount) as total_revenue
              FROM orders WHERE status IN ('Active', 'For Delivery', 'Completed') "; // <<< ADAPT Statuses
    $params = []; $types = "";
    if ($startDate) { $query .= " AND DATE(order_date) >= ?"; $params[] = $startDate; $types .= "s"; }
    if ($endDate) { $query .= " AND DATE(order_date) <= ?"; $params[] = $endDate; $types .= "s"; }
    $query .= " GROUP BY username ORDER BY total_revenue DESC";
    $stmt = $conn->prepare($query);
    if ($stmt === false) throw new mysqli_sql_exception("DB prepare error (Sales by Client): " . $conn->error, $conn->errno);
    if ($types) {
        $stmt->bind_param($types, ...$params);
        if ($stmt->errno) { $err = $stmt->error; $errno = $stmt->errno; $stmt->close(); throw new mysqli_sql_exception("DB bind_param error (Sales by Client): " . $err, $errno); }
    }
    if (!$stmt->execute()){ $err = $stmt->error; $errno = $stmt->errno; $stmt->close(); throw new mysqli_sql_exception("DB execute error (Sales by Client): " . $err, $errno); }
    if (!method_exists($stmt, 'get_result')) { $stmt->close(); throw new Exception("Server configuration error: get_result missing (Sales by Client)."); }
    $result = $stmt->get_result();
    if ($stmt->errno) { $err = $stmt->error; $errno = $stmt->errno; $stmt->close(); throw new mysqli_sql_exception("DB get_result error (Sales by Client): " . $err, $errno); }

    $dateRangeStr = '';
    if ($startDate && $endDate) $dateRangeStr = " from " . htmlspecialchars(date("M d, Y", strtotime($startDate))) . " to " . htmlspecialchars(date("M d, Y", strtotime($endDate)));
    elseif ($startDate) $dateRangeStr = " from " . htmlspecialchars(date("M d, Y", strtotime($startDate))) . " onwards";
    elseif ($endDate) $dateRangeStr = " up to " . htmlspecialchars(date("M d, Y", strtotime($endDate)));

    echo "<h3 class='report-title'>Sales by Client Report" . $dateRangeStr . "</h3>";
    if ($result && $result->num_rows > 0) {
        // Use consistent table class
        echo "<table class='report-table'><thead><tr><th>Client Username</th><th>Total Orders</th><th>Total Revenue (PHP)</th></tr></thead><tbody>";
        while ($row = $result->fetch_assoc()) {
            $client = htmlspecialchars($row['username'] ?? 'N/A');
            $orders = number_format($row['order_count'] ?? 0);
            $revenue = number_format($row['total_revenue'] ?? 0, 2);
            echo "<tr><td>{$client}</td><td class='numeric'>{$orders}</td><td class='currency'>₱ {$revenue}</td></tr>";
        } echo "</tbody></table>";
    } else { echo "<p class='no-data-message'>No sales data found for the selected criteria.</p>"; }
    if ($result) { $result->close(); }
    $stmt->close();
    error_log("--- Finished generateSalesByClient ---");
}


/**
 * Generates a Sales by Product Report by processing JSON order data.
 */
function generateSalesByProduct($conn, $startDate, $endDate) {
    error_log("--- Starting generateSalesByProduct ---");
    $productSalesData = []; // Array to hold aggregated data [product_id => [data]]

    // SQL to fetch the JSON 'orders' column from completed orders within date range
    $sql = "SELECT orders FROM orders WHERE status = 'Completed'"; // Focus on completed sales
    $params = [];
    $types = "";
    if ($startDate) { $sql .= " AND DATE(order_date) >= ?"; $params[] = $startDate; $types .= "s"; }
    if ($endDate) { $sql .= " AND DATE(order_date) <= ?"; $params[] = $endDate; $types .= "s"; }
    error_log("Sales By Product SQL: " . $sql);

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("!!! DB prepare error (Sales by Product - Fetch): " . $conn->error);
        throw new mysqli_sql_exception("DB prepare error (Sales by Product - Fetch): " . $conn->error, $conn->errno);
    }
    error_log("Sales By Product statement prepared.");

    if ($types) {
        $stmt->bind_param($types, ...$params);
        if ($stmt->errno) { $err = $stmt->error; $errno = $stmt->errno; $stmt->close(); throw new mysqli_sql_exception("DB bind_param error (Sales by Product): " . $err, $errno); }
    }

    if (!$stmt->execute()){
        $err = $stmt->error; $errno = $stmt->errno;
        $stmt->close(); throw new mysqli_sql_exception("DB execute error (Sales by Product): " . $err, $errno);
    }
    error_log("Sales By Product statement executed.");

    // Fetch results (JSON strings)
    $stmt->bind_result($ordersJson);
    error_log("Sales By Product results bound.");

    // Loop through each order and process its JSON
    while ($stmt->fetch()) {
        if (empty($ordersJson)) continue; // Skip if JSON is empty

        // Decode JSON - use JSON_THROW_ON_ERROR for better error handling in PHP 7.3+
        // For PHP 7.2, check json_last_error()
        $items = json_decode($ordersJson, true); // Decode as associative array
        $jsonError = json_last_error();

        if ($jsonError !== JSON_ERROR_NONE) {
             error_log("!!! JSON Decode Error: " . json_last_error_msg() . " | JSON: " . substr($ordersJson, 0, 200) . "...");
             // Decide whether to skip this order or throw an error
             // For robustness, let's skip this order and log it
             continue;
             // throw new JsonException("Failed to decode order JSON: " . json_last_error_msg());
        }

        if (!is_array($items)) continue; // Skip if decoded result is not an array

        // Process each item in the order
        foreach ($items as $item) {
            // Validate essential item data
            if (!isset($item['product_id'], $item['quantity'], $item['price'])) {
                error_log("!!! Invalid item structure in JSON: " . json_encode($item));
                continue; // Skip invalid item
            }

            $productId = $item['product_id'];
            $quantity = filter_var($item['quantity'], FILTER_VALIDATE_INT);
            $price = filter_var($item['price'], FILTER_VALIDATE_FLOAT);
            $itemRevenue = ($quantity !== false && $price !== false) ? ($quantity * $price) : 0;

            // Ensure quantity and price are valid numbers
            if ($quantity === false || $quantity <= 0 || $price === false || $price < 0) {
                 error_log("!!! Invalid quantity or price for product_id {$productId}: Qty={$item['quantity']}, Price={$item['price']}");
                 continue; // Skip item with invalid numeric values
            }


            // Aggregate data
            if (!isset($productSalesData[$productId])) {
                // Initialize if first time seeing this product
                $productSalesData[$productId] = [
                    'category' => $item['category'] ?? 'Unknown',
                    'item_description' => $item['item_description'] ?? 'Unknown Product',
                    'packaging' => $item['packaging'] ?? '',
                    'total_quantity' => 0,
                    'total_revenue' => 0.0
                ];
            }

            $productSalesData[$productId]['total_quantity'] += $quantity;
            $productSalesData[$productId]['total_revenue'] += $itemRevenue;
        }
    }
    $stmt->close();
    error_log("Finished processing order JSONs. Aggregated products: " . count($productSalesData));

    // Sort the aggregated data (e.g., by total revenue descending)
    uasort($productSalesData, function($a, $b) {
        return $b['total_revenue'] <=> $a['total_revenue']; // Descending revenue sort
    });

    // --- Generate HTML Output ---
    $dateRangeStr = '';
    if ($startDate && $endDate) $dateRangeStr = " from " . htmlspecialchars(date("M d, Y", strtotime($startDate))) . " to " . htmlspecialchars(date("M d, Y", strtotime($endDate)));
    elseif ($startDate) $dateRangeStr = " from " . htmlspecialchars(date("M d, Y", strtotime($startDate))) . " onwards";
    elseif ($endDate) $dateRangeStr = " up to " . htmlspecialchars(date("M d, Y", strtotime($endDate)));

    echo "<h3 class='report-title'>Sales by Product Report" . $dateRangeStr . "</h3>";

    if (!empty($productSalesData)) {
         // Use consistent table class
        echo "<table class='report-table'>";
        echo "<thead><tr><th>Category</th><th>Product Description</th><th>Packaging</th><th>Total Qty Sold</th><th>Total Revenue (PHP)</th></tr></thead>";
        echo "<tbody>";
        foreach ($productSalesData as $productId => $data) {
            $category = htmlspecialchars($data['category']);
            $description = htmlspecialchars($data['item_description']);
            $packaging = htmlspecialchars($data['packaging']);
            $totalQty = number_format($data['total_quantity']);
            $totalRevenue = number_format($data['total_revenue'], 2);

            echo "<tr>";
            echo "<td>{$category}</td>";
            echo "<td>{$description}</td>";
            echo "<td>{$packaging}</td>";
            echo "<td class='numeric'>{$totalQty}</td>";
            echo "<td class='currency'>₱ {$totalRevenue}</td>";
            echo "</tr>";
        }
        echo "</tbody>";
        echo "</table>";
        error_log("Sales by product table generated.");
    } else {
        echo "<p class='no-data-message'>No product sales data found for the selected criteria.</p>";
        error_log("No sales by product data found message generated.");
    }
    error_log("--- Finished generateSalesByProduct ---");
}

?>