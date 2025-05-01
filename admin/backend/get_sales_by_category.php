<?php
header('Content-Type: application/json');
include "db_connection.php"; // Adjust path as needed

// --- Get Parameters ---
$period = $_GET['period'] ?? 'weekly'; // Default to weekly

// --- Get Distinct Categories ---
$categories = [];
$sql_cats = "SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category ASC";
$result_cats = $conn->query($sql_cats);
if ($result_cats && $result_cats->num_rows > 0) {
    while ($row = $result_cats->fetch_assoc()) {
        $categories[] = $row['category'];
    }
} else {
    // No categories found, return empty data
    echo json_encode([
        'categories' => [],
        'currentYear' => ['year' => date('Y'), 'data' => []],
        'lastYear' => ['year' => date('Y') - 1, 'data' => []]
    ]);
    $conn->close();
    exit;
}

// --- Calculate Date Ranges and Years ---
$currentYear = date('Y');
$lastYear = $currentYear - 1;
$currentYearWeek = null;
$lastYearWeek = null;
$currentMonth = null;
$lastYearMonth = null; // Not actually needed for month comparison, just the year

$sql_date_filter = "";

if ($period === 'weekly') {
    // Using ISO 8601 week definition (Monday as first day)
    $currentYearWeek = date('o-W'); // e.g., 2023-45
    $lastYearWeek = date('o-W', strtotime('-1 year')); // e.g., 2022-45
    // Filter condition for SQL using YEARWEEK (mode 1: Monday, week contains Jan 4th)
    $sql_date_filter = " (YEARWEEK(o.order_date, 1) = YEARWEEK(CURDATE(), 1) OR YEARWEEK(o.order_date, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 YEAR), 1)) ";

} elseif ($period === 'monthly') {
    $currentMonth = date('m');
    // Filter condition for SQL using YEAR and MONTH
    $sql_date_filter = " ( (YEAR(o.order_date) = ? AND MONTH(o.order_date) = ?) OR (YEAR(o.order_date) = ? AND MONTH(o.order_date) = ?) ) ";
    // Parameters for monthly: currentYear, currentMonth, lastYear, currentMonth
} else {
    // Invalid period, return error or default
    echo json_encode(['error' => true, 'message' => 'Invalid period specified']);
    $conn->close();
    exit;
}


// --- Prepare Sales Data Structure ---
// Initialize sales data arrays with 0 for all categories
$currentYearSales = array_fill_keys($categories, 0);
$lastYearSales = array_fill_keys($categories, 0);

// --- Build and Execute the Main SQL Query ---
$sql = "SELECT
            p.category,
            YEAR(o.order_date) as order_year,
            COUNT(DISTINCT o.order_id) as order_count
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN products p ON oi.product_id = p.product_id
        WHERE
            o.status = 'Completed'  -- Only count completed orders for sales
            AND p.category IS NOT NULL AND p.category != '' -- Ensure product has category
            AND " . $sql_date_filter . "
        GROUP BY
            p.category,
            order_year
        ORDER BY
            p.category";

try {
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Bind parameters only if needed (for monthly)
    if ($period === 'monthly') {
        $stmt->bind_param("iiii", $currentYear, $currentMonth, $lastYear, $currentMonth);
    }

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();

    // --- Process SQL Results ---
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $category = $row['category'];
            $year = (int)$row['order_year'];
            $count = (int)$row['order_count'];

            // Ensure category exists in our initial list (safety check)
            if (array_key_exists($category, $currentYearSales)) {
                if ($year == $currentYear) {
                    $currentYearSales[$category] = $count;
                } elseif ($year == $lastYear) {
                    $lastYearSales[$category] = $count;
                }
            }
        }
    }

    $stmt->close();

    // --- Prepare Final JSON Output ---
    // Ensure the data arrays are in the same order as the $categories array
    $finalCurrentData = [];
    $finalLastData = [];
    foreach ($categories as $cat) {
        $finalCurrentData[] = $currentYearSales[$cat];
        $finalLastData[] = $lastYearSales[$cat];
    }

    $output = [
        'categories' => $categories, // The dynamic list of category labels
        'currentYear' => [
            'year' => $currentYear,
            'data' => $finalCurrentData // Sales data for current year period
        ],
        'lastYear' => [
            'year' => $lastYear,
            'data' => $finalLastData // Sales data for last year period
        ]
    ];

    echo json_encode($output);

} catch (Exception $e) {
    error_log("Error in get_sales_by_category.php: " . $e->getMessage());
    echo json_encode(['error' => true, 'message' => 'An error occurred while fetching sales data. SQL: ' . $sql]); // Include SQL in error for debugging
} finally {
    if ($conn && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>