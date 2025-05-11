<?php
session_start();
include "db_connection.php"; // Make sure this path is correct

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if all required fields are present
$required_fields = ['category', 'product_name', 'item_description', 'packaging', 'price'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

// Get product type (company or walkin)
$product_type = isset($_POST['product_type']) && $_POST['product_type'] === 'walkin' ? 'walkin' : 'company';
$table = $product_type === 'walkin' ? 'walkin_products' : 'products';

// Extract values from POST
$category = trim($_POST['category']);
if ($category === 'new' && isset($_POST['new_category']) && !empty(trim($_POST['new_category']))) { // Also check new_category is not empty
    $category = trim($_POST['new_category']);
}

$product_name = trim($_POST['product_name']);
if ($product_name === 'new' && isset($_POST['new_product_name']) && !empty(trim($_POST['new_product_name']))) { // Also check new_product_name is not empty
    $product_name = trim($_POST['new_product_name']);
}

$item_description = trim($_POST['item_description']);
$packaging = trim($_POST['packaging']);
$price = floatval($_POST['price']);
$stock_quantity = isset($_POST['stock_quantity']) ? intval($_POST['stock_quantity']) : 0;
$additional_description = isset($_POST['additional_description']) ? trim($_POST['additional_description']) : '';
$product_image = '';

// --- MODIFICATION: Receive expiration value ---
$expiration = isset($_POST['expiration']) && !empty(trim($_POST['expiration'])) ? trim($_POST['expiration']) : NULL;
// --- END MODIFICATION ---

// Upload image if provided
if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
    $allowed_types = ['image/jpeg', 'image/png'];
    $max_size = 20 * 1024 * 1024; // 20MB
    
    $file_type = $_FILES['product_image']['type'];
    $file_size = $_FILES['product_image']['size'];
    
    if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
        // Create folder for product images
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/'; // Ensure this path is correct and writable
        
        // Create folder based on item description
        $item_folder = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $item_description); // Allow underscores, dots, hyphens
        $item_dir = $upload_dir . $item_folder . '/';
        
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                echo json_encode(['success' => false, 'message' => 'Failed to create upload directory. Check permissions.']);
                exit;
            }
        }
        
        if (!file_exists($item_dir)) {
            if (!mkdir($item_dir, 0777, true)) {
                echo json_encode(['success' => false, 'message' => 'Failed to create item directory. Check permissions.']);
                exit;
            }
        }
        
        // Generate a unique filename based on extension
        $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
        $filename = 'product_image.' . $file_extension; // Consider making this more unique if needed: uniqid() . '.' . $file_extension
        $target_path = $item_dir . $filename;
        
        // Move uploaded file to destination directory
        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_path)) {
            // Save relative path to the database
            $product_image = '/uploads/products/' . $item_folder . '/' . $filename;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to move uploaded image. Check permissions and path.']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid file type or size. Maximum file size is 20MB. Allowed types: JPG, PNG.']);
        exit;
    }
}

// Check if there is already a product with the same item_description
$check_stmt = $conn->prepare("SELECT product_id FROM $table WHERE item_description = ?");
$check_stmt->bind_param("s", $item_description);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'A product with this Product Variant (Item Description) already exists.']);
    $check_stmt->close();
    exit;
}
$check_stmt->close();

// --- MODIFICATION: Add 'expiration' column to INSERT statement and update bind_param ---
$stmt = $conn->prepare("INSERT INTO $table (category, product_name, item_description, packaging, price, stock_quantity, additional_description, product_image, expiration) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
// The types string should now be "ssssdissS" (s for string for expiration, can be NULL)
$stmt->bind_param("ssssdissS", $category, $product_name, $item_description, $packaging, $price, $stock_quantity, $additional_description, $product_image, $expiration);
// --- END MODIFICATION ---

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Product added successfully']);
} else {
    error_log("SQL Error in add_product.php: " . $stmt->error); // Log the specific SQL error
    echo json_encode(['success' => false, 'message' => 'Failed to add product. Please check server logs for details. Error: ' . $conn->error]);
}

$stmt->close();
$conn->close(); // Close the connection
?>