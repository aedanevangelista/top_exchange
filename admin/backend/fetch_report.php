<?php
// UTC: 2025-05-04 07:36:05
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

// Basic security check
$isLoggedIn = isset($_SESSION['admin_user_id']) || isset($_SESSION['client_user_id']) || isset($_SESSION['user_id']);
if (!$isLoggedIn) {
    http_response_code(403);
    echo "<div class='report-error-message'>Access Denied. Please log in.</div>";
    exit;
}

include_once __DIR__ . '/db_connection.php';

// Default error response function
function sendError($message, $httpCode = 500) {
    http_response_code($httpCode);
    echo "<div class='report-error-message'>" . htmlspecialchars($message) . "</div>";
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        $GLOBALS['conn']->close();
    }
    exit;
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

// DB Connection Check
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
            generateInventoryStatus($conn);
            break;

        case 'order_trends':
            generateOrderTrends($conn, $startDate, $endDate);
            break;

        case 'sales_by_client':
            generateSalesByClient($conn, $startDate, $endDate);
            break;

        // **** ADDED CASE for Sales by Product ****
        case 'sales_by_product':
            generateSalesByProduct($conn, $startDate, $endDate); // Call the new function
            break;

        default:
            error_log("Invalid report type specified: {$reportType}");
            sendError("Invalid report type specified: " . htmlspecialchars($reportType), 400);
            break;
    }
    error_log("--- Finished report generation for type: {$reportType} ---");
} catch (mysqli_sql_exception $e) {
    error_log("!!! CAUGHT SQL Exception in fetch_report.php (Report: {$reportType}): " . $e->getMessage() . " | SQL State: " . $e->getSqlState() . " | Error Code: " . $e->getCode());
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

// generateSalesSummary, generateInventoryStatus, generateOrderTrends, generateSalesByClient functions (as previously defined) go here...
// (Keep the versions from the last correct code block you have)

/**
 * Generates a Sales Summary Report.
 * (Based on user's 07:18:20 code)
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
 * Generates Low Inventory Report for 'products' and 'walkin_products'.
 * (Based on user's 07:18:20 code, corrected table/column names)
 */
function generateInventoryStatus($conn) {
    error_log("--- Starting generateInventoryStatus (Restored Dual Table Logic) ---");
    $lowStockThreshold = 50; $companyStockData = []; $walkinStockData = [];
    $errorOccurred = false; $companyQueryError = false; $walkinQueryError = false;

    // Company Order Stock ('products')
    $sqlCompany = "SELECT item_description, stock_quantity FROM products WHERE stock_quantity <= ? ORDER BY item_description ASC";
    $stmtCompany = $conn->prepare($sqlCompany);
    if (!$stmtCompany) { error_log("!!! DB prepare error (Company Inventory): " . $conn->error); $companyQueryError = true; $errorOccurred = true; }
    else {
        $stmtCompany->bind_param("i", $lowStockThreshold);
        if (!$stmtCompany->execute()) { error_log("!!! DB execute error (Company Inventory): " . $stmtCompany->error); $companyQueryError = true; $errorOccurred = true; }
        else {
            if (!method_exists($stmtCompany, 'get_result')) { error_log("!!! CRITICAL: get_result() failed for Company Inventory."); $companyQueryError = true; $errorOccurred = true; }
            else {
                $resultCompany = $stmtCompany->get_result();
                if ($stmtCompany->errno) { error_log("!!! DB get_result error (Company Inventory): " . $stmtCompany->error); $companyQueryError = true; $errorOccurred = true; }
                else { while ($row = $resultCompany->fetch_assoc()) { $companyStockData[] = $row; } $resultCompany->close(); }
            }
        }
        $stmtCompany->close();
    }

    // Walk-in Stock ('walkin_products')
    $sqlWalkin = "SELECT item_description, stock_quantity FROM walkin_products WHERE stock_quantity <= ? ORDER BY item_description ASC";
    $stmtWalkin = $conn->prepare($sqlWalkin);
    if (!$stmtWalkin) { error_log("!!! DB prepare error (Walkin Inventory): " . $conn->error . " - Check 'walkin_products' table."); $walkinQueryError = true; } // Non-critical if table might not exist
    else {
        $stmtWalkin->bind_param("i", $lowStockThreshold);
        if (!$stmtWalkin->execute()) { error_log("!!! DB execute error (Walkin Inventory): " . $stmtWalkin->error); $walkinQueryError = true; $errorOccurred = true; } // Execute error is critical
        else {
            if (!method_exists($stmtWalkin, 'get_result')) { error_log("!!! CRITICAL: get_result() failed for Walkin Inventory."); $walkinQueryError = true; $errorOccurred = true; }
            else {
                $resultWalkin = $stmtWalkin->get_result();
                if ($stmtWalkin->errno) { error_log("!!! DB get_result error (Walkin Inventory): " . $stmtWalkin->error); $walkinQueryError = true; $errorOccurred = true; }
                else { while ($row = $resultWalkin->fetch_assoc()) { $walkinStockData[] = $row; } $resultWalkin->close(); }
            }
        }
        $stmtWalkin->close();
    }

    echo "<h3 class='report-title'>Low Inventory Stock Report (Threshold: " . htmlspecialchars($lowStockThreshold) . " or less)</h3>";
    // Company Section
    echo "<div class='inventory-section company-inventory'><h4 class='report-subtitle'>Company Order Inventory ('products' table)</h4>";
    if ($companyQueryError) { echo "<div class='report-error-message'>Error retrieving Company Order stock data. Check logs.</div>"; }
    elseif (!empty($companyStockData)) {
        echo "<table class='accounts-table report-table'><thead><tr><th>Item Description</th><th>Stock Quantity</th></tr></thead><tbody>";
        foreach ($companyStockData as $row) {
            $itemDesc = htmlspecialchars($row['item_description'] ?? 'N/A');
            $stockQty = htmlspecialchars($row['stock_quantity'] ?? 0);
            $style = ($row['stock_quantity'] <= 0) ? 'color:red; font-weight:bold;' : (($row['stock_quantity'] <= $lowStockThreshold) ? 'color:orange;' : '');
            echo "<tr style='{$style}'><td>{$itemDesc}</td><td class='numeric'>{$stockQty}</td></tr>";
        } echo "</tbody></table>";
    } else { echo "<p class='no-data-message'>No low stock items found for Company Orders.</p>"; }
    echo "</div>";
    // Walk-in Section
    echo "<div class='inventory-section walkin-inventory'><h4 class='report-subtitle'>Walk-in Inventory ('walkin_products' table)</h4>";
    if ($walkinQueryError) { echo "<div class='report-error-message'>Error retrieving Walk-in stock data. Check logs (verify table/columns exist).</div>"; }
    elseif (!empty($walkinStockData)) {
        echo "<table class='accounts-table report-table'><thead><tr><th>Item Description</th><th>Stock Quantity</th></tr></thead><tbody>";
        foreach ($walkinStockData as $row) {
            $itemDesc = htmlspecialchars($row['item_description'] ?? 'N/A');
            $stockQty = htmlspecialchars($row['stock_quantity'] ?? 0);
            $style = ($row['stock_quantity'] <= 0) ? 'color:red; font-weight:bold;' : (($row['stock_quantity'] <= $lowStockThreshold) ? 'color:orange;' : '');
            echo "<tr style='{$style}'><td>{$itemDesc}</td><td class='numeric'>{$stockQty}</td></tr>";
        } echo "</tbody></table>";
    } else { if (!$walkinQueryError) { echo "<p class='no-data-message'>No low stock items found for Walk-in.</p>"; } }
    echo "</div>";
    error_log("--- Finished generateInventoryStatus ---");
}

/**
 * Generates an Order Listing Report.
 * (Based on user's 07:18:20 code)
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
    if (!method_exists($stmt, 'get_result')) { $stmt->close(); throw new Exception("Server configuration error: get_result missing."); }
    $result = $stmt->get_result();
    if ($stmt->errno) { $err = $stmt->error; $errno = $stmt->errno; $stmt->close(); throw new mysqli_sql_exception("DB get_result error (Order Trends): " . $err, $errno); }

    $dateRangeStr = '';
    if ($startDate && $endDate) $dateRangeStr = " from " . htmlspecialchars(date("M d, Y", strtotime($startDate))) . " to " . htmlspecialchars(date("M d, Y", strtotime($endDate)));
    elseif ($startDate) $dateRangeStr = " from " . htmlspecialchars(date("M d, Y", strtotime($startDate))) . " onwards";
    elseif ($endDate) $dateRangeStr = " up to " . htmlspecialchars(date("M d, Y", strtotime($endDate)));

    echo "<h3 class='report-title'>Order Listing" . $dateRangeStr . "</h3>";
    if ($result && $result->num_rows > 0) {
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
        } echo "</tbody></table>";
    } else { echo "<p class='no-data-message'>No orders found within the specified criteria.</p>"; }
    if ($result) $result->close();
    $stmt->close();
    error_log("--- Finished generateOrderTrends ---");
}

/**
 * Generates a Sales by Client Report.
 * (Based on previous steps)
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
    if (!method_exists($stmt, 'get_result')) { $stmt->close(); throw new Exception("Server configuration error: get_result missing."); }
    $result = $stmt->get_result();
    if ($stmt->errno) { $err = $stmt->error; $errno = $stmt->errno; $stmt->close(); throw new mysqli_sql_exception("DB get_result error (Sales by Client): " . $err, $errno); }

    $dateRangeStr = '';
    if ($startDate && $endDate) $dateRangeStr = " from " . htmlspecialchars(date("M d, Y", strtotime($startDate))) . " to " . htmlspecialchars(date("M d, Y", strtotime($endDate)));
    elseif ($startDate) $dateRangeStr = " from " . htmlspecialchars(date("M d, Y", strtotime($startDate))) . " onwards";
    elseif ($endDate) $dateRangeStr = " up to " . htmlspecialchars(date("M d, Y", strtotime($endDate)));

    echo "<h3 class='report-title'>Sales by Client Report" . $dateRangeStr . "</h3>";
    if ($result && $result->num_rows > 0) {
        echo "<table class='accounts-table report-table'><thead><tr><th>Client Username</th><th>Total Orders</th><th>Total Revenue (PHP)</th></tr></thead><tbody>";
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
 * Uses 'orders' table: orders (JSON), order_date, status
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
        echo "<table class='accounts-table report-table'>"; // Use your table class
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