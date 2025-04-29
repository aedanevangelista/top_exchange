<?php
session_start();
include "db_connection.php";
include "check_role.php";
checkRole('Payment History');

// Check if the required parameters are provided
if (!isset($_POST['username']) || !isset($_POST['month']) || !isset($_POST['year']) || !isset($_POST['monthName'])) {
    die("Missing required parameters");
}

$username = $_POST['username'];
$month = (int) $_POST['month'];
$year = (int) $_POST['year'];
$monthName = $_POST['monthName'];

// Calculate date range for the month
$firstDayOfMonth = sprintf("%04d-%02d-01", $year, $month);
$lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));

// Fetch completed orders for the month
$sql = "SELECT po_number, order_date, delivery_date, delivery_address, orders, total_amount 
        FROM orders 
        WHERE username = ? 
        AND order_date BETWEEN ? AND ? 
        AND status = 'Completed'
        ORDER BY order_date";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $username, $firstDayOfMonth, $lastDayOfMonth);
$stmt->execute();
$result = $stmt->get_result();

$orders = array();
while ($row = $result->fetch_assoc()) {
    // Parse orders JSON if it's stored as a string
    if (isset($row['orders']) && is_string($row['orders'])) {
        $row['orders'] = json_decode($row['orders'], true);
    }
    $orders[] = $row;
}

// If no orders found, show error message
if (count($orders) === 0) {
    die("No completed orders found for $username in $monthName $year.");
}

// Require FPDF library
require('../../libs/fpdf/fpdf.php');

class PDF extends FPDF {
    function Header() {
        // Logo
        $this->Image('../../images/logo.png', 10, 6, 30);
        // Arial bold 15
        $this->SetFont('Arial', 'B', 15);
        // Move to the right
        $this->Cell(80);
        // Title
        $this->Cell(30, 10, 'Top Exchange', 0, 0, 'C');
        // Line break
        $this->Ln(20);
    }

    function Footer() {
        // Position at 1.5 cm from bottom
        $this->SetY(-15);
        // Arial italic 8
        $this->SetFont('Arial', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
    
    function OrderHeader($poNumber, $orderDate, $deliveryDate, $deliveryAddress, $username) {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'Purchase Order: ' . $poNumber, 0, 1);
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 6, 'Customer: ' . $username, 0, 1);
        $this->Cell(0, 6, 'Order Date: ' . $orderDate, 0, 1);
        $this->Cell(0, 6, 'Delivery Date: ' . $deliveryDate, 0, 1);
        $this->Cell(0, 6, 'Delivery Address: ' . $deliveryAddress, 0, 1);
        $this->Ln(5);
    }
    
    function OrderTable($header, $data) {
        // Colors, line width and bold font
        $this->SetFillColor(200, 220, 255);
        $this->SetTextColor(0);
        $this->SetDrawColor(128, 128, 128);
        $this->SetLineWidth(0.3);
        $this->SetFont('Arial', 'B', 10);
        
        // Header
        $w = array(40, 70, 25, 25, 15, 25);
        for($i=0; $i<count($header); $i++)
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
        $this->Ln();
        
        // Data
        $this->SetFillColor(224, 235, 255);
        $this->SetTextColor(0);
        $this->SetFont('Arial', '', 9);
        $fill = false;
        $total = 0;
        
        foreach($data as $row) {
            $subtotal = $row['price'] * $row['quantity'];
            $total += $subtotal;
            
            $this->Cell($w[0], 6, $row['category'], 'LR', 0, 'L', $fill);
            $this->Cell($w[1], 6, $row['item_description'], 'LR', 0, 'L', $fill);
            $this->Cell($w[2], 6, $row['packaging'], 'LR', 0, 'L', $fill);
            $this->Cell($w[3], 6, 'PHP ' . number_format($row['price'], 2), 'LR', 0, 'R', $fill);
            $this->Cell($w[4], 6, $row['quantity'], 'LR', 0, 'C', $fill);
            $this->Cell($w[5], 6, 'PHP ' . number_format($subtotal, 2), 'LR', 0, 'R', $fill);
            $this->Ln();
            $fill = !$fill;
        }
        
        // Closing line and total
        $this->Cell(array_sum($w), 0, '', 'T');
        $this->Ln();
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(array_sum($w) - 25, 6, 'Total:', 0, 0, 'R');
        $this->Cell(25, 6, 'PHP ' . number_format($total, 2), 0, 0, 'R');
        $this->Ln(15);
        
        return $total;
    }
}

// Initialize PDF
$pdf = new PDF();
$pdf->AliasNbPages();
$title = "Monthly Orders - $username - $monthName $year";
$pdf->SetTitle($title);
$pdf->SetAuthor('Top Exchange');

// Get the details of the client
$clientSql = "SELECT * FROM clients_accounts WHERE username = ?";
$clientStmt = $conn->prepare($clientSql);
$clientStmt->bind_param("s", $username);
$clientStmt->execute();
$clientResult = $clientStmt->get_result();
$clientData = $clientResult->fetch_assoc();

$grandTotal = 0;

foreach ($orders as $orderIndex => $order) {
    $pdf->AddPage();
    
    // Order header with PO details
    $pdf->OrderHeader(
        $order['po_number'],
        $order['order_date'],
        $order['delivery_date'],
        $order['delivery_address'] ?: 'Not specified',
        $username
    );
    
    // Order items table
    $header = array('Category', 'Item Description', 'Packaging', 'Price', 'Qty', 'Subtotal');
    $orderTotal = $pdf->OrderTable($header, $order['orders']);
    $grandTotal += $orderTotal;
    
    // Add signature fields if this is the last order
    if ($orderIndex == count($orders) - 1) {
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'Monthly Summary - ' . $monthName . ' ' . $year, 0, 1);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 8, 'Total Number of Orders: ' . count($orders), 0, 1);
        $pdf->Cell(0, 8, 'Monthly Total Amount: PHP ' . number_format($grandTotal, 2), 0, 1);
        $pdf->Ln(10);
        
        // Signature fields
        $pdf->Cell(90, 10, 'Prepared by:', 0, 0);
        $pdf->Cell(90, 10, 'Received by:', 0, 1);
        $pdf->Ln(10);
        
        $pdf->Cell(90, 0, '', 'T', 0);
        $pdf->Cell(15, 0, '', 0, 0);
        $pdf->Cell(75, 0, '', 'T', 1);
        
        $pdf->Cell(90, 10, 'Top Exchange', 0, 0, 'C');
        $pdf->Cell(15, 10, '', 0, 0);
        $pdf->Cell(75, 10, $clientData['full_name'] ?: $username, 0, 1, 'C');
        
        $pdf->Cell(90, 10, 'Date: _______________', 0, 0);
        $pdf->Cell(15, 10, '', 0, 0);
        $pdf->Cell(75, 10, 'Date: _______________', 0, 1);
    }
}

// Send the PDF to the browser
$pdf->Output('D', "Orders_$username" . "_$monthName" . "_$year.pdf");

$stmt->close();
$clientStmt->close();
$conn->close();
?>