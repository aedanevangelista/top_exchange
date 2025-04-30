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

// Get the details of the client
$clientSql = "SELECT * FROM clients_accounts WHERE username = ?";
$clientStmt = $conn->prepare($clientSql);
$clientStmt->bind_param("s", $username);
$clientStmt->execute();
$clientResult = $clientStmt->get_result();
$clientData = $clientResult->fetch_assoc();

// Create PDF content
require_once '../tcpdf/tcpdf.php'; 

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Top Exchange');
$pdf->SetTitle("Monthly Orders - $username - $monthName $year");
$pdf->SetSubject('Monthly Orders Report');
$pdf->SetKeywords('Orders, Monthly, Report, PDF');

// Set default header data
$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, 'Top Exchange', "Monthly Orders Report\n$username - $monthName $year");

// Set header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Set font
$pdf->SetFont('dejavusans', '', 10);

$grandTotal = 0;

foreach ($orders as $orderIndex => $order) {
    // Add a page
    $pdf->AddPage();
    
    // Order header with PO details
    $pdf->SetFont('dejavusans', 'B', 14);
    $pdf->Cell(0, 10, 'Purchase Order: ' . $order['po_number'], 0, 1);
    
    $pdf->SetFont('dejavusans', '', 11);
    $pdf->Cell(0, 6, 'Customer: ' . $username, 0, 1);
    $pdf->Cell(0, 6, 'Order Date: ' . $order['order_date'], 0, 1);
    $pdf->Cell(0, 6, 'Delivery Date: ' . $order['delivery_date'], 0, 1);
    $pdf->Cell(0, 6, 'Delivery Address: ' . ($order['delivery_address'] ?: 'Not specified'), 0, 1);
    $pdf->Ln(5);
    
    // Items table header
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->SetFillColor(240, 240, 240);
    
    // Table header
    $pdf->Cell(40, 7, 'Category', 1, 0, 'C', true);
    $pdf->Cell(60, 7, 'Item Description', 1, 0, 'C', true);
    $pdf->Cell(30, 7, 'Packaging', 1, 0, 'C', true);
    $pdf->Cell(20, 7, 'Price', 1, 0, 'C', true);
    $pdf->Cell(15, 7, 'Qty', 1, 0, 'C', true);
    $pdf->Cell(25, 7, 'Subtotal', 1, 1, 'C', true);
    
    // Table data
    $pdf->SetFont('dejavusans', '', 9);
    $totalAmount = 0;
    
    foreach ($order['orders'] as $item) {
        $subtotal = $item['price'] * $item['quantity'];
        $totalAmount += $subtotal;
        
        $pdf->Cell(40, 6, $item['category'], 1);
        $pdf->Cell(60, 6, $item['item_description'], 1);
        $pdf->Cell(30, 6, $item['packaging'], 1);
        $pdf->Cell(20, 6, 'PHP ' . number_format($item['price'], 2), 1, 0, 'R');
        $pdf->Cell(15, 6, $item['quantity'], 1, 0, 'C');
        $pdf->Cell(25, 6, 'PHP ' . number_format($subtotal, 2), 1, 1, 'R');
    }
    
    // Total for this order
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->Cell(165, 7, 'Total:', 1, 0, 'R');
    $pdf->Cell(25, 7, 'PHP ' . number_format($totalAmount, 2), 1, 1, 'R');
    
    $grandTotal += $totalAmount;
}

// Add a summary page
$pdf->AddPage();
$pdf->SetFont('dejavusans', 'B', 14);
$pdf->Cell(0, 10, 'Monthly Summary - ' . $monthName . ' ' . $year, 0, 1);

$pdf->SetFont('dejavusans', '', 11);
$pdf->Cell(0, 8, 'Username: ' . $username, 0, 1);
$pdf->Cell(0, 8, 'Total Number of Orders: ' . count($orders), 0, 1);
$pdf->Cell(0, 8, 'Monthly Total Amount: PHP ' . number_format($grandTotal, 2), 0, 1);
$pdf->Ln(10);

// Signature fields
$pdf->Cell(90, 10, 'Prepared by:', 0, 0);
$pdf->Cell(90, 10, 'Received by:', 0, 1);
$pdf->Ln(15);

$pdf->Cell(90, 0, '', 'T', 0);
$pdf->Cell(15, 0, '', 0, 0);
$pdf->Cell(75, 0, '', 'T', 1);

$pdf->Cell(90, 10, 'Top Exchange', 0, 0, 'C');
$pdf->Cell(15, 10, '', 0, 0);
$pdf->Cell(75, 10, $clientData['full_name'] ?: $username, 0, 1, 'C');

$pdf->Cell(90, 10, 'Date: _______________', 0, 0);
$pdf->Cell(15, 10, '', 0, 0);
$pdf->Cell(75, 10, 'Date: _______________', 0, 1);

// Output the PDF
$pdf->Output("Orders_$username" . "_$monthName" . "_$year.pdf", 'D');

$stmt->close();
$clientStmt->close();
$conn->close();
?>