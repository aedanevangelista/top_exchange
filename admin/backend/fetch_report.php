<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Basic security check - ensure user is logged in.
$isLoggedIn = isset($_SESSION['admin_user_id']) || isset($_SESSION['client_user_id']) || isset($_SESSION['user_id']);
if (!$isLoggedIn) {
    http_response_code(403); // Forbidden
    echo "<p class='error-message' style='color: red;'>Access Denied. Please log in.</p>";
    exit;
}

include_once __DIR__ . '/db_connection.php'; // Establishes $conn

// Default error response function
function sendError($message, $httpCode = 500) {
    http_response_code($httpCode);
    // Using a more structured error message div
    echo "<div class='report-error-message'>" . htmlspecialchars($message) . "</div>";
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
$startDate = !empty(trim($_POST['start_date'])) ? trim($_POST['start_date']) : null;
$endDate = !empty(trim($_POST['end_date'])) ? trim($_POST['end_date']) : null;

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
            generateInventoryStatus($conn); // Function modified below
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
 * Generates an Enhanced Sales Summary Report.
 * Uses 'orders' table: id, total_amount, order_date, status, username
 */
function generateSalesSummary($conn, $startDate, $endDate) {
    // ... (Sales Summary code remains the same) ...
    $dateCondition = "";
    $params = [];
    $paramTypes = "";

    if ($startDate) {
        $dateCondition .= " AND DATE(order_date) >= ?";
        $params[] = $startDate;
        $paramTypes .= "s";
    }
    if ($endDate) {
        $dateCondition .= " AND DATE(order_date) <= ?";
        $params[] = $endDate;
        $paramTypes .= "s";
    }

    // 1. Get Total Orders and Sales Value for 'Completed' orders
    $sqlSummary = "SELECT COUNT(id) as total_orders, SUM(total_amount) as total_sales_value
                   FROM orders WHERE status = 'Completed' {$dateCondition}";

    $stmtSummary = $conn->prepare($sqlSummary);
    if (!$stmtSummary) throw new mysqli_sql_exception("DB prepare error (Summary): " . $conn->error, $conn->errno);
    if (!empty($paramTypes)) $stmtSummary->bind_param($paramTypes, ...$params);
    if (!$stmtSummary->execute()) { $err = $stmtSummary->error; $errno = $stmtSummary->errno; $stmtSummary->close(); throw new mysqli_sql_exception("DB execute error (Summary): " . $err, $errno); }
    $resultSummary = $stmtSummary->get_result();
    $summary = $resultSummary->fetch_assoc();
    $resultSummary->close();
    $stmtSummary->close();

    // 2. Get Unique Customers ('username') for 'Completed' orders
    $uniqueCustomers = 0;
    $sqlCustomers = "SELECT COUNT(DISTINCT username) as unique_customer_count
                     FROM orders WHERE status = 'Completed' {$dateCondition}";

    $stmtCustomers = $conn->prepare($sqlCustomers);
    if ($stmtCustomers) {
         if (!empty($paramTypes)) $stmtCustomers->bind_param($paramTypes, ...$params);
         if ($stmtCustomers->execute()) {
             $resultCustomers = $stmtCustomers->get_result();
             $customerData = $resultCustomers->fetch_assoc();
             $uniqueCustomers = $customerData['unique_customer_count'] ?? 0;
             $resultCustomers->close();
         } else { error_log("DB execute error (Customers): " . $stmtCustomers->error); }
         $stmtCustomers->close();
    } else { error_log("DB prepare error (Customers): " . $conn->error); }

    // Calculate derived metrics
    $totalOrders = $summary['total_orders'] ?? 0;
    $totalSales = $summary['total_sales_value'] ?? 0;
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

    // --- Optional: Daily Sales Breakdown ---
    if ($startDate && $endDate) {
        $sqlDaily = "SELECT DATE(order_date) as sale_date, COUNT(id) as daily_orders, SUM(total_amount) as daily_sales
                     FROM orders WHERE status = 'Completed' {$dateCondition}
                     GROUP BY DATE(order_date) ORDER BY sale_date ASC";

        $stmtDaily = $conn->prepare($sqlDaily);
        if (!$stmtDaily) throw new mysqli_sql_exception("DB prepare error (Daily): " . $conn->error, $conn->errno);
        if (!empty($paramTypes)) $stmtDaily->bind_param($paramTypes, ...$params);
        if (!$stmtDaily->execute()){ $err = $stmtDaily->error; $errno = $stmtDaily->errno; $stmtDaily->close(); throw new mysqli_sql_exception("DB execute error (Daily): " . $err, $errno); }
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
                echo "<td class='currency'>" . number_format($row['daily_sales'], 2) . "</td>"; // Class for alignment
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else { echo "<p class='no-data-message'>No daily sales data found for the selected period.</p>"; }
        $resultDaily->close();
        $stmtDaily->close();
    }
}


/**
 * Generates a Low Inventory Stock Report, separated by type.
 * Queries 'products' (Company Orders) and 'walkin_products' (Walk-in).
 */
function generateInventoryStatus($conn) {
    // *** UPDATED LOW STOCK THRESHOLD ***
    $lowStockThreshold = 50; // Set your desired low stock level here

    $companyStockFound = false;
    $walkinStockFound = false;
    $errorOccurred = false;

    // --- Query for Company Order Low Stock (products table) ---
    $sqlCompany = "SELECT item_description, stock_quantity
                   FROM products
                   WHERE stock_quantity <= ?
                   ORDER BY item_description ASC";

    $stmtCompany = $conn->prepare($sqlCompany);
    if (!$stmtCompany) {
        error_log("DB prepare error (Company Inventory): " . $conn->error);
        echo "<h3 class='report-title'>Low Stock Report</h3><div class='report-error-message'>Error preparing company stock query.</div>";
        $errorOccurred = true;
    } else {
        $stmtCompany->bind_param("i", $lowStockThreshold);
        if (!$stmtCompany->execute()) {
            error_log("DB execute error (Company Inventory): " . $stmtCompany->error);
             echo "<h3 class='report-title'>Low Stock Report</h3><div class='report-error-message'>Error executing company stock query.</div>";
            $errorOccurred = true;
        } else {
            $resultCompany = $stmtCompany->get_result();
            $companyStockFound = $resultCompany->num_rows > 0;
        }
    }

    // --- Query for Walk-in Low Stock (walkin_products table) ---
    $sqlWalkin = "SELECT item_description, stock_quantity
                  FROM walkin_products
                  WHERE stock_quantity <= ?
                  ORDER BY item_description ASC";

    $stmtWalkin = $conn->prepare($sqlWalkin);
     if (!$stmtWalkin) {
        error_log("DB prepare error (Walkin Inventory): " . $conn->error);
        // Display title only if not already shown due to company error
        if (!$errorOccurred) echo "<h3 class='report-title'>Low Stock Report</h3>";
        echo "<div class='report-error-message'>Error preparing walk-in stock query.</div>";
        $errorOccurred = true; // Ensure we don't proceed if prepare fails
    } else {
        $stmtWalkin->bind_param("i", $lowStockThreshold);
        if (!$stmtWalkin->execute()) {
            error_log("DB execute error (Walkin Inventory): " . $stmtWalkin->error);
             // Display title only if not already shown due to company error
            if (!$errorOccurred) echo "<h3 class='report-title'>Low Stock Report</h3>";
            echo "<div class='report-error-message'>Error executing walk-in stock query.</div>";
            $errorOccurred = true;
        } else {
            $resultWalkin = $stmtWalkin->get_result();
            $walkinStockFound = $resultWalkin->num_rows > 0;
        }
    }

    // --- Display Results ---
    if ($errorOccurred) {
        // Error messages already displayed
        if (isset($stmtCompany) && $stmtCompany instanceof mysqli_stmt) $stmtCompany->close();
        if (isset($stmtWalkin) && $stmtWalkin instanceof mysqli_stmt) $stmtWalkin->close();
        return; // Stop execution if errors occurred
    }

    echo "<h3 class='report-title'>Low Inventory Stock Report (Threshold: " . htmlspecialchars($lowStockThreshold) . " or less)</h3>";

    // Display Company Order Low Stock
    // *** USING CSS CLASSES INSTEAD OF INLINE STYLE ***
    echo "<div class='inventory-section company-inventory'>"; // Container for Company Stock
    echo "<h4 class='report-subtitle'>Company Order Inventory</h4>";
    if ($companyStockFound) {
        echo "<table class='accounts-table report-table'>"; // Use consistent table classes
        echo "<thead><tr><th>Item Description</th><th>Stock Quantity</th></tr></thead>";
        echo "<tbody>";
        while ($row = $resultCompany->fetch_assoc()) {
            $itemDescription = $row['item_description'] ?? 'N/A';
            $stockQuantity = $row['stock_quantity'] ?? 0;
            echo "<tr>";
            echo "<td>" . htmlspecialchars($itemDescription) . "</td>";
            // *** USING CSS CLASS FOR HIGHLIGHTING ***
            echo "<td class='low-stock-highlight numeric'>" . htmlspecialchars($stockQuantity) . "</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p class='no-data-message'>No low stock items found for Company Orders.</p>";
    }
    echo "</div>"; // End Company Stock container
    if (isset($resultCompany)) $resultCompany->close();
    if (isset($stmtCompany)) $stmtCompany->close();


    // Display Walk-in Low Stock
     // *** USING CSS CLASSES INSTEAD OF INLINE STYLE ***
    echo "<div class='inventory-section walkin-inventory'>"; // Container for Walk-in Stock
    echo "<h4 class='report-subtitle'>Walk-in Inventory</h4>";
    if ($walkinStockFound) {
        echo "<table class='accounts-table report-table'>"; // Use consistent table classes
        echo "<thead><tr><th>Item Description</th><th>Stock Quantity</th></tr></thead>";
        echo "<tbody>";
        while ($row = $resultWalkin->fetch_assoc()) {
            $itemDescription = $row['item_description'] ?? 'N/A';
            $stockQuantity = $row['stock_quantity'] ?? 0;
            echo "<tr>";
            echo "<td>" . htmlspecialchars($itemDescription) . "</td>";
             // *** USING CSS CLASS FOR HIGHLIGHTING ***
            echo "<td class='low-stock-highlight numeric'>" . htmlspecialchars($stockQuantity) . "</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p class='no-data-message'>No low stock items found for Walk-in.</p>";
    }
    echo "</div>"; // End Walk-in Stock container
    if (isset($resultWalkin)) $resultWalkin->close();
    if (isset($stmtWalkin)) $stmtWalkin->close();

    // Overall message if nothing found
    if (!$companyStockFound && !$walkinStockFound) {
         echo "<p class='no-data-message' style='margin-top: 20px;'>No low stock items found in either inventory below the threshold.</p>";
    }
}


/**
 * Generates an Order Listing Report.
 * Uses 'orders' table: id, order_date, username, company, total_amount, status
 */
function generateOrderTrends($conn, $startDate, $endDate) {
    // ... (Order Trends code remains mostly the same, added some classes) ...
    $sql = "SELECT id, order_date, username, company, total_amount, status
            FROM orders WHERE 1=1";

    $params = [];
    $paramTypes = "";

    if ($startDate) { $sql .= " AND DATE(order_date) >= ?"; $params[] = $startDate; $paramTypes .= "s"; }
    if ($endDate) { $sql .= " AND DATE(order_date) <= ?"; $params[] = $endDate; $paramTypes .= "s"; }
    $sql .= " ORDER BY order_date DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new mysqli_sql_exception("DB prepare error (Order Trends): " . $conn->error, $conn->errno);
    if (!empty($paramTypes)) $stmt->bind_param($paramTypes, ...$params);
    if (!$stmt->execute()){ $err = $stmt->error; $errno = $stmt->errno; $stmt->close(); throw new mysqli_sql_exception("DB execute error (Order Trends): " . $err, $errno); }
    $result = $stmt->get_result();

    $dateRangeStr = '';
    if ($startDate && $endDate) $dateRangeStr = " from " . htmlspecialchars($startDate) . " to " . htmlspecialchars($endDate);
    elseif ($startDate) $dateRangeStr = " from " . htmlspecialchars($startDate) . " onwards";
    elseif ($endDate) $dateRangeStr = " up to " . htmlspecialchars($endDate);

    echo "<h3 class='report-title'>Order Listing" . $dateRangeStr . "</h3>";
    if ($result->num_rows > 0) {
        echo "<table class='accounts-table report-table'>"; // Use consistent table classes
        echo "<thead><tr><th>Order ID</th><th>Date</th><th>Username</th><th>Company</th><th>Total</th><th>Status</th></tr></thead>";
        echo "<tbody>";
        while ($row = $result->fetch_assoc()) {
             $statusClass = 'status-' . strtolower(preg_replace('/[^a-z0-9]+/', '-', $row['status'] ?? 'unknown'));
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars(date('Y-m-d H:i', strtotime($row['order_date']))) . "</td>";
            echo "<td>" . htmlspecialchars($row['username'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($row['company'] ?? 'N/A') . "</td>";
            echo "<td class='currency'>" . number_format($row['total_amount'] ?? 0, 2) . "</td>"; // Class for alignment
            echo "<td class='" . htmlspecialchars($statusClass) . "'>" . htmlspecialchars($row['status']) . "</td>";
            echo "</tr>";
        }
        echo "</tbody>";
        echo "</table>";
    } else { echo "<p class='no-data-message'>No orders found within the specified criteria.</p>"; }
    $result->close();
    $stmt->close();
}

?>