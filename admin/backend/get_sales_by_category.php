<?php
header('Content-Type: application/json');
// Recommended: Keep error display off in production, rely on logs
error_reporting(E_ALL);
ini_set('display_errors', 0); // Keep 0 for production
ini_set('log_errors', 1); // Ensure errors are logged
// ini_set('error_log', '/path/to/your/php-error.log'); // Optional: Specify log file path

// Initialize variables for potential use in finally block or error reporting
$conn = null;
$stmt = null;

try {
    // Establish connection using your include file
    include "db_connection.php"; // Ensure this path is correct

    if (!$conn) {
        throw new Exception("Database connection failed after include.");
    }
     // Set charset AFTER connection is established
     if (!$conn->set_charset("utf8mb4")) {
        error_log("Error loading character set utf8mb4: " . $conn->error);
        // Don't necessarily throw exception, but log it
    }


    // --- 1. Get Distinct Categories ---
    $sql_cats = "SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category ASC";
    $result_cats = $conn->query($sql_cats);
    if ($result_cats === false) {
        throw new Exception("Error fetching categories: " . $conn->error);
    }
    $allCategories = [];
    while ($row = $result_cats->fetch_assoc()) {
        $allCategories[] = $row['category'];
    }
    if (empty($allCategories)) {
        // No categories found, return empty data structure
        echo json_encode([
            'categories' => [],
            'currentYear' => ['year' => date('Y'), 'data' => []],
            'lastYear' => ['year' => date('Y') - 1, 'data' => []]
        ]);
        // Ensure connection is closed before exiting
        if ($conn && $conn instanceof mysqli) $conn->close();
        exit; // Stop script execution here
    }


    // --- 2. Determine Parameters and Date Ranges ---
    $period = $_GET['period'] ?? 'weekly'; // Default to weekly
    $currentYear = (int)date('Y');
    $lastYear = $currentYear - 1;

    $sql_date_filter = "";
    $params = [];
    $param_types = "";

    // We need to compare against the specific week/month in BOTH years
    if ($period === 'weekly') {
        // Use YEARWEEK with mode 1 (Monday start, week contains Jan 4th)
        // Calculate the target YEARWEEK value for the current date and the date one year ago
        $currentYearWeekTarget = $conn->query("SELECT YEARWEEK(CURDATE(), 1)")->fetch_row()[0];
        $lastYearWeekTarget = $conn->query("SELECT YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 YEAR), 1)")->fetch_row()[0];

        $sql_date_filter = " AND (YEARWEEK(o.order_date, 1) = ? OR YEARWEEK(o.order_date, 1) = ?) ";
        $params = [$currentYearWeekTarget, $lastYearWeekTarget];
        $param_types = "ii"; // YEARWEEK returns integer

    } elseif ($period === 'monthly') {
        $currentMonth = (int)date('m');
        // Compare YEAR and MONTH
        $sql_date_filter = " AND MONTH(o.order_date) = ? AND YEAR(o.order_date) IN (?, ?) ";
        $params = [$currentMonth, $currentYear, $lastYear];
        $param_types = "iii"; // month, year, year
    } else {
        throw new Exception("Invalid period specified.");
    }


    // --- 3. Fetch Relevant Order Data ---
    // Select orders from the relevant years that match the period filter
    $sql = "SELECT
                o.order_date,
                o.orders -- The JSON column
            FROM orders o
            WHERE
                o.status = 'Completed'
                AND YEAR(o.order_date) IN (?, ?) -- Filter by relevant years first
                " . $sql_date_filter; // Add the weekly/monthly filter

    // Prepend the year parameters to the $params array
    array_unshift($params, $currentYear, $lastYear);
    $param_types = "ii" . $param_types; // Prepend year types

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error . " || SQL: " . $sql);
    }

    // Dynamically bind parameters
    if (!empty($param_types) && !empty($params)) {
         if (!$stmt->bind_param($param_types, ...$params)) {
              throw new Exception("Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error);
         }
    }

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result === false) {
        throw new Exception("Getting result set failed: (" . $stmt->errno . ") " . $stmt->error);
    }

    // --- 4. Process Orders and Aggregate Sales ---
    $currentYearSales = array_fill_keys($allCategories, 0);
    $lastYearSales = array_fill_keys($allCategories, 0);

    while ($order = $result->fetch_assoc()) {
        $orderYear = (int)date('Y', strtotime($order['order_date']));
        $orderItemsJson = $order['orders'];

        // Attempt to decode the JSON data for the order items
        $orderItems = json_decode($orderItemsJson, true);

        // Check if JSON decoding was successful and is an array
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($orderItems)) {
            error_log("Skipping order due to invalid JSON in 'orders' column for order_date " . $order['order_date'] . ". JSON: " . $orderItemsJson);
            continue; // Skip this order if JSON is invalid
        }

        // Determine which year's sales to update based on the order date's year
        $targetSalesArray = null;
        if ($orderYear === $currentYear) {
            $targetSalesArray = &$currentYearSales;
        } elseif ($orderYear === $lastYear) {
            $targetSalesArray = &$lastYearSales;
        } else {
            continue; // Skip if year doesn't match (shouldn't happen with SQL filter)
        }

        // Iterate through items in the decoded JSON
        foreach ($orderItems as $item) {
            // Basic validation for item structure
            if (isset($item['category']) && isset($item['quantity'])) {
                $category = $item['category'];
                $quantity = (int)$item['quantity']; // Ensure quantity is integer

                // Check if the category from the item exists in our fetched list
                if (array_key_exists($category, $targetSalesArray)) {
                    $targetSalesArray[$category] += $quantity;
                } else {
                    // Optional: Log categories found in orders but not in products table
                     error_log("Category mismatch: Category '{$category}' found in order (date: {$order['order_date']}) but not in distinct product categories list.");
                }
            } else {
                 error_log("Skipping item due to missing 'category' or 'quantity' in order (date: {$order['order_date']}). Item data: " . json_encode($item));
            }
        }
        // Unset the reference to avoid accidental modification later
        unset($targetSalesArray);
    }

    // $stmt->close(); // <<<--- REMOVED THIS LINE FROM HERE

    // --- 5. Prepare Final JSON Output ---
    // Map the aggregated sales data onto the ordered list of all categories
    $response = [
        'categories' => $allCategories,
        'timePeriod' => $period, // Include the period for context
        'currentYear' => [
            'year' => $currentYear,
            'data' => array_map(function($category) use ($currentYearSales) {
                return $currentYearSales[$category] ?? 0; // Use null coalescing just in case
            }, $allCategories)
        ],
        'lastYear' => [
            'year' => $lastYear,
            'data' => array_map(function($category) use ($lastYearSales) {
                return $lastYearSales[$category] ?? 0; // Use null coalescing just in case
            }, $allCategories)
        ]
    ];

    // --- 6. Send Response ---
    echo json_encode($response);
    // IMPORTANT: No other echos or output should happen after this line in the try block.

} catch (Exception $e) {
    // Log the detailed error to the server log
    error_log("Fatal error in get_sales_by_category.php: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());

    // Send a generic error response to the client
    // Avoid sending detailed error messages or stack traces to the client in production
    // Ensure this echo happens only ONCE if an error occurs
    if (!headers_sent()) { // Check if headers are already sent to avoid further errors
         // We might still be able to send a JSON error if headers not sent
         // If headers ARE sent, this echo will likely fail or cause more issues
         echo json_encode([
             'error' => true,
             'message' => 'An internal server error occurred while fetching sales data. Please check server logs.'
             // 'debug_message' => $e->getMessage() // Optional: Uncomment for debugging ONLY
         ]);
    }

} finally {
    // Ensure statement and connection are closed if they were opened
    // This block runs regardless of whether an exception occurred or not
    if ($stmt && $stmt instanceof mysqli_stmt) {
        @$stmt->close(); // <<<--- KEEP THIS LINE (using @ is okay here)
    }
    if ($conn && $conn instanceof mysqli) {
        $conn->close();
    }
}

// IMPORTANT: No code or whitespace should exist after the closing PHP tag (if you have one)
?>