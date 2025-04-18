<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Department Forecast');

$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

if ($month < 1 || $month > 12) {
    $month = intval(date('m'));
}
if ($year < 2020 || $year > 2030) {
    $year = intval(date('Y'));
}

$firstDay = strtotime("$year-$month-01");
$daysInMonth = date('t', $firstDay);

// Get all orders for the selected month and year
$sql = "SELECT o.delivery_date, COUNT(DISTINCT o.id) as order_count 
        FROM orders o
        WHERE MONTH(o.delivery_date) = ? 
        AND YEAR(o.delivery_date) = ? 
        AND o.status = 'Active'
        GROUP BY o.delivery_date";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $month, $year);
$stmt->execute();
$result = $stmt->get_result();

$deliveryDates = [];
while ($row = $result->fetch_assoc()) {
    $deliveryDates[$row['delivery_date']] = $row['order_count'];
}
$stmt->close();

// Get all available categories
$categoriesQuery = "SELECT DISTINCT category FROM products ORDER BY category";
$categoriesResult = $conn->query($categoriesQuery);
$allCategories = [];
while ($row = $categoriesResult->fetch_assoc()) {
    $allCategories[] = $row['category'];
}

function getMonthNavigation($month, $year) {
    $prevMonth = $month - 1;
    $prevYear = $year;
    if ($prevMonth < 1) {
        $prevMonth = 12;
        $prevYear--;
    }
    
    $nextMonth = $month + 1;
    $nextYear = $year;
    if ($nextMonth > 12) {
        $nextMonth = 1;
        $nextYear++;
    }
    
    return [
        'prev' => "?month=$prevMonth&year=$prevYear",
        'next' => "?month=$nextMonth&year=$nextYear"
    ];
}

$navigation = getMonthNavigation($month, $year);

