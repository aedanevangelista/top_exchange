<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Basic security check - ensure user is logged in.
// You might want more robust role checking identical to reporting.php if needed.
$isLoggedIn = isset($_SESSION['admin_user_id']) || isset($_SESSION['client_user_id']) || isset($_SESSION['user_id']);
if (!$isLoggedIn) {
    http_response_code(403); // Forbidden
    echo "<p style='color: red;'>Access Denied. Please log in.</p>";
    exit;
}

include_once __DIR__ . '/db_connection.php'; // Establishes $conn

// Default error response
function sendError($message, $httpCode = 500) {
    http_response_code($httpCode);
    echo "<p style='color: red;'>Error: " . htmlspecialchars($message) . "</p>";
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
        generateInventoryStatus($conn); // Dates might not be needed here, or could be added
        break;

    case 'order_trends':
        generateOrderTrends($conn, $startDate, $endDate);
        break;

    // Add more cases for other report types

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
 * Generates a Sales Summary Report.
 */
function generateSalesSummary($conn, $startDate, $endDate) {
    // --- Assumptions about your database schema ---
    // 1. There's an `orders` table.
    // 2. `orders` has a `total_amount` column (or similar).
    // 3. `orders` has an `order_date` or `created_at` column (DATETIME or DATE).
    // 4. We consider orders within the date range (inclusive).
    // 5. We might want to filter by order `status` (e.g., only 'Completed', 'Delivered'). Let's assume 'Completed' for now.
    // --- Adjust the query based on your actual schema ---

    $sql = "SELECT
                COUNT(id) as total_orders,
                SUM(total_amount) as total_sales_value
            FROM orders
            WHERE status = 'Completed'"; // Filter for completed orders

    $params = [];
    $paramTypes = "";

    // Add date filtering if dates are provided
    if ($startDate) {
        $sql .= " AND DATE(order_date) >= ?"; // Assuming 'order_date' column
        $params[] = $startDate;
        $paramTypes .= "s";
    }
    if ($endDate) {
        $sql .= " AND DATE(order_date) <= ?"; // Assuming 'order_date' column
        $params[] = $endDate;
        $paramTypes .= "s";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        sendError("Database error preparing sales summary: " . $conn->error);
    }

    if (!empty($paramTypes)) {
        $stmt->bind_param($paramTypes, ...$params);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        sendError("Database error executing sales summary: " . $conn->error);
    }

    $result = $stmt->get_result();
    $summary = $result->fetch_assoc();
    $stmt->close();

    // Format the output as HTML
    $totalOrders = $summary['total_orders'] ?? 0;
    $totalSales = $summary['total_sales_value'] ?? 0;
    $dateRangeStr = '';
    if ($startDate && $endDate) {
        $dateRangeStr = " from " . htmlspecialchars($startDate) . " to " . htmlspecialchars($endDate);
    } elseif ($startDate) {
        $dateRangeStr = " from " . htmlspecialchars($startDate) . " onwards";
    } elseif ($endDate) {
        $dateRangeStr = " up to " . htmlspecialchars($endDate);
    }

    echo "<h4>Sales Summary Report" . $dateRangeStr . "</h4>";
    echo "<ul>";
    echo "<li>Total Completed Orders: <strong>" . number_format($totalOrders) . "</strong></li>";
    echo "<li>Total Sales Value: <strong>₱" . number_format($totalSales, 2) . "</strong></li>"; // Assuming PHP currency format
    echo "</ul>";
    // You could add more details, like average order value etc.
}

/**
 * Generates an Inventory Status Report (Placeholder).
 */
function generateInventoryStatus($conn) {
    // --- Assumptions ---
    // 1. You have an `inventory` or `products` table.
    // 2. It has columns like `product_name`, `current_stock`, `reorder_level`.
    // --- Adjust query as needed ---

    // Example: Show items below reorder level
    $sql = "SELECT product_name, current_stock, reorder_level
            FROM inventory
            WHERE current_stock < reorder_level
            ORDER BY product_name";

    $result = $conn->query($sql);
    if (!$result) {
        sendError("Database error fetching inventory status: " . $conn->error);
    }

    echo "<h4>Inventory Status Report (Low Stock Items)</h4>";
    if ($result->num_rows > 0) {
        echo "<table>";
        echo "<thead><tr><th>Product Name</th><th>Current Stock</th><th>Reorder Level</th></tr></thead>";
        echo "<tbody>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['product_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['current_stock']) . "</td>";
            echo "<td>" . htmlspecialchars($row['reorder_level']) . "</td>";
            echo "</tr>";
        }
        echo "</tbody>";
        echo "</table>";
    } else {
        echo "<p>No items are currently below their reorder level.</p>";
    }
    $result->close(); // Close result set
}

/**
 * Generates an Order Trends Report (Placeholder).
 */
function generateOrderTrends($conn, $startDate, $endDate) {
    // This could be more complex, e.g., grouping orders by day/week/month
    // For simplicity, let's just list orders within the date range.

    $sql = "SELECT id, order_date, customer_name, total_amount, status
            FROM orders WHERE 1=1"; // Start with WHERE 1=1 for easy appending

    $params = [];
    $paramTypes = "";

    if ($startDate) {
        $sql .= " AND DATE(order_date) >= ?";
        $params[] = $startDate;
        $paramTypes .= "s";
    }
    if ($endDate) {
        $sql .= " AND DATE(order_date) <= ?";
        $params[] = $endDate;
        $paramTypes .= "s";
    }
    $sql .= " ORDER BY order_date DESC"; // Show most recent first

    $stmt = $conn->prepare($sql);
     if (!$stmt) {
        sendError("Database error preparing order trends: " . $conn->error);
    }

    if (!empty($paramTypes)) {
        $stmt->bind_param($paramTypes, ...$params);
    }

     if (!$stmt->execute()) {
        $stmt->close();
        sendError("Database error executing order trends: " . $conn->error);
    }

    $result = $stmt->get_result();

    $dateRangeStr = '';
     if ($startDate && $endDate) {
        $dateRangeStr = " from " . htmlspecialchars($startDate) . " to " . htmlspecialchars($endDate);
    } // Add other date range descriptions if needed

    echo "<h4>Order Listing" . $dateRangeStr . "</h4>";
    if ($result->num_rows > 0) {
        echo "<table>";
        echo "<thead><tr><th>Order ID</th><th>Date</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead>";
        echo "<tbody>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars(date('Y-m-d', strtotime($row['order_date']))) . "</td>"; // Format date
            echo "<td>" . htmlspecialchars($row['customer_name'] ?? 'N/A') . "</td>"; // Assuming customer_name column
            echo "<td style='text-align: right;'>₱" . number_format($row['total_amount'] ?? 0, 2) . "</td>";
            echo "<td>" . htmlspecialchars($row['status']) . "</td>";
            echo "</tr>";
        }
        echo "</tbody>";
        echo "</table>";
    } else {
        echo "<p>No orders found within the specified criteria.</p>";
    }
    $stmt->close();
}

?>