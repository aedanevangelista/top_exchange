<?php
session_start();
include "db_connection.php";

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
if ($category === 'new' && isset($_POST['new_category']) && !empty($_POST['new_category'])) {
    $category = trim($_POST['new_category']);
}

$product_name = trim($_POST['product_name']);
if ($product_name === 'new' && isset($_POST['new_product_name']) && !empty($_POST['new_product_name'])) {
    $product_name = trim($_POST['new_product_name']);
}

$item_description = trim($_POST['item_description']);
$packaging = trim($_POST['packaging']);
$price = floatval($_POST['price']);
$stock_quantity = isset($_POST['stock_quantity']) ? intval($_POST['stock_quantity']) : 0;
$additional_description = isset($_POST['additional_description']) ? trim($_POST['additional_description']) : '';
$product_image = '';

// Upload image if provided
if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
    $allowed_types = ['image/jpeg', 'image/png'];
    $max_size = 20 * 1024 * 1024; // 20MB
    
    $file_type = $_FILES['product_image']['type'];
    $file_size = $_FILES['product_image']['size'];
    
    if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
        // Create folder for product images
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/';
        
        // Create folder based on item description
        $item_folder = preg_replace('/[^a-zA-Z0-9]/', '_', $item_description);
        $item_dir = $upload_dir . $item_folder . '/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        if (!file_exists($item_dir)) {
            mkdir($item_dir, 0777, true);
        }
        
        // Generate a unique filename based on extension
        $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
        $filename = 'product_image.' . $file_extension;
        $target_path = $item_dir . $filename;
        
        // Move uploaded file to destination directory
        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_path)) {
            // Save relative path to the database
            $product_image = '/uploads/products/' . $item_folder . '/' . $filename;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid file type or size. Maximum file size is 20MB.']);
        exit;
    }
}

// Check if there is already a product with the same item_description
$check_stmt = $conn->prepare("SELECT product_id FROM $table WHERE item_description = ?");
$check_stmt->bind_param("s", $item_description);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'A product with this description already exists']);
    $check_stmt->close();
    exit;
}
$check_stmt->close();

// Insert the new product into the database
$stmt = $conn->prepare("INSERT INTO $table (category, product_name, item_description, packaging, price, stock_quantity, additional_description, product_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssdiss", $category, $product_name, $item_description, $packaging, $price, $stock_quantity, $additional_description, $product_image);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Product added successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add product: ' . $conn->error]);
}

$stmt->close();
?>