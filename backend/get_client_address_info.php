<?php
// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
include "db_connection.php";

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Check if username parameter is provided
if (!isset($_GET['username']) || empty($_GET['username'])) {
    echo json_encode(['success' => false, 'error' => 'Username is required']);
    exit;
}

// Sanitize the input
$username = mysqli_real_escape_string($conn, $_GET['username']);

// Query to get client address information
$sql = "SELECT bill_to, bill_to_attn, ship_to, ship_to_attn, company, company_address 
        FROM clients_accounts 
        WHERE username = ?";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to prepare query']);
    exit;
}

$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $client = $result->fetch_assoc();
    
    // Return the client's address information
    echo json_encode([
        'success' => true,
        'bill_to' => $client['bill_to'] ?: $client['company_address'], // Fallback to company_address if bill_to is empty
        'bill_to_attn' => $client['bill_to_attn'] ?: $client['company'], // Fallback to company if bill_to_attn is empty
        'ship_to' => $client['ship_to'] ?: $client['company_address'], // Fallback to company_address if ship_to is empty
        'ship_to_attn' => $client['ship_to_attn'] ?: $client['company'] // Fallback to company if ship_to_attn is empty
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Client not found']);
}

// Close statement and connection
$stmt->close();
$conn->close();
?>