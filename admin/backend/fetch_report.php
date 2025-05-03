<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Basic security check
$isLoggedIn = isset($_SESSION['admin_user_id']) || isset($_SESSION['client_user_id']) || isset($_SESSION['user_id']);
if (!$isLoggedIn) {
    http_response_code(403);
    echo "<p class='error-message' style='color: red;'>Access Denied. Please log in.</p>";
    exit;
}

include_once __DIR__ . '/db_connection.php'; // Establishes $conn

// Error response function
function sendError($message, $httpCode = 500) {
    http_response_code($httpCode);
    echo "<div class='error-message' style='padding: 10px; background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; border-radius: 4px; margin-top: 10px;'>" . htmlspecialchars($message) . "</div>";
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        $GLOBALS['conn']->close();
    }
    exit;
}

// Validate request
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['report_type'])) {
    sendError("Invalid request method or missing report type.", 400);
}

// --- Input Retrieval ---
$reportType = trim($_POST['report_type']);
$startDate = !empty(trim($_POST['start_date'])) ? trim($_POST['start_date']) : null;
$endDate = !empty(trim($_POST['end_date'])) ? trim($_POST['end_date']) : null;
// Get inventory source only if report type is inventory_status
$inventorySource = ($reportType === 'inventory_status' && isset($_POST['inventory_source'])) ? trim($_POST['inventory_source']) : null;

// --- Report Generation Logic ---

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
     sendError("Database connection failed: " . ($conn->connect_error ?? 'Unknown error'), 500);
}

try {
    switch ($reportType) {
        case 'sales_summary':
            generateSalesSummary($conn, $startDate, $endDate);
            break;

        case 'inventory_status':
            // ** Reinstated validation for inventory source **
            if (!$inventorySource || !in_array($inventorySource, ['company', 'walkin'])) {
                 sendError("Invalid or missing inventory source specified. Please select 'Company' or 'Walk-in'.", 400);
            }
            generateInventoryStatus($conn, $inventorySource); // Pass source to function
            break;

        case 'order_trends':
            generateOrderTrends($conn, $startDate, $endDate);
            break;

        default:
            sendError("Invalid report type specified: " . htmlspecialchars($reportType), 400);
            break;
    }
} catch (mysqli_sql_exception $e) {
    error_log("SQL Exception in fetch_report.php: " . $e->getMessage());
    sendError("An unexpected database error occurred while generating the report.", 500);
} catch (Exception $e) {
    error_log("General Exception in fetch_report.php: " . $e->getMessage());
    sendError("An unexpected error occurred.", 500);
} finally {
    if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
        $conn->close();
    }
}

// --- Report Generating Functions ---

/**
 * Generates Sales Summary Report.
 */
