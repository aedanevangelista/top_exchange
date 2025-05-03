<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Basic security check - ensure user is logged in.
$isLoggedIn = isset($_SESSION['admin_user_id']) || isset($_SESSION['client_user_id']) || isset($_SESSION['user_id']);
if (!$isLoggedIn) {
    http_response_code(403); // Forbidden
    echo "<p class='error-message'>Access Denied. Please log in.</p>"; // Use a class for styling errors
    exit;
}

include_once __DIR__ . '/db_connection.php'; // Establishes $conn

// Default error response
function sendError($message, $httpCode = 500) {
    http_response_code($httpCode);
    // Use a consistent error display, maybe match modal error styles if possible
    echo "<div class='error-message' style='padding: 10px; background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; border-radius: 4px;'>" . htmlspecialchars($message) . "</div>";
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        $GLOBALS['conn']->close();
    }
    exit;
}

// Check if it's a POST request and report_type is set
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['report_type'])) {
    sendError("Invalid request.", 400);
}

$reportType = $_POST['report_type'];
$startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
$endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

// --- Report Generation Logic ---

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

    default:
        sendError("Invalid report type specified.", 400);
        break;
}

// Close DB connection if it's still open
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

// --- Report Functions ---

/**
 * Generates an Enhanced Sales Summary Report.
 */
