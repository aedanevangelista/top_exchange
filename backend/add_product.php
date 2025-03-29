<?php
// Set content type to JSON
header('Content-Type: application/json');

// Turn off display errors while keeping error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start output buffering to catch unexpected output
ob_start();

try {
    include "db_connection.php";
    
    // Get product data from the form
    $category = $_POST['category'] ?? '';
    $product_name = $_POST['product_name'] ?? ''; // Product name from dropdown
    $item_description = $_POST['item_description'] ?? '';
    $packaging = $_POST['packaging'] ?? '';
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $stock_quantity = isset($_POST['stock_quantity']) ? intval($_POST['stock_quantity']) : 0;
    $additional_description = $_POST['additional_description'] ?? '';
    
    // Validate inputs
    if (empty($category) || empty($item_description) || empty($packaging) || $price <= 0) {
        throw new Exception('All required fields must be filled out');
    }
    
    // Check if new category was added
    if ($category === 'new' && isset($_POST['new_category']) && !empty($_POST['new_category'])) {
        $category = $_POST['new_category'];
    }
    
    // Check if new product name was added
    if ($product_name === 'new' && isset($_POST['new_product_name']) && !empty($_POST['new_product_name'])) {
        $product_name = $_POST['new_product_name'];
    }
    
    // If product_name is still empty, extract it from item_description
    if (empty($product_name)) {
        $product_name = trim(preg_replace('/\s*\(.*\).*$/', '', $item_description));
    }
    
    // Check if product already exists
    $check_sql = "SELECT COUNT(*) as count FROM products WHERE item_description = ? AND packaging = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $item_description, $packaging);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        throw new Exception('Product with this description and packaging already exists.');
    }
    
    // Process image upload if submitted
    $product_image = '';
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png'];
        $max_size = 20 * 1024 * 1024; // 20MB
        $file_type = $_FILES['product_image']['type'];
        $file_size = $_FILES['product_image']['size'];
        
        if (!in_array($file_type, $allowed_types)) {
            throw new Exception('Invalid file type. Only JPG and PNG are allowed.');
        }
        
        if ($file_size > $max_size) {
            throw new Exception('File size too large. Maximum size is 20MB.');
        }
        
        // Create folder for product images if it doesn't exist
        $upload_dir = __DIR__ . '/../uploads/products/';
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                throw new Exception('Failed to create upload directory');
            }
        }
        
        // Create folder for this specific product
        $item_folder = preg_replace('/[^a-zA-Z0-9]/', '_', $item_description);
        $item_dir = $upload_dir . $item_folder . '/';
        if (!file_exists($item_dir)) {
            if (!mkdir($item_dir, 0777, true)) {
                throw new Exception('Failed to create product directory');
            }
        }
        
        $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
        $filename = 'product_image.' . $file_extension;
        
        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $item_dir . $filename)) {
            $product_image = '/top_exchange/uploads/products/' . $item_folder . '/' . $filename;
        } else {
            throw new Exception('Failed to upload image');
        }
    }
    
    // If we have a product image and the product shares the same name as existing products,
    // update all those products to use the same image
    if ($product_image) {
        $update_images_sql = "UPDATE products SET product_image = ? WHERE item_description = ?";
        $update_images_stmt = $conn->prepare($update_images_sql);
        $update_images_stmt->bind_param("ss", $product_image, $item_description);
        $update_images_stmt->execute();
    }
    
    // Insert new product with product_name field
    $sql = "INSERT INTO products (category, product_name, item_description, packaging, price, stock_quantity, additional_description, product_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $stmt->bind_param("ssssdiis", $category, $product_name, $item_description, $packaging, $price, $stock_quantity, $additional_description, $product_image);
    
    if (!$stmt->execute()) {
        throw new Exception("Database execution error: " . $stmt->error);
    }
    
    // Clear the output buffer and send success response
    ob_clean();
    echo json_encode(['success' => true, 'message' => 'Product added successfully']);
    
} catch (Exception $e) {
    // Clear any output that might have been sent
    ob_clean();
    
    // Send proper JSON error response
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>