function generateSalesSummary($conn, $startDate, $endDate) {
    // ... (Sales Summary code remains the same) ...
    $dateCondition = ""; $params = []; $paramTypes = "";
    if ($startDate) { $dateCondition .= " AND DATE(order_date) >= ?"; $params[] = $startDate; $paramTypes .= "s"; }
    if ($endDate) { $dateCondition .= " AND DATE(order_date) <= ?"; $params[] = $endDate; $paramTypes .= "s"; }
    $sqlSummary = "SELECT COUNT(id) as total_orders, SUM(total_amount) as total_sales_value FROM orders WHERE status = 'Completed' {$dateCondition}";
    $stmtSummary = $conn->prepare($sqlSummary); if (!$stmtSummary) throw new mysqli_sql_exception("DB prepare error (Summary): " . $conn->error, $conn->errno); if (!empty($paramTypes)) $stmtSummary->bind_param($paramTypes, ...$params); if (!$stmtSummary->execute()) { $err = $stmtSummary->error; $errno = $stmtSummary->errno; $stmtSummary->close(); throw new mysqli_sql_exception("DB execute error (Summary): " . $err, $errno); } $resultSummary = $stmtSummary->get_result(); $summary = $resultSummary->fetch_assoc(); $resultSummary->close(); $stmtSummary->close();
    $uniqueCustomers = 0; $sqlCustomers = "SELECT COUNT(DISTINCT username) as unique_customer_count FROM orders WHERE status = 'Completed' {$dateCondition}"; $stmtCustomers = $conn->prepare($sqlCustomers); if ($stmtCustomers) { if (!empty($paramTypes)) $stmtCustomers->bind_param($paramTypes, ...$params); if ($stmtCustomers->execute()) { $resultCustomers = $stmtCustomers->get_result(); $customerData = $resultCustomers->fetch_assoc(); $uniqueCustomers = $customerData['unique_customer_count'] ?? 0; $resultCustomers->close(); } else { error_log("DB execute error (Customers): " . $stmtCustomers->error); } $stmtCustomers->close(); } else { error_log("DB prepare error (Customers): " . $conn->error); }
    $totalOrders = $summary['total_orders'] ?? 0; $totalSales = $summary['total_sales_value'] ?? 0; $averageOrderValue = ($totalOrders > 0) ? ($totalSales / $totalOrders) : 0;
    $dateRangeStr = ''; if ($startDate && $endDate) $dateRangeStr = " from " . htmlspecialchars($startDate) . " to " . htmlspecialchars($endDate); elseif ($startDate) $dateRangeStr = " from " . htmlspecialchars($startDate) . " onwards"; elseif ($endDate) $dateRangeStr = " up to " . htmlspecialchars($endDate);
    echo "<h3>Sales Summary Report" . $dateRangeStr . "</h3>"; echo "<div class='report-summary-box' style='margin-bottom: 20px;'>"; echo "<table class='summary-table' style='width: auto; border: 1px solid #ddd; background-color: #f9f9f9; border-collapse: collapse;'><tbody>"; echo "<tr><td style='padding: 5px 10px; font-weight: bold; text-align: left; border: 1px solid #ddd;'>Total Completed Orders:</td><td style='padding: 5px 10px; text-align: right; border: 1px solid #ddd;'>" . number_format($totalOrders) . "</td></tr>"; echo "<tr><td style='padding: 5px 10px; font-weight: bold; text-align: left; border: 1px solid #ddd;'>Total Sales Value:</td><td style='padding: 5px 10px; text-align: right; border: 1px solid #ddd;'>₱ " . number_format($totalSales, 2) . "</td></tr>"; echo "<tr><td style='padding: 5px 10px; font-weight: bold; text-align: left; border: 1px solid #ddd;'>Average Order Value:</td><td style='padding: 5px 10px; text-align: right; border: 1px solid #ddd;'>₱ " . number_format($averageOrderValue, 2) . "</td></tr>"; echo "<tr><td style='padding: 5px 10px; font-weight: bold; text-align: left; border: 1px solid #ddd;'>Unique Customers:</td><td style='padding: 5px 10px; text-align: right; border: 1px solid #ddd;'>" . number_format($uniqueCustomers) . "</td></tr>"; echo "</tbody></table></div>";
    if ($startDate && $endDate) { $sqlDaily = "SELECT DATE(order_date) as sale_date, COUNT(id) as daily_orders, SUM(total_amount) as daily_sales FROM orders WHERE status = 'Completed' {$dateCondition} GROUP BY DATE(order_date) ORDER BY sale_date ASC"; $stmtDaily = $conn->prepare($sqlDaily); if (!$stmtDaily) throw new mysqli_sql_exception("DB prepare error (Daily): " . $conn->error, $conn->errno); if (!empty($paramTypes)) $stmtDaily->bind_param($paramTypes, ...$params); if (!$stmtDaily->execute()){ $err = $stmtDaily->error; $errno = $stmtDaily->errno; $stmtDaily->close(); throw new mysqli_sql_exception("DB execute error (Daily): " . $err, $errno); } $resultDaily = $stmtDaily->get_result(); if ($resultDaily->num_rows > 0) { echo "<h4>Daily Sales Breakdown</h4><table class='accounts-table'><thead><tr><th>Date</th><th>Orders</th><th>Sales Value</th></tr></thead><tbody>"; while ($row = $resultDaily->fetch_assoc()) { echo "<tr><td>" . htmlspecialchars($row['sale_date']) . "</td><td>" . number_format($row['daily_orders']) . "</td><td style='text-align: right;'>₱ " . number_format($row['daily_sales'], 2) . "</td></tr>"; } echo "</tbody></table>"; } else { echo "<p>No daily sales data found for the selected period.</p>"; } $resultDaily->close(); $stmtDaily->close(); }
}


/**
 * Generates Inventory Status Report for a specific source (Company or Walk-in).
 * Uses the simpler single-table output.
 * @param mysqli $conn Database connection
 * @param string $inventorySource Source identifier ('company' or 'walkin')
 */
