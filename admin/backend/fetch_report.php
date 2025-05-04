<?php
// UTC: 2025-05-04 07:05:07
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . '/db_connection.php'; // Assumes db_connection.php is in the same directory

// --- Basic Permission/Authentication Check (Adapt as needed) ---
$isLoggedIn = isset($_SESSION['admin_user_id']) || isset($_SESSION['client_user_id']) || isset($_SESSION['user_id']);
if (!$isLoggedIn) {
    http_response_code(403); // Forbidden
    // Use the specific CSS class for error messages for consistency
    echo "<div class='report-error-message'>Access Denied: Not logged in.</div>";
    exit;
}
// Consider adding role-based checks here if different roles have access to different reports.

// --- Input Handling ---
$reportType = $_POST['report_type'] ?? '';
$startDate = $_POST['start_date'] ?? null;
$endDate = $_POST['end_date'] ?? null;

// Validate date format if provided (basic example)
if ($startDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $startDate = null;
if ($endDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) $endDate = null;

// --- Report Generation Logic ---
if ($conn) {
    try {
        switch ($reportType) {
            case 'sales_summary':
                // --- ADAPT THIS SECTION with your actual Sales Summary logic ---
                // Example structure:
                $query = "SELECT
                            COUNT(*) as total_orders,
                            SUM(total_amount) as total_revenue,
                            AVG(total_amount) as average_order_value
                          FROM orders
                          WHERE status IN ('Active', 'For Delivery', 'Completed') "; // <<< ADAPT Statuses
                $params = [];
                $types = "";
                if ($startDate) { $query .= " AND order_date >= ?"; $params[] = $startDate; $types .= "s"; }
                if ($endDate) { $query .= " AND order_date <= ?"; $params[] = $endDate; $types .= "s"; }

                $stmt = $conn->prepare($query);
                if ($stmt === false) throw new Exception("Prepare failed (Sales Summary): " . $conn->error);
                if ($types) $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                // --- Generate HTML for Sales Summary ---
                echo "<div class='report-container'>";
                echo "<h2>Sales Summary Report</h2>";
                if ($startDate || $endDate) {
                    $period = "Period: ";
                    $period .= $startDate ? date("M d, Y", strtotime($startDate)) : 'Start';
                    $period .= " to ";
                    $period .= $endDate ? date("M d, Y", strtotime($endDate)) : 'End';
                    echo "<p class='report-period'>" . htmlspecialchars($period) . "</p>";
                }
                if ($result) {
                    echo "<table class='report-table'>";
                    echo "<tr><th>Metric</th><th>Value</th></tr>";
                    echo "<tr><td>Total Orders</td><td>" . number_format($result['total_orders'] ?? 0) . "</td></tr>";
                    echo "<tr><td>Total Revenue</td><td>PHP " . number_format($result['total_revenue'] ?? 0, 2) . "</td></tr>";
                    echo "<tr><td>Average Order Value</td><td>PHP " . number_format($result['average_order_value'] ?? 0, 2) . "</td></tr>";
                    echo "</table>";
                } else {
                    echo "<p>No sales data found for the selected period.</p>";
                }
                echo "</div>";
                break;

            // **** NEW CASE for Sales by Client ****
            case 'sales_by_client':
                $query = "SELECT
                            username,
                            COUNT(*) as order_count,
                            SUM(total_amount) as total_revenue
                          FROM orders
                          WHERE status IN ('Active', 'For Delivery', 'Completed') "; // <<< ADAPT Statuses
                $params = [];
                $types = "";
                if ($startDate) { $query .= " AND order_date >= ?"; $params[] = $startDate; $types .= "s"; }
                if ($endDate) { $query .= " AND order_date <= ?"; $params[] = $endDate; $types .= "s"; }
                $query .= " GROUP BY username ORDER BY total_revenue DESC"; // Order by revenue

                $stmt = $conn->prepare($query);
                if ($stmt === false) throw new Exception("Prepare failed (Sales by Client): " . $conn->error);
                if ($types) $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();

                // --- Generate HTML for Sales by Client ---
                echo "<div class='report-container'>";
                echo "<h2>Sales by Client Report</h2>";
                 if ($startDate || $endDate) {
                    $period = "Period: ";
                    $period .= $startDate ? date("M d, Y", strtotime($startDate)) : 'Start';
                    $period .= " to ";
                    $period .= $endDate ? date("M d, Y", strtotime($endDate)) : 'End';
                    echo "<p class='report-period'>" . htmlspecialchars($period) . "</p>";
                }
                if ($result && $result->num_rows > 0) {
                    echo "<table class='report-table sortable'>"; // Add sortable class if you implement JS sorting
                    echo "<thead><tr><th>Client Username</th><th>Total Orders</th><th>Total Revenue (PHP)</th></tr></thead>";
                    echo "<tbody>";
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                        echo "<td>" . number_format($row['order_count']) . "</td>";
                        echo "<td>" . number_format($row['total_revenue'], 2) . "</td>";
                        echo "</tr>";
                    }
                    echo "</tbody>";
                    echo "</table>";
                } else {
                    echo "<p>No sales data found for the selected criteria.</p>";
                }
                echo "</div>";
                $stmt->close();
                break;

            case 'inventory_status':
                // --- ADAPT THIS SECTION with your actual Low Inventory logic ---
                // Example structure (assumes 'inventory' table and 'low_stock_threshold' column)
                 $query = "SELECT product_id, item_description, packaging, category, quantity_in_stock, low_stock_threshold
                           FROM inventory -- <<< ADAPT Table Name
                           WHERE quantity_in_stock <= low_stock_threshold AND quantity_in_stock >= 0 -- <<< ADAPT Columns/Logic
                           ORDER BY category, item_description";
                 $stmt = $conn->prepare($query);
                 if ($stmt === false) throw new Exception("Prepare failed (Inventory Status): " . $conn->error);
                 $stmt->execute();
                 $result = $stmt->get_result();

                 // --- Generate HTML ---
                 echo "<div class='report-container'>";
                 echo "<h2>Low Inventory Status Report</h2>";
                 // No date range typically needed for current status
                 if ($result && $result->num_rows > 0) {
                    echo "<table class='report-table'>";
                    echo "<thead><tr><th>Category</th><th>Product</th><th>Packaging</th><th>Qty in Stock</th><th>Low Stock Threshold</th></tr></thead>";
                    echo "<tbody>";
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr style='" . ($row['quantity_in_stock'] <= 0 ? "color:red; font-weight:bold;" : ($row['quantity_in_stock'] <= $row['low_stock_threshold'] ? "color:orange;" : "")) . "'>"; // Example styling
                        echo "<td>" . htmlspecialchars($row['category'] ?? 'N/A') . "</td>";
                        echo "<td>" . htmlspecialchars($row['item_description']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['packaging'] ?? 'N/A') . "</td>";
                        echo "<td>" . number_format($row['quantity_in_stock']) . "</td>";
                        echo "<td>" . number_format($row['low_stock_threshold']) . "</td>";
                        echo "</tr>";
                    }
                    echo "</tbody>";
                    echo "</table>";
                 } else {
                    echo "<p>No items currently at or below the low stock threshold.</p>";
                 }
                 echo "</div>";
                 $stmt->close();
                break;

            case 'order_trends': // Assuming this is your "Order Listing" report
                // --- ADAPT THIS SECTION with your actual Order Listing logic ---
                 $query = "SELECT po_number, username, order_date, delivery_date, total_amount, status
                           FROM orders ";
                 $params = [];
                 $types = "";
                 $conditions = [];
                 if ($startDate) { $conditions[] = "order_date >= ?"; $params[] = $startDate; $types .= "s"; }
                 if ($endDate) { $conditions[] = "order_date <= ?"; $params[] = $endDate; $types .= "s"; }
                 // Add more filters if needed (e.g., by status, by client)
                 // if (!empty($_POST['filter_status'])) { ... }

                 if (!empty($conditions)) { $query .= " WHERE " . implode(" AND ", $conditions); }
                 $query .= " ORDER BY order_date DESC"; // Example sort

                 $stmt = $conn->prepare($query);
                 if ($stmt === false) throw new Exception("Prepare failed (Order Listing): " . $conn->error);
                 if ($types) $stmt->bind_param($types, ...$params);
                 $stmt->execute();
                 $result = $stmt->get_result();

                 // --- Generate HTML ---
                 echo "<div class='report-container'>";
                 echo "<h2>Order Listing</h2>";
                 if ($startDate || $endDate) {
                    $period = "Period: ";
                    $period .= $startDate ? date("M d, Y", strtotime($startDate)) : 'Start';
                    $period .= " to ";
                    $period .= $endDate ? date("M d, Y", strtotime($endDate)) : 'End';
                    echo "<p class='report-period'>" . htmlspecialchars($period) . "</p>";
                 }
                 if ($result && $result->num_rows > 0) {
                    echo "<table class='report-table'>"; // Consider adding 'sortable' class if using JS sorting
                    echo "<thead><tr><th>PO Number</th><th>Client</th><th>Order Date</th><th>Delivery Date</th><th>Total (PHP)</th><th>Status</th></tr></thead>";
                    echo "<tbody>";
                    while ($row = $result->fetch_assoc()) {
                        // Add status class for potential styling
                        $statusClass = 'status-' . strtolower(str_replace(' ', '-', $row['status']));
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['po_number']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['order_date']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['delivery_date']) . "</td>";
                        echo "<td>" . number_format($row['total_amount'], 2) . "</td>";
                        echo "<td><span class='status-badge " . $statusClass . "'>" . htmlspecialchars($row['status']) . "</span></td>"; // Example using status badge
                        echo "</tr>";
                    }
                    echo "</tbody>";
                    echo "</table>";
                 } else {
                    echo "<p>No orders found for the selected criteria.</p>";
                 }
                 echo "</div>";
                 $stmt->close();
                break;

            default:
                // Use the specific CSS class for error messages
                echo "<div class='report-error-message'>Invalid report type selected.</div>";
                break;
        }
    } catch (Exception $e) {
        error_log("Report Generation Error (" . $reportType . "): " . $e->getMessage()); // Log the detailed error
        http_response_code(500); // Internal Server Error
        // Send a user-friendly error message using the CSS class
        echo "<div class='report-error-message'>Error generating report: " . htmlspecialchars($e->getMessage()) . "</div>";
    } finally {
        // Connection is closed globally in reporting.php, no need to close here
        // if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
    }
} else {
    http_response_code(500);
    // Use the specific CSS class for error messages
    echo "<div class='report-error-message'>Database connection error.</div>";
}
?>