// Get current date and time for display
$currentDateTime = date('Y-m-d H:i:s');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Forecast</title>
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="/css/orders.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/css/toast.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <!-- Include jsPDF library for PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <style>
        body {
            background-color: #f5f7fa;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .forecast-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #ddd;
            background-color: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .forecast-header h1 {
            font-size: 24px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #333;
        }
        
        .month-navigation {
            display: flex;
            align-items: center;
        }
        
        .month-navigation a {
            background-color: #424242;
            color: white;
            padding: 8px 15px;
            margin: 0 5px;
            text-decoration: none;
            border-radius: 80px;
            font-weight: 600;
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .month-navigation a:hover {
            background-color: #212121;
        }
        
        .current-month {
            font-size: 1.2em;
            font-weight: bold;
            margin: 0 15px;
            color: #333;
        }
        
        .calendar-container {
            background-color: #ffffff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0px 3px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .calendar {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
        }
        
        .calendar th {
            background-color: #424242;
            color: white;
            padding: 12px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #ddd;
        }
        
        .calendar td {
            border: 1px solid #ddd;
            height: 120px;
            width: 14.28%;
            vertical-align: top;
            padding: 8px;
            background-color: #fff;
        }
        
        .calendar .day-number {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 1.2em;
        }
        
        .calendar .empty {
            background-color: #f9f9f9;
        }
        
        .delivery-day {
            background-color: #f5f9ff;
        }
        
        .orders-box {
            background-color: #DAA520;
            color: white;
            padding: 8px;
            text-align: center;
            border-radius: 80px;
            margin-top: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .orders-box:hover {
            background-color: #B8860B;
        }
        
        .orders-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.7);
            backdrop-filter: blur(3px);
            animation: fadeIn 0.3s ease-in-out;
        }
        
        .modal-content {
            background-color: #f5f7fa;  /* Pastel background for modal */
            margin: 5% auto;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
            width: 80%;
            max-width: 900px;
            animation: modalFadeIn 0.3s ease-in-out;
            max-height: 80vh;
            display: flex;
            flex-direction: column;
        }
        
        .modal-header {
            margin-bottom: 20px;
            background-color: #fff;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header-left {
            flex-grow: 1;
            text-align: center;
        }
        
        .modal-header-right {
            display: flex;
            gap: 10px;
        }
        
        .modal-body {
            overflow-y: auto;
            flex-grow: 1;
            padding-right: 10px;
        }
        
        .close-btn {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close-btn:hover,
        .close-btn:focus {
            color: black;
            text-decoration: none;
        }
        
        .print-btn {
            background-color: #4caf50;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.3s;
        }
        
        .print-btn:hover {
            background-color: #3e8e41;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
            margin-bottom: 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .orders-table th {
            background-color: #424242;
            color: white;
            padding: 12px;
            text-align: center;
            font-weight: bold;
        }
        
        .orders-table td {
            border-bottom: 1px solid #ddd;
            padding: 10px;
            text-align: center;
            height: auto;
            background-color: #fff;
        }
        
        .orders-table tbody tr:hover {
            background-color: #f5f5f5;
        }
        
        .view-orders-btn {
            background-color: #424242;
            color: white;
            padding: 5px 10px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 80px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.3s;
        }
        
        .view-orders-btn:hover {
            background-color: #212121;
        }

        .modal-date-header {
            font-size: 24px;
            color: #333;
            text-align: center;
            font-weight: 600;
            margin: 0;
        }

        .weekend {
            background-color: #f0f0f0;
        }
        
        .delivery-day {
            background-color: #f5f9ff;
            position: relative;
        }

        .non-delivery-day {
            background-color: #f0f0f0;
            color: #999;
        }

        .today {
            background-color: #fff8e1;
            border: 2px solid #DAA520;
        }
        
        .no-orders {
            color: #999;
            font-style: italic;
            text-align: center;
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .calendar td {
                height: 80px;
                padding: 5px;
            }
            
            .calendar .day-number {
                font-size: 1em;
            }
            
            .orders-box {
                padding: 5px;
                font-size: 0.9em;
            }
            
            .modal-content {
                width: 95%;
                padding: 15px;
                margin: 10% auto;
            }
        }
        
        .delivery-address {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .delivery-address:hover {
            white-space: normal;
            overflow: visible;
        }

        .category-badge {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .department-header {
            font-weight: bold;
            padding: 12px 15px;
            border-radius: 8px 8px 0 0;
            margin-top: 0;
            margin-bottom: 0;
            font-size: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }
        
        .order-count-badge {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 20px;
            padding: 3px 8px;
            font-size: 12px;
            border: 1px solid rgba(255, 255, 255, 0.4);
        }
        
        .product-summary {
            margin-bottom: 5px;
        }
        
        .timestamp {
            font-size: 12px;
            color: #777;
            text-align: right;
            margin-top: 20px;
            font-style: italic;
            background-color: #fff;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .no-category-orders {
            padding: 15px;
            color: #777;
            font-style: italic;
            text-align: center;
            border: 1px dashed #ddd;
            border-radius: 8px;
            margin: 10px 0 20px 0;
            background: #f9f9f9;
        }
        
        .scrollbar-style::-webkit-scrollbar {
            width: 8px;
        }
        
        .scrollbar-style::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .scrollbar-style::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        
        .scrollbar-style::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        .materials-summary {
            background-color: #fff;
            border: 1px solid #ddd;
            padding: 0;
            margin-top: 0;
            margin-bottom: 0;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .materials-summary h3 {
            margin: 0;
            padding: 12px 15px;
            font-size: 16px;
            color: white;
            background: linear-gradient(135deg, #607d8b, #455a64);
        }
        
        .department-section[data-category="Siopao"] .materials-summary h3 {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
        }
        
        .department-section[data-category="Dimsum & Dumplings"] .materials-summary h3,
        .department-section[data-category="Dimsum"] .materials-summary h3,
        .department-section[data-category="Dumplings"] .materials-summary h3 {
            background: linear-gradient(135deg, #FF9800, #E65100);
        }
        
        .department-section[data-category="Healthy Dimsum"] .materials-summary h3 {
            background: linear-gradient(135deg, #8BC34A, #558B2F);
        }
        
        .department-section[data-category="Sauces"] .materials-summary h3 {
            background: linear-gradient(135deg, #F44336, #B71C1C);
        }
        
        .department-section[data-category="Marinated Items"] .materials-summary h3 {
            background: linear-gradient(135deg, #9C27B0, #4A148C);
        }
        
        .department-section[data-category="Noodles & Wrappers"] .materials-summary h3,
        .department-section[data-category="Noodles"] .materials-summary h3,
        .department-section[data-category="Wrappers"] .materials-summary h3 {
            background: linear-gradient(135deg, #2196F3, #0D47A1);
        }
        
        .department-section[data-category="Pork"] .materials-summary h3 {
            background: linear-gradient(135deg, #E91E63, #880E4F);
        }
        
        .materials-container {
            padding: 15px;
        }
        
        .material-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            padding: 8px 0;
            border-bottom: 1px dashed #eee;
        }
        
        .material-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .material-name {
            font-weight: 500;
        }
        
        .material-amount {
            font-weight: 600;
            color: #333;
        }
        
        .tab-container {
            margin-top: 0;
        }
        
        .tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            background-color: #fff;
            border-radius: 0 0 0 0;
        }
        
        .tab {
            padding: 10px 15px;
            margin-right: 5px;
            border: 1px solid #ddd;
            border-bottom: none;
            border-radius: 5px 5px 0 0;
            cursor: pointer;
            background-color: #f5f5f5;
            transition: background-color 0.3s;
        }
        
        .tab.active {
            background-color: #fff;
            font-weight: bold;
            border-bottom: 1px solid #fff;
            margin-bottom: -1px;
        }
        
        .tab-content {
            display: none;
            padding: 15px;
            background-color: #fff;
            border-radius: 0 0 8px 8px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .department-section {
            margin-bottom: 30px;
            border-radius: 8px;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        /* Category-specific colors */
        .department-section[data-category="Siopao"] .department-header {
            background-color: #4CAF50; /* Green */
            border-left: 5px solid #2E7D32;
        }
        
        .department-section[data-category="Dimsum & Dumplings"] .department-header,
        .department-section[data-category="Dimsum"] .department-header,
        .department-section[data-category="Dumplings"] .department-header {
            background-color: #FF9800; /* Orange */
            border-left: 5px solid #E65100;
        }
        
        .department-section[data-category="Healthy Dimsum"] .department-header {
            background-color: #8BC34A; /* Light Green */
            border-left: 5px solid #558B2F;
        }
        
        .department-section[data-category="Sauces"] .department-header {
            background-color: #F44336; /* Red */
            border-left: 5px solid #B71C1C;
        }
        
        .department-section[data-category="Marinated Items"] .department-header {
            background-color: #9C27B0; /* Purple */
            border-left: 5px solid #4A148C;
        }
        
        .department-section[data-category="Noodles & Wrappers"] .department-header,
        .department-section[data-category="Noodles"] .department-header,
        .department-section[data-category="Wrappers"] .department-header {
            background-color: #2196F3; /* Blue */
            border-left: 5px solid #0D47A1;
        }
        
        .department-section[data-category="Pork"] .department-header {
            background-color: #E91E63; /* Pink */
            border-left: 5px solid #880E4F;
        }
        
        /* Default color for any other categories */
        .department-section .department-header {
            background-color: #607D8B; /* Blue Grey */
            border-left: 5px solid #37474F;
        }
        
        .unit-badge {
            background-color: #e0e0e0;
            color: #333;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: normal;
            margin-left: 5px;
        }
        
        /* Print-specific styles */
        @media print {
            body * {
                visibility: hidden;
            }
            .print-container, .print-container * {
                visibility: visible;
            }
            .print-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
        }
        
        .print-container {
            display: none;
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    
    <div class="main-content">
        <div class="forecast-header">
            <h1><i class="fas fa-chart-line"></i> Department Forecast</h1>
            <div class="month-navigation">
                <a href="<?= $navigation['prev'] ?>"><i class="fas fa-chevron-left"></i> Previous Month</a>
                <span class="current-month"><?= date('F Y', strtotime("$year-$month-01")) ?></span>
                <a href="<?= $navigation['next'] ?>">Next Month <i class="fas fa-chevron-right"></i></a>
            </div>
        </div>
        
        <div class="calendar-container">
            <table class="calendar">
                <thead>
                    <tr>
                        <th>Sunday</th>
                        <th>Monday</th>
                        <th>Tuesday</th>
                        <th>Wednesday</th>
                        <th>Thursday</th>
                        <th>Friday</th>
                        <th>Saturday</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $firstDayOfWeek = date('w', $firstDay);
                    
                    $totalDays = $firstDayOfWeek + $daysInMonth;
                    $totalRows = ceil($totalDays / 7);
                    
                    $dayCounter = 1;
                    
                    for ($row = 0; $row < $totalRows; $row++) {
                        echo "<tr>";
                        
                        for ($col = 0; $col < 7; $col++) {
                            if ($row === 0 && $col < $firstDayOfWeek) {
                                echo '<td class="empty"></td>';
                                continue;
                            }
                            
                            if ($dayCounter > $daysInMonth) {
                                echo '<td class="empty"></td>';
                                continue;
                            }
                            
                            $currentDate = sprintf("%04d-%02d-%02d", $year, $month, $dayCounter);
                            $dayOfWeek = date('w', strtotime($currentDate));
                            
                            $classes = [];
                            if ($currentDate === date('Y-m-d')) {
                                $classes[] = 'today';
                            }
                            
                            $isDeliveryDay = in_array($dayOfWeek, [1, 3, 5]);
                            if ($isDeliveryDay) {
                                $classes[] = 'delivery-day';
                            } else if ($dayOfWeek == 0 || $dayOfWeek == 6) {
                                $classes[] = 'weekend';
                            } else {
                                $classes[] = 'non-delivery-day';
                            }
                            
                            $orderCount = isset($deliveryDates[$currentDate]) ? $deliveryDates[$currentDate] : 0;
                            
                            echo '<td class="' . implode(' ', $classes) . '">';
                            echo '<div class="day-number">' . $dayCounter . '</div>';
                            
                            if ($isDeliveryDay && $orderCount > 0) {
                                echo '<div class="orders-box" onclick="showOrders(\'' . $currentDate . '\')">';
                                echo '<i class="fas fa-box"></i> ' . $orderCount . ' ' . ($orderCount == 1 ? 'Order' : 'Orders');
                                echo '</div>';
                            } else if ($isDeliveryDay) {
                                echo '<div class="no-orders">No Orders</div>';
                            }
                            
                            echo '</td>';
                            
                            $dayCounter++;
                        }
                        
                        echo "</tr>";
                        
                        if ($dayCounter > $daysInMonth) {
                            break;
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <div class="timestamp">
            Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): <?= $currentDateTime ?>
            <br>
            Current User's Login: <?= isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Guest' ?>
        </div>
    </div>
    
    <div id="departmentOrdersModal" class="orders-modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-header-left">
                    <h2 class="modal-date-header" id="modalDate">Orders for Date</h2>
                </div>
                <div class="modal-header-right">
                    <button class="print-btn" id="printOrdersBtn" onclick="generateProductsPDF()">
                        <i class="fas fa-print"></i> Print Products
                    </button>
                    <span class="close-btn" onclick="closeModal()">&times;</span>
                </div>
            </div>
            <div id="departmentOrdersContainer" class="modal-body scrollbar-style">
                <!-- Department orders will be loaded here -->
            </div>
        </div>
    </div>
    
    <!-- Hidden container for print formatting -->
    <div id="printContainer" class="print-container"></div>
    
    <script>
    // Initialize jsPDF
    window.jspdf = window.jspdf || {};
    window.jspdf.jsPDF = window.jsPDF.jsPDF;
    
    // Pass the PHP array of all categories to JavaScript
    const allCategories = <?= json_encode($allCategories) ?>;
    // Store the current date and order data
    let currentOrderDate = '';
    let currentOrderData = [];
    
    // Format number with commas and decimal places
    function formatNumber(num, decimals = 2) {
        return num.toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    
    // Format material amount (convert to kg if over 1000g)
    function formatMaterialAmount(amount) {
        if (amount >= 1000) {
            return formatNumber(amount / 1000, 2) + ' <span class="unit-badge">kg</span>';
        } else {
            return formatNumber(amount, 2) + ' <span class="unit-badge">g</span>';
        }
    }
    
    // Generate Products PDF with each department on a separate page
    function generateProductsPDF() {
        // Get formatted date for the filename and header
        const formattedDate = new Date(currentOrderDate).toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        // Initialize PDF with portrait orientation
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({
            orientation: 'portrait',
            unit: 'mm',
            format: 'a4'
        });
        
        // Title styles
        const titleFontSize = 16;
        const subTitleFontSize = 12;
        const normalFontSize = 10;
        const pageWidth = doc.internal.pageSize.getWidth();
        
        // Sort categories by total items (descending)
        const categoriesWithCounts = allCategories.map(category => {
            const products = currentOrderData[category]?.products || [];
            const totalItems = products.reduce((sum, product) => sum + product.total_quantity, 0);
            
            return {
                name: category,
                products: products,
                totalItems: totalItems
            };
        }).filter(cat => cat.totalItems > 0)
          .sort((a, b) => b.totalItems - a.totalItems);
        
        // Generate a page for each department with products
        let isFirstPage = true;
        
        categoriesWithCounts.forEach((categoryData, index) => {
            // Add a new page for each category except the first one
            if (!isFirstPage) {
                doc.addPage();
            } else {
                isFirstPage = false;
            }
            
            // Add page header
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(titleFontSize);
            doc.text(`Department Orders for ${formattedDate}`, pageWidth / 2, 20, { align: 'center' });
            
            // Add department name as subtitle
            doc.setFontSize(subTitleFontSize);
            doc.text(`${categoryData.name} Department (${categoryData.totalItems} items)`, pageWidth / 2, 30, { align: 'center' });
            
            // Add timestamp
            const timestamp = new Date().toLocaleString();
            doc.setFontSize(8);
            doc.setTextColor(100);
            doc.text(`Generated: ${timestamp}`, pageWidth - 15, 10, { align: 'right' });
            doc.setTextColor(0);
            
            // Add products table if there are products
            if (categoryData.products.length > 0) {
                // Prepare table data
                const tableHeaders = [['Product', 'Packaging', 'Quantity']];
                const tableData = categoryData.products.map(product => [
                    product.item_description,
                    product.packaging,
                    product.total_quantity.toString()
                ]);
                
                // Add table to PDF
                doc.autoTable({
                    startY: 40,
                    head: tableHeaders,
                    body: tableData,
                    theme: 'grid',
                    headStyles: {
                        fillColor: [66, 66, 66],
                        textColor: [255, 255, 255],
                        fontStyle: 'bold',
                        halign: 'center'
                    },
                    styles: {
                        fontSize: normalFontSize,
                        cellPadding: 5
                    },
                    columnStyles: {
                        0: { cellWidth: 'auto' }, // Product name
                        1: { cellWidth: 'auto' }, // Packaging
                        2: { cellWidth: 30, halign: 'center' } // Quantity
                    }
                });
            } else {
                doc.setFontSize(normalFontSize);
                doc.text('No products available for this department.', 20, 50);
            }
            
            // Add page number at bottom
            const pageNumber = doc.internal.getNumberOfPages();
            doc.setFontSize(10);
            doc.setTextColor(100);
            doc.text(`Page ${pageNumber} of ${categoriesWithCounts.length}`, pageWidth - 20, doc.internal.pageSize.getHeight() - 10);
        });
        
        // Save the PDF with a descriptive filename
        const datePart = currentOrderDate.replace(/-/g, '');
        doc.save(`Department_Orders_${datePart}.pdf`);
    }
    
    function showOrders(date) {
        const modal = document.getElementById('departmentOrdersModal');
        const modalDate = document.getElementById('modalDate');
        const departmentOrdersContainer = document.getElementById('departmentOrdersContainer');
        
        // Store the current date for PDF generation
        currentOrderDate = date;
        
        const formattedDate = new Date(date).toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        modalDate.textContent = 'Department Orders for ' + formattedDate;
        departmentOrdersContainer.innerHTML = '<div style="text-align: center; margin: 20px;"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Loading orders...</p></div>';
        
        fetch(`/backend/get_orders_by_date_and_category.php?date=${date}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            // Store the order data for PDF generation
            currentOrderData = data;
            departmentOrdersContainer.innerHTML = '';
            
            // Calculate total items for each category to sort by most orders
            const categoriesWithCounts = allCategories.map(category => {
                const products = data[category]?.products || [];
                const totalItems = products.reduce((sum, product) => sum + product.total_quantity, 0);
                const rawMaterials = data[category]?.materials || {};
                
                return {
                    name: category,
                    products: products,
                    materials: rawMaterials,
                    totalItems: totalItems
                };
            });
            
            // Sort categories by total items (descending)
            categoriesWithCounts.sort((a, b) => b.totalItems - a.totalItems);
            
            // Display each category
            categoriesWithCounts.forEach(categoryData => {
                const { name: category, products, materials, totalItems } = categoryData;
                const safeCategoryId = category.replace(/\s+/g, '-').replace(/[^a-zA-Z0-9-]/g, '');
                
                // Create a container for this department section
                const departmentSection = document.createElement('div');
                departmentSection.className = 'department-section';
                departmentSection.id = `department-${safeCategoryId}`;
                departmentSection.setAttribute('data-category', category);
                
                // Create department header with order count badge
                const departmentHeader = document.createElement('div');
                departmentHeader.className = 'department-header';
                departmentHeader.innerHTML = `
                    <span><i class="fas fa-tag"></i> ${category}</span>
                    <span class="order-count-badge">${totalItems} items</span>
                `;
                departmentSection.appendChild(departmentHeader);
                
                // Create tab container for products and materials
                const tabContainer = document.createElement('div');
                tabContainer.className = 'tab-container';
                tabContainer.innerHTML = `
                    <div class="tabs" id="tabs-${safeCategoryId}">
                        <div class="tab active" data-target="products-${safeCategoryId}">Products</div>
                        <div class="tab" data-target="materials-${safeCategoryId}">Raw Materials</div>
                    </div>
                `;
                departmentSection.appendChild(tabContainer);
                
                // Products tab content
                const productsTabContent = document.createElement('div');
                productsTabContent.className = 'tab-content active';
                productsTabContent.id = `products-${safeCategoryId}`;
                
                if (products.length > 0) {
                    // Create table for products
                    const table = document.createElement('table');
                    table.className = 'orders-table';
                    table.innerHTML = `
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Packaging</th>
                                <th>Total Quantity</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    `;
                    
                    const tbody = table.querySelector('tbody');
                    
                    // Add rows for each product
                    products.forEach(product => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${product.item_description}</td>
                            <td>${product.packaging}</td>
                            <td>${product.total_quantity}</td>
                        `;
                        tbody.appendChild(row);
                    });
                    
                    productsTabContent.appendChild(table);
                } else {
                    // Show no orders message
                    const noOrdersDiv = document.createElement('div');
                    noOrdersDiv.className = 'no-category-orders';
                    noOrdersDiv.innerHTML = 'No orders for today in this category';
                    productsTabContent.appendChild(noOrdersDiv);
                }
                departmentSection.appendChild(productsTabContent);
                
                // Materials tab content
                const materialsTabContent = document.createElement('div');
                materialsTabContent.className = 'tab-content';
                materialsTabContent.id = `materials-${safeCategoryId}`;
                
                if (Object.keys(materials).length > 0) {
                    const materialsSummary = document.createElement('div');
                    materialsSummary.className = 'materials-summary';
                    
                    // Create header for materials section
                    materialsSummary.innerHTML = '<h3><i class="fas fa-mortar-pestle"></i> Raw Materials Required</h3>';
                    
                    // Add container for material items
                    const materialsContainer = document.createElement('div');
                    materialsContainer.className = 'materials-container';
                    
                    // Sort materials by amount in descending order
                    const sortedMaterials = Object.entries(materials)
                        .sort((a, b) => b[1] - a[1]);
                    
                    sortedMaterials.forEach(([materialName, amount]) => {
                        const materialItem = document.createElement('div');
                        materialItem.className = 'material-item';
                        materialItem.innerHTML = `
                            <span class="material-name">${materialName}</span>
                            <span class="material-amount">${formatMaterialAmount(amount)}</span>
                        `;
                        materialsContainer.appendChild(materialItem);
                    });
                    
                    materialsSummary.appendChild(materialsContainer);
                    materialsTabContent.appendChild(materialsSummary);
                } else {
                    const noMaterialsDiv = document.createElement('div');
                    noMaterialsDiv.className = 'no-category-orders';
                    noMaterialsDiv.innerHTML = 'No raw materials data available for this category';
                    materialsTabContent.appendChild(noMaterialsDiv);
                }
                departmentSection.appendChild(materialsTabContent);
                
                // Add the complete section to the container
                departmentOrdersContainer.appendChild(departmentSection);
                
                // Add event listeners to tabs
                const tabs = tabContainer.querySelectorAll('.tab');
                tabs.forEach(tab => {
                    tab.addEventListener('click', function() {
                        const targetId = this.getAttribute('data-target');
                        const parentSection = this.closest('.department-section');
                        
                        // Remove active class from all tabs within this section
                        parentSection.querySelectorAll('.tab').forEach(t => {
                            t.classList.remove('active');
                        });
                        
                        // Remove active class from all tab contents within this section
                        parentSection.querySelectorAll('.tab-content').forEach(tc => {
                            tc.classList.remove('active');
                        });
                        
                        // Add active class to clicked tab and corresponding content
                        this.classList.add('active');
                        parentSection.querySelector(`#${targetId}`).classList.add('active');
                    });
                });
            });
        })
        .catch(error => {
            console.error('Error fetching orders:', error);
            departmentOrdersContainer.innerHTML = `<div style="text-align: center; padding: 20px; color: red;">
                <i class="fas fa-exclamation-triangle"></i> Error fetching orders: ${error.message}
            </div>`;
        });
                
        modal.style.display = 'block';
    }
    
    function closeModal() {
        document.getElementById('departmentOrdersModal').style.display = 'none';
    }
    
    window.onclick = function(event) {
        const departmentOrdersModal = document.getElementById('departmentOrdersModal');
        
        if (event.target === departmentOrdersModal) {
            departmentOrdersModal.style.display = 'none';
        }
    };
    </script>
</body>
</html>