function generateInventoryStatus($conn, $inventorySource) {
    // ** Determine table name based on source **
    $tableName = ($inventorySource === 'walkin') ? 'walkin_products' : 'products';
    $sourceName = ($inventorySource === 'walkin') ? 'Walk-in Customers' : 'Company Orders'; // Match tab names
    $lowStockThreshold = 5; // Define low stock level for styling

    // --- Check if selected table exists ---
    $checkTableSql = "SHOW TABLES LIKE '" . $conn->real_escape_string($tableName) . "'";
    $tableResult = $conn->query($checkTableSql);
    if (!$tableResult) { error_log("Error checking for table {$tableName}: " . $conn->error); sendError("Error checking database structure.", 500); return; }
    if ($tableResult->num_rows == 0) { echo "<h3>Inventory Stock List ({$sourceName})</h3><p>Note: The table '{$tableName}' was not found.</p>"; $tableResult->close(); return; }
    $tableResult->close();
    // --- End Table Check ---

    // --- Query Full Inventory List for the selected source ---
    $sqlFullList = "SELECT item_description, stock_quantity
                    FROM `" . $conn->real_escape_string($tableName) . "`
                    ORDER BY item_description ASC";

    $resultFullList = $conn->query($sqlFullList);
    if (!$resultFullList) {
        error_log("DB query error (Inventory List - {$sourceName}): " . $conn->error . " | SQL: " . $sqlFullList);
        echo "<h3>Inventory Stock List ({$sourceName})</h3>"; // Still show title
        echo "<p style='color: red;'>Error retrieving inventory list for {$sourceName}.</p>";
        return; // Exit if list fails
    }

    // --- Display Full List Section ---
    echo "<h3>Inventory Stock List ({$sourceName})</h3>"; // Title indicates the source
    if ($resultFullList->num_rows > 0) {
        echo "<table class='accounts-table'>"; // Use your standard table class
        echo "<thead><tr><th>Item Description</th><th>Stock Quantity</th></tr></thead>";
        echo "<tbody>";
        while ($row = $resultFullList->fetch_assoc()) {
            $itemDescription = $row['item_description'] ?? 'N/A';
            $stockQuantity = $row['stock_quantity'] ?? 0;

            // Optional: Highlight low/zero stock within the list
            $stockStyle = '';
            if ($stockQuantity <= 0) {
                $stockStyle = " style='color: red; font-weight: bold;'";
            } elseif ($stockQuantity <= $lowStockThreshold) {
                $stockStyle = " style='color: orange; font-weight: bold;'";
            }

            echo "<tr>";
            echo "<td>" . htmlspecialchars($itemDescription) . "</td>";
            echo "<td{$stockStyle}>" . htmlspecialchars($stockQuantity) . "</td>";
            echo "</tr>";
        }
        echo "</tbody>";
        echo "</table>";
    } else {
        echo "<p>No products found in the '{$tableName}' table ({$sourceName}).</p>";
    }
    $resultFullList->close();
}


/**
 * Generates Order Listing Report.
 */
function generateOrderTrends($conn, $startDate, $endDate) {
    // ... (Order Trends code remains the same) ...
     $sql = "SELECT id, order_date, username, company, total_amount, status FROM orders WHERE 1=1"; $params = []; $paramTypes = ""; if ($startDate) { $sql .= " AND DATE(order_date) >= ?"; $params[] = $startDate; $paramTypes .= "s"; } if ($endDate) { $sql .= " AND DATE(order_date) <= ?"; $params[] = $endDate; $paramTypes .= "s"; } $sql .= " ORDER BY order_date DESC"; $stmt = $conn->prepare($sql); if (!$stmt) throw new mysqli_sql_exception("DB prepare error (Order Trends): " . $conn->error, $conn->errno); if (!empty($paramTypes)) $stmt->bind_param($paramTypes, ...$params); if (!$stmt->execute()){ $err = $stmt->error; $errno = $stmt->errno; $stmt->close(); throw new mysqli_sql_exception("DB execute error (Order Trends): " . $err, $errno); } $result = $stmt->get_result(); $dateRangeStr = ''; if ($startDate && $endDate) $dateRangeStr = " from " . htmlspecialchars($startDate) . " to " . htmlspecialchars($endDate); elseif ($startDate) $dateRangeStr = " from " . htmlspecialchars($startDate) . " onwards"; elseif ($endDate) $dateRangeStr = " up to " . htmlspecialchars($endDate); echo "<h3>Order Listing" . $dateRangeStr . "</h3>"; if ($result->num_rows > 0) { echo "<table class='accounts-table'><thead><tr><th>Order ID</th><th>Date</th><th>Username</th><th>Company</th><th>Total</th><th>Status</th></tr></thead><tbody>"; while ($row = $result->fetch_assoc()) { $statusClass = 'status-' . strtolower(preg_replace('/[^a-z0-9]+/', '-', $row['status'] ?? 'unknown')); echo "<tr><td>" . htmlspecialchars($row['id']) . "</td><td>" . htmlspecialchars(date('Y-m-d H:i', strtotime($row['order_date']))) . "</td><td>" . htmlspecialchars($row['username'] ?? 'N/A') . "</td><td>" . htmlspecialchars($row['company'] ?? 'N/A') . "</td><td style='text-align: right;'>₱ " . number_format($row['total_amount'] ?? 0, 2) . "</td><td class='" . htmlspecialchars($statusClass) . "'>" . htmlspecialchars($row['status']) . "</td></tr>"; } echo "</tbody></table>"; } else { echo "<p>No orders found within the specified criteria.</p>"; } $result->close(); $stmt->close();

}

?>