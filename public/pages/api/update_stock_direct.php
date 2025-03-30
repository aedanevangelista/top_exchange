<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// Initialize response array
$response = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Decode JSON request body
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required parameters
    if (!isset($data['product_id'], $data['stock_quantity'])) {
        $response['error'] = "Missing required parameters.";
        echo json_encode($response);
        exit();
    }

    include $_SERVER['DOCUMENT_ROOT'] . "/backend/db_connection.php";

    // Check database connection
    if ($conn->connect_error) {
        $response['error'] = "Database connection failed: " . $conn->connect_error;
        echo json_encode($response);
        exit();
    }

    // Sanitize and validate input
    $productId = intval($data['product_id']);
    $stockQuantity = intval($data['stock_quantity']);

    if ($stockQuantity < 0) {
        $response['error'] = "Stock quantity cannot be negative.";
        echo json_encode($response);
        exit();
    }

    // Prepare SQL query
    $stmt = $conn->prepare("UPDATE products SET stock_quantity = ? WHERE product_id = ?");
    if (!$stmt) {
        $response['error'] = "Error preparing SQL statement: " . $conn->error;
        echo json_encode($response);
        exit();
    }

    // Bind and execute query
    $stmt->bind_param("ii", $stockQuantity, $productId);
    if ($stmt->execute()) {
        $response['message'] = "Stock updated successfully.";
    } else {
        $response['error'] = "Error updating stock: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
} else {
    $response['error'] = "Invalid request method.";
}

// Output JSON response
echo json_encode($response);
?>