function generateSalesSummary($conn, $startDate, $endDate) {
    // --- Schema assumptions based on provided 'orders' table ---
    // `orders` table: id, total_amount, order_date, status, username

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

    // 1. Get Total Orders and Sales Value
    $sqlSummary = "SELECT
                        COUNT(id) as total_orders,
                        SUM(total_amount) as total_sales_value
                    FROM orders
                    WHERE status = 'Completed' {$dateCondition}"; // Filter for completed orders

    $stmtSummary = $conn->prepare($sqlSummary);
    if (!$stmtSummary) sendError("DB error (Summary): " . $conn->error);
    if (!empty($paramTypes)) $stmtSummary->bind_param($paramTypes, ...$params);
    if (!$stmtSummary->execute()) {
        $err = $stmtSummary->error;
        $stmtSummary->close(); // Close statement before sending error
        sendError("DB execute error (Summary): " . $err);
    }
    $resultSummary = $stmtSummary->get_result();
    $summary = $resultSummary->fetch_assoc();
    $stmtSummary->close();

    // 2. Get Unique Customers (Using 'username' column)
    $uniqueCustomers = 'N/A'; // Default
    $sqlCustomers = "SELECT COUNT(DISTINCT username) as unique_customer_count
                     FROM orders
                     WHERE status = 'Completed' {$dateCondition}"; // *** Use username ***
    $stmtCustomers = $conn->prepare($sqlCustomers);
    if ($stmtCustomers) { // Only proceed if prepare succeeded
         if (!empty($paramTypes)) $stmtCustomers->bind_param($paramTypes, ...$params);
         if ($stmtCustomers->execute()) {
             $resultCustomers = $stmtCustomers->get_result();
             $customerData = $resultCustomers->fetch_assoc();
             $uniqueCustomers = $customerData['unique_customer_count'] ?? 0;
             $resultCustomers->close(); // Close result set
         } else {
              error_log("DB execute error (Customers): " . $stmtCustomers->error); // Log error, don't stop report
         }
         $stmtCustomers->close();
    } else {
         error_log("DB prepare error (Customers): " . $conn->error); // Log error
    }


    // Calculate derived metrics
    $totalOrders = $summary['total_orders'] ?? 0;
    $totalSales = $summary['total_sales_value'] ?? 0;
    $averageOrderValue = ($totalOrders > 0) ? ($totalSales / $totalOrders) : 0;

    // Format the output using a definition list or styled divs for better appearance
    $dateRangeStr = '';
    if ($startDate && $endDate) $dateRangeStr = " from " . htmlspecialchars($startDate) . " to " . htmlspecialchars($endDate);
    elseif ($startDate) $dateRangeStr = " from " . htmlspecialchars($startDate) . " onwards";
    elseif ($endDate) $dateRangeStr = " up to " . htmlspecialchars($endDate);

    echo "<h3>Sales Summary Report" . $dateRangeStr . "</h3>";
    echo "<div class='report-summary-box' style='border: 1px solid #ddd; background-color: #f9f9f9; padding: 15px; border-radius: 4px; margin-bottom: 20px;'>"; // Added inline style for immediate effect
    echo "<dl style='margin: 0;'>"; // Use definition list <dl> for key-value pairs

    echo "<dt style='font-weight: bold; width: 180px; float: left; clear: left;'>Total Completed Orders:</dt>";
    echo "<dd style='margin-left: 190px; margin-bottom: 5px;'>" . number_format($totalOrders) . "</dd>";

    echo "<dt style='font-weight: bold; width: 180px; float: left; clear: left;'>Total Sales Value:</dt>";
    echo "<dd style='margin-left: 190px; margin-bottom: 5px;'>₱ " . number_format($totalSales, 2) . "</dd>";

    echo "<dt style='font-weight: bold; width: 180px; float: left; clear: left;'>Average Order Value:</dt>";
    echo "<dd style='margin-left: 190px; margin-bottom: 5px;'>₱ " . number_format($averageOrderValue, 2) . "</dd>";

    // Only show unique customers if calculated
    if ($uniqueCustomers !== 'N/A') {
        echo "<dt style='font-weight: bold; width: 180px; float: left; clear: left;'>Unique Customers:</dt>";
        echo "<dd style='margin-left: 190px; margin-bottom: 5px;'>" . number_format($uniqueCustomers) . "</dd>";
    }

    echo "</dl>";
    echo "</div>";

    // --- Optional: Add a table breakdown by date (if useful) ---
    // This query groups sales by date within the range
    if ($startDate && $endDate) {
        $sqlDaily = "SELECT DATE(order_date) as sale_date, COUNT(id) as daily_orders, SUM(total_amount) as daily_sales
                     FROM orders
                     WHERE status = 'Completed' {$dateCondition}
                     GROUP BY DATE(order_date)
                     ORDER BY sale_date ASC";

        $stmtDaily = $conn->prepare($sqlDaily);
         if (!$stmtDaily) sendError("DB error (Daily): " . $conn->error);
         // Parameters ($params) are already set from the main date condition
         if (!empty($paramTypes)) $stmtDaily->bind_param($paramTypes, ...$params);
         if (!$stmtDaily->execute()){
              $err = $stmtDaily->error;
              $stmtDaily->close();
              sendError("DB execute error (Daily): " . $err);
         }
         $resultDaily = $stmtDaily->get_result();

         if ($resultDaily->num_rows > 0) {
             echo "<h4>Daily Sales Breakdown</h4>";
             // Apply the same table class as used in accounts.php
             echo "<table class='accounts-table'>"; // <--- USE YOUR TABLE CLASS
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
         }
         $resultDaily->close(); // Close result set
         $stmtDaily->close();
    }
}


/**
 * Generates an Inventory Status Report (Low Stock Items).
 * NOTE: Requires an 'inventory' table. Adjust schema details below.
 */
