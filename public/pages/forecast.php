<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Forecast'); // Ensure the user has access to the Forecast page

// Get current month and year
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Validate month and year
if ($month < 1 || $month > 12) {
    $month = intval(date('m'));
}
if ($year < 2020 || $year > 2030) {
    $year = intval(date('Y'));
}

// Get the first day of the month
$firstDay = strtotime("$year-$month-01");
// Get the number of days in the month
$daysInMonth = date('t', $firstDay);

// Create array to store delivery dates and counts
$deliveryDates = [];

// Fetch orders with delivery date in the current month
$sql = "SELECT delivery_date, COUNT(*) as order_count 
        FROM orders 
        WHERE MONTH(delivery_date) = ? 
        AND YEAR(delivery_date) = ? 
        AND status = 'Active'
        GROUP BY delivery_date";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $month, $year);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $deliveryDates[$row['delivery_date']] = $row['order_count'];
}
$stmt->close();

// Function to get previous and next month links
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Forecast</title>
    <link rel="stylesheet" href="/top_exchange/public/css/sidebar.css">
    <link rel="stylesheet" href="/top_exchange/public/css/orders.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/top_exchange/public/css/toast.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
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
        }
        
        .forecast-header h1 {
            font-size: 24px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 12px;
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
            box-shadow: 0px 3px 6px rgba(0, 0, 0, 0.1);
        }
        
        .calendar {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
        }
        
        .calendar th {
            background-color: black;
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
            background-color: #fefefe;
            margin: 5% auto;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
            width: 80%;
            max-width: 900px;
            animation: modalFadeIn 0.3s ease-in-out;
        }
        
        .close-btn {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close-btn:hover,
        .close-btn:focus {
            color: black;
            text-decoration: none;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .orders-table th {
            background-color: black;
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
            margin-bottom: 20px;
            color: #333;
            text-align: center;
        }

        /* Make sure weekends are differently colored but MWF are highlighted */
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
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    
    <div class="main-content">
        <div class="forecast-header">
            <h1>Delivery Forecast</h1>
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
                    // Get the weekday of the first day (0 = Sunday, 6 = Saturday)
                    $firstDayOfWeek = date('w', $firstDay);
                    
                    // Calculate the number of rows needed in the calendar
                    $totalDays = $firstDayOfWeek + $daysInMonth;
                    $totalRows = ceil($totalDays / 7);
                    
                    $dayCounter = 1;
                    
                    // Loop through each row
                    for ($row = 0; $row < $totalRows; $row++) {
                        echo "<tr>";
                        
                        // Loop through each column (day of the week)
                        for ($col = 0; $col < 7; $col++) {
                            // Skip cells before the first day of the month
                            if ($row === 0 && $col < $firstDayOfWeek) {
                                echo '<td class="empty"></td>';
                                continue;
                            }
                            
                            // Skip cells after the last day of the month
                            if ($dayCounter > $daysInMonth) {
                                echo '<td class="empty"></td>';
                                continue;
                            }
                            
                            // Format the date for comparison
                            $currentDate = sprintf("%04d-%02d-%02d", $year, $month, $dayCounter);
                            $dayOfWeek = date('w', strtotime($currentDate));
                            
                            // Add classes for today and weekends
                            $classes = [];
                            if ($currentDate === date('Y-m-d')) {
                                $classes[] = 'today';
                            }
                            
                            // Check if it's Monday (1), Wednesday (3), or Friday (5)
                            $isDeliveryDay = in_array($dayOfWeek, [1, 3, 5]);
                            if ($isDeliveryDay) {
                                $classes[] = 'delivery-day';
                            } else if ($dayOfWeek == 0 || $dayOfWeek == 6) {
                                $classes[] = 'weekend';
                            } else {
                                $classes[] = 'non-delivery-day';
                            }
                            
                            // Get number of orders for this date
                            $orderCount = isset($deliveryDates[$currentDate]) ? $deliveryDates[$currentDate] : 0;
                            
                            echo '<td class="' . implode(' ', $classes) . '">';
                            echo '<div class="day-number">' . $dayCounter . '</div>';
                            
                            // Only show orders box for delivery days (Mon, Wed, Fri)
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
                        
                        // Break if we've displayed all days
                        if ($dayCounter > $daysInMonth) {
                            break;
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Orders Modal -->
    <div id="ordersModal" class="orders-modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            <h2 class="modal-date-header" id="modalDate">Orders for Date</h2>
            <div class="orders-table-container">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>PO Number</th>
                            <th>Username</th>
                            <th>Order Date</th>
                            <th>Orders</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTableBody">
                        <!-- Orders will be populated here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div id="orderDetailsModal" class="orders-modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeOrderDetailsModal()">&times;</span>
            <h2 class="modal-date-header">Order Details</h2>
            <div class="orders-table-container">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Product</th>
                            <th>Packaging</th>
                            <th>Price</th>
                            <th>Quantity</th>
                        </tr>
                    </thead>
                    <tbody id="orderDetailsBody">
                        <!-- Order details will be populated here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="/top_exchange/public/js/orders.js"></script>
    <script>
        // Function to show the orders modal for a specific date
        function showOrders(date) {
            const modal = document.getElementById('ordersModal');
            const modalDate = document.getElementById('modalDate');
            const ordersTableBody = document.getElementById('ordersTableBody');
            
            // Format the date for display
            const formattedDate = new Date(date).toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            modalDate.textContent = 'Orders for ' + formattedDate;
            
            // Fetch orders for the selected date
            fetch(`/top_exchange/backend/get_orders_by_date.php?date=${date}`)
                .then(response => response.json())
                .then(data => {
                    ordersTableBody.innerHTML = '';
                    
                    if (data.length === 0) {
                        ordersTableBody.innerHTML = '<tr><td colspan="5">No orders for this date.</td></tr>';
                        return;
                    }
                    
                    data.forEach(order => {
                        const row = document.createElement('tr');
                        const orderJSON = JSON.stringify(order.orders).replace(/"/g, '&quot;');
                        
                        row.innerHTML = `
                            <td>${order.po_number}</td>
                            <td>${order.username}</td>
                            <td>${order.order_date}</td>
                            <td><button class="view-orders-btn" onclick='viewOrderDetails(${orderJSON})'>
                                <i class="fas fa-clipboard-list"></i> View Orders</button></td>
                            <td>PHP ${parseFloat(order.total_amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                        `;
                        ordersTableBody.appendChild(row);
                    });
                })
                .catch(error => {
                    console.error('Error fetching orders:', error);
                    ordersTableBody.innerHTML = '<tr><td colspan="5">Error fetching orders.</td></tr>';
                });
            
            modal.style.display = 'block';
        }
        
        // Function to close the orders modal
        function closeModal() {
            document.getElementById('ordersModal').style.display = 'none';
        }
        
        // Function to close the order details modal
        function closeOrderDetailsModal() {
            document.getElementById('orderDetailsModal').style.display = 'none';
        }
        
        // Close modals when clicking outside the content
        window.onclick = function(event) {
            const ordersModal = document.getElementById('ordersModal');
            const orderDetailsModal = document.getElementById('orderDetailsModal');
            
            if (event.target === ordersModal) {
                ordersModal.style.display = 'none';
            }
            
            if (event.target === orderDetailsModal) {
                orderDetailsModal.style.display = 'none';
            }
        };
    </script>
</body>
</html>