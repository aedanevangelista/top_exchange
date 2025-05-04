<?php
// UTC: 2025-05-04 07:15:14
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

            case 'sales_by_client':
                // --- Sales by Client Logic (As provided before) ---
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
                $query .= " GROUP BY username ORDER BY total_revenue DESC";

                $stmt = $conn->prepare($query);
                if ($stmt === false) throw new Exception("Prepare failed (Sales by Client): " . $conn->error);
                if ($types) $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();

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

            // **** CORRECTED CASE for Inventory Status ****
            case 'inventory_status':
                // Define a default low stock threshold
                $defaultLowStockThreshold = 10; // You can change this value

                // Use the correct table and column names from your schema
                $query = "SELECT
                            p.product_id,         -- From products table
                            p.item_description,   -- From products table
                            p.packaging,          -- From products table
                            p.category,           -- From products table
                            p.stock_quantity      -- From products table
                          FROM products p         -- Correct table name
                          WHERE p.stock_quantity <= ? -- Compare against the default threshold
                            AND p.stock_quantity >= 0 -- Show low stock, including zero
                          ORDER BY p.category, p.item_description";

                $stmt = $conn->prepare($query);
                // Check prepare statement success
                if ($stmt === false) {
                     // Include query in error message for easier debugging
                     throw new Exception("Prepare failed (Inventory Status): " . $conn->error . " | Query: " . $query);
                }

                // Bind the default threshold value to the placeholder '?'
                $stmt->bind_param("i", $defaultLowStockThreshold);

                $stmt->execute();
                $result = $stmt->get_result();

                 // --- Generate HTML ---
                 echo "<div class='report-container'>";
                 echo "<h2>Low Inventory Status Report</h2>";
                 // Display the threshold being used
                 echo "<p class='report-period'>Showing items with stock at or below threshold (" . $defaultLowStockThreshold . ")</p>";

                 if ($result && $result->num_rows > 0) {
                    echo "<table class='report-table'>";
                    // Header row without the threshold column
                    echo "<thead><tr><th>Category</th><th>Product Description</th><th>Packaging</th><th>Qty in Stock</th></tr></thead>";
                    echo "<tbody>";
                    while ($row = $result->fetch_assoc()) {
                        // Use the default threshold for styling comparison
                        $threshold = $defaultLowStockThreshold;

                        // Style row based on stock level
                        $style = '';
                        if ($row['stock_quantity'] <= 0) {
                            $style = 'color:red; font-weight:bold;'; // Out of stock
                        } elseif ($row['stock_quantity'] <= $threshold) {
                            $style = 'color:orange;'; // Low stock
                        }

                        echo "<tr style='" . $style . "'>";
                        echo "<td>" . htmlspecialchars($row['category'] ?? 'N/A') . "</td>";
                        // Use item_description as the main product identifier here
                        echo "<td>" . htmlspecialchars($row['item_description']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['packaging'] ?? 'N/A') . "</td>";
                        // Use stock_quantity column
                        echo "<td>" . number_format($row['stock_quantity']) . "</td>";
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

                 if (!empty($conditions)) { $query .= " WHERE " . implode(" AND ", $conditions); }
                 $query .= " ORDER BY order_date DESC";

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
                    echo "<table class='report-table'>";
                    echo "<thead><tr><th>PO Number</th><th>Client</th><th>Order Date</th><th>Delivery Date</th><th>Total (PHP)</th><th>Status</th></tr></thead>";
                    echo "<tbody>";
                    while ($row = $result->fetch_assoc()) {
                        $statusClass = 'status-' . strtolower(str_replace(' ', '-', $row['status']));
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['po_number']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['order_date']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['delivery_date']) . "</td>";
                        echo "<td>" . number_format($row['total_amount'], 2) . "</td>";
                        echo "<td><span class='status-badge " . $statusClass . "'>" . htmlspecialchars($row['status']) . "</span></td>";
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
        // Log the detailed error to the server's error log
        error_log("Report Generation Error (" . $reportType . "): " . $e->getMessage());
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