function generateInventoryStatus($conn) {
    // --- Adjust schema: Assumes `inventory` table: `product_name`, `current_stock`, `reorder_level`, `status` ---

    $sql = "SELECT product_name, current_stock, reorder_level
            FROM inventory -- *** ADJUST TABLE NAME IF NEEDED ***
            WHERE current_stock < reorder_level AND status = 'Active' -- Example: only show active products
            ORDER BY product_name";

    $result = $conn->query($sql);
    // Check if query execution failed
    if (!$result) {
        // Check if the error is because the table doesn't exist
        if ($conn->errno === 1146) { // Error code for "Table doesn't exist"
             echo "<h3>Inventory Status Report</h3>";
             echo "<p>Note: The 'inventory' table was not found in the database. Cannot generate this report.</p>";
             return; // Exit the function gracefully
        } else {
             // For other SQL errors
             sendError("DB error (Inventory): " . $conn->error);
        }
    }


    echo "<h3>Inventory Status Report (Low Stock Items)</h3>";
    if ($result->num_rows > 0) {
        // Apply the same table class as used in accounts.php
        echo "<table class='accounts-table'>"; // <--- USE YOUR TABLE CLASS
        echo "<thead><tr><th>Product Name</th><th>Current Stock</th><th>Reorder Level</th><th>Difference</th></tr></thead>";
        echo "<tbody>";
        while ($row = $result->fetch_assoc()) {
            $difference = $row['reorder_level'] - $row['current_stock'];
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['product_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['current_stock']) . "</td>";
            echo "<td>" . htmlspecialchars($row['reorder_level']) . "</td>";
            // Highlight the difference
            echo "<td style='color: red; font-weight: bold;'>" . htmlspecialchars($difference) . " below</td>";
            echo "</tr>";
        }
        echo "</tbody>";
        echo "</table>";
    } else {
        echo "<p>No active items are currently below their reorder level.</p>";
    }
    $result->close(); // Close result set
}


/**
 * Generates an Order Listing Report.
 */
function generateOrderTrends($conn, $startDate, $endDate) {
    // --- Schema from provided 'orders' table: `id`, `order_date`, `username`, `company`, `total_amount`, `status` ---

    // Include 'company' in the select list
    $sql = "SELECT id, order_date, username, company, total_amount, status
            FROM orders WHERE 1=1";

    $params = [];
    $paramTypes = "";

    if ($startDate) { $sql .= " AND DATE(order_date) >= ?"; $params[] = $startDate; $paramTypes .= "s"; }
    if ($endDate) { $sql .= " AND DATE(order_date) <= ?"; $params[] = $endDate; $paramTypes .= "s"; }
    $sql .= " ORDER BY order_date DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) sendError("DB error (Order Trends): " . $conn->error);
    if (!empty($paramTypes)) $stmt->bind_param($paramTypes, ...$params);
    if (!$stmt->execute()){
         $err = $stmt->error;
         $stmt->close();
         sendError("DB execute error (Order Trends): " . $err);
    }
    $result = $stmt->get_result();

    $dateRangeStr = '';
    if ($startDate && $endDate) $dateRangeStr = " from " . htmlspecialchars($startDate) . " to " . htmlspecialchars($endDate);
    // Add other date range descriptions if needed

    echo "<h3>Order Listing" . $dateRangeStr . "</h3>";
    if ($result->num_rows > 0) {
        // Apply the same table class as used in accounts.php
        echo "<table class='accounts-table'>"; // <--- USE YOUR TABLE CLASS
        // Add 'Company' column to header
        echo "<thead><tr><th>Order ID</th><th>Date</th><th>Username</th><th>Company</th><th>Total</th><th>Status</th></tr></thead>";
        echo "<tbody>";
        while ($row = $result->fetch_assoc()) {
             // Get status class similar to accounts.php if applicable
             $statusClass = 'status-' . strtolower($row['status'] ?? 'unknown');
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars(date('Y-m-d H:i', strtotime($row['order_date']))) . "</td>"; // Format date
            echo "<td>" . htmlspecialchars($row['username'] ?? 'N/A') . "</td>";
            // Display Company, handle NULLs
            echo "<td>" . htmlspecialchars($row['company'] ?? 'N/A') . "</td>";
            echo "<td style='text-align: right;'>₱ " . number_format($row['total_amount'] ?? 0, 2) . "</td>";
            // Apply status class for potential styling from accounts.css
            echo "<td class='" . htmlspecialchars($statusClass) . "'>" . htmlspecialchars($row['status']) . "</td>";
            echo "</tr>";
        }
        echo "</tbody>";
        echo "</table>";
    } else {
        echo "<p>No orders found within the specified criteria.</p>";
    }
    $result->close(); // Close result set
    $stmt->close();
}

?>