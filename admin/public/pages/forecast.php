<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Forecast');

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

$deliveryDates = [];

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
    <link rel="stylesheet" href="/admin/css/sidebar.css">
    <link rel="stylesheet" href="/admin/css/orders.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/admin/css/toast.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
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
    </div>
    
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
                            <th>Delivery Address</th>
                            <th>Orders</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTableBody">
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="orderDetailsModal" class="orders-modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeOrderDetailsModal()">&times;</span>
            <h2 class="modal-date-header">Order Details</h2>
            <div id="orderDetailsHeader" style="margin-bottom: 20px; text-align: center;">
                <p id="orderPoNumber"></p>
                <p id="orderDeliveryAddress"></p>
            </div>
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
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="/admin/js/orders.js"></script>
    <script>
    function showOrders(date) {
        const modal = document.getElementById('ordersModal');
        const modalDate = document.getElementById('modalDate');
        const ordersTableBody = document.getElementById('ordersTableBody');
        
        const formattedDate = new Date(date).toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        modalDate.textContent = 'Orders for ' + formattedDate;
        
        fetch(`/backend/get_orders_by_date.php?date=${date}`)
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        ordersTableBody.innerHTML = '';
        
        // Check if data is null or not an array
        if (!data || !Array.isArray(data)) {
            console.error('Expected array but got:', data);
            ordersTableBody.innerHTML = '<tr><td colspan="6">Error: Unexpected data format received.</td></tr>';
            return;
        }
        
        if (data.length === 0) {
            ordersTableBody.innerHTML = '<tr><td colspan="6">No orders for this date.</td></tr>';
            return;
        }
        
        data.forEach(order => {
            // Parse orders JSON string into an object if it's a string
            let orderData;
            if (typeof order.orders === 'string') {
                try {
                    orderData = JSON.parse(order.orders);
                } catch (e) {
                    console.error('Failed to parse order JSON:', e);
                    orderData = [];
                }
            } else {
                orderData = order.orders;
            }
            
            const row = document.createElement('tr');
            const orderJSON = JSON.stringify(orderData).replace(/"/g, '&quot;');
            const poNumber = order.po_number;
            const deliveryAddress = order.delivery_address || 'Not specified';
            
            row.innerHTML = `
                <td>${order.po_number}</td>
                <td>${order.username}</td>
                <td>${order.order_date}</td>
                <td class="delivery-address">${deliveryAddress}</td>
                <td><button class="view-orders-btn" onclick='viewOrderDetails(${orderJSON}, "${poNumber}", "${deliveryAddress}")'>
                    <i class="fas fa-clipboard-list"></i> View Orders</button></td>
                <td>PHP ${parseFloat(order.total_amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
            `;
            ordersTableBody.appendChild(row);
        });
    })
    .catch(error => {
        console.error('Error fetching orders:', error);
        ordersTableBody.innerHTML = '<tr><td colspan="6">Error fetching orders: ' + error.message + '</td></tr>';
    });
            
        modal.style.display = 'block';
    }
    
    function viewOrderDetails(orders, poNumber, deliveryAddress) {
        try {
            const orderDetailsBody = document.getElementById('orderDetailsBody');
            const orderPoNumber = document.getElementById('orderPoNumber');
            const orderDeliveryAddress = document.getElementById('orderDeliveryAddress');
            const orderDetailsModal = document.getElementById('orderDetailsModal');
            
            orderPoNumber.textContent = `PO Number: ${poNumber}`;
            orderDeliveryAddress.textContent = `Delivery Address: ${deliveryAddress || 'Not specified'}`;
            
            orderDetailsBody.innerHTML = '';
            
            orders.forEach(product => {
                const row = document.createElement('tr');
                
                row.innerHTML = `
                    <td>${product.category}</td>
                    <td>${product.item_description}</td>
                    <td>${product.packaging}</td>
                    <td>PHP ${parseFloat(product.price).toFixed(2)}</td>
                    <td>${product.quantity}</td>
                `;
                orderDetailsBody.appendChild(row);
            });
            
            orderDetailsModal.style.display = 'block';
            
        } catch (e) {
            console.error('Error parsing order details:', e);
            alert('Error displaying order details');
        }
    }
    
    function closeModal() {
        document.getElementById('ordersModal').style.display = 'none';
    }
    
    function closeOrderDetailsModal() {
        document.getElementById('orderDetailsModal').style.display = 'none';
    }